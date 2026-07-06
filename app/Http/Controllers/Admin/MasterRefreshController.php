<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\UseCases\Fx\MasterList\MasterRefreshUseCase;
use App\UseCases\Fx\MasterList\MasterStatusUseCase;
use Illuminate\Http\JsonResponse;

class MasterRefreshController extends Controller
{
    public function __construct(
        private readonly MasterStatusUseCase $masterStatusUseCase,
        private readonly MasterRefreshUseCase $masterRefreshUseCase,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'returnCode' => 0,
            'message' => $this->masterStatusUseCase->execute(),
        ]);
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'returnCode' => 0,
            'message' => $this->masterRefreshUseCase->execute(),
        ]);
    }
}
