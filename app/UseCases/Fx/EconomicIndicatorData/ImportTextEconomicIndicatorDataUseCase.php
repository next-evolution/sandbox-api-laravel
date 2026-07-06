<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use App\Models\FxCountry;
use App\Models\FxEconomicIndicator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportTextEconomicIndicatorDataUseCase
{
    public function __construct(private readonly EconomicIndicatorDataParser $parser) {}

    /**
     * @param  UploadedFile[]  $files
     */
    public function execute(array $files, string $userSub): array
    {
        $countryMap = $this->buildCountryMap();
        $indicatorMap = $this->buildIndicatorMap($countryMap);

        return DB::transaction(function () use ($files, $userSub, $countryMap, $indicatorMap): array {
            $resultList = [];
            foreach ($files as $file) {
                $resultList[] = $this->processFile($file, $countryMap, $indicatorMap, $userSub);
            }

            return $resultList;
        });
    }

    private function processFile(UploadedFile $file, array $countryMap, array $indicatorMap, string $userSub): array
    {
        $originalFileName = $file->getClientOriginalName();
        $fileSize = (int) $file->getSize();

        $this->saveFile($file, $originalFileName, $userSub);

        $dataList = $this->parser->parseFile($file->getRealPath(), $originalFileName, $countryMap, $indicatorMap);

        DB::table('fx_economic_indicator_data_load')->delete();

        if ($dataList !== []) {
            DB::table('fx_economic_indicator_data_load')->insert(array_map(fn (array $d) => [
                'code' => $d['code'],
                'country_code' => $d['countryCode'],
                'publication' => $d['publication'],
                'sub_title' => $d['subTitle'],
                'result_value' => $d['resultValue'],
                'forecast_value' => $d['forecastValue'],
                'previous_value' => $d['previousValue'],
                'memo' => null,
            ], $dataList));
        }

        $diffCount = DB::table('fx_economic_indicator_data_load as L')
            ->join('fx_economic_indicator_data as T', function ($join): void {
                $join->on('T.code', '=', 'L.code')
                    ->on('T.country_code', '=', 'L.country_code')
                    ->on('T.publication', '=', 'L.publication');
            })
            ->where(function ($q): void {
                $q->whereColumn('T.result_value', '!=', 'L.result_value')
                    ->orWhereColumn('T.forecast_value', '!=', 'L.forecast_value')
                    ->orWhereColumn('T.previous_value', '!=', 'L.previous_value');
            })
            ->count();

        if ($diffCount === 0) {
            // load テーブルのうち本テーブルに未登録のもの（code, country_code, publication が一致しない）だけを取り込む
            DB::statement('
                INSERT INTO fx_economic_indicator_data
                SELECT L.code, L.country_code, L.publication, L.sub_title, L.result_value, L.forecast_value, L.previous_value, L.memo
                FROM fx_economic_indicator_data_load L
                LEFT JOIN fx_economic_indicator_data D
                    ON D.code = L.code AND D.country_code = L.country_code AND D.publication = L.publication
                WHERE D.code IS NULL
            ');
        } else {
            // 既存データと値が異なる場合は上書きせず、取込をスキップして警告ログのみ残す（移植元 Java 版の挙動に合わせる）
            Log::warning('経済指標データのインポートで差分を検出したため取込をスキップしました', [
                'fileName' => $originalFileName,
                'diffCount' => $diffCount,
            ]);
        }

        return [
            'fileName' => $originalFileName,
            'fileSize' => $fileSize,
            'resultStatus' => 'OK',
            'readCount' => count($dataList),
            'message' => null,
        ];
    }

    private function saveFile(UploadedFile $file, string $originalFileName, string $userSub): void
    {
        Storage::disk('local')->putFileAs(
            'fx/economic-indicator-data/'.$userSub,
            $file,
            $originalFileName,
        );
    }

    private function buildCountryMap(): array
    {
        return FxCountry::pluck('code', 'name')->all();
    }

    private function buildIndicatorMap(array $countryMap): array
    {
        $result = [];

        foreach ($countryMap as $countryName => $countryCode) {
            $result[$countryName] = FxEconomicIndicator::where('country_code', $countryCode)
                ->where('deleted', false)
                ->get()
                ->mapWithKeys(fn (FxEconomicIndicator $e) => [
                    $e->name => [
                        'code' => $e->code,
                        'countryCode' => $e->country_code,
                        'importance' => $e->importance,
                    ],
                ])
                ->all();
        }

        return $result;
    }
}
