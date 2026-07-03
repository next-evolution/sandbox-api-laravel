<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicator;

use App\Models\FxEconomicIndicator;

class SearchEconomicIndicatorUseCase
{
    public function execute(?string $countryCode, ?string $importance, ?string $name, int $page, int $size): array
    {
        $query = FxEconomicIndicator::with('country')->where('deleted', false);

        if ($countryCode !== null) {
            $query->where('country_code', $countryCode);
        }

        if ($importance !== null) {
            $query->where('importance', $importance);
        }

        if ($name !== null) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        $query->orderBy('country_code')->orderBy('code');

        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxEconomicIndicator $e) => $e->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
