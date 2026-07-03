<?php

declare(strict_types=1);

namespace App\UseCases\Fx\BarData;

use App\Models\FxBarData;
use Illuminate\Support\Facades\DB;

class StatusBarDataUseCase
{
    public function execute(string $symbolType, string $barType): array
    {
        $table = FxBarData::tableForBarType($barType);

        $rows = DB::select(
            "SELECT
                 c.symbol AS symbol,
                 DATE_FORMAT(MIN(b.bar_date_time), '%Y-%m-%d %H:%i') AS barDateTimeMinS,
                 DATE_FORMAT(MAX(b.bar_date_time), '%Y-%m-%d %H:%i') AS barDateTimeMaxS,
                 COUNT(b.symbol) AS count
             FROM fx_symbol c
             LEFT JOIN {$table} b ON b.symbol = c.symbol
             WHERE c.symbol_type = ?
             GROUP BY c.symbol
             ORDER BY c.sort_order",
            [$symbolType],
        );

        return array_map(fn ($row) => [
            'symbol' => $row->symbol,
            'existsCount' => (int) $row->count,
            'message' => $row->barDateTimeMinS === null && $row->barDateTimeMaxS === null
                ? null
                : $row->barDateTimeMinS.'~'.$row->barDateTimeMaxS,
            'fileName' => null,
            'fileSize' => null,
            'resultStatus' => null,
            'readCount' => null,
            'barDateTime' => null,
            'insertCount' => null,
            'differenceCount' => null,
        ], $rows);
    }
}
