<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Country;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\FxCountry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddCountryUseCase
{
    public function execute(array $countryData, string $author): void
    {
        $code = $countryData['code'];

        if (FxCountry::where('code', $code)->exists()) {
            throw new DuplicateException($code);
        }

        $now = Carbon::now();
        $row = new FxCountry;
        $row->code = $code;
        $row->name = $countryData['name'];
        $row->currency_code = $countryData['currencyCode'];
        $row->name_en = $countryData['nameEn'];
        $row->name_short = $countryData['nameShort'];
        $row->sort_order = $countryData['sortOrder'];
        $row->deleted = false;
        $row->created_at = $now;
        $row->created_by = $author;
        $row->updated_at = $now;
        $row->updated_by = $author;

        DB::transaction(function () use ($row): void {
            if (! $row->save()) {
                throw new InsertException($row->code);
            }
        });
    }
}
