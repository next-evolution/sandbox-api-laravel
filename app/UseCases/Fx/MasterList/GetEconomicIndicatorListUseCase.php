<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxEconomicIndicator;
use App\Services\MasterCacheService;

class GetEconomicIndicatorListUseCase
{
    public function __construct(private readonly MasterCacheService $masterCacheService) {}

    public function execute(string $countryCode): array
    {
        return $this->masterCacheService->get($this->cacheKey($countryCode)) ?? $this->refresh($countryCode);
    }

    public function refresh(string $countryCode): array
    {
        $list = FxEconomicIndicator::where('country_code', $countryCode)
            ->where('deleted', false)
            ->orderBy('code')
            ->get()
            ->map(fn (FxEconomicIndicator $e) => ['key' => $e->code, 'value' => $e->name])
            ->all();

        $this->masterCacheService->put($this->cacheKey($countryCode), $list);

        return $list;
    }

    private function cacheKey(string $countryCode): string
    {
        return "economic_indicator_{$countryCode}";
    }
}
