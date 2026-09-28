<?php

namespace Paymob\Laravel\Support;

use Illuminate\Support\Facades\Log;

final class PaymobLogger
{
    public static function debug(string $event, array $context = []): void
    {
        self::log('debug', $event, $context);
    }

    public static function info(string $event, array $context = []): void
    {
        self::log('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::log('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::log('error', $event, $context);
    }

    private static function log(string $level, string $event, array $context): void
    {
        if (config('paymob.logging.enabled', true) !== true) {
            return;
        }

        $logger = Log::channel(config('paymob.logging.channel'));

        $logger->{$level}($event, PaymobLogRedactor::redact(array_merge([
            'event' => $event,
        ], $context)));
    }
}
