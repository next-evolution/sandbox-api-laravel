<?php

declare(strict_types=1);

namespace App\UseCases\Fx\SummerTime;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\FxSummerTime;
use Illuminate\Support\Facades\DB;

class AddSummerTimeUseCase
{
    public function execute(array $summerTimeData): void
    {
        $targetYear = (int) $summerTimeData['targetYear'];

        if (FxSummerTime::where('target_year', $targetYear)->exists()) {
            throw new DuplicateException((string) $targetYear);
        }

        $row = new FxSummerTime;
        $row->target_year = $targetYear;
        $row->apply_start = $summerTimeData['applyStart'];
        $row->apply_end = $summerTimeData['applyEnd'];

        DB::transaction(function () use ($row): void {
            if (! $row->save()) {
                throw new InsertException((string) $row->target_year);
            }
        });
    }
}
