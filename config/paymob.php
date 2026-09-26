<?php

return [
    'api_key' => env('PAYMOB_API_KEY'),

    'integration_id' => env('PAYMOB_INTEGRATION_ID'),

    'iframe_id' => env('PAYMOB_IFRAME_ID'),

    'secret_key' => env('PAYMOB_SECRET_KEY'),

    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

    'timeout' => env('PAYMOB_TIMEOUT', 30),

    'connect_timeout' => env('PAYMOB_CONNECT_TIMEOUT', 10),

    'paymob_webhook_url' => env('PAYMOB_WEBHOOK_URL', '/'),

    'hmac_secret' => env('PAYMOB_HMAC', ''),

    'token_cache' => [
        'store' => env('PAYMOB_TOKEN_CACHE_STORE'),
        'environment' => env('PAYMOB_TOKEN_CACHE_ENVIRONMENT', env('APP_ENV', 'production')),
        'prefix' => env('PAYMOB_TOKEN_CACHE_PREFIX', 'paymob:auth-token'),
        'ttl_seconds' => env('PAYMOB_TOKEN_CACHE_TTL_SECONDS', 58 * 60),
        'lock_seconds' => env('PAYMOB_TOKEN_CACHE_LOCK_SECONDS', 10),
        'lock_wait_seconds' => env('PAYMOB_TOKEN_CACHE_LOCK_WAIT_SECONDS', 5),
    ],

    "retry_limit" => 5,
    'retry_base_delay_ms' => 500,
    'retry_max_delay_ms' => 10000,
    'retry_jitter_ms' => 250,
];
