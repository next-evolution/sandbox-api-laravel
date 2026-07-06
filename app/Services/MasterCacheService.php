<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class MasterCacheService
{
    private const KEY_PREFIX = 'master:';

    public function put(string $name, array $list): void
    {
        Redis::connection('cache')->set($this->toKey($name), json_encode($list));
    }

    public function get(string $name): ?array
    {
        $json = Redis::connection('cache')->get($this->toKey($name));

        if ($json === null) {
            return null;
        }

        return json_decode($json, true);
    }

    public function status(): string
    {
        $keys = array_map($this->stripConnectionPrefix(...), Redis::connection('cache')->keys(self::KEY_PREFIX.'*'));
        sort($keys);

        $lines = [];
        foreach ($keys as $key) {
            $list = json_decode(Redis::connection('cache')->get($key), true) ?? [];
            $lines[] = substr($key, strlen(self::KEY_PREFIX)).'='.count($list);
        }

        return implode("\n", $lines);
    }

    public function deleteByPattern(string $prefix): void
    {
        $keys = Redis::connection('cache')->keys($prefix.'*');

        foreach ($keys as $key) {
            Redis::connection('cache')->del($this->stripConnectionPrefix($key));
        }
    }

    private function toKey(string $name): string
    {
        return self::KEY_PREFIX.$name;
    }

    /**
     * predis クライアントは KEYS コマンドの結果に接続プレフィックスを付けたまま返す
     * （GET/SET/DEL は呼び出し側で自動的にプレフィックスを付与するため、そのまま渡すと二重付与になる）。
     */
    private function stripConnectionPrefix(string $key): string
    {
        $prefix = (string) config('database.redis.options.prefix');

        return $prefix !== '' && str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
    }
}
