# Release preparation and verification

Reviewed 2026-10-26. Publication intentionally not performed: the user requested
three local branches from package-skeleton and explicitly prohibited pushes and PRs.

## Exact references

- Shared task base: `1762b8cc23a58a7a13261c6e7385a54322052333`.
- Existing annotated v0.1.0 tag object: `8f6cd20446ba46aeefebb5d236d6e02effdaedb2`.
- Existing v0.1.0 peeled commit: `09a28c730d8bc75e5e4a13679dfe8e31b72e736b`.
- Local tag object and origin tag/peeled commit matched using read-only ls-remote.
- The tag already exists on origin. It was neither moved nor recreated.
- Public GitHub release-by-tag API returned 404; no new GitHub release was created.
- No final release commit has been selected. The current base does not include the
  independent security and README changes and cannot satisfy the release gates.

The existing tag points to an older ancestor of package-skeleton. New changes must
use a new version after review (for example 0.1.1); they cannot be published by
repointing v0.1.0. CHANGELOG.md separates historical tagged features from unreleased
baseline features to avoid claiming fixes in an artifact that does not contain them.

## Local evidence

- PHP 8.4.25; installed package test dependencies include PHPUnit 12.5.36.
- Baseline/documentation branch suite: 43 tests, 139 assertions passed.
- Independent security branch suite: 50 tests, 174 assertions passed using only
  local SQLite fixtures; no live payment calls.
- Baseline/documentation static analysis: passes at configured level 5.
- Full style checks: fail on inherited formatting and checkout line endings.
- Fresh-app README example: Laravel 13.38.0 / PHP 8.4.25, local path package,
  fake-only HTTP requests; configuration publication, migrations, cached config,
  route registration, contract/facade resolution and redirect passed.
- Multi-version CI status on a future combined commit: not verified. Local results
  do not establish successful remote CI, approval or readiness of the old tag.

## Remaining gates before a new release

1. Review and integrate webhook-security and package-readme. Keep the
   README's callback behavior synchronized with the final integrated controller.
2. Address inherited style failures and verify installation with clean dependencies.
3. Review the capture job's uncertain remote-result retry behavior and configure
   idempotent fulfillment/outbox handling and provider reconciliation.
4. Run full tests, static analysis, style and supported-version CI on one clean,
   approved commit. Record its full SHA and exact test matrix results here.
5. Update the changelog for a new, unused version, including security changes only
   once those changes are actually in the release commit.
6. When publication is authorized, create and inspect an annotated tag on that exact
   commit; push it and publish matching GitHub release notes. Verify both resolve
   to the recorded SHA. Never move an existing published tag.

The existing tag can be inspected at
https://github.com/yassergharieb/laravel-paymob/tree/v0.1.0.
There is no verified new release URL to report.
