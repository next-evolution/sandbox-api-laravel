<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\EconomicIndicator\AddEconomicIndicatorUseCase;
use App\UseCases\Fx\EconomicIndicator\GetEconomicIndicatorUseCase;
use App\UseCases\Fx\EconomicIndicator\SearchEconomicIndicatorUseCase;
use App\UseCases\Fx\EconomicIndicator\UpdateEconomicIndicatorUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EconomicIndicatorController extends Controller
{
    public function __construct(
        private readonly SearchEconomicIndicatorUseCase $searchEconomicIndicatorUseCase,
        private readonly AddEconomicIndicatorUseCase $addEconomicIndicatorUseCase,
        private readonly GetEconomicIndicatorUseCase $getEconomicIndicatorUseCase,
        private readonly UpdateEconomicIndicatorUseCase $updateEconomicIndicatorUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'countryCode' => ['nullable', 'string', 'max:2'],
            'importance' => ['nullable', 'string', 'max:1'],
            'name' => ['nullable', 'string', 'max:64'],
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->searchEconomicIndicatorUseCase->execute(
            $request->input('countryCode'),
            $request->input('importance'),
            $request->input('name'),
            (int) $request->input('page'),
            (int) $request->input('size'),
        );

        return response()->json([
            'returnCode' => 0,
            'totalCount' => $result['totalCount'],
            'searchCount' => $result['totalCount'],
            'totalPage' => $result['totalPage'],
            'list' => $result['list'],
        ]);
    }

    public function add(Request $request): Response
    {
        $request->validate([
            'indicator.code' => ['required', 'string', 'max:32'],
            'indicator.countryCode' => ['required', 'string', 'max:2'],
            'indicator.importance' => ['required', 'string', 'max:1'],
            'indicator.name' => ['required', 'string', 'max:64'],
            'indicator.description' => ['nullable', 'string', 'max:255'],
            'indicator.unitOfValue' => ['nullable', 'string', 'max:8'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->addEconomicIndicatorUseCase->execute($request->input('indicator'), $authUser->sub);

        return response('', 200);
    }

    public function get(string $countryCode, string $code): JsonResponse
    {
        return response()->json($this->getEconomicIndicatorUseCase->execute($countryCode, $code));
    }

    public function update(Request $request, string $countryCode, string $code): Response
    {
        $request->validate([
            'indicator.code' => ['required', 'string', 'max:32'],
            'indicator.countryCode' => ['required', 'string', 'max:2'],
            'indicator.importance' => ['required', 'string', 'max:1'],
            'indicator.name' => ['required', 'string', 'max:64'],
            'indicator.description' => ['nullable', 'string', 'max:255'],
            'indicator.unitOfValue' => ['nullable', 'string', 'max:8'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->updateEconomicIndicatorUseCase->execute($countryCode, $code, $request->input('indicator'), $authUser->sub);

        return response('', 200);
    }
}
