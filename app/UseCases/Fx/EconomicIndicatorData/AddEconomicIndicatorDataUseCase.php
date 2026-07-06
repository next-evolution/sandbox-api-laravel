<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\FxEconomicIndicatorData;
use Carbon\Carbon;

class AddEconomicIndicatorDataUseCase
{
    public function execute(array $data): void
    {
        $code = $data['code'];
        $countryCode = $data['countryCode'];
        $publication = Carbon::parse($data['publication']);

        if (FxEconomicIndicatorData::where('code', $code)
            ->where('country_code', $countryCode)
            ->where('publication', $publication)
            ->exists()) {
            throw new DuplicateException("({$countryCode}) {$code} / {$publication->format('Y-m-d H:i:s')}");
        }

        $row = new FxEconomicIndicatorData;
        $row->code = $code;
        $row->country_code = $countryCode;
        $row->publication = $publication;
        $row->sub_title = $data['subTitle'] ?? null;
        $row->result_value = $data['resultValue'];
        $row->forecast_value = $data['forecastValue'] ?? null;
        $row->previous_value = $data['previousValue'] ?? null;
        $row->memo = $data['memo'] ?? null;

        if (! $row->save()) {
            throw new InsertException("({$countryCode}) {$code} / {$publication->format('Y-m-d H:i:s')}");
        }
    }
}
