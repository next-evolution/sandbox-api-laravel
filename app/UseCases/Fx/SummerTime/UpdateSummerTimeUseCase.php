<?php

declare(strict_types=1);

namespace App\UseCases\Fx\SummerTime;

use App\Exceptions\DuplicateException;
use App\Exceptions\UpdateException;
use App\Models\FxSummerTime;
use Illuminate\Support\Facades\DB;

class UpdateSummerTimeUseCase
{
    public function execute(int $baseYear, array $summerTimeData): void
    {
        $newYear = (int) $summerTimeData['targetYear'];

        DB::transaction(function () use ($baseYear, $newYear, $summerTimeData): void {
            $row = FxSummerTime::where('target_year', $baseYear)->first();

            if ($row === null) {
                throw new UpdateException((string) $baseYear);
            }

            if ($baseYear !== $newYear && FxSummerTime::where('target_year', $newYear)->exists()) {
                throw new DuplicateException((string) $newYear);
            }

            $row->target_year = $newYear;
            $row->apply_start = $summerTimeData['applyStart'];
            $row->apply_end = $summerTimeData['applyEnd'];

            if (! $row->save()) {
                throw new UpdateException((string) $baseYear);
            }
        });
    }
}
