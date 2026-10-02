<?php

declare(strict_types=1);

/**
 * React（sandbox-spa-react、別オリジン配信）からのCookie送受信を許可するためのCORS設定。
 * CORS_ORIGIN1 / CORS_ORIGIN2 で許可オリジンを明示する
 * （Cookie利用時、Originのワイルドカード指定はブラウザ仕様上使えない）。
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('CORS_ORIGIN1'),
        env('CORS_ORIGIN2'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
