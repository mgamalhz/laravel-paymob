# Paymob Webhook HMAC Awareness

## Core Idea

A payment webhook is not proof that a payment happened.

It is only an HTTP request from the public internet that claims a payment happened. Anyone can send an HTTP request to your application if they know or guess the webhook URL. Because of that, the package must treat every Paymob callback as untrusted until it proves that Paymob actually signed it.

The proof is the HMAC signature.

Once the HMAC is verified, downstream code can safely assume the request passed the package trust boundary. Before verification, the request must not update payments, bookings, orders, captures, refunds, user balances, inventory, or any other application state.

## What The HMAC Proves

Paymob and your application share a private HMAC secret.

Paymob takes specific callback fields, concatenates them in a documented order, signs that string using HMAC-SHA512, and sends the resulting signature as `hmac`.

Your package must independently rebuild the same string, compute the same HMAC using `config('paymob.hmac_secret')`, and compare the result with the incoming `hmac`.

If both values match, the callback was produced by someone who knows the shared secret. In practice, that should be Paymob.

If they do not match, the callback is untrusted.

## Why This Is The Trust Boundary

Payment callbacks often trigger sensitive side effects:

- marking an order as paid
- confirming a booking
- granting access to a product
- reducing inventory
- sending invoices or receipts
- triggering capture, refund, or fulfillment logic

If the package accepts unsigned or badly signed callbacks, an attacker can fake a successful payment by sending JSON that looks like a Paymob success payload.

That is why HMAC verification should live in one place at the beginning of the callback flow. Everything after that point should only receive verified payloads.

## Handler Responsibility

The webhook controller or invokable handler should have a strict first step:

1. Read the incoming callback payload.
2. Read the incoming `hmac`.
3. Read the HMAC secret from package config.
4. Rebuild the canonical Paymob signing string.
5. Compute the expected HMAC.
6. Compare the expected HMAC with the incoming HMAC using constant-time comparison.
7. Reject immediately if verification fails.
8. Only then process the transaction.

The important part is ordering. No database update, queued job, event dispatch, model lookup with side effects, capture, booking confirmation, or business callback should happen before the signature is verified.

## Paymob Transaction HMAC Fields

For Paymob transaction callbacks, the HMAC is built from selected fields in a fixed order. For the server-to-server transaction processed callback, these values are usually read from the callback `obj` payload:

1. `amount_cents`
2. `created_at`
3. `currency`
4. `error_occured`
5. `has_parent_transaction`
6. `id`
7. `integration_id`
8. `is_3d_secure`
9. `is_auth`
10. `is_capture`
11. `is_refunded`
12. `is_standalone_payment`
13. `is_voided`
14. `order.id`
15. `owner`
16. `pending`
17. `source_data.pan`
18. `source_data.sub_type`
19. `source_data.type`
20. `success`

These values are concatenated as strings with no separator.

The field order matters. The field names matter. The exact callback shape matters. Using the right values in the wrong order produces a different HMAC. Adding separators also produces a different HMAC.

Paymob may expose a different shape for browser redirect response callbacks, where fields are flattened rather than nested. Do not mix the server-to-server callback field paths with the redirect callback field paths.

## HMAC Computation

The expected digest is computed with:

```text
HMAC-SHA512(canonical_string, hmac_secret)
```

In PHP terms, the algorithm is `sha512`.

The secret must come from configuration:

```text
config('paymob.hmac_secret')
```

The package config should read this from an environment variable such as:

```text
PAYMOB_HMAC_SECRET=
```

The secret must not be committed into the package, tests, examples, docs, or source code as a real production value.

## Constant-Time Comparison

Use `hash_equals` for comparing the computed HMAC with the incoming HMAC.

Do not use:

```text
==
===
strcmp(...)
```

Normal string comparisons can exit early when they find a difference. That creates timing differences. Timing differences can sometimes leak information about the expected signature.

`hash_equals` is designed for this kind of security-sensitive comparison.

## Missing Or Bad Signature

A missing `hmac` and an invalid `hmac` should both be rejected.

The response should be `403 Forbidden`.

The handler should log the attempt, but the log should avoid storing sensitive values such as the HMAC secret. Useful log context includes:

- reason: missing signature or invalid signature
- remote IP if available
- Paymob transaction id if available
- request path
- a safe request identifier

The handler should not touch application state for rejected requests.

That means:

- no payment row status change
- no booking confirmation
- no event dispatch that may update state
- no queued job for payment processing
- no domain callback to the host app

## Idempotency And Replays

Webhook providers commonly use at-least-once delivery. This means the same real callback may arrive more than once.

A replay can also happen when someone resends a previously valid signed payload. HMAC proves authenticity and integrity, but it does not automatically prove that this is the first time you received the message.

The package should key processing by Paymob transaction id.

For this package, there is already a `transaction_id` concept on the `Payment` model and migration. The important design rule is:

```text
same Paymob transaction id = same transaction outcome, not a new payment
```

A second verified callback for the same transaction should be a no-op or should update only in a controlled, idempotent way.

Examples:

- If transaction `123` already marked payment `A` as paid, receiving transaction `123` again should not confirm the booking twice.
- If transaction `123` already dispatched fulfillment, receiving transaction `123` again should not dispatch fulfillment again.
- If transaction `123` already exists, the handler can return success after confirming it was already handled.

In database terms, idempotency is strongest when backed by a unique constraint or atomic lookup/update strategy. Application-level checks alone can race under concurrent duplicate deliveries.

## Processing Flow To Aim For

Think of the callback flow in two gates.

Gate 1 is security:

```text
public HTTP request -> verify HMAC -> reject or continue
```

Gate 2 is business idempotency:

```text
verified Paymob transaction -> check transaction id -> process once
```

Only after both gates should business effects happen.

## Testing Mindset

The tests should prove the boundary, not only the happy path.

### Verified Payload

Use a realistic Paymob transaction payload, compute a valid HMAC using the test secret, send the callback, and assert:

- response is accepted
- payment state changes as expected
- transaction id is recorded
- downstream processing happens once

### Tampered Payload

Start from the same valid payload and signature, then change one signed field, such as:

- `success`
- `amount_cents`
- `id`
- `order.id`

Do not recompute the HMAC after tampering.

Assert:

- response is `403`
- payment state does not change
- no processing job/event/business callback runs

This proves the signature covers the fields that matter.

### Missing Signature

Send the payload without `hmac`.

Assert:

- response is `403`
- payment state does not change
- no downstream processing happens
- attempt is logged

This proves the package does not silently accept unsigned requests.

### Replay / Duplicate

Send the same valid payload with the same valid HMAC twice.

Assert:

- first request is accepted and processed
- second request is accepted or acknowledged but does not repeat side effects
- only one payment transition or processing action occurs for that transaction id

This proves the package can handle provider retries and malicious replays of a previously valid callback.

## Common Mistakes

- Verifying after updating the payment.
- Comparing HMACs with `==` or `===`.
- Signing the raw JSON body instead of Paymob's documented field concatenation.
- Using the wrong field order.
- Mixing server callback field paths with redirect callback field paths.
- Pulling the secret from a hardcoded value instead of config.
- Logging the HMAC secret.
- Treating a valid HMAC as proof that the callback is new.
- Handling duplicate transaction ids as separate payments.
- Dispatching jobs before verification.

## Practical Rule

Until the HMAC passes, the payload is attacker-controlled text.

After the HMAC passes, the payload is an authentic Paymob callback that still needs idempotent processing.

Those are separate responsibilities:

```text
HMAC verification answers: "Did Paymob sign this exact callback?"
Idempotency answers: "Have we already handled this Paymob transaction?"
```

Both are required for safe payment webhook handling.

## References

- Paymob Developer Portal: Webhook callbacks and HMAC: https://developers.paymob.com/paymob-docs/developers/webhook-callbacks-and-hmac
- Paymob Developer Portal: APIs callback security note: https://developers.paymob.com/paymob-docs/integration-paths/apis
- Hookdeck Paymob webhook guide, useful as a secondary explanation of callback shape and idempotency: https://hookdeck.com/webhooks/platforms/guide-to-paymob-webhooks-features-and-best-practices
