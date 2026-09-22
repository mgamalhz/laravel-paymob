<?php

namespace Paymob\Laravel\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\Support\PaymobTokenCacheKey;
use Throwable;

final class PaymobTokenManager
{
    public function __construct(
        private readonly array $config,
        private readonly ?CacheRepository $repository = null,
    ) {
    }

    public function get(callable $authenticate): AuthenticationResponseDto
    {
        if ($cached = $this->cached()) {
            return $cached;
        }

        return $this->underLock($authenticate);
    }

    public function refresh(string $rejectedToken, callable $authenticate): AuthenticationResponseDto
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
    ): AuthenticationResponseDto {
        $refreshStarted = false;

        try {
            return $this->cache()->lock(
                $this->key() . ':lock',
                max(1, (int) data_get($this->config, 'token_cache.lock_seconds', 10)),
            )->block(
                max(1, (int) data_get($this->config, 'token_cache.lock_wait_seconds', 5)),
                function () use ($authenticate, $rejectedToken, &$refreshStarted): AuthenticationResponseDto {
                    $cached = $this->cached();

                    if ($cached && ($rejectedToken === null || $cached->token !== $rejectedToken)) {
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

    private function cached(): ?AuthenticationResponseDto
    {
        try {
            $cached = $this->cache()->get($this->key());

            return $cached instanceof AuthenticationResponseDto ? $cached : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function store(AuthenticationResponseDto $authentication): AuthenticationResponseDto
    {
        try {
            $this->cache()->put(
                $this->key(),
                $authentication,
                max(1, (int) data_get($this->config, 'token_cache.ttl_seconds', 3300)),
            );
        } catch (Throwable) {
            // The fresh token can still be used for the current request.
        }

        return $authentication;
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
