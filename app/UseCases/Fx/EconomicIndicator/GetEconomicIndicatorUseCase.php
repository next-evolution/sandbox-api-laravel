<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicator;

use App\Exceptions\NotFoundException;
use App\Models\FxEconomicIndicator;

class GetEconomicIndicatorUseCase
{
    public function execute(string $countryCode, string $code): array
    {
        $row = FxEconomicIndicator::with('country')
            ->where('code', $code)
            ->where('country_code', $countryCode)
            ->where('deleted', false)
            ->first();

        if ($row === null) {
            throw new NotFoundException($countryCode.'/'.$code);
        }

        return $row->toDtoArray();
    }
}
