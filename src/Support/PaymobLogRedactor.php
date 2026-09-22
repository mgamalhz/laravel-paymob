<?php

namespace Paymob\Laravel\Support;

final class PaymobLogRedactor
{
    private const REDACTED = '[redacted]';

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'api_key',
        'auth_token',
        'authorization',
        'card_number',
        'cvv',
        'cvc',
        'email',
        'first_name',
        'hmac',
        'last_name',
        'phone_number',
        'secret',
        'secret_key',
        'signature',
        'source_data',
        'street',
        'token',
    ];

    public static function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::redactArray($value);
        }

        if (is_string($value)) {
            return self::redactString($value);
        }

        return $value;
    }

    private static function redactArray(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = self::redact($item);
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private static function redactString(string $value): string
    {
        $value = preg_replace('/(token|api_key|secret|signature|hmac)=([^&\s]+)/i', '$1=' . self::REDACTED, $value) ?? $value;
        $value = preg_replace(
            '/(["\']?(?:api_key|auth_token|authorization|card_number|cvv|cvc|email|first_name|hmac|last_name|phone_number|secret|secret_key|signature|street|token)["\']?\s*[:=]\s*)["\']?([^,"\'\s}]+)/i',
            '$1' . self::REDACTED,
            $value
        ) ?? $value;
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer ' . self::REDACTED, $value) ?? $value;
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', self::REDACTED, $value) ?? $value;
        $value = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', self::REDACTED, $value) ?? $value;

        return $value;
    }
}
