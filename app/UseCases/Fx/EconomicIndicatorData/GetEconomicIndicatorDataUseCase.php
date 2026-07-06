<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use App\Exceptions\NotFoundException;
use App\Models\FxEconomicIndicatorData;
use Carbon\Carbon;

class GetEconomicIndicatorDataUseCase
{
    public function execute(string $countryCode, string $code, Carbon $publication): array
    {
        $table = (new FxEconomicIndicatorData)->getTable();

        $row = FxEconomicIndicatorData::withDetails()
            ->where($table.'.code', $code)
            ->where($table.'.country_code', $countryCode)
            ->where($table.'.publication', $publication)
            ->first();

        if ($row === null) {
            throw new NotFoundException("({$countryCode}) {$code} / {$publication->format('Y-m-d H:i:s')}");
        }

        return $row->toDtoArray();
    }
}
