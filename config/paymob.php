<?php


return [
    'api_key' => env('PAYMOB_API_KEY'),

    "integration_id" => env('PAYMOB_INTEGRATION_ID'),

    'secret_key' => env('PAYMOB_SECRET_KEY'),

    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

    'timeout' => env('PAYMOB_TIMEOUT', 30),

    'connect_timeout' => env('PAYMOB_CONNECT_TIMEOUT', 10),

    'paymob_webhook_url' => env('PAYMOB_WEBHOOK_URL', '/'),

    'receipts' => [
        'disk' => env('PAYMOB_RECEIPTS_DISK', 's3'),
        'prefix' => env('PAYMOB_RECEIPTS_PREFIX', 'paymob/receipts'),
    ],
    'hmac_secret' => env('PAYMOB_HMAC', ''),
];
