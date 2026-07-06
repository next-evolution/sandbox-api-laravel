<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Services\MasterCacheService;

class MasterRefreshUseCase
{
    private const SYMBOL_TYPES = ['Trade', 'Analyze'];

    public function __construct(
        private readonly GetCountryListUseCase $getCountryListUseCase,
        private readonly GetSymbolListUseCase $getSymbolListUseCase,
        private readonly GetEconomicIndicatorListUseCase $getEconomicIndicatorListUseCase,
        private readonly MasterCacheService $masterCacheService,
    ) {}

    public function execute(): string
    {
        $countryList = $this->getCountryListUseCase->refresh();

        foreach (self::SYMBOL_TYPES as $symbolType) {
            $this->getSymbolListUseCase->refresh($symbolType);
        }

        foreach ($countryList as $country) {
            $this->getEconomicIndicatorListUseCase->refresh($country['key']);
        }

        $this->masterCacheService->deleteByPattern('price');

        return $this->masterCacheService->status();
    }
}
