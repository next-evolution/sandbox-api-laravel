<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\SummerTime\AddSummerTimeUseCase;
use App\UseCases\Fx\SummerTime\GetSummerTimeUseCase;
use App\UseCases\Fx\SummerTime\SearchSummerTimeUseCase;
use App\UseCases\Fx\SummerTime\UpdateSummerTimeUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SummerTimeController extends Controller
{
    public function __construct(
        private readonly SearchSummerTimeUseCase $searchSummerTimeUseCase,
        private readonly AddSummerTimeUseCase $addSummerTimeUseCase,
        private readonly GetSummerTimeUseCase $getSummerTimeUseCase,
        private readonly UpdateSummerTimeUseCase $updateSummerTimeUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->searchSummerTimeUseCase->execute(
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
            'summerTime.targetYear' => ['required', 'integer'],
            'summerTime.applyStart' => ['required', 'date_format:Y-m-d'],
            'summerTime.applyEnd' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->addSummerTimeUseCase->execute($request->input('summerTime'));

        return response('', 200);
    }

    public function get(int $targetYear): JsonResponse
    {
        return response()->json($this->getSummerTimeUseCase->execute($targetYear));
    }

    public function update(Request $request, int $targetYear): Response
    {
        $request->validate([
            'summerTime.targetYear' => ['required', 'integer'],
            'summerTime.applyStart' => ['required', 'date_format:Y-m-d'],
            'summerTime.applyEnd' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->updateSummerTimeUseCase->execute($targetYear, $request->input('summerTime'));

        return response('', 200);
    }
}
