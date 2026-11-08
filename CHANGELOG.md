# Changelog

Changes are grouped by user impact. An existing Git tag is not evidence of a
published GitHub release or verified production readiness.

## Unreleased

These entries describe package-skeleton at
`1762b8cc23a58a7a13261c6e7385a54322052333`, after the existing v0.1.0 tag.
Independent security/README branches are pending review and are not included here.

### Added

- Typed PaymobWebhookReceived notification for verified transaction callbacks.
- PDF payment receipts rendered with Dompdf and stored privately on a configured
  Laravel filesystem disk.
- Structured request, response, retry and webhook logging with sensitive-value
  redaction and correlation IDs.
- Package test matrix and dedicated static-analysis/style checks.

### Changed

- Explicit PHP upper bound below 8.5 and Laravel component constraints covering
  10.50.3+, 11, 12 and 13; actual support depends on successful matrix checks.
- Authentication and payment requests use configurable timeouts and bounded retry
  delays; authentication can refresh once after authorization failures.

### Fixed

- Order creation retries are disabled unless a merchant reference is provided.
- HTTP failures are surfaced through package domain/authentication exceptions.

### Known Limitations

- This baseline has an empty README and a facade discovery namespace mismatch.
  The independent README branch addresses both; it must be integrated before release.
- Webhook replay concurrency, terminal-state validation, automatic capture and raw
  payload retention require the independent webhook-security branch. Do not ship
  this baseline as a hardened payment integration.
- Refund/void lifecycle handling, transactional outbox delivery, remote capture
  reconciliation and production database concurrency acceptance remain application
  responsibilities. Neither SQLite tests nor a successful HMAC prove fulfillment.
- Some RegisterOrderData fields are not sent by the Accept order implementation;
  this is not a Unified Checkout/intention API client.
- Local style checks report inherited formatting/line-ending failures. No remote
  CI success for a new combined release commit has been established.

## 0.1.0 — existing annotated tag (2026-09-24)

Tag target: `09a28c730d8bc75e5e4a13679dfe8e31b72e736b`.
This historical entry describes the immutable tag, not the current working branch.
No GitHub release was returned by the public release-by-tag API during this review.

### Added

- Laravel service provider, environment-backed configuration and publishable assets.
- Accept authentication, order registration, payment-key creation, capture API and
  iframe redirect URL helpers with request/response DTOs.
- Merchant-scoped cached authentication with refresh locking and fallback behavior.
- HMAC-SHA512 transaction callback verification and database transaction-ID receipts.
- Queued capture processing with overlap protection and a local payment ledger.

### Known Limitations

- Pre-1.0 API; the tagged README is empty and facade alias metadata is incorrect.
- The tagged Composer requirements do not establish every claimed PHP/Laravel
  combination as tested. This tag predates the current CI matrix and quality tooling.
- The tag does not contain the later PDF receipts, structured logging, typed webhook
  event or the new independent security fixes. Do not advertise those as v0.1.0 features.
- Replay handling and payment processing need security review before production use.

[Existing v0.1.0 tag](https://github.com/yassergharieb/laravel-paymob/tree/v0.1.0)
