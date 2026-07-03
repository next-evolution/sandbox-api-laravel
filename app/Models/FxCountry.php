<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxCountry extends Model
{
    protected $table = 'fx_country';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'deleted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toDtoArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'currencyCode' => $this->currency_code,
            'nameEn' => $this->name_en,
            'nameShort' => $this->name_short,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
