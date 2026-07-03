<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\MasterList\GetCountryListUseCase;
use App\UseCases\Fx\MasterList\GetEconomicIndicatorListUseCase;
use App\UseCases\Fx\MasterList\GetSymbolListUseCase;
use Illuminate\Http\JsonResponse;

class MasterListController extends Controller
{
    public function __construct(
        private readonly GetSymbolListUseCase $getSymbolListUseCase,
        private readonly GetCountryListUseCase $getCountryListUseCase,
        private readonly GetEconomicIndicatorListUseCase $getEconomicIndicatorListUseCase,
    ) {}

    public function symbolList(string $symbolType): JsonResponse
    {
        return response()->json($this->getSymbolListUseCase->execute($symbolType));
    }

    public function countryList(): JsonResponse
    {
        return response()->json($this->getCountryListUseCase->execute());
    }

    public function currencyPairList(): JsonResponse
    {
        return response()->json($this->getSymbolListUseCase->execute('Trade'));
    }

    public function currencyIndexList(): JsonResponse
    {
        return response()->json($this->getSymbolListUseCase->execute('Analyze'));
    }

    public function economicIndicatorList(string $countryCode): JsonResponse
    {
        return response()->json($this->getEconomicIndicatorListUseCase->execute($countryCode));
    }
}
