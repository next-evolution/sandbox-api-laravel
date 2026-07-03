<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxSummerTime extends Model
{
    protected $table = 'fx_summer_time';

    protected $primaryKey = 'target_year';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $casts = [
        'apply_start' => 'date',
        'apply_end' => 'date',
    ];

    public function toDtoArray(): array
    {
        return [
            'targetYear' => (int) $this->target_year,
            'applyStart' => $this->apply_start->toDateString(),
            'applyEnd' => $this->apply_end->toDateString(),
        ];
    }
}
