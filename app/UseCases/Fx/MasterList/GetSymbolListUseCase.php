<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxSymbol;
use App\Services\MasterCacheService;

class GetSymbolListUseCase
{
    public function __construct(private readonly MasterCacheService $masterCacheService) {}

    public function execute(string $symbolType): array
    {
        return $this->masterCacheService->get($this->cacheKey($symbolType)) ?? $this->refresh($symbolType);
    }

    public function refresh(string $symbolType): array
    {
        $list = FxSymbol::where('symbol_type', $symbolType)
            ->where('deleted', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FxSymbol $s) => ['key' => $s->symbol, 'value' => $s->name])
            ->all();

        $this->masterCacheService->put($this->cacheKey($symbolType), $list);

        return $list;
    }

    private function cacheKey(string $symbolType): string
    {
        return "symbol_{$symbolType}";
    }
}
