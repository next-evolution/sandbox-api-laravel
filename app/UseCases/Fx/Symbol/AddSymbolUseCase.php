<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Symbol;

use App\Exceptions\DuplicateException;
use App\Exceptions\InsertException;
use App\Models\FxSymbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddSymbolUseCase
{
    public function execute(array $symbolData, string $author): void
    {
        $symbol = $symbolData['symbol'];

        if (FxSymbol::where('symbol', $symbol)->exists()) {
            throw new DuplicateException($symbol);
        }

        $now = Carbon::now();
        $row = new FxSymbol;
        $row->symbol = $symbol;
        $row->symbol_type = $symbolData['symbolType'];
        $row->name = $symbolData['name'];
        $row->valid_scale = $symbolData['validScale'];
        $row->target_volatility = $symbolData['targetVolatility'];
        $row->sort_order = $symbolData['sortOrder'];
        $row->deleted = false;
        $row->created_at = $now;
        $row->created_by = $author;
        $row->updated_at = $now;
        $row->updated_by = $author;

        DB::transaction(function () use ($row): void {
            if (! $row->save()) {
                throw new InsertException($row->symbol);
            }
        });
    }
}
