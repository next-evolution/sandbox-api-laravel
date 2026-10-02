<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BearerTokenResolver;
use App\Services\JwtCookieService;
use App\UseCases\Auth\LoginUseCase;
use App\UseCases\Auth\LogoutUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly LogoutUseCase $logoutUseCase,
        private readonly JwtCookieService $jwtCookieService,
    ) {}

    /**
     * sandbox-spa-react（Web）向けログイン。リクエストは Authorization: Bearer で受け取り
     * （ログイン成立前は Cookie が無いため）、成功時は JWT を HttpOnly Cookie として Set-Cookie する。
     * レスポンスボディにトークンは含めない。
     */
    public function loginWeb(Request $request): JsonResponse
    {
        $response = response()->json($this->executeLogin($request));

        $jwt = BearerTokenResolver::resolve($request);
        if ($jwt !== null) {
            $response->headers->setCookie($this->jwtCookieService->buildLoginCookie($jwt));
        }

        return $response;
    }

    /**
     * sandbox-app-flutter向けログイン。従来どおり Bearer 方式のみ・Cookie発行は行わない。
     */
    public function loginApp(Request $request): JsonResponse
    {
        return response()->json($this->executeLogin($request));
    }

    public function logout(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('authUser');
        $userId = $request->input('userId', '');

        $this->logoutUseCase->execute($authUser, $userId);

        $response = response()->json([
            'returnCode' => 0,
            'message' => null,
        ]);
        // React（Web）向け Cookie を失効させる。Flutter（App）は Cookie 未使用のため無害
        $response->headers->setCookie($this->jwtCookieService->buildLogoutCookie());

        return $response;
    }

    private function executeLogin(Request $request): array
    {
        $authUser = $request->attributes->get('authUser');
        $email = $request->input('email', '');

        $userDto = $this->loginUseCase->execute($authUser, $email);

        return [
            'returnCode' => $userDto !== null ? 0 : 1,
            'user' => $userDto,
        ];
    }
}
