<?php


return [
    'api_key' => env('PAYMOB_API_KEY'),

    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),

    'timeout' => env('PAYMOB_TIMEOUT', 30),

    'connect_timeout' => env('PAYMOB_CONNECT_TIMEOUT', 10),
];