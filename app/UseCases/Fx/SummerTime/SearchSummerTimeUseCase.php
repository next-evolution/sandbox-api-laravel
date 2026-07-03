<?php

declare(strict_types=1);

namespace App\UseCases\Fx\SummerTime;

use App\Models\FxSummerTime;

class SearchSummerTimeUseCase
{
    public function execute(int $page, int $size): array
    {
        $query = FxSummerTime::orderBy('target_year');
        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxSummerTime $s) => $s->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
