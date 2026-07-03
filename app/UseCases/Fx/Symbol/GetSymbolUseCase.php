<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Symbol;

use App\Exceptions\NotFoundException;
use App\Models\FxSymbol;

class GetSymbolUseCase
{
    public function execute(string $symbol): array
    {
        $row = FxSymbol::where('symbol', $symbol)->where('deleted', false)->first();

        if ($row === null) {
            throw new NotFoundException($symbol);
        }

        return $row->toDtoArray();
    }
}
