<?php

namespace Paymob\Laravel\Support;

final class PaymobTokenCacheKey
{
    /**
     * Build a cache key unique to one Paymob account and environment.
     *
     * The API key is hashed so credentials never appear in the cache key.
     */
    public static function make(array $config): string
    {
        $baseUrl = strtolower(rtrim((string) data_get($config, 'base_url', ''), '/'));
        $environment = (string) data_get(
            $config,
            'token_cache.environment',
            config('app.env', 'production'),
        );
        $apiKey = (string) data_get($config, 'api_key', '');
        $accountFingerprint = hash('sha256', $apiKey);

        $accountScope = implode('|', [
            $baseUrl,
            $environment,
            $accountFingerprint,
        ]);

        $prefix = (string) data_get(
            $config,
            'token_cache.prefix',
            'paymob:auth-token',
        );

        return $prefix . ':' . hash('sha256', $accountScope);
    }
}
