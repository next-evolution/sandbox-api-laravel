<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuthUser;
use Illuminate\Support\Facades\Cache;

class SessionService
{
    private const KEY_PREFIX = 'session:';

    private int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('jwt.session_ttl', 1800);
    }

    public function save(AuthUser $authUser): void
    {
        Cache::store('redis')->put(
            $this->toKey($authUser->sub),
            json_encode($authUser->toArray()),
            $this->ttl,
        );
    }

    public function findBySub(string $sub): ?AuthUser
    {
        $json = Cache::store('redis')->get($this->toKey($sub));

        if ($json === null) {
            return null;
        }

        return AuthUser::fromArray(json_decode($json, true));
    }

    public function deleteBySub(string $sub): void
    {
        Cache::store('redis')->forget($this->toKey($sub));
    }

    public function update(AuthUser $authUser): void
    {
        $key = $this->toKey($authUser->sub);
        if (Cache::store('redis')->has($key)) {
            Cache::store('redis')->put($key, json_encode($authUser->toArray()), $this->ttl);
        }
    }

    private function toKey(string $sub): string
    {
        return self::KEY_PREFIX . $sub;
    }
}
