<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FxEconomicIndicatorData extends Model
{
    protected $table = 'fx_economic_indicator_data';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'publication' => 'datetime',
    ];

    // 複合主キー（code, country_code, publication）のため、更新クエリの WHERE 条件を明示的に組み立てる
    protected function setKeysForSaveQuery($query): Builder
    {
        $query->where('code', '=', $this->getOriginal('code') ?? $this->getAttribute('code'))
            ->where('country_code', '=', $this->getOriginal('country_code') ?? $this->getAttribute('country_code'))
            ->where('publication', '=', $this->getOriginal('publication') ?? $this->getAttribute('publication'));

        return $query;
    }

    // fx_economic_indicator・fx_country と結合し、表示用項目（name・importance・countryName 等）を付与したクエリ
    public static function withDetails(): Builder
    {
        $table = (new self)->getTable();

        return self::query()
            ->join('fx_economic_indicator as E', function ($join) use ($table): void {
                $join->on('E.code', '=', $table.'.code')
                    ->on('E.country_code', '=', $table.'.country_code');
            })
            ->join('fx_country as C', 'C.code', '=', $table.'.country_code')
            ->select([
                $table.'.code',
                $table.'.country_code',
                'E.importance',
                'C.name as country_name',
                'C.name_short as country_name_short',
                'E.name',
                'E.description',
                $table.'.publication',
                DB::raw("DATE_FORMAT({$table}.publication, '%Y-%m-%d') as publication_date"),
                DB::raw("DATE_FORMAT({$table}.publication, '%H:%i') as publication_time"),
                DB::raw("DATE_FORMAT({$table}.publication, '%w') as day_of_week"),
                $table.'.sub_title',
                $table.'.result_value',
                $table.'.forecast_value',
                $table.'.previous_value',
                'E.unit_of_value',
                $table.'.memo',
            ]);
    }

    public function toDtoArray(): array
    {
        return [
            'code' => $this->code,
            'countryCode' => $this->country_code,
            'name' => $this->name,
            'importance' => $this->importance,
            'description' => $this->description,
            'publication' => $this->publication?->toIso8601String(),
            'publicationDate' => $this->publication_date,
            'publicationTime' => $this->publication_time,
            'dayOfWeek' => $this->day_of_week !== null ? (int) $this->day_of_week : null,
            'subTitle' => $this->sub_title,
            'resultValue' => $this->result_value,
            'forecastValue' => $this->forecast_value,
            'previousValue' => $this->previous_value,
            'unitOfValue' => $this->unit_of_value,
            'memo' => $this->memo,
            'countryName' => $this->country_name,
            'countryNameShort' => $this->country_name_short,
        ];
    }
}
