<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

/**
 * リクエストの Authorization ヘッダーから Bearer トークンを取り出す。
 * Cookie 方式（Web）との判別（CSRF 検証要否など）にも使う。
 */
class BearerTokenResolver
{
    public static function resolve(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public static function isBearerRequest(Request $request): bool
    {
        return self::resolve($request) !== null;
    }
}
