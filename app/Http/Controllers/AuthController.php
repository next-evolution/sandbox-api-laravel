<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\Auth\LoginUseCase;
use App\UseCases\Auth\LogoutUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly LogoutUseCase $logoutUseCase,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $email    = $request->input('email', '');

        $userDto = $this->loginUseCase->execute($authUser, $email);

        return response()->json([
            'returnCode' => $userDto !== null ? 0 : 1,
            'user'       => $userDto,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId   = $request->input('userId', '');

        $this->logoutUseCase->execute($authUser, $userId);

        return response()->json([
            'returnCode' => 0,
            'message'    => null,
        ]);
    }
}
