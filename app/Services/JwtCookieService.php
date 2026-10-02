<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * sandbox-spa-react（Web）向けに、CognitoのJWTをそのままHttpOnly CookieとしてSet-Cookieする。
 * トークン自体の検証はJwtServiceが行うため、ここではCookieの発行・失効のみを担う。
 * React/API は別オリジン配信のため SameSite=None; Secure が必須。
 */
class JwtCookieService
{
    public const COOKIE_NAME = 'sandbox_jwt';

    private int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('jwt.session_ttl', 1800);
    }

    public function buildLoginCookie(string $jwt): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: $jwt,
            expire: time() + $this->ttl,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'none',
        );
    }

    public function buildLogoutCookie(): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: '',
            expire: 1,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'none',
        );
    }
}
