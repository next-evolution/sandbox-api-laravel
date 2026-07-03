<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fx;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\Country\AddCountryUseCase;
use App\UseCases\Fx\Country\GetCountryUseCase;
use App\UseCases\Fx\Country\SearchCountryUseCase;
use App\UseCases\Fx\Country\UpdateCountryUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CountryController extends Controller
{
    public function __construct(
        private readonly SearchCountryUseCase $searchCountryUseCase,
        private readonly AddCountryUseCase $addCountryUseCase,
        private readonly GetCountryUseCase $getCountryUseCase,
        private readonly UpdateCountryUseCase $updateCountryUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->searchCountryUseCase->execute(
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
            'country.code' => ['required', 'string', 'max:2'],
            'country.name' => ['required', 'string', 'max:64'],
            'country.currencyCode' => ['required', 'string', 'max:3'],
            'country.nameEn' => ['required', 'string', 'max:64'],
            'country.nameShort' => ['required', 'string', 'max:8'],
            'country.sortOrder' => ['required', 'integer'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->addCountryUseCase->execute($request->input('country'), $authUser->sub);

        return response('', 200);
    }

    public function get(string $code): JsonResponse
    {
        return response()->json($this->getCountryUseCase->execute($code));
    }

    public function update(Request $request, string $code): Response
    {
        $request->validate([
            'country.code' => ['required', 'string', 'max:2'],
            'country.name' => ['required', 'string', 'max:64'],
            'country.currencyCode' => ['required', 'string', 'max:3'],
            'country.nameEn' => ['required', 'string', 'max:64'],
            'country.nameShort' => ['required', 'string', 'max:8'],
            'country.sortOrder' => ['required', 'integer'],
        ]);

        $authUser = $request->attributes->get('authUser');

        $this->updateCountryUseCase->execute($code, $request->input('country'), $authUser->sub);

        return response('', 200);
    }
}
