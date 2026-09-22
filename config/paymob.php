<?php


return [
    'api_key' => env('PAYMOB_API_KEY'),

    "integration_id" => env('PAYMOB_INTEGRATION_ID'),

    'iframe_id' => env('PAYMOB_IFRAME_ID'),

    'secret_key' => env('PAYMOB_SECRET_KEY'),

    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

    'timeout' => env('PAYMOB_TIMEOUT', 30),

    'connect_timeout' => env('PAYMOB_CONNECT_TIMEOUT', 10),

    'paymob_webhook_url' => env('PAYMOB_WEBHOOK_URL', '/'),

    'hmac_secret' => env('PAYMOB_HMAC', ''),

    'logging' => [
        'enabled' => env('PAYMOB_LOGGING_ENABLED', true),
        'channel' => env('PAYMOB_LOG_CHANNEL'),
        'include_payloads' => env('PAYMOB_LOG_PAYLOADS', false),
        'correlation_header' => env('PAYMOB_CORRELATION_HEADER', 'X-Correlation-ID'),
    ],

    'retry' => [
        'times' => env('PAYMOB_RETRY_TIMES', 3),
        'sleep' => env('PAYMOB_RETRY_SLEEP', 100),
    ],
];
