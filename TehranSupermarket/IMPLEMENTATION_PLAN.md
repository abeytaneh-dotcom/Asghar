# Tehran Market — Enterprise Implementation Plan (FA)

Status: **Staging only / NOT production-ready**
Source: `abeytaneh-dotcom/Asghar`, base `websaza-hosting` at `0e6991143794dcce393c735bb43407e1e83e0900`
Development branch: `feature/enterprise-v3`
Store: `TehranSupermarket/`
**Never merge or deploy automatically.** Production changes require independently verified backup/restore, human review, and an explicit production approval.

## Immutable safeguards

- Do not modify or delete live `websaza.ir/tehran`, `tehranmarket-baharestan.ir`, production database, orders or payments.
- Preserve product identifiers, `barcode`, `pyramid_code2`, names, images, brands, categories, active state and order history.
- Never commit or print secrets, tokens, database connection details, payment identifiers or customer records.
- Never connect to or modify Pyramid's database. Only sanctioned API/export integration after vendor documentation.
- Pyramid is eventually the authoritative source for `price` and `stock`; before documented integration, use **compare/dry-run only**.
- Block browser-facing installers, seed routines and schema changes. Schema migrations must be explicit CLI/admin-controlled, versioned, backup-backed, independently reversible and tested on a clone.
- Any module must fail closed on missing prerequisites; AI/Pyramid/map/SMS unavailability must not corrupt checkout.
- No claim of load capacity or production-readiness without benchmark, recovery drill and acceptance tests.

## Release gates

0. Read-only inventory of source, GitHub Actions triggers, staging host capabilities, schema and cPanel configuration.
1. Snapshot both code and database. Demonstrate restore on **separate staging database**. Never publish backup or credentials to Git.
2. Apply versioned migrations to staging **only after** backup + preflight. Assert table/column compatibility; capture schema diff and rollback plan.
3. Complete automated tests, authorization review, concurrency tests, security scans, manual UX checks.
4. Compare 9,849 products and historical orders before/after; verify original storefront visuals, AI, barcode scanner.
5. Test login, orders, cancellations, refunds, payment callbacks, DNS and TLS.
6. Separate human authorization required for production cutover.

## Phase checklist

### Phase 1 — Safety core and audit (IN PROGRESS)
- [x] Enumerate repository and inspect existing PHP store source and selected workflows.
- [x] Identify browser-triggered `ts_seed_pyramid_1400()`, product image mutations and runtime `ensure_product_schema()`.
- [x] Isolate work on `feature/enterprise-v3`.
- [x] Remove browser-triggered demo/seed and DDL side effects on staging branch; protect private endpoints (static regression passed).
- [x] Add fail-closed installer/seed, protected paths and static regression checks to the branch; production HTTP validation remains pending.
- [ ] Review authentication/CSRF/authorization/rate-limiting, uploads, API keys, error handling and backup policy.
- [x] PHP syntax check and static regression suite in isolated GitHub CI (no JS modified in this phase).
- [ ] Test actual GET/POST flows against a restored staging clone and verify Apache protection, UI and checkout.
- [ ] Obtain staging backup and execute restore drill; verify cPanel limits (CPU/RAM/DB connections/Cron).
- [ ] Final human review. Phase stays open until staging tests pass.

### Phase 2 — Data schema and ledgers (NOT STARTED)
- Versioned migrations for stock reservations, `stock_movements`, order revisions/returns, audit logs, wallets, shifts, payroll, print and job queues.
- Support `unit_type`, weighted goods, multi-barcodes and units without changing existing identity keys.
- Record exact schema preconditions and rollback using tested staging backup.
- Test data-preservation and idempotent migration execution.

### Phase 3 — Orders and payments (NOT STARTED)
- Lock products in ascending `product_id` order; bounded retries on lock contention.
- Distinguish physical, reserved and safety stock; reservation TTL (configurable), atomic release.
- Server-side cart pricing, payment verification, immutable paid amounts, duplicate callback safety.
- Pickup/delivery/address validation, editable pre-lock orders, partial returns and reconciliations.
- Test last-item races, cancellations, returns, duplicate events, double charges and IDOR.

### Phase 4 — Staff and admin PWA (NOT STARTED)
- Roles and permissions: owner, cashier, picker, warehouse, courier, accountant; deny by default.
- One-time expiring invitations with opt-in PWA installation, revoke sessions immediately.
- Server-owned scoped resources; immutable audit events; shift entry/exit; mobile/desktop UX.
- Test role/record isolation and session expiry.

### Phase 5 — Compensation (NOT STARTED)
- Effective-dated fixed/hourly/sales/order/delivery/mixed remuneration, adjustments, advance payment.
- Reversible commission postings for returns; period snapshots and owner-approved payroll.
- Independent financial reconciliation and employee-only payslip access.

### Phase 6 — Official Pyramid bridge + printing (NOT STARTED)
- First obtain exact vendor name/version, official SDK/API/export contract, pricing units, inventory rules.
- `MockTestAdapter`, `FileCsvAdapter`, `OfficialApiAdapter`: no undocumented database writes.
- Dry-run barcode matching, anomaly guard and one-way price/stock updates ONLY after explicit approval.
- HMAC with timestamp/nonce, per-device scopes, idempotency, backoff/last-known-good timestamp.
- Orders outbox and manually reviewed POS entry until official write support verified.
- Windows bridge with offline queue, customer display, staff notification and thermal print queue.

### Phase 7 — Search, scanner, AI and repeat customers (NOT STARTED)
- Persian/Arabic digit and text normalization without altering raw barcode identity.
- Camera and HID scanner tests with UPC/EAN/Code128 and leading-zero exact-match integrity.
- AI per-user isolated conversations; bounded tool permissions, retry/circuit-breaker, no client-authoritative price.
- OTP and consent-based repeat-customer device recognition; **never** identify customers by IP alone.
- Test cross-session leakage, prompt injection, shopping-cart ownership, AI outage fallback.

### Phase 8 — Verification and deployment (NOT STARTED)
- Simulate at least 100 concurrent checkout attempts; prove stock nonnegative and no double decrement.
- Security tests for SQLi, XSS, CSRF, IDOR, replay, rates, malicious uploads, invoice lock.
- Confirm staging backup restore and performance thresholds on measured hosting resources.
- Provide `DEPLOYMENT_FA.md` and a human-reviewed production cutover checklist; no automatic production changes.

## Phase reporting contract
Each phase must document:
1. files created/changed;
2. migration and rollback procedure;
3. commands/tests actually executed with pass/fail evidence;
4. non-secret admin prerequisites;
5. remaining unknowns and risks.

## Current known blockers

- No verified working **staging clone** and database restore exercise.
- Current MySQL/PHP/cPanel CPU, RAM, concurrency, disk and Cron limits unknown.
- Pyramid's vendor documentation, version, price currency, official API and order/stock event semantics unknown.
- Live payment provider configuration, checkout acceptance, DNS and TLS not independently verified.
- Legacy browser-triggered schema/seed operations have been removed on the feature branch; staging runtime compatibility and rollback remain untested.
- Public assets, AI and barcode UI differ across old/new deployments; UI parity remains a separate acceptance gate.

**Status gate:** code-only work in isolated Git branch is allowed; no production deploy, DB writes or cutover without explicit approval.

## Phase 1 evidence

- [Security audit and changed-file inventory](PHASE1_SECURITY_AUDIT_FA.md)
- [Static regression tests](tests/test_phase1_safety.py)
- [Staging-only read-only database preflight](bin/preflight.php)
- [CI workflow](../.github/workflows/tehran-phase1-security-ci.yml) (stored at repository root under `.github/workflows/`)
- Live DB operations, migrations and production cutover: **none**.
