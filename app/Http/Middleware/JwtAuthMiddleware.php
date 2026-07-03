<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuthUser;
use App\Models\SandboxUser;
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

    public function handle(Request $request, Closure $next): Response
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

        $authUser = $this->sessionService->findBySub($jwtAuthUser->sub);

        if ($authUser !== null) {
            $this->sessionService->update($authUser);
        } else {
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
            }
        }

        if ($authUser === null) {
            return $this->forbidden('User not found');
        }

        if (! $authUser->isApproved()) {
            return $this->forbidden('Not approved');
        }

        $request->attributes->set('authUser', $authUser);

        return $next($request);
    }

    private function resolveToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
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
