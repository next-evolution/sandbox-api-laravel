<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\Models\FxSymbol;
use App\UseCases\Fx\BarData\ImportCsvBarDataUseCase;
use App\UseCases\Fx\BarData\SearchBarDataUseCase;
use App\UseCases\Fx\BarData\StatusBarDataUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarDataController extends Controller
{
    public function __construct(
        private readonly SearchBarDataUseCase $searchBarDataUseCase,
        private readonly ImportCsvBarDataUseCase $importCsvBarDataUseCase,
        private readonly StatusBarDataUseCase $statusBarDataUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'barType' => ['required', 'string', 'in:M15,H1,H4,D1'],
            'symbol' => ['required', 'string', 'regex:/^([A-Z0-9]{3,6}|[A-Z]{3})$/'],
            'barDateFrom' => ['nullable', 'string', 'regex:/^$|^[0-9]{8}$/'],
            'barDateTo' => ['nullable', 'string', 'regex:/^$|^[0-9]{8}$/'],
            'sortAsc' => ['nullable', 'boolean'],
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $barDateFrom = $request->input('barDateFrom') ?: null;
        $barDateTo = $request->input('barDateTo') ?: null;

        $result = $this->searchBarDataUseCase->execute(
            $request->input('barType'),
            $request->input('symbol'),
            $barDateFrom,
            $barDateTo,
            (bool) $request->input('sortAsc', false),
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

    public function importCsv(Request $request, string $symbol, string $barType, string $skipLatest): JsonResponse
    {
        $request->validate([
            'uploadFile' => ['required', 'file'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $result = $this->importCsvBarDataUseCase->execute(
            $symbol,
            $barType,
            filter_var($skipLatest, FILTER_VALIDATE_BOOLEAN),
            $request->file('uploadFile'),
            $authUser->sub,
        );

        return response()->json($result);
    }

    public function status(string $symbolType, string $barType): JsonResponse
    {
        FxSymbol::assertValidSymbolType($symbolType);

        $result = $this->statusBarDataUseCase->execute($symbolType, $barType);

        return response()->json($result);
    }
}
