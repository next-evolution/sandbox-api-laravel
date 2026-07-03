<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Country;

use App\Models\FxCountry;

class SearchCountryUseCase
{
    public function execute(int $page, int $size): array
    {
        $query = FxCountry::where('deleted', false)->orderBy('sort_order');
        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxCountry $c) => $c->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
