<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxEconomicIndicator extends Model
{
    protected $table = 'fx_economic_indicator';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'deleted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 複合主キー（code, country_code）のため、更新・削除クエリの WHERE 条件を明示的に組み立てる
    protected function setKeysForSaveQuery($query): Builder
    {
        $query->where('code', '=', $this->getOriginal('code') ?? $this->getAttribute('code'))
            ->where('country_code', '=', $this->getOriginal('country_code') ?? $this->getAttribute('country_code'));

        return $query;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(FxCountry::class, 'country_code', 'code');
    }

    public function toDtoArray(): array
    {
        return [
            'code' => $this->code,
            'countryCode' => $this->country_code,
            'importance' => $this->importance,
            'name' => $this->name,
            'description' => $this->description,
            'unitOfValue' => $this->unit_of_value,
            'countryName' => $this->country?->name,
            'countryNameShort' => $this->country?->name_short,
        ];
    }
}
