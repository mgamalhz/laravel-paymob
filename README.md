# Laravel Paymob

A Laravel package for the Paymob Accept authentication, order, payment-key and
iframe APIs, with signed transaction callbacks, capture jobs and PDF receipts.

**Pre-1.0 / experimental.** The API can change. Production use requires merchant
sandbox acceptance and review of the limitations below. No public release or
Packagist availability is assumed by this README.

## Requirements and installation

Composer declares PHP `>=8.1 <8.5` and Laravel components `^10.50.3`, `^11`, `^12`
or `^13`. Each Laravel version also imposes its own PHP requirements; these are
constraints, not a claim that every matrix combination has passed CI.
Use SQLite/MySQL/PostgreSQL for package tables; configure a queue, cache and storage
before enabling capture/receipts. Dompdf is installed as a dependency.

Until a registry release exists, install from a local checkout in a fresh Laravel
application (replace the absolute path with your checkout):

```sh
composer config repositories.paymob path D:/laravel-paymob
composer require paymob/laravel:dev-package-readme -W
```

After an approved registry release is actually available, the normal command is:

```sh
composer require paymob/laravel:^0.1
```

Laravel discovers `Paymob\Laravel\PaymobServiceProvider` automatically. If discovery
is disabled, register it in `config/app.php` providers (Laravel 10) or
`bootstrap/providers.php` (Laravel 11+). The optional facade is
`Paymob\Laravel\Facade\Paymob`; dependency injection via the contract is recommended.

```sh
php artisan vendor:publish --provider="Paymob\Laravel\PaymobServiceProvider" --tag=paymob-config
php artisan migrate
php artisan config:cache
php artisan route:list --path=paymob
```

Migrations and the webhook route load automatically. Do not publish migrations into
an app that already ran the originals. Optional tags are `paymob-migrations` and
`paymob-routes`; publishing routes only copies a file and does not load that copy.
Do not load both route files.

## Configuration

Use placeholder values below only in local development; obtain real values from
your merchant account and inject production secrets through deployment settings.
Never commit `.env`, log credentials or return configuration/authentication DTOs.
Run `php artisan config:cache` again after configuration changes.

```dotenv
PAYMOB_API_KEY=replace-with-your-api-key
PAYMOB_INTEGRATION_ID=123456
PAYMOB_IFRAME_ID=123456
PAYMOB_HMAC=replace-with-your-webhook-hmac-secret
PAYMOB_WEBHOOK_URL=/paymob/webhook
```

All options live in `config/paymob.php`:

| Config key | Environment variable | Default / use |
|---|---|---|
| api_key | PAYMOB_API_KEY | Required for authenticated API calls |
| integration_id | PAYMOB_INTEGRATION_ID | Required by your integration; pass explicitly to RequestPaymentKeyData |
| iframe_id | PAYMOB_IFRAME_ID | Required by paymentRedirectUrl unless supplied as argument |
| hmac_secret | PAYMOB_HMAC | Required for callbacks; empty rejects signatures |
| secret_key | PAYMOB_SECRET_KEY | Optional; current Accept flow does not use it |
| base_url | PAYMOB_BASE_URL | https://accept.paymob.com; select your merchant region explicitly |
| timeout | PAYMOB_TIMEOUT | 30 seconds per HTTP attempt |
| connect_timeout | PAYMOB_CONNECT_TIMEOUT | 10 seconds per connection attempt |
| paymob_webhook_url | PAYMOB_WEBHOOK_URL | `/`; change to a dedicated path; empty disables automatic route |
| receipts.disk | PAYMOB_RECEIPTS_DISK | s3; configure Laravel filesystem credentials separately |
| receipts.prefix | PAYMOB_RECEIPTS_PREFIX | paymob/receipts |
| logging.enabled | PAYMOB_LOGGING_ENABLED | true |
| logging.channel | PAYMOB_LOG_CHANNEL | null; Laravel default channel |
| logging.include_payloads | PAYMOB_LOG_PAYLOADS | false; keep disabled, even for troubleshooting |
| logging.correlation_header | PAYMOB_CORRELATION_HEADER | X-Correlation-ID |
| token_cache.store | PAYMOB_TOKEN_CACHE_STORE | null; Laravel default cache store |
| token_cache.environment | PAYMOB_TOKEN_CACHE_ENVIRONMENT | APP_ENV or production |
| token_cache.prefix | PAYMOB_TOKEN_CACHE_PREFIX | paymob:auth-token |
| token_cache.ttl_seconds | PAYMOB_TOKEN_CACHE_TTL_SECONDS | 3480 |
| token_cache.lock_seconds | PAYMOB_TOKEN_CACHE_LOCK_SECONDS | 10 |
| token_cache.lock_wait_seconds | PAYMOB_TOKEN_CACHE_LOCK_WAIT_SECONDS | 5 |
| retry_limit | none; edit config | 5 total HTTP attempts, not five retries |
| retry_base_delay_ms | none; edit config | 500 |
| retry_max_delay_ms | none; edit config | 10000 |
| retry_jitter_ms | none; edit config | 250 |

## Shortest payment flow

Run this server-side in a controller/service. Amounts are integer minor units
(10000 = EGP 100.00). Resolve the client after configuration is loaded.

```php
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;

$paymob = app(PaymobClientContract::class);
$authentication = $paymob->authenticate(); // cached internally; never log token
$billing = new BillingDataDto(
    firstName: 'Local', lastName: 'Customer', email: 'buyer@example.test',
    phoneNumber: '0000000000', street: 'Example Street', building: '1',
    city: 'Cairo', country: 'EG',
);
$order = $paymob->registerOrder(new RegisterOrderData(
    amount: 10000, currency: 'EGP',
    paymentMethodIds: [(int) config('paymob.integration_id')],
    items: [new OrderItemDto(name: 'Example item', amount: 10000, quantity: 1)],
    billingData: $billing,
    specialReference: 'local-order-123', // stable unique merchant order reference
));
$key = $paymob->requestPaymentKey(new RequestPaymentKeyData(
    amountCents: 10000, currency: 'EGP', orderId: $order->id,
    integrationId: (int) config('paymob.integration_id'), billingData: $billing,
));
$url = $paymob->paymentRedirectUrl($key->token);
// In a controller:
return redirect()->away($url);
```

Alternatively pass `$url` into a Blade view and use `<iframe src="{{ $url }}"
 title="Secure payment"></iframe>`. Only expose the short-lived payment URL to the
intended payer; never put it in analytics or logs. A browser redirect is not proof
of payment. Persist the Paymob order ID and expected amount against your own order
before the payer can complete checkout.

`registerOrder` currently sends amount, currency, items, delivery_needed=false,
auth_token and optional merchant_order_id. The DTO also accepts paymentMethodIds,
billingData, customer, notificationUrl and redirectionUrl, but those fields are
not sent by this Accept order endpoint implementation. Billing data is sent by
`requestPaymentKey`. Do not assume this is a Unified Checkout/intention client.

## Webhooks and fulfillment

Set PAYMOB_WEBHOOK_URL to `/paymob/webhook` and configure the merchant's transaction
processed callback to your public HTTPS URL. The package registers POST automatically
without the web middleware group. If registering the controller yourself under web
middleware, exempt only that route from CSRF. Do not add a second duplicate route.

Set PAYMOB_HMAC to the merchant HMAC secret. The signature uses SHA512 over Paymob's
ordered transaction fields with lowercase boolean values and a timing-safe compare.
The API key and HMAC secret are different credentials.

This documentation branch starts independently from package-skeleton. Its inherited
controller expects `hmac` and `obj` in request input, returns 422 for missing hmac
and 403 for invalid signatures, and stores one receipt per transaction ID. Sequential
replays return 200 without re-emitting `PaymobWebhookReceived`. It does not support
multiple lifecycle notifications for the same transaction. The independent
`webhook-security` branch hardens query-signature handling, concurrent delivery,
state validation and payload retention; merge and review that branch before release.

Listen for `Paymob\Laravel\Events\PaymobWebhookReceived`; the typed payload exposes
transactionId, orderId, amountCents, status and verifiedAt. Match the persisted order,
amount, currency, integration and terminal status before fulfilling. Make listeners
idempotent and defer external effects until commit. The inherited controller may
dispatch capture on successful callbacks; do not enable that flow before applying
the security changes. Signed success alone does not mean capture is appropriate.

## Operations and failures

Authentication uses a cache scoped by base URL, environment and API-key hash, with
an atomic refresh lock. Use a shared protected Redis/database cache in multi-worker
apps. Cache/lock failures fall back to uncached authentication; an old `paymob_token`
cache key is still accepted for compatibility: clear it when switching merchants.

HTTP retries cover connection failures, 429 and 5xx with bounded exponential backoff,
jitter and numeric Retry-After. A 401/403 refreshes authentication once. Order creation
only enables retries when specialReference is set. A timeout can still mean the
remote operation succeeded: reconcile by merchant reference before submitting again.

Catch `PaymobAuthenticationException` for authentication and `PaymobDomainException`
for other API failures. Both extend the package exception hierarchy. Show customers
a generic failure and record sanitized operation/status metadata; do not log the
exception chain or raw HTTP response. Never catch an error and mark an order paid.

For explicit capture, implement `PaymobCapturable` on a persisted order and inspect
`ProcessPaymobPayment` before dispatching it. It has 5 tries, backoff 30/120/300/900
seconds, a 120-second timeout and cache-based WithoutOverlapping middleware. Use a
shared lock-capable cache and queue retry_after greater than worker/job timeout.
Capture retries have uncertain remote outcomes and need reconciliation. Receipt jobs
render PDF via Dompdf and store on receipts.disk; configure private storage and
application-authorized access. Run workers when these jobs are used:

```sh
php artisan queue:work --timeout=120
php artisan queue:failed
```

Use a database/Redis queue in production, monitor failed jobs and investigate before
retrying capture work. The sync queue runs work inside the request. Never enable
APP_DEBUG in production. Keep payload logging off; existing redaction is a defense,
not permission to log payment bodies, headers or personal information.

## Troubleshooting

- **Package not found:** this checkout may not be on Packagist; use the local path repository command above with the checked-out branch name.
- **Binding/provider missing:** run `composer dump-autoload` and `php artisan package:discover`; check discovery exclusions and manual provider registration.
- **No config or stale credentials:** publish with tag paymob-config, check environment injection, then rebuild the config cache.
- **401/403 from API:** verify region, API key and integration ownership; clear merchant-scoped/legacy token cache after changing credentials.
- **Missing iframe:** set a positive iframe ID or pass the second paymentRedirectUrl argument.
- **404/419 callback:** inspect route:list, public HTTPS routing, cached routes and route-specific CSRF exemption.
- **Invalid HMAC:** verify PAYMOB_HMAC, complete signed fields and unmodified JSON; do not disable verification.
- **Missing tables:** run migrations once; check the database connection and avoid publishing duplicate migrations.
- **Stuck jobs/receipts:** check queue workers, failed jobs, cache lock support, private filesystem credentials and Dompdf availability.
- **Duplicate or ambiguous payment:** reconcile with the provider before retrying; do not delete replay receipts to force fulfillment.

## Contributors

```sh
composer install
composer test
composer analyse
composer format:test
composer format
```

`composer format` changes files; other commands check them. CI defines a Laravel/PHP
matrix in `.github/workflows/tests.yml` and style/static analysis in quality.yml.
Use only fake credentials and `Http::fake()` / `Http::preventStrayRequests()` in tests.
Do not contact a live payment environment. See `docs/readme-verification.md` for the
actual local verification and its limits. Run supported-version CI before release.
