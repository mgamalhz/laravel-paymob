<?php


return [
    'api_key' => env('PAYMOB_API_KEY'),

    "integration_id" => env('PAYMOB_INTEGRATION_ID'),

    'secret_key' => env('PAYMOB_SECRET_KEY'),

    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

    'timeout' => env('PAYMOB_TIMEOUT', 30),

    'connect_timeout' => env('PAYMOB_CONNECT_TIMEOUT', 10),

    'token_cache' => [
        'store' => env('PAYMOB_TOKEN_CACHE_STORE'),
        'prefix' => env('PAYMOB_TOKEN_CACHE_PREFIX', 'paymob:auth-token'),
        // Paymob tokens normally live for one hour; keep a five-minute safety margin.
        'ttl_seconds' => env('PAYMOB_TOKEN_TTL_SECONDS', 3300),
        'lock_seconds' => env('PAYMOB_TOKEN_LOCK_SECONDS', 10),
        'lock_wait_seconds' => env('PAYMOB_TOKEN_LOCK_WAIT_SECONDS', 5),
        'environment' => env('PAYMOB_ENVIRONMENT', env('APP_ENV', 'production')),
    ],

    'paymob_webhook_url' => env('PAYMOB_WEBHOOK_URL', '/'),

    'hmac_secret' => env('PAYMOB_HMAC', ''),
];
