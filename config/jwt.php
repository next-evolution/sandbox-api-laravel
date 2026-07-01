<?php

declare(strict_types=1);

return [
    'issuer' => env('JWT_ISSUER'),
    'audiences' => array_values(array_filter([
        env('JWT_AUDIENCE1'),
        env('JWT_AUDIENCE2'),
        env('JWT_AUDIENCE3'),
    ])),
    'session_ttl' => (int) env('SESSION_LIFETIME', 30) * 60,
    'jwks_cache_ttl' => 3600,
];
