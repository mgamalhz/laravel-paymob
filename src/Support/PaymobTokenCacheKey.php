<?php

namespace Paymob\Laravel\Support;

final class PaymobTokenCacheKey
{
    public static function make(array $config): string
    {
        $scope = implode('|', [
            strtolower(rtrim((string) data_get($config, 'base_url', ''), '/')),
            (string) data_get($config, 'token_cache.environment', config('app.env', 'production')),
            hash('sha256', (string) data_get($config, 'api_key', '')),
        ]);

        return (string) data_get($config, 'token_cache.prefix', 'paymob:auth-token')
            . ':' . hash('sha256', $scope);
    }
}
