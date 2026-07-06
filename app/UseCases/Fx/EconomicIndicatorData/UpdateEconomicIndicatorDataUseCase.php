<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use App\Exceptions\DuplicateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Models\FxEconomicIndicatorData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEconomicIndicatorDataUseCase
{
    public function execute(string $countryCode, string $code, Carbon $publication, array $data): void
    {
        $newCode = $data['code'];
        $newCountryCode = $data['countryCode'];
        $newPublication = Carbon::parse($data['publication']);

        DB::transaction(function () use ($countryCode, $code, $publication, $newCode, $newCountryCode, $newPublication, $data): void {
            $row = FxEconomicIndicatorData::where('code', $code)
                ->where('country_code', $countryCode)
                ->where('publication', $publication)
                ->first();

            if ($row === null) {
                throw new NotFoundException("({$countryCode}) {$code} / {$publication->format('Y-m-d H:i:s')}");
            }

            // publication は LocalDateTime 相当（オフセットはタイムゾーン変換に使わず文字列として比較する）
            $keyChanged = $code !== $newCode
                || $countryCode !== $newCountryCode
                || $publication->format('Y-m-d H:i:s') !== $newPublication->format('Y-m-d H:i:s');

            if ($keyChanged && FxEconomicIndicatorData::where('code', $newCode)
                ->where('country_code', $newCountryCode)
                ->where('publication', $newPublication)
                ->exists()) {
                throw new DuplicateException("({$newCountryCode}) {$newCode} / {$newPublication->format('Y-m-d H:i:s')}");
            }

            $row->code = $newCode;
            $row->country_code = $newCountryCode;
            $row->publication = $newPublication;
            $row->sub_title = $data['subTitle'] ?? null;
            $row->result_value = $data['resultValue'];
            $row->forecast_value = $data['forecastValue'] ?? null;
            $row->previous_value = $data['previousValue'] ?? null;
            $row->memo = $data['memo'] ?? null;

            if (! $row->save()) {
                throw new UpdateException("({$countryCode}) {$code} / {$publication->format('Y-m-d H:i:s')}");
            }
        });
    }
}
