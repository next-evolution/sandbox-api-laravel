<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuthUser;
use App\Models\SandboxUser;
use App\Services\BearerTokenResolver;
use App\Services\JwtCookieService;
use App\Services\JwtService;
use App\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly SessionService $sessionService,
    ) {}

    /**
     * @param  string  $mode  'approved'（デフォルト）= 承認済みユーザーのみ通過。
     *                        'any' = JWTが有効であれば承認前・未登録でも通過させる
     *                        （ログインAPI、POST/GET /v1/user 専用。コントローラ側で個別処理する）
     */
    public function handle(Request $request, Closure $next, string $mode = 'approved'): Response
    {
        $token = $this->resolveToken($request);

        if ($token === null) {
            return $this->unauthorized('No token provided');
        }

        try {
            $jwtAuthUser = $this->jwtService->parse($token);
        } catch (\Throwable $e) {
            Log::error('JwtAuthMiddleware['.$request->path().'] '.$e->getMessage());

            return $this->unauthorized($e->getMessage());
        }

        $authUser = $this->resolveAuthUser($jwtAuthUser);

        if ($mode === 'approved' && ! $authUser->isApproved()) {
            return $this->forbidden('Not approved');
        }

        $request->attributes->set('authUser', $authUser);

        return $next($request);
    }

    private function resolveAuthUser(AuthUser $jwtAuthUser): AuthUser
    {
        $authUser = $this->sessionService->findBySub($jwtAuthUser->sub);

        if ($authUser !== null) {
            $this->sessionService->update($authUser);

            return $authUser;
        }

        // silent login: DB から AuthUser を復元
        $user = SandboxUser::where('user_id', $jwtAuthUser->sub)->first();
        if ($user !== null) {
            $authUser = new AuthUser(
                sub: $jwtAuthUser->sub,
                email: $jwtAuthUser->email,
                emailVerified: $jwtAuthUser->emailVerified,
                admin: $user->admin,
                approved: $user->isApproved(),
            );
            $this->sessionService->save($authUser);

            return $authUser;
        }

        // sandbox_user 未登録（初回ログイン・登録前）は JWT 由来の authUser にフォールバックする
        return $jwtAuthUser;
    }

    /**
     * トークン文字列を取り出す。Authorization ヘッダー（Bearer、Flutter向け）を優先し、
     * 無ければ Cookie（sandbox_jwt、React向け）を見る。
     */
    private function resolveToken(Request $request): ?string
    {
        $bearerToken = BearerTokenResolver::resolve($request);
        if ($bearerToken !== null) {
            return $bearerToken;
        }

        $cookieToken = $request->cookie(JwtCookieService::COOKIE_NAME);

        return is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : null;
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'status' => 401,
            'statusText' => 'UNAUTHORIZED',
            'message' => $message,
        ], 401);
    }

    private function forbidden(string $message): Response
    {
        return response()->json([
            'status' => 403,
            'statusText' => 'FORBIDDEN',
            'message' => $message,
        ], 403);
    }
}
