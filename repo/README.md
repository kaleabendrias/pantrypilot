# PantryPilot

PantryPilot is an offline-first, containerized pantry operations platform for kiosk and local Wi-Fi retail environments.

## Single Startup Command

```bash
docker compose up
```

That is the default operator startup command.

## Services and Exposed Ports

- `web` (`nginx:1.27-alpine`) -> `8080:80`
  - Purpose: serves the Layui-based web UI and proxies `/api/*` to backend API.
- `api` (ThinkPHP on `php:8.2-apache`) -> `8000:80`
  - Purpose: REST API layer (identity, recipes, booking, operations, finance, notifications, files, reporting, administration).
- `mysql` (`mysql:8.0`) -> `3307:3306`
  - Purpose: persistent relational data store and deterministic bootstrap schema/seed.

Operator access:

- UI: `http://<host-ip>:8080`
- API: `http://<host-ip>:8000`
- DB: `<host-ip>:3307`

## Test Execution (Default)

Run all tests with one command:

```bash
./run_tests.sh
```

Equivalent explicit workflow (what `run_tests.sh` runs):

```bash
docker compose exec -T api php /var/www/html/scripts/wait_for_mysql.php --host=mysql --port=3306 --db=pantrypilot --user=pantry --pass=pantrypass --timeout=120
docker compose exec -T api php /var/www/html/scripts/reset_test_data.php
docker compose exec -T api php /workspace/tests/Unit/domain_tests.php
docker compose exec -T api php /workspace/tests/Unit/service_tests.php
docker compose exec -T api php /workspace/tests/Unit/service_logic_tests.php
docker compose exec -T api env PANTRYPILOT_TEST_NOW="2026-01-15 10:30:00" php /workspace/tests/Integration/run_api_tests.php
```

What it does:

- checks required containers are running
- resets deterministic test data using `backend/scripts/reset_test_data.php`
- runs unit coverage from `tests/Unit/`
- runs integration/API behavior coverage from `tests/Integration/`
- exits with nonzero status on any failure

No local PHP/node tooling is required; backend tests run inside containers.

## Frontend-Only Preview

To preview the frontend without the full Docker stack:

```bash
# Serve the frontend directory with any static HTTP server
npx serve frontend -l 3000
# Or open frontend/index.html directly in a browser
```

The frontend operates as a standalone Layui SPA. Without the backend, API calls will fail gracefully. The offline queue captures transport failures for later replay.

## Frontend Tests

Frontend unit tests can be run standalone with Node.js (no containers required):

```bash
node frontend/tests/app.test.js
```

These tests cover XSS escaping, duplicate-submit prevention, auth token session storage, role-based tab visibility, and search query wiring.

## Operator UI Inputs (No Hardcoded Record Values)

- Booking flow now requires explicit operator inputs for pickup point (API-loaded select or manual ID), slot window/date, ZIP+4, region, coordinates, and quantity.
- Recipe create, operations module/template save, payment/reconciliation actions, notification send, and file upload now use form-provided values (with validation) instead of fixed demo payload assumptions.
- Button labels no longer imply fixed record IDs (for example `#1`/`PAY-REF`).
- Pickup point options are loaded from `GET /api/v1/bookings/pickup-points` at runtime.

Optional deterministic UI defaults for test/demo sessions are isolated behind explicit test path:

- `frontend/test-config/ui-defaults.json`
- enabled only when opening UI with `?ui_test_defaults=1` (not used in normal production UI logic)

## Verification Checklist

Use this checklist after `docker compose up` and `./run_tests.sh`.

- Login security
  - password policy enforced (min 10 chars, letter+number)
  - failed login lockout triggers after 5 attempts for 15 minutes
  - authenticated requests require bearer token
- Search relevance
  - synonym search resolves `garbanzo -> chickpea`
  - fuzzy typo search still returns relevant recipes
  - tag filtering works with combined constraints (e.g. `tags=vegan&prep_under=30&max_budget=13`)
  - ranking modes (`popular`, `time-saving`, `budget`, `low-calorie`) produce ordered results
- Booking rules
  - bookings beyond 7 days are rejected
  - bookings inside 2-hour cutoff are rejected
  - slot remaining capacity is returned immediately
  - over-capacity contention rejects excess requests
  - slot reservation + booking persistence are strongly consistent (transactional rollback on insert failure)
  - ZIP+4 and admin-region validation enforced
  - ZIP+4 to region consistency validated against local offline ZIP+4 dataset
  - service-radius Haversine distance check enforced
- Operations no-show handling
  - today pickup console returns operational queue
  - check-in marks arrivals
  - no-show sweep marks no-shows at 15+ minutes after slot start
  - repeated no-show user is auto-blacklisted
- Payment and reconciliation correctness
  - local WeChat-compatible pending order creation works
  - pending orders auto-cancel after 10 minutes
  - callback signature (HMAC-SHA256) verification enforced
  - callback idempotency keyed by transaction reference works
  - daily reconciliation flags missed-order mismatches
  - repair/close/refund/adjustment require re-auth token
  - tamper-evident audit hash chain is written
- Notification throttling
  - marketing opt-out enforced
  - max 2 marketing messages/day enforced
  - quiet hours 21:00-08:00 enforced
  - read/click analytics populated
  - non-admin send is blocked for out-of-scope recipients (Forbidden)
- File governance
  - upload type policy enforced (`image/png`, `image/jpeg`, `application/pdf`, `text/csv`)
  - max upload size enforced at 10 MB
  - magic-byte verification enforced
  - SHA-256 fingerprint recorded
  - image watermark rendering uses real binary image processing (PNG/JPEG) and fails explicitly if GD prerequisites are unavailable
  - signed download URLs expire after 5 minutes
  - hotlink token validation enforced
  - lifecycle cleanup endpoint removes old files (180-day policy)
  - non-admin cleanup only affects in-scope/owned expired attachments; admin cleanup is global
- Encryption and masking
  - sensitive fields encrypted at rest
  - masked values returned to UI/export contexts
  - encrypted raw values not exposed in API list payloads
- Anomaly reporting
  - oversell, refund-rate spike, and stockout-rate checks produce alerts
  - stockout metrics are scope-aware (scoped users see scoped stock snapshots only)
  - CSV export endpoint returns deterministic output payloads

## Security Hardening Changelog (Current Cycle)

- Payment callback idempotency and race safety
  - callback deduplication is now keyed strictly by `transaction_ref` (not payload hash)
  - DB uniqueness on `gateway_callbacks.transaction_ref` enforces single-write idempotency
  - callback processing now runs atomically so duplicate/racing callbacks cannot create duplicate captured payments
  - tests cover altered payload reuse on same `transaction_ref` and concurrent duplicate callback attempts
- Operations scope isolation
  - campaigns, homepage modules, message templates, and operations dashboard now enforce store/warehouse/department scope filtering
  - operations write endpoints force scope fields from authenticated middleware context and ignore client scope injection
  - tests prove scoped users cannot read cross-scope operations records and that out-of-scope dashboard data does not leak
- Booking recipe-detail IDOR protection
  - booking recipe detail now applies explicit existence and scope checks
  - behavior is pinned by tests for `404` (missing recipe) and `403` (existing but cross-scope recipe)
- File lifecycle governance
  - cleanup now removes both DB records and physical files
  - cleanup is idempotent and resilient: missing files are logged and DB cleanup still proceeds
  - tests validate combined DB + filesystem cleanup behavior, including missing-file scenarios
- Test-hardening for authz/isolation
  - permissive unit fakes that always approved scope/ownership were replaced with stateful fakes/negative assertions
  - integration coverage expanded for authz and IDOR boundaries without changing API response contracts/status mapping

## Search Verification Examples

- Synonym search (`garbanzo -> chickpea`):
  - `curl -s "http://127.0.0.1:8080/api/v1/recipes/search?ingredient=garbanzo&prep_under=30" -H "Authorization: Bearer <token>"`
- Typo-fuzzy search (`chikpea -> chickpea`):
  - `curl -s "http://127.0.0.1:8080/api/v1/recipes/search?ingredient=chikpea&prep_under=30" -H "Authorization: Bearer <token>"`
- Combined tag+budget+prep filter:
  - `curl -s "http://127.0.0.1:8080/api/v1/recipes/search?tags=vegan&prep_under=30&max_budget=13&rank_mode=budget" -H "Authorization: Bearer <token>"`

## Required Environment Variables

The application **requires** the following environment variables to start. No hardcoded secrets are shipped in the repository.

| Variable | Description | Example |
|---|---|---|
| `PANTRYPILOT_GATEWAY_HMAC_SECRET` | HMAC-SHA256 secret for payment gateway callback signature verification | A random 64+ character string |
| `PANTRYPILOT_CRYPTO_KEY` | AES-256-CBC encryption key for sensitive data at rest (must be exactly 32 bytes) | A random 32-byte string |
| `PANTRYPILOT_CRYPTO_IV` | Legacy decryption IV (16+ chars). New encryption uses random IVs prepended to ciphertext. | A random 16+ character string |

For local development, `docker-compose.yml` provides placeholder values. **You must replace them before any non-local deployment.** The backend will refuse to start if these variables are unset.

The seeded admin account in the bootstrap SQL uses a temporary password that **must be rotated on first login**.

## Security Regression Commands (Auditor)

Run one deterministic suite:

```bash
./run_tests.sh
```

Expected PASS lines (data isolation + IDOR-style protections):

- `Scoped cleanup cannot delete cross-scope attachments but can delete in-scope owned files`
- `Notification send enforces recipient scope for non-admin and allows admin global send`
- `Scoped file listing excludes foreign attachments`
- `Scoped user cannot generate signed URL for foreign file`
- `IDOR blocked: scoped user cannot mark-read admin message`
- `IDOR blocked: scoped user cannot read foreign dispatch note`

## Hardcoded Value Refactor Map

- Pickup point IDs -> runtime `pickup-points` API select (`frontend/assets/js/app.js`, `frontend/index.html`)
- ZIP/region/coordinates -> operator booking form inputs with validation (`frontend/index.html`, `frontend/assets/js/app.js`)
- Recipe create payload fields (timing/servings/difficulty/calories/cost/status) -> operator form inputs (`frontend/index.html`, `frontend/assets/js/app.js`)
- Operations module banner/template category fields -> operator form inputs (`frontend/index.html`, `frontend/assets/js/app.js`)
- Recipient `user_id` for notifications -> operator input + scoped auth enforcement (`frontend/index.html`, `frontend/assets/js/app.js`, backend notification service)
- Payment refs, gateway amount, adjustment fields, and reconciliation issue/note -> operator input or row selection (`frontend/index.html`, `frontend/assets/js/app.js`)
- File upload sample payload assumptions -> MIME-aware generated payload or operator-provided content (`frontend/assets/js/app.js`)
- Fixed-ID button text -> neutral selected/input-based labels (`frontend/index.html`)

These changes preserve offline-first behavior while tightening deterministic, auditable controls required for local deployment security/compliance.

## Audit Closure Note (Prior Partial -> Full)

- Payment idempotency model drift (Partial) -> Full
  - unified on strict `transaction_ref` idempotency in both bootstrap and migration SQL, plus atomic callback processing in service/repository
  - runtime proof: integration tests assert one callback row + one captured payment under replay and concurrency
- Recipe-search safety and coverage (Partial) -> Full
  - search route ordering fixed, filters fully parameterized in query builder (including tags), and injection-style negative inputs covered
  - runtime proof: synonym/fuzzy/combined-filter tests plus injection-safe test all green
- Error-mapping fragility (Partial) -> Full
  - string-fragile controller mappings replaced by typed API exceptions and centralized response mapping, preserving status semantics
  - runtime proof: regression test verifies stable 401/403/404/409/422 mapping
- Offline address validation depth (Partial) -> Full
  - added local `zip4_reference` dataset/table and ZIP+4-to-region consistency enforcement before booking create
  - runtime proof: mismatch path returns expected 422 while cutoff/window/radius constraints still pass existing tests
- Time-dependent notification ambiguity (Partial) -> Full
  - integration run enforces fixed clock (`PANTRYPILOT_TEST_NOW`) and includes deterministic-clock assertion
  - runtime proof: quiet-hours and daily-cap tests pass consistently in full suite

## Risk-to-Test Traceability Matrix

- Booking integrity
  - happy path: successful booking creation and list/pagination
  - error path: cutoff/window/ZIP+4-region mismatch/slot over-capacity
  - security boundary: scoped users cannot access foreign recipe-detail or dispatch-note
- Payment correctness
  - happy path: valid callback produces captured payment and reconciliation flow
  - error path: invalid callback signature and reconciliation mismatch handling
  - security boundary: strict transaction-ref idempotency blocks replay/race duplication
- Notification policy
  - happy path: valid sends and analytics paths
  - error path: opt-out and quiet-hours rejection
  - security boundary: IDOR block on foreign message read
- File governance
  - happy path: upload, signed URL, valid download
  - error path: invalid token/expired URL, lifecycle cleanup on missing physical file
  - security boundary: scoped users blocked from foreign file signed-url/listing
- Operations/reporting scope isolation
  - happy path: in-scope campaigns/modules/templates/dashboard and exports
  - error path: scoped visibility excludes out-of-scope records
  - security boundary: cross-scope records do not leak via list/export/dashboard metrics

## Unit Fake vs Integration Real Coverage

- Unit fake-based checks (`tests/Unit/*.php`)
  - isolate domain policies and service-level invariants with deterministic fakes/mocks
  - verify local logic such as password policy, token hashing, masking, callback idempotent branching, and scope guard branches
- Integration real-persistence checks (`tests/Integration/run_api_tests.php`)
  - validate end-to-end API + middleware + ACL + DB behavior against real MySQL state
  - cover account lifecycle admin flows, scope-filtered analytics/exports/listing, booking contention persistence, callback rollback/idempotency, IDOR negatives, and reconciliation edges

Use integration coverage as source of truth for production behavior and unit coverage for fast invariant regression detection.

## Architecture and Module Boundaries

- UI/presentation: `frontend/`
- API controllers: `backend/app/controller/api/v1/`
- application services: `backend/app/service/`
- domain logic: `backend/app/domain/`
- data access repositories: `backend/app/repository/`
- infrastructure adapters: `backend/app/infrastructure/`
- tests: `tests/Unit/`, `tests/Integration/`

## Configuration, Offline Guarantees, and Auditability

- No `.env` files are used for runtime configuration.
- Runtime configuration is versioned in repository files (`docker-compose.yml`, `backend/config/*.php`, SQL bootstrap/migrations).
- No external uncontrollable APIs are called by application runtime code; all payment/message/file flows are locally emulated and stored.
- Database schema and seeds are containerized and deterministic:
  - startup bootstrap: `docker/mysql/init/001_schema.sql`
  - migration-ready SQL: `backend/database/migrations/*.sql`
- MySQL persistence behavior:
  - data is stored in named volume `mysql_data`
  - first bootstrap executes init SQL scripts
  - repeated runs keep data unless volume is removed
  - deterministic test runs always reseed via `backend/scripts/reset_test_data.php`

## Release Hygiene

- temporary runtime artifact `backend/runtime/route_list.php` removed
- runtime ignore rules hardened in `.gitignore`
- naming remains consistent across module folders and API route groups
- project is reproducible from fresh clone with:
  1. `docker compose up`
  2. `./run_tests.sh`

## Troubleshooting (DNS/Readiness)

- Symptom: seed reset fails with `php_network_getaddresses` or cannot resolve `mysql`.
  - Cause: API process started before Docker DNS/network readiness.
  - Resolution: run `./run_tests.sh` (it waits for DNS + DB reachability before tests).
- Symptom: DB connection refused on startup.
  - Cause: MySQL container still warming up or recovering persisted volume.
  - Resolution: check `docker compose logs mysql`, then rerun `./run_tests.sh`.
- Symptom: unexpected old data in manual validation.
  - Cause: persistent `mysql_data` volume retained previous state.
  - Resolution: deterministic checks use `./run_tests.sh`; for a clean runtime reset use `docker compose down -v` then `docker compose up`.

## Troubleshooting (Malformed JSON)

- Symptom: browser shows JSON parsing error on first load (commonly when `app.js` calls `/api/v1/reporting/dashboard`).
  - Diagnosis: capture raw response with headers and confirm there are no bytes after the JSON document:
    - `curl -i "http://127.0.0.1:8080/api/v1/reporting/dashboard"`
    - expected: `Content-Type: application/json` and body is exactly one JSON object.
  - Root cause pattern: runtime directory permissions can prevent ThinkPHP log/runtime writes, triggering a framework exception page appended after JSON.
  - Resolution in this repo: API container startup now creates runtime dirs and sets write permissions before Apache starts.
- Verification steps:
  1. `docker compose up`
  2. `./run_tests.sh`
  3. confirm API tests include strict JSON contract checks (no trailing bytes after JSON) and pass.

## Default Credentials (Offline Seed)

- Admin username: `admin`
- Admin password: `admin12345`
- DB user/password: `pantry` / `pantrypass`
