<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxCountry;
use App\Services\MasterCacheService;

class GetCountryListUseCase
{
    private const CACHE_KEY = 'country';

    public function __construct(private readonly MasterCacheService $masterCacheService) {}

    public function execute(): array
    {
        return $this->masterCacheService->get(self::CACHE_KEY) ?? $this->refresh();
    }

    public function refresh(): array
    {
        $list = FxCountry::where('deleted', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FxCountry $c) => ['key' => $c->code, 'value' => $c->name])
            ->all();

        $this->masterCacheService->put(self::CACHE_KEY, $list);

        return $list;
    }
}
