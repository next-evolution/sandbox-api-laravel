<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicator;

use App\Exceptions\DuplicateException;
use App\Exceptions\UpdateException;
use App\Models\FxEconomicIndicator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEconomicIndicatorUseCase
{
    public function execute(string $baseCountryCode, string $baseCode, array $economicIndicatorData, string $author): void
    {
        $newCode = $economicIndicatorData['code'];
        $newCountryCode = $economicIndicatorData['countryCode'];

        DB::transaction(function () use ($baseCountryCode, $baseCode, $newCode, $newCountryCode, $economicIndicatorData, $author): void {
            $row = FxEconomicIndicator::where('code', $baseCode)->where('country_code', $baseCountryCode)->first();

            if ($row === null) {
                throw new UpdateException($baseCountryCode.'/'.$baseCode);
            }

            $keyChanged = $baseCode !== $newCode || $baseCountryCode !== $newCountryCode;

            if ($keyChanged && FxEconomicIndicator::where('code', $newCode)->where('country_code', $newCountryCode)->exists()) {
                throw new DuplicateException($newCountryCode.'/'.$newCode);
            }

            $row->code = $newCode;
            $row->country_code = $newCountryCode;
            $row->importance = $economicIndicatorData['importance'];
            $row->name = $economicIndicatorData['name'];
            $row->description = $economicIndicatorData['description'] ?? null;
            $row->unit_of_value = $economicIndicatorData['unitOfValue'] ?? null;
            $row->updated_at = Carbon::now();
            $row->updated_by = $author;

            if (! $row->save()) {
                throw new UpdateException($baseCountryCode.'/'.$baseCode);
            }
        });
    }
}
