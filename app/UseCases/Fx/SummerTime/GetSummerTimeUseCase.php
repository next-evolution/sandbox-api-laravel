<?php

declare(strict_types=1);

namespace App\UseCases\Fx\SummerTime;

use App\Exceptions\NotFoundException;
use App\Models\FxSummerTime;

class GetSummerTimeUseCase
{
    public function execute(int $targetYear): array
    {
        $row = FxSummerTime::where('target_year', $targetYear)->first();

        if ($row === null) {
            throw new NotFoundException((string) $targetYear);
        }

        return $row->toDtoArray();
    }
}
