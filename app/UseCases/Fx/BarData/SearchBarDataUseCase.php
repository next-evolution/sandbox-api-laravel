<?php

declare(strict_types=1);

namespace App\UseCases\Fx\BarData;

use App\Models\FxBarData;
use Carbon\Carbon;

class SearchBarDataUseCase
{
    // RSI はレンジ違いで複数保持されるが、bar-data 検索では期間14固定で結合する
    private const RSI_RANGE = 14;

    public function execute(
        string $barType,
        string $symbol,
        ?string $barDateFrom,
        ?string $barDateTo,
        bool $sortAsc,
        int $page,
        int $size,
    ): array {
        $table = FxBarData::tableForBarType($barType);
        $rsiTable = $table.'_rsi';

        $model = new FxBarData;
        $model->setTable($table);

        $query = $model->newQuery()
            ->leftJoin($rsiTable, function ($join) use ($table, $rsiTable): void {
                $join->on($rsiTable.'.symbol', '=', $table.'.symbol')
                    ->on($rsiTable.'.bar_date_time', '=', $table.'.bar_date_time')
                    ->where($rsiTable.'.rsi_range', '=', self::RSI_RANGE);
            })
            ->where($table.'.symbol', $symbol)
            ->select($table.'.*', $rsiTable.'.rsi_value', $rsiTable.'.rsi_ma');

        if ($barDateFrom !== null) {
            $query->where($table.'.bar_date_time', '>=', Carbon::createFromFormat('Ymd', $barDateFrom)->startOfDay());
        }

        if ($barDateTo !== null) {
            $query->where($table.'.bar_date_time', '<=', Carbon::createFromFormat('Ymd', $barDateTo)->endOfDay());
        }

        $query->orderBy($table.'.bar_date_time', $sortAsc ? 'asc' : 'desc');

        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxBarData $b) => $b->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
