<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxSymbol;

class GetSymbolListUseCase
{
    public function execute(string $symbolType): array
    {
        return FxSymbol::where('symbol_type', $symbolType)
            ->where('deleted', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FxSymbol $s) => ['key' => $s->symbol, 'value' => $s->name])
            ->all();
    }
}
