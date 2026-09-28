# Laravel Paymob

[![Tests](https://github.com/mgamaltech/laravel-paymob/actions/workflows/tests.yml/badge.svg)](https://github.com/mgamaltech/laravel-paymob/actions/workflows/tests.yml)

## Supported versions

Every supported pairing is tested independently with stable dependencies. The oldest pairing is also tested with the lowest dependency versions allowed by Composer.

| PHP | Laravel | Testbench | Dependencies |
| --- | --- | --- | --- |
| 8.1 | 10 | `^8.0` | `prefer-stable` |
| 8.2 | 10 | `^8.0` | `prefer-stable` |
| 8.3 | 10 | `^8.0` | `prefer-stable` |
| 8.4 | 10 | `^8.0` | `prefer-stable` |
| 8.2 | 11 | `^9.0` | `prefer-stable` |
| 8.3 | 11 | `^9.0` | `prefer-stable` |
| 8.4 | 11 | `^9.0` | `prefer-stable` |
| 8.2 | 12 | `^10.0` | `prefer-stable` |
| 8.3 | 12 | `^10.0` | `prefer-stable` |
| 8.4 | 12 | `^10.0` | `prefer-stable` |
| 8.3 | 13 | `^11.0` | `prefer-stable` |
| 8.4 | 13 | `^11.0` | `prefer-stable` |
| 8.1 | 10 | `^8.0` | `prefer-lowest` |

## Structured logs

The package emits structured Paymob diagnostic logs through Laravel logging. Logs are enabled by default and use the application's default log channel unless `PAYMOB_LOG_CHANNEL` is set.

```php
// config/paymob.php
'logging' => [
    'enabled' => env('PAYMOB_LOGGING_ENABLED', true),
    'channel' => env('PAYMOB_LOG_CHANNEL'),
    'include_payloads' => env('PAYMOB_LOG_PAYLOADS', false),
    'correlation_header' => env('PAYMOB_CORRELATION_HEADER', 'X-Correlation-ID'),
],
```

Available events:

| Event | Level | Description |
| --- | --- | --- |
| `paymob.request` | `debug` | Outbound request attempt started. |
| `paymob.response` | `info` | Outbound response received. |
| `paymob.retry` | `warning` | Outbound request is being retried. |
| `paymob.failure` | `error` | Outbound request or capture job failed. |
| `paymob.webhook.received` | `info` | Validated webhook payload received. |
| `paymob.webhook.accepted` | `info` | Webhook accepted and recorded. |
| `paymob.webhook.duplicate` | `info` | Webhook transaction was already processed. |
| `paymob.webhook.rejected` | `warning` | Webhook rejected, for example invalid HMAC. |

Common fields:

| Field | Description |
| --- | --- |
| `event` | Stable event name. |
| `operation` | Package operation, for example `authenticate`, `register_order`, `request_payment_key`, `capture`, `webhook`, or `capture_job`. |
| `correlation_id` | Provided correlation ID or a generated outbound ID. Webhooks read `X-Correlation-ID` or `X-Request-ID`. |
| `merchant_reference` | Merchant reference or Paymob order reference when available. |
| `attempt` | HTTP attempt number. Present on request, retry, response, and failure events. |
| `duration_ms` | Elapsed outbound request duration in milliseconds. |
| `status` | HTTP status for outbound responses, or webhook success status for webhooks. |
| `endpoint` | Request path only. Query strings are not logged. |
| `exception` | Exception class for failure events. |

To propagate your own correlation ID on outbound calls:

```php
app(\Paymob\Laravel\PaymobClient::class)
    ->withCorrelationId($request->header('X-Correlation-ID'))
    ->registerOrder($data);
```

Complete request and response payloads are not logged by default. If `PAYMOB_LOG_PAYLOADS=true`, payload snapshots are redacted before they reach the logger. The redactor removes tokens, API keys, secrets, signatures, HMACs, authorization headers, card fields, source card data, email addresses, phone numbers, and sensitive billing/customer fields.
