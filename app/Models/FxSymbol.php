<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxSymbol extends Model
{
    protected $table = 'fx_symbol';

    protected $primaryKey = 'symbol';

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
            'symbol' => $this->symbol,
            'symbolType' => $this->symbol_type,
            'name' => $this->name,
            'validScale' => (int) $this->valid_scale,
            'targetVolatility' => (float) $this->target_volatility,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
