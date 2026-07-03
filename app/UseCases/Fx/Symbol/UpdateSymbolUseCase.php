<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Symbol;

use App\Exceptions\DuplicateException;
use App\Exceptions\UpdateException;
use App\Models\FxSymbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateSymbolUseCase
{
    public function execute(string $baseSymbol, array $symbolData, string $author): void
    {
        $newSymbol = $symbolData['symbol'];

        DB::transaction(function () use ($baseSymbol, $newSymbol, $symbolData, $author): void {
            $row = FxSymbol::where('symbol', $baseSymbol)->first();

            if ($row === null) {
                throw new UpdateException($baseSymbol);
            }

            if ($baseSymbol !== $newSymbol && FxSymbol::where('symbol', $newSymbol)->exists()) {
                throw new DuplicateException($newSymbol);
            }

            $row->symbol = $newSymbol;
            $row->symbol_type = $symbolData['symbolType'];
            $row->name = $symbolData['name'];
            $row->valid_scale = $symbolData['validScale'];
            $row->target_volatility = $symbolData['targetVolatility'];
            $row->sort_order = $symbolData['sortOrder'];
            $row->updated_at = Carbon::now();
            $row->updated_by = $author;

            if (! $row->save()) {
                throw new UpdateException($baseSymbol);
            }
        });
    }
}
