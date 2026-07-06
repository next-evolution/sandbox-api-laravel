<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use App\Models\FxEconomicIndicatorData;
use Carbon\Carbon;

class SearchEconomicIndicatorDataUseCase
{
    public function execute(
        ?string $code,
        ?string $importance,
        ?string $countryCode,
        ?string $publicationBaseDate,
        int $page,
        int $size,
        bool $sortAsc,
    ): array {
        $table = (new FxEconomicIndicatorData)->getTable();

        $query = FxEconomicIndicatorData::withDetails();

        if ($code !== null) {
            $query->where($table.'.code', $code);
        }

        if ($importance !== null) {
            $query->where('E.importance', $importance);
        }

        if ($countryCode !== null) {
            $query->where($table.'.country_code', $countryCode);
        }

        if ($publicationBaseDate !== null) {
            $query->where($table.'.publication', '>=', Carbon::createFromFormat('Y-m-d', $publicationBaseDate)->startOfDay());
        }

        $query->orderBy($table.'.publication', $sortAsc ? 'asc' : 'desc');

        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxEconomicIndicatorData $d) => $d->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
