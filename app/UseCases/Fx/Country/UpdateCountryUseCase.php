<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Country;

use App\Exceptions\DuplicateException;
use App\Exceptions\UpdateException;
use App\Models\FxCountry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateCountryUseCase
{
    public function execute(string $baseCode, array $countryData, string $author): void
    {
        $newCode = $countryData['code'];

        DB::transaction(function () use ($baseCode, $newCode, $countryData, $author): void {
            $row = FxCountry::where('code', $baseCode)->first();

            if ($row === null) {
                throw new UpdateException($baseCode);
            }

            if ($baseCode !== $newCode && FxCountry::where('code', $newCode)->exists()) {
                throw new DuplicateException($newCode);
            }

            $row->code = $newCode;
            $row->name = $countryData['name'];
            $row->currency_code = $countryData['currencyCode'];
            $row->name_en = $countryData['nameEn'];
            $row->name_short = $countryData['nameShort'];
            $row->sort_order = $countryData['sortOrder'];
            $row->updated_at = Carbon::now();
            $row->updated_by = $author;

            if (! $row->save()) {
                throw new UpdateException($baseCode);
            }
        });
    }
}
