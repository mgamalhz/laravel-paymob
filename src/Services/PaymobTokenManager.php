<?php

namespace Paymob\Laravel\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Paymob\Laravel\Support\PaymobTokenCacheKey;
use Throwable;

final class PaymobTokenManager
{
    public function __construct(
        private readonly array $config,
        private readonly ?CacheRepository $repository = null,
    ) {
    }

    public function get(callable $authenticate): string
    {
        if ($cached = $this->cached()) {
            return $cached;
        }

        return $this->underLock($authenticate);
    }

    public function refresh(string $rejectedToken, callable $authenticate): string
    {
        return $this->underLock($authenticate, $rejectedToken);
    }

    public function key(): string
    {
        return PaymobTokenCacheKey::make($this->config);
    }

    private function underLock(
        callable $authenticate,
        ?string $rejectedToken = null,
    ): string {
        $refreshStarted = false;

        try {
            return $this->cache()->lock(
                $this->key() . ':lock',
                max(1, (int) data_get($this->config, 'token_cache.lock_seconds', 10)),
            )->block(
                max(1, (int) data_get($this->config, 'token_cache.lock_wait_seconds', 5)),
                function () use ($authenticate, $rejectedToken, &$refreshStarted): string {
                    $cached = $this->cached();

                    if ($cached !== null && ($rejectedToken === null || $cached !== $rejectedToken)) {
                        return $cached;
                    }

                    $refreshStarted = true;

                    return $this->store($authenticate());
                },
            );
        } catch (Throwable $exception) {
            if ($refreshStarted) {
                throw $exception;
            }

            return $authenticate();
        }
    }

    private function cached(): ?string
    {
        try {
            $cached = $this->cache()->get($this->key());

            return is_string($cached) && $cached !== '' ? $cached : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function store(string $token): string
    {
        try {
            $this->cache()->put(
                $this->key(),
                $token,
                max(1, (int) data_get($this->config, 'token_cache.ttl_seconds', 3300)),
            );
        } catch (Throwable) {
            // The fresh token can still be used for the current request.
        }

        return $token;
    }

    private function cache(): CacheRepository
    {
        if ($this->repository) {
            return $this->repository;
        }

        $store = data_get($this->config, 'token_cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }
}
