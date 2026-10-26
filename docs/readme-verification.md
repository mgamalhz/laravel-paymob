# README verification

Date: 2026-10-26. Windows, PHP 8.4.25, Composer 2.9.4.
Fresh application: Laravel skeleton 13.11.0 / framework 13.38.0, created outside
this repository at D:/paymob-readme-verification. Package installed through a local
Composer path repository; no package publication or live payment environment used.

Verified:

- Local Composer repository configuration and require command with `-W`; Guzzle
  downgraded from 8.x to the package-compatible 7.x. Dependency installation and
  package discovery completed. The facade metadata had an incorrect `Facades`
  namespace; fixed to the actual `Facade` namespace and confirmed class discovery.
- Published config with the exact provider and `paymob-config` tag.
- Migrated all three package tables into a fresh local SQLite database.
- Set only fake environment values, built the configuration cache and inspected
  the configured webhook route.
- Executed the README PHP example itself (extracted from its code block) against
  Laravel Http::fake with preventStrayRequests enabled. Authentication, order and
  payment-key requests totalled three; the redirect matched the fake payment token
  and configured iframe. Contract resolution and the global facade were checked.
- Inspected failed jobs and ran an empty queue worker once with timeout 120.
- Package `composer test`: 43 tests / 139 assertions passed on this independent
  documentation branch. The security branch has separate additional coverage.

Local setup qualification: Composer optimized autoload generation stalled on this
Windows machine. The two local Composer processes were stopped, optimization was
disabled in the throwaway application's config, autoload regenerated and composer
install rerun successfully. No dependency checks were disabled. A first route filter
found no match before the documented webhook environment value was applied; it must
be set before caching configuration.

Limits: no claim of real credential acceptance, actual browser iframe payment,
private S3 upload, live capture, queue failure recovery, registry availability,
manual registration on every Laravel major or multi-version CI success. Registry
installation is explicitly conditional on publication. Full style checks currently
report inherited formatting/Windows line-ending violations; this is a release gate,
not a passing check. The separate security branch must be reviewed and merged before
production release; the README describes this branch's inherited behavior honestly.
