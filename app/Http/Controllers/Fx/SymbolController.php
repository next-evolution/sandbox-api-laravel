<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\Symbol\AddSymbolUseCase;
use App\UseCases\Fx\Symbol\GetSymbolUseCase;
use App\UseCases\Fx\Symbol\SearchSymbolUseCase;
use App\UseCases\Fx\Symbol\UpdateSymbolUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SymbolController extends Controller
{
    public function __construct(
        private readonly SearchSymbolUseCase $searchSymbolUseCase,
        private readonly AddSymbolUseCase $addSymbolUseCase,
        private readonly GetSymbolUseCase $getSymbolUseCase,
        private readonly UpdateSymbolUseCase $updateSymbolUseCase,
    ) {}

    public function currencyPairList(): JsonResponse
    {
        $result = $this->searchSymbolUseCase->execute('Trade', 1, 500);

        return response()->json($result['list']);
    }

    public function currencyIndexList(): JsonResponse
    {
        $result = $this->searchSymbolUseCase->execute('Analyze', 1, 500);

        return response()->json($result['list']);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'symbolType' => ['required', 'string', 'in:Trade,Analyze'],
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->searchSymbolUseCase->execute(
            $request->input('symbolType'),
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
            'symbol.symbol' => ['required', 'string', 'max:16'],
            'symbol.symbolType' => ['required', 'string', 'in:Trade,Analyze'],
            'symbol.name' => ['required', 'string', 'max:64'],
            'symbol.validScale' => ['required', 'integer'],
            'symbol.targetVolatility' => ['required', 'numeric'],
            'symbol.sortOrder' => ['required', 'integer'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->addSymbolUseCase->execute($request->input('symbol'), $authUser->sub);

        return response('', 200);
    }

    public function get(string $symbol): JsonResponse
    {
        return response()->json($this->getSymbolUseCase->execute($symbol));
    }

    public function update(Request $request, string $symbol): Response
    {
        $request->validate([
            'symbol.symbol' => ['required', 'string', 'max:16'],
            'symbol.symbolType' => ['required', 'string', 'in:Trade,Analyze'],
            'symbol.name' => ['required', 'string', 'max:64'],
            'symbol.validScale' => ['required', 'integer'],
            'symbol.targetVolatility' => ['required', 'numeric'],
            'symbol.sortOrder' => ['required', 'integer'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->updateSymbolUseCase->execute($symbol, $request->input('symbol'), $authUser->sub);

        return response('', 200);
    }
}
