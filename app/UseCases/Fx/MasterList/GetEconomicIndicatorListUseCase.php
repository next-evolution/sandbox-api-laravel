<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxEconomicIndicator;

class GetEconomicIndicatorListUseCase
{
    public function execute(string $countryCode): array
    {
        return FxEconomicIndicator::where('country_code', $countryCode)
            ->where('deleted', false)
            ->orderBy('code')
            ->get()
            ->map(fn (FxEconomicIndicator $e) => ['key' => $e->code, 'value' => $e->name])
            ->all();
    }
}
