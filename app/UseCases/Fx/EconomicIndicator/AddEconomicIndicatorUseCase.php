<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicator;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\FxEconomicIndicator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddEconomicIndicatorUseCase
{
    public function execute(array $economicIndicatorData, string $author): void
    {
        $code = $economicIndicatorData['code'];
        $countryCode = $economicIndicatorData['countryCode'];

        if (FxEconomicIndicator::where('code', $code)->where('country_code', $countryCode)->exists()) {
            throw new DuplicateException($countryCode.'/'.$code);
        }

        $now = Carbon::now();
        $row = new FxEconomicIndicator;
        $row->code = $code;
        $row->country_code = $countryCode;
        $row->importance = $economicIndicatorData['importance'];
        $row->name = $economicIndicatorData['name'];
        $row->description = $economicIndicatorData['description'] ?? null;
        $row->unit_of_value = $economicIndicatorData['unitOfValue'] ?? null;
        $row->deleted = false;
        $row->created_at = $now;
        $row->created_by = $author;
        $row->updated_at = $now;
        $row->updated_by = $author;

        DB::transaction(function () use ($row): void {
            if (! $row->save()) {
                throw new InsertException($row->country_code.'/'.$row->code);
            }
        });
    }
}
