<?php

declare(strict_types=1);

namespace App\UseCases\Fx\BarData;

use App\Models\FxBarData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportCsvBarDataUseCase
{
    private const RSI_RANGE = 14;

    // CSVの MA 列(sma200, sma75, sma20 の順)に対応する SMA 期間
    private const SMA_RANGES = [200, 75, 20];

    public function execute(
        string $symbol,
        string $barType,
        bool $skipLatest,
        UploadedFile $uploadFile,
        string $userSub,
    ): array {
        $originalFileName = $uploadFile->getClientOriginalName();
        $fileSize = (int) $uploadFile->getSize();

        $expectedPattern = $symbol.'_'.FxBarData::csvKeywordForBarType($barType);
        if (! str_contains($originalFileName, $expectedPattern)) {
            return [
                'fileName' => $originalFileName,
                'fileSize' => $fileSize,
                'resultStatus' => 'ERROR',
                'readCount' => 0,
                'message' => 'file not exists.',
                'symbol' => null,
                'barDateTime' => null,
                'existsCount' => 0,
                'insertCount' => 0,
                'differenceCount' => 0,
            ];
        }

        $this->saveFile($uploadFile, $originalFileName, $userSub);

        $table = FxBarData::tableForBarType($barType);

        DB::table('fx_bar_load')->where('symbol', $symbol)->delete();
        DB::table('fx_bar_load_sma')->where('symbol', $symbol)->delete();
        DB::table('fx_bar_load_rsi')->where('symbol', $symbol)->delete();

        $readCount = $this->loadCsv($uploadFile, $symbol, $barType);

        if ($skipLatest && $readCount > 0) {
            $this->deleteLatestLoad($symbol);
            $readCount--;
        }

        $latestBarDateTime = DB::table('fx_bar_load')->where('symbol', $symbol)->max('bar_date_time');

        [$existsCount, $diffCount] = $this->importCheck($symbol, $table);

        $importCheckSkip = (bool) config('fx.bar_data.import_check_skip');

        if ($existsCount === 0 && ! $importCheckSkip) {
            Log::error('インポートチェックエラー: 既存レコードが0件です。', ['symbol' => $symbol]);

            return [
                'symbol' => $symbol,
                'barDateTime' => $latestBarDateTime,
                'fileName' => $originalFileName,
                'fileSize' => $fileSize,
                'resultStatus' => 'ERROR',
                'readCount' => $readCount,
                'existsCount' => 0,
                'insertCount' => 0,
                'differenceCount' => 0,
                'message' => 'import check error.',
            ];
        }

        if ($diffCount > 0) {
            Log::warning('差分データ検出', ['symbol' => $symbol, 'diffCount' => $diffCount]);
        }

        $insertCount = DB::affectingStatement(
            "INSERT INTO {$table}
                SELECT
                     L.symbol
                    ,L.bar_date_time
                    ,L.open_price
                    ,L.high_price
                    ,L.low_price
                    ,L.close_price
                    ,L.volume
                    ,(L.high_price  - L.open_price)
                    ,(L.low_price   - L.open_price)
                    ,(L.close_price - L.open_price)
                    ,(L.high_price  - L.low_price)
                FROM fx_bar_load L
                WHERE L.symbol = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM {$table} M
                    WHERE M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time
                  )",
            [$symbol],
        );

        $insertSmaCount = DB::affectingStatement(
            "INSERT INTO {$table}_sma (symbol, bar_date_time, sma_range, sma_price, sma_cross)
                SELECT L.symbol, L.bar_date_time, L.sma_range, L.sma_price, L.sma_cross
                FROM fx_bar_load_sma L
                WHERE L.symbol = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM {$table}_sma M
                    WHERE M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time AND M.sma_range = L.sma_range
                  )",
            [$symbol],
        );

        $insertRsiCount = DB::affectingStatement(
            "INSERT INTO {$table}_rsi (symbol, bar_date_time, rsi_range, rsi_value, rsi_ma)
                SELECT L.symbol, L.bar_date_time, L.rsi_range, L.rsi_value, L.rsi_ma
                FROM fx_bar_load_rsi L
                WHERE L.symbol = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM {$table}_rsi M
                    WHERE M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time AND M.rsi_range = L.rsi_range
                  )",
            [$symbol],
        );

        Log::info('インサート完了', [
            'symbol' => $symbol,
            'bar' => $insertCount,
            'sma' => $insertSmaCount,
            'rsi' => $insertRsiCount,
        ]);

        $differenceCount = $this->processDiffUpdate($symbol, $table);

        $resultStatus = $insertCount > 0 ? 'OK' : 'SKIP';

        return [
            'symbol' => $symbol,
            'barDateTime' => $latestBarDateTime,
            'fileName' => $originalFileName,
            'fileSize' => $fileSize,
            'resultStatus' => $resultStatus,
            'readCount' => $readCount,
            'existsCount' => $existsCount,
            'insertCount' => $insertCount,
            'differenceCount' => $differenceCount,
            'message' => null,
        ];
    }

    private function saveFile(UploadedFile $uploadFile, string $originalFileName, string $userSub): void
    {
        Storage::disk('local')->putFileAs(
            'fx/bar-data/'.$userSub,
            $uploadFile,
            $originalFileName,
        );
    }

    private function loadCsv(UploadedFile $uploadFile, string $symbol, string $barType): int
    {
        $handle = fopen($uploadFile->getRealPath(), 'r');

        // ヘッダー行はCSVの列名・列数確認のためだけに存在し、値は列位置で束縛する（ヘッダーに "MA" が重複するため名前解決不可）
        fgetcsv($handle, escape: '');

        $barBuffer = [];
        $smaBuffer = [];
        $rsiBuffer = [];
        $count = 0;
        $bulkLoadSize = (int) config('fx.bar_data.csv_bulk_load_size');

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $barDateTime = FxBarData::parseCsvBarDateTime($barType, $row[0]);
            $highPrice = $row[2];
            $lowPrice = $row[3];

            $barBuffer[] = [$symbol, $barDateTime, $row[1], $highPrice, $lowPrice, $row[4], $row[5] !== '' ? (int) $row[5] : 0];

            foreach (self::SMA_RANGES as $i => $smaRange) {
                $smaPrice = $row[6 + $i];
                $smaCross = $smaPrice !== '' && $highPrice >= $smaPrice && $lowPrice <= $smaPrice;
                $smaBuffer[] = [$symbol, $barDateTime, $smaRange, $smaPrice, $smaCross];
            }

            $rsiBuffer[] = [$symbol, $barDateTime, self::RSI_RANGE, $row[9], $row[10]];

            $count++;

            if (count($barBuffer) >= $bulkLoadSize) {
                $this->flushLoadBuffers($barBuffer, $smaBuffer, $rsiBuffer);
            }
        }

        fclose($handle);

        if ($barBuffer !== []) {
            $this->flushLoadBuffers($barBuffer, $smaBuffer, $rsiBuffer);
        }

        return $count;
    }

    private function flushLoadBuffers(array &$barBuffer, array &$smaBuffer, array &$rsiBuffer): void
    {
        DB::table('fx_bar_load')->insert(array_map(
            fn (array $r) => [
                'symbol' => $r[0], 'bar_date_time' => $r[1], 'open_price' => $r[2],
                'high_price' => $r[3], 'low_price' => $r[4], 'close_price' => $r[5], 'volume' => $r[6],
            ],
            $barBuffer,
        ));

        DB::table('fx_bar_load_sma')->insert(array_map(
            fn (array $r) => [
                'symbol' => $r[0], 'bar_date_time' => $r[1], 'sma_range' => $r[2],
                'sma_price' => $r[3], 'sma_cross' => $r[4],
            ],
            $smaBuffer,
        ));

        DB::table('fx_bar_load_rsi')->insert(array_map(
            fn (array $r) => [
                'symbol' => $r[0], 'bar_date_time' => $r[1], 'rsi_range' => $r[2],
                'rsi_value' => $r[3], 'rsi_ma' => $r[4],
            ],
            $rsiBuffer,
        ));

        $barBuffer = [];
        $smaBuffer = [];
        $rsiBuffer = [];
    }

    private function deleteLatestLoad(string $symbol): void
    {
        $maxDt = DB::table('fx_bar_load')->where('symbol', $symbol)->max('bar_date_time');

        DB::table('fx_bar_load')
            ->where('symbol', $symbol)
            ->where('bar_date_time', $maxDt)
            ->delete();
    }

    private function importCheck(string $symbol, string $table): array
    {
        $row = DB::selectOne(
            "SELECT
                COUNT(M.bar_date_time) AS existsCount,
                SUM(
                    CASE WHEN M.bar_date_time IS NOT NULL
                              AND (M.open_price != L.open_price OR M.high_price != L.high_price
                                   OR M.low_price != L.low_price OR M.close_price != L.close_price)
                         THEN 1 ELSE 0 END
                ) AS diffCount
            FROM fx_bar_load L
            LEFT JOIN {$table} M ON M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time
            WHERE L.symbol = ?",
            [$symbol],
        );

        return [(int) $row->existsCount, (int) $row->diffCount];
    }

    private function processDiffUpdate(string $symbol, string $table): int
    {
        $diffData = DB::select(
            "SELECT L.symbol, L.bar_date_time, L.open_price, L.close_price
                FROM fx_bar_load L
                INNER JOIN {$table} M ON M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time
                WHERE L.symbol = ?
                  AND (M.open_price != L.open_price OR M.high_price != L.high_price
                       OR M.low_price != L.low_price OR M.close_price != L.close_price)",
            [$symbol],
        );

        if ($diffData !== []) {
            foreach ($diffData as $d) {
                Log::warning('BarData差分', (array) $d);
            }

            DB::update(
                "UPDATE {$table} M
                    INNER JOIN fx_bar_load L ON L.symbol = M.symbol AND L.bar_date_time = M.bar_date_time
                    SET M.open_price = L.open_price, M.high_price = L.high_price,
                        M.low_price = L.low_price, M.close_price = L.close_price, M.volume = L.volume
                    WHERE L.symbol = ?
                      AND (M.open_price != L.open_price OR M.high_price != L.high_price
                           OR M.low_price != L.low_price OR M.close_price != L.close_price)",
                [$symbol],
            );
        }

        $diffSma = DB::select(
            "SELECT L.symbol FROM fx_bar_load_sma L
                INNER JOIN {$table}_sma M ON M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time AND M.sma_range = L.sma_range
                WHERE L.symbol = ? AND (M.sma_price != L.sma_price OR M.sma_cross != L.sma_cross)",
            [$symbol],
        );

        if ($diffSma !== []) {
            Log::warning('BarSma差分', ['symbol' => $symbol, 'diffCount' => count($diffSma)]);

            DB::update(
                "UPDATE {$table}_sma M
                    INNER JOIN fx_bar_load_sma L ON L.symbol = M.symbol AND L.bar_date_time = M.bar_date_time AND L.sma_range = M.sma_range
                    SET M.sma_price = L.sma_price, M.sma_cross = L.sma_cross
                    WHERE L.symbol = ? AND (M.sma_price != L.sma_price OR M.sma_cross != L.sma_cross)",
                [$symbol],
            );
        }

        $diffRsi = DB::select(
            "SELECT L.symbol FROM fx_bar_load_rsi L
                INNER JOIN {$table}_rsi M ON M.symbol = L.symbol AND M.bar_date_time = L.bar_date_time AND M.rsi_range = L.rsi_range
                WHERE L.symbol = ? AND (M.rsi_value != L.rsi_value OR M.rsi_ma != L.rsi_ma)",
            [$symbol],
        );

        if ($diffRsi !== []) {
            Log::warning('BarRsi差分', ['symbol' => $symbol, 'diffCount' => count($diffRsi)]);

            DB::update(
                "UPDATE {$table}_rsi M
                    INNER JOIN fx_bar_load_rsi L ON L.symbol = M.symbol AND L.bar_date_time = M.bar_date_time AND L.rsi_range = M.rsi_range
                    SET M.rsi_value = L.rsi_value, M.rsi_ma = L.rsi_ma
                    WHERE L.symbol = ? AND (M.rsi_value != L.rsi_value OR M.rsi_ma != L.rsi_ma)",
                [$symbol],
            );
        }

        return count($diffData);
    }
}
