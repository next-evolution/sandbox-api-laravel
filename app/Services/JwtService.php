<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\AuthUser;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class JwtService
{
    public function parse(string $token): AuthUser
    {
        $keySet = $this->getKeySet();

        try {
            $decoded = JWT::decode($token, $keySet);
        } catch (\Exception $e) {
            throw new AuthenticationException('JWT invalid: ' . $e->getMessage());
        }

        $issuer = config('jwt.issuer');
        if (($decoded->iss ?? null) !== $issuer) {
            throw new AuthenticationException('JWT issuer invalid');
        }

        $audiences        = (array) ($decoded->aud ?? []);
        $allowedAudiences = config('jwt.audiences', []);
        if (empty(array_intersect($audiences, $allowedAudiences))) {
            throw new AuthenticationException('JWT audience invalid');
        }

        $sub           = $decoded->sub ?? null;
        $email         = $decoded->email ?? null;
        $emailVerified = isset($decoded->email_verified) ? (bool) $decoded->email_verified : null;

        if ($sub === null || $email === null || $emailVerified === null) {
            throw new AuthenticationException('Required JWT claims missing');
        }

        return new AuthUser($sub, $email, $emailVerified, false, false);
    }

    /** @return array<string, \Firebase\JWT\Key> */
    private function getKeySet(): array
    {
        $cacheKey = 'jwks';
        $ttl      = (int) config('jwt.jwks_cache_ttl', 3600);

        $cached = Cache::store('redis')->get($cacheKey);
        if ($cached !== null) {
            return JWK::parseKeySet(json_decode($cached, true));
        }

        $jwksUri  = rtrim((string) config('jwt.issuer'), '/') . '/.well-known/jwks.json';
        $response = Http::timeout(5)->get($jwksUri);

        if (!$response->ok()) {
            throw new AuthenticationException('Failed to fetch JWKS');
        }

        $body = $response->body();
        Cache::store('redis')->put($cacheKey, $body, $ttl);

        return JWK::parseKeySet(json_decode($body, true));
    }
}
