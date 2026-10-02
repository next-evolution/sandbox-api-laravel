<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenException;
use App\Services\BearerTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie方式（Web）向けのCSRF対策（ダブルサブミットCookie、XSRF-TOKEN / X-XSRF-TOKEN）。
 * Bearer方式（Flutter）はCSRF検証をスキップする（sandbox-app-flutterはトークンをCookieに
 * 保存しないため、そもそもCSRFの対象外）。
 *
 * ログインAPI（/v1/auth/login/web・/app）はBearerリクエストとして呼ばれる
 * （ログイン成立前はCookieが無いため）ため、この検証は自然にスキップされる。
 * XSRF-TOKEN CookieはログインAPIのレスポンスを含む全レスポンスで発行・更新する。
 */
class CsrfCookieMiddleware
{
    private const COOKIE_NAME = 'XSRF-TOKEN';

    private const HEADER_NAME = 'X-XSRF-TOKEN';

    private const TOKEN_LENGTH = 40;

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->resolveToken($request);

        if (! in_array($request->method(), self::SAFE_METHODS, true)
            && ! BearerTokenResolver::isBearerRequest($request)
        ) {
            $header = (string) $request->header(self::HEADER_NAME, '');
            if ($header === '' || ! hash_equals($token, $header)) {
                throw new ForbiddenException('CSRFトークンが不正です');
            }
        }

        $response = $next($request);

        $response->headers->setCookie(new Cookie(
            name: self::COOKIE_NAME,
            value: $token,
            expire: 0,
            path: '/',
            secure: true,
            httpOnly: false,
            sameSite: 'none',
        ));

        return $response;
    }

    private function resolveToken(Request $request): string
    {
        $existing = $request->cookie(self::COOKIE_NAME);
        if (is_string($existing) && strlen($existing) === self::TOKEN_LENGTH) {
            return $existing;
        }

        return Str::random(self::TOKEN_LENGTH);
    }
}
