<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Symbol;

use App\Models\FxSymbol;

class SearchSymbolUseCase
{
    public function execute(string $symbolType, int $page, int $size): array
    {
        $query = FxSymbol::where('symbol_type', $symbolType)->where('deleted', false)->orderBy('sort_order');
        $totalCount = $query->count();
        $list = $totalCount === 0
            ? []
            : $query->forPage($page, $size)->get()->map(fn (FxSymbol $s) => $s->toDtoArray())->all();

        return [
            'totalCount' => $totalCount,
            'totalPage' => $totalCount === 0 ? 0 : (int) ceil($totalCount / $size),
            'list' => $list,
        ];
    }
}
