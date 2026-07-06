<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\EconomicIndicatorData\AddEconomicIndicatorDataUseCase;
use App\UseCases\Fx\EconomicIndicatorData\GetEconomicIndicatorDataUseCase;
use App\UseCases\Fx\EconomicIndicatorData\ImportTextEconomicIndicatorDataUseCase;
use App\UseCases\Fx\EconomicIndicatorData\SearchEconomicIndicatorDataUseCase;
use App\UseCases\Fx\EconomicIndicatorData\UpdateEconomicIndicatorDataUseCase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EconomicIndicatorDataController extends Controller
{
    public function __construct(
        private readonly SearchEconomicIndicatorDataUseCase $searchEconomicIndicatorDataUseCase,
        private readonly GetEconomicIndicatorDataUseCase $getEconomicIndicatorDataUseCase,
        private readonly AddEconomicIndicatorDataUseCase $addEconomicIndicatorDataUseCase,
        private readonly UpdateEconomicIndicatorDataUseCase $updateEconomicIndicatorDataUseCase,
        private readonly ImportTextEconomicIndicatorDataUseCase $importTextEconomicIndicatorDataUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'importance' => ['nullable', 'string', 'max:1'],
            'countryCode' => ['nullable', 'string', 'max:2'],
            'publicationBaseDate' => ['nullable', 'date_format:Y-m-d'],
            'sortAsc' => ['nullable', 'boolean'],
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->searchEconomicIndicatorDataUseCase->execute(
            $request->input('code'),
            $request->input('importance'),
            $request->input('countryCode'),
            $request->input('publicationBaseDate'),
            (int) $request->input('page'),
            (int) $request->input('size'),
            (bool) $request->input('sortAsc', false),
        );

        return response()->json([
            'returnCode' => 0,
            'totalCount' => $result['totalCount'],
            'searchCount' => $result['totalCount'],
            'totalPage' => $result['totalPage'],
            'list' => $result['list'],
        ]);
    }

    public function get(string $countryCode, string $code, string $publication): JsonResponse
    {
        $publicationAt = Carbon::createFromFormat('Y-m-d H:i:s', $publication);

        return response()->json($this->getEconomicIndicatorDataUseCase->execute($countryCode, $code, $publicationAt));
    }

    public function add(Request $request): Response
    {
        $request->validate([
            'data.code' => ['required', 'string', 'max:32'],
            'data.countryCode' => ['required', 'string', 'max:2'],
            'data.publication' => ['required', 'date'],
            'data.subTitle' => ['nullable', 'string', 'max:16'],
            'data.resultValue' => ['required', 'string', 'max:32'],
            'data.forecastValue' => ['nullable', 'string', 'max:32'],
            'data.previousValue' => ['nullable', 'string', 'max:32'],
            'data.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->addEconomicIndicatorDataUseCase->execute($request->input('data'));

        return response('', 200);
    }

    public function update(Request $request, string $countryCode, string $code, string $publication): Response
    {
        $request->validate([
            'data.code' => ['required', 'string', 'max:32'],
            'data.countryCode' => ['required', 'string', 'max:2'],
            'data.publication' => ['required', 'date'],
            'data.subTitle' => ['nullable', 'string', 'max:16'],
            'data.resultValue' => ['required', 'string', 'max:32'],
            'data.forecastValue' => ['nullable', 'string', 'max:32'],
            'data.previousValue' => ['nullable', 'string', 'max:32'],
            'data.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $publicationAt = Carbon::createFromFormat('Y-m-d H:i:s', $publication);

        $this->updateEconomicIndicatorDataUseCase->execute($countryCode, $code, $publicationAt, $request->input('data'));

        return response('', 200);
    }

    public function importText(Request $request): JsonResponse
    {
        $request->validate([
            'uploadFileList' => ['required', 'array', 'min:1'],
            'uploadFileList.*' => ['file'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $result = $this->importTextEconomicIndicatorDataUseCase->execute($request->file('uploadFileList'), $authUser->sub);

        return response()->json($result);
    }
}
