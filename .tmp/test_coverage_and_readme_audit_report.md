# Test Coverage Audit

## Scope and Method
- Audit mode: static inspection only (no execution).
- Endpoint source of truth: `repo/backend/route/app.php`.
- API test evidence: `repo/tests/Integration/run_api_tests.php`, `repo/tests/Integration/run_e2e_tests.php`.
- Unit test evidence: `repo/tests/Unit/*.php`, `repo/frontend/tests/app.test.js`.

## Project Type Detection
- README top declaration: fullstack (`repo/README.md:3`).
- Inferred type (confirmed): fullstack.

## Backend Endpoint Inventory
Resolved prefix: `/api/v1` from `Route::group('api/v1', ...)` (`repo/backend/route/app.php:20`).

1. `GET /`
2. `POST /api/v1/identity/login`
3. `POST /api/v1/identity/register`
4. `POST /api/v1/identity/rotate-password`
5. `GET /api/v1/recipes/search`
6. `GET /api/v1/recipes`
7. `POST /api/v1/recipes`
8. `GET /api/v1/tags`
9. `POST /api/v1/tags`
10. `GET /api/v1/bookings/recipe/:recipeId`
11. `GET /api/v1/bookings/slot-capacity`
12. `GET /api/v1/bookings/today-pickups`
13. `POST /api/v1/bookings/check-in`
14. `POST /api/v1/bookings/no-show-sweep`
15. `GET /api/v1/bookings/:bookingId/dispatch-note`
16. `GET /api/v1/bookings/pickup-points`
17. `GET /api/v1/pickup-points`
18. `GET /api/v1/bookings`
19. `POST /api/v1/bookings`
20. `GET /api/v1/operations/campaigns`
21. `POST /api/v1/operations/campaigns`
22. `GET /api/v1/operations/homepage-modules`
23. `POST /api/v1/operations/homepage-modules`
24. `GET /api/v1/operations/message-templates`
25. `POST /api/v1/operations/message-templates`
26. `GET /api/v1/operations/dashboard`
27. `POST /api/v1/payments/gateway/orders`
28. `POST /api/v1/payments/gateway/callback`
29. `POST /api/v1/payments/gateway/auto-cancel`
30. `GET /api/v1/payments/reconcile/batches`
31. `GET /api/v1/payments/reconcile/issues`
32. `POST /api/v1/payments/reconcile/daily`
33. `POST /api/v1/payments/reconcile/repair`
34. `POST /api/v1/payments/reconcile/close`
35. `POST /api/v1/payments/reconcile`
36. `POST /api/v1/payments/reauth`
37. `POST /api/v1/payments/refund`
38. `POST /api/v1/payments/adjust`
39. `GET /api/v1/payments`
40. `POST /api/v1/payments`
41. `GET /api/v1/notifications/events`
42. `POST /api/v1/notifications/events`
43. `POST /api/v1/notifications/preferences/opt-out`
44. `POST /api/v1/notifications/messages/:id/read`
45. `POST /api/v1/notifications/messages/:id/click`
46. `POST /api/v1/notifications/messages`
47. `GET /api/v1/notifications/inbox`
48. `GET /api/v1/notifications/analytics`
49. `POST /api/v1/files/upload-base64`
50. `GET /api/v1/files/:id/signed-url`
51. `GET /api/v1/files/download/:id`
52. `POST /api/v1/files/cleanup`
53. `GET /api/v1/files`
54. `GET /api/v1/reporting/dashboard`
55. `GET /api/v1/reporting/anomalies`
56. `POST /api/v1/reporting/anomalies/generate`
57. `GET /api/v1/reporting/exports/bookings-csv`
58. `GET /api/v1/admin/users`
59. `GET /api/v1/admin/audit-logs`
60. `POST /api/v1/admin/reauth`
61. `GET /api/v1/admin/roles`
62. `POST /api/v1/admin/roles`
63. `GET /api/v1/admin/permissions`
64. `GET /api/v1/admin/resources`
65. `POST /api/v1/admin/grants`
66. `POST /api/v1/admin/user-roles`
67. `POST /api/v1/admin/users/:userId/enable`
68. `POST /api/v1/admin/users/:userId/disable`
69. `POST /api/v1/admin/users/:userId/reset-password`
70. `POST /api/v1/admin/users/:userId/scopes`

## API Test Mapping Table
Legend: covered is strict yes/no by static evidence of deterministic request invocation.

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| GET / | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Health check endpoint returns service status") at `run_api_tests.php:2141`; erunCase("Nginx serves frontend SPA HTML at root path") at `run_e2e_tests.php:98` |
| POST /api/v1/identity/login | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Authentication login success") at `run_api_tests.php:276`; erunCase("Complete login-to-dashboard...") at `run_e2e_tests.php:132` |
| POST /api/v1/identity/register | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Public registration cannot assign privileged roles") at `run_api_tests.php:1597`; erunCase("User registration, login...") at `run_e2e_tests.php:159` |
| POST /api/v1/identity/rotate-password | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Password rotation endpoint accepts valid bootstrap rotation...") at `run_api_tests.php:2151` |
| GET /api/v1/recipes/search | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Recipe fuzzy and synonym search...") at `run_api_tests.php:386` |
| GET /api/v1/recipes | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Recipe list and create endpoints...") at `run_api_tests.php:2179`; erunCase("Complete login-to-dashboard...") at `run_e2e_tests.php:132` |
| POST /api/v1/recipes | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Recipe list and create endpoints...") at `run_api_tests.php:2179` |
| GET /api/v1/tags | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Tag list and create endpoints...") at `run_api_tests.php:2209` |
| POST /api/v1/tags | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Tag list and create endpoints...") at `run_api_tests.php:2209` |
| GET /api/v1/bookings/recipe/:recipeId | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Booking recipe detail enforces scope...") at `run_api_tests.php:440` |
| GET /api/v1/bookings/slot-capacity | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Slot capacity check rejects foreign pickup point...") at `run_api_tests.php:1896` |
| GET /api/v1/bookings/today-pickups | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Today-pickups respects data scopes...") at `run_api_tests.php:660` |
| POST /api/v1/bookings/check-in | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Check-in rejects bookings with invalid state") at `run_api_tests.php:1650` |
| POST /api/v1/bookings/no-show-sweep | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("No-show sweep triggers blacklist automation") at `run_api_tests.php:776` |
| GET /api/v1/bookings/:bookingId/dispatch-note | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("IDOR blocked: scoped user cannot read foreign dispatch note") at `run_api_tests.php:1481` |
| GET /api/v1/bookings/pickup-points | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Bookings pickup-points route is covered by ACL") at `run_api_tests.php:1641`; erunCase("Operations staff workflow...") at `run_e2e_tests.php:182` |
| GET /api/v1/pickup-points | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Pickup-points alias endpoint...") at `run_api_tests.php:2229` |
| GET /api/v1/bookings | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Bookings list pagination boundary...") at `run_api_tests.php:615`; erunCase("Complete login-to-dashboard...") at `run_e2e_tests.php:132` |
| POST /api/v1/bookings | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Slot capacity contention allows only one booking") at `run_api_tests.php:490` |
| GET /api/v1/operations/campaigns | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| POST /api/v1/operations/campaigns | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| GET /api/v1/operations/homepage-modules | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| POST /api/v1/operations/homepage-modules | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| GET /api/v1/operations/message-templates | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| POST /api/v1/operations/message-templates | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| GET /api/v1/operations/dashboard | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Operations endpoints enforce scope filtering...") at `run_api_tests.php:675` |
| POST /api/v1/payments/gateway/orders | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Payment callback signature verification...") at `run_api_tests.php:793` |
| POST /api/v1/payments/gateway/callback | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Payment callback signature verification...") at `run_api_tests.php:793` |
| POST /api/v1/payments/gateway/auto-cancel | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Gateway callback rejects cancelled order") at `run_api_tests.php:1790` |
| GET /api/v1/payments/reconcile/batches | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Scoped finance user cannot see cross-scope reconciliation batches") at `run_api_tests.php:1838` |
| GET /api/v1/payments/reconcile/issues | no | unit-only / indirect | repo/tests/Integration/run_api_tests.php | Endpoint call is conditional inside foreach over runtime batch list (`run_api_tests.php:1844`), no deterministic unconditional invocation by static proof |
| POST /api/v1/payments/reconcile/daily | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Reconciliation mismatch detection...") at `run_api_tests.php:903` |
| POST /api/v1/payments/reconcile/repair | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Re-auth token one-time use...") at `run_api_tests.php:1379` |
| POST /api/v1/payments/reconcile/close | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Manual reconcile entry and close batch lifecycle...") at `run_api_tests.php:2243` |
| POST /api/v1/payments/reconcile | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Manual reconcile entry and close batch lifecycle...") at `run_api_tests.php:2243` |
| POST /api/v1/payments/reauth | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Finance re-auth endpoint is accessible...") at `run_api_tests.php:1615` |
| POST /api/v1/payments/refund | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Re-auth token one-time use...") at `run_api_tests.php:1379` |
| POST /api/v1/payments/adjust | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Re-auth token one-time use...") at `run_api_tests.php:1379` |
| GET /api/v1/payments | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Sensitive-data masking in payment list") at `run_api_tests.php:1212`; erunCase("Operations staff workflow...") at `run_e2e_tests.php:182` |
| POST /api/v1/payments | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Sensitive-data masking in payment list") at `run_api_tests.php:1212` |
| GET /api/v1/notifications/events | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Notification events list endpoint...") at `run_api_tests.php:2284`; erunCase("Operations staff workflow...") at `run_e2e_tests.php:182` |
| POST /api/v1/notifications/events | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Notification event creation includes scope...") at `run_api_tests.php:1623` |
| POST /api/v1/notifications/preferences/opt-out | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Marketing opt-out enforcement blocks sends") at `run_api_tests.php:1309` |
| POST /api/v1/notifications/messages/:id/read | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Customer can opt-out, view inbox, mark-read...") at `run_api_tests.php:2054` |
| POST /api/v1/notifications/messages/:id/click | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Customer can opt-out, view inbox, mark-read...") at `run_api_tests.php:2054` |
| POST /api/v1/notifications/messages | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Notification send enforces recipient scope...") at `run_api_tests.php:1269` |
| GET /api/v1/notifications/inbox | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Customer can opt-out, view inbox...") at `run_api_tests.php:2054`; erunCase("User registration, login, and self-service...") at `run_e2e_tests.php:159` |
| GET /api/v1/notifications/analytics | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Notification analytics is scope-isolated...") at `run_api_tests.php:1243` |
| POST /api/v1/files/upload-base64 | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("File upload enforces strict type and 10MB size...") at `run_api_tests.php:930` |
| GET /api/v1/files/:id/signed-url | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Signed URL expiry and hotlink validation") at `run_api_tests.php:970` |
| GET /api/v1/files/download/:id | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Signed URL expiry and hotlink validation") at `run_api_tests.php:970` |
| POST /api/v1/files/cleanup | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("File lifecycle cleanup removes DB rows...") at `run_api_tests.php:1082` |
| GET /api/v1/files | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Scoped file listing excludes foreign attachments") at `run_api_tests.php:1025` |
| GET /api/v1/reporting/dashboard | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Initial UI calls return strict JSON only") at `run_api_tests.php:266`; erunCase("Complete login-to-dashboard...") at `run_e2e_tests.php:132` |
| GET /api/v1/reporting/anomalies | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Anomaly alerts endpoint returns structured payload") at `run_api_tests.php:1454` |
| POST /api/v1/reporting/anomalies/generate | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Reporting anomaly generate endpoint...") at `run_api_tests.php:2296` |
| GET /api/v1/reporting/exports/bookings-csv | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("CSV export returns decodable CSV payload") at `run_api_tests.php:1443` |
| GET /api/v1/admin/users | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Admin user list and audit log scope filtering...") at `run_api_tests.php:1818`; erunCase("User registration, login...") at `run_e2e_tests.php:159` |
| GET /api/v1/admin/audit-logs | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin user list and audit log scope filtering...") at `run_api_tests.php:1818` |
| POST /api/v1/admin/reauth | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Re-auth token one-time use...") at `run_api_tests.php:1379` |
| GET /api/v1/admin/roles | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php, repo/tests/Integration/run_e2e_tests.php | runCase("Admin metadata read endpoints...") at `run_api_tests.php:2304`; erunCase("Complete login-to-dashboard...") at `run_e2e_tests.php:132` |
| POST /api/v1/admin/roles | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Non-global-admin cannot create roles...") at `run_api_tests.php:1852` |
| GET /api/v1/admin/permissions | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin metadata read endpoints...") at `run_api_tests.php:2304` |
| GET /api/v1/admin/resources | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin metadata read endpoints...") at `run_api_tests.php:2304` |
| POST /api/v1/admin/grants | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Non-global-admin cannot create roles or grant permissions") at `run_api_tests.php:1852` |
| POST /api/v1/admin/user-roles | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Non-global-admin cannot create roles...") at `run_api_tests.php:1852` |
| POST /api/v1/admin/users/:userId/enable | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin account lifecycle APIs enforce authorization...") at `run_api_tests.php:1494` |
| POST /api/v1/admin/users/:userId/disable | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin account lifecycle APIs enforce authorization...") at `run_api_tests.php:1494` |
| POST /api/v1/admin/users/:userId/reset-password | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin password reset rejects weak passwords...") at `run_api_tests.php:1543` |
| POST /api/v1/admin/users/:userId/scopes | yes | true no-mock HTTP | repo/tests/Integration/run_api_tests.php | runCase("Admin account lifecycle APIs enforce authorization...") at `run_api_tests.php:1494` |

## API Test Classification

### 1) True No-Mock HTTP
- `repo/tests/Integration/run_api_tests.php`
- `repo/tests/Integration/run_e2e_tests.php`
- Evidence of real HTTP path:
  - Direct network calls to `http://127.0.0.1` in `api(...)`/`apiWithHeaders(...)` (`run_api_tests.php:94`, `run_api_tests.php:131`).
  - Proxy-layer E2E via nginx to `http://web` in `webRaw(...)`/`webApi(...)` (`run_e2e_tests.php:35`, `run_e2e_tests.php:58`).
  - Middleware is globally wired (`repo/backend/config/middleware.php:3-5`) and requests target declared routes (`repo/backend/route/app.php`).

### 2) HTTP with Mocking
- None detected in API HTTP tests.

### 3) Non-HTTP (unit/integration without HTTP)
- `repo/tests/Unit/domain_tests.php`
- `repo/tests/Unit/service_tests.php`
- `repo/tests/Unit/service_logic_tests.php`
- `repo/frontend/tests/app.test.js`

## Mock Detection

### API HTTP tests
- No `jest.mock`, `vi.mock`, `sinon.stub`, DI override, controller/service stubbing detected in API HTTP test files.
- Evidence: grep scan found no mocking framework calls in API integration files.

### Non-HTTP tests (detected stubbing/test doubles)
- File: `repo/tests/Unit/service_logic_tests.php`
- What is mocked/stubbed: repository facades and infrastructure classes are replaced by in-file fake classes (e.g., `IdentityRepository`, `BookingRepository`, `PaymentRepository`, `FileRepository`, `AdminRepository`) via `eval(...)` definitions.
- Where: starts near top of file (`service_logic_tests.php:6` onward, multiple class blocks).

- File: `repo/frontend/tests/app.test.js`
- What is mocked/stubbed: DOM shim (`document`, `localStorage`, `sessionStorage`), global `fetch`, and lightweight `layui` stub.
- Where: `app.test.js:49` onward (`document`), `app.test.js:64` onward (`localStorage`/`sessionStorage`), `app.test.js:152` (`layui`), `app.test.js:157` (`fetch`).

## Coverage Summary
- Total endpoints: 70 (`repo/backend/route/app.php`, concrete method routes).
- Endpoints with HTTP tests (strict deterministic): 69.
- Endpoints with true no-mock HTTP tests: 69.
- HTTP coverage: 98.57%.
- True API coverage: 98.57%.
- Strict uncovered endpoint: `GET /api/v1/payments/reconcile/issues` (conditional-only invocation in test logic).

## Unit Test Summary

### Backend Unit Tests
- Test files:
  - `repo/tests/Unit/domain_tests.php`
  - `repo/tests/Unit/service_tests.php`
  - `repo/tests/Unit/service_logic_tests.php`

- Modules covered:
  - Domain policies: `PasswordPolicy`, `LoginLockPolicy`, `BookingDomainPolicy`, `PaymentDomainPolicy`, `RecipeDomainPolicy` (`domain_tests.php`).
  - Services: `AuthTokenService`, `CryptoService`, `NotificationService` (`service_tests.php`), plus `IdentityService`, `BookingService`, `PaymentService`, `FileService`, `AdministrationService` logic (`service_logic_tests.php`).
  - Auth/authorization behavior indirectly through service-level logic tests (not middleware execution path).

- Important backend modules NOT unit-tested directly:
  - Controllers under `repo/backend/app/controller/api/v1/*.php`.
  - Middleware: `AuthenticationMiddleware`, `AuthorizationMiddleware`, `CorsMiddleware` (only covered via integration path, not unit-level).
  - Repositories concrete DB implementations under `repo/backend/app/repository/*.php` (service logic tests use fakes/stubs).
  - Services with weak/no direct unit focus: `OperationsService`, `ReportingService`, `RecipeService`, `TagService`, `AuthorizationService`.

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files detected:
  - `repo/frontend/tests/app.test.js`

- Frameworks/tools detected:
  - Node.js custom test harness (manual `suite`, `assert`, `process.exit`), no Jest/Vitest markers.
  - Custom DOM/network shims and module loading via `eval` from `frontend/assets/js/modules/*.js`.

- Components/modules covered:
  - `frontend/assets/js/modules/api.js` (escape, submit guards, token/session behavior, queue basics).
  - `frontend/assets/js/modules/auth.js`, `recipes.js`, `bookings.js`, `ops.js`, `finance.js`, `admin.js` are loaded into test runtime and partially behavior-checked through exposed globals.

- Important frontend components/modules NOT tested (or not directly asserted):
  - `repo/frontend/assets/js/app.js` bootstrap orchestration and initial-load workflow.
  - Real browser/layui integration behavior and tab rendering lifecycle.
  - Full UI interaction chains against actual DOM and real fetch responses.

- Mandatory verdict:
  - **Frontend unit tests: PRESENT**

- Strict adequacy finding:
  - **CRITICAL GAP**: tests exist but are shallow relative to fullstack scope; they rely on heavy shimming and do not validate full UI bootstrap/runtime behavior in browser context.

### Cross-Layer Observation
- Backend testing is broad and deep (integration-heavy), frontend unit testing is narrow and synthetic.
- Balance verdict: backend-heavy; frontend coverage depth is materially weaker.

## API Observability Check
- Strong observability in most API tests:
  - Explicit method/path requests, input payloads, and response assertions are present (`run_api_tests.php` runCase blocks).
- Weak observability areas:
  - `GET /api/v1/payments/reconcile/issues` test path is conditional and not deterministically asserted (`run_api_tests.php:1838-1845`).
  - Some assertions are permissive (e.g., allowing `200` or `403` for analytics in customer flow), reducing precision (`run_api_tests.php:2104`).

## Tests Check
- `run_tests.sh` is Docker-based and container-contained (`repo/run_tests.sh` uses `docker compose exec` throughout).
- No local package-manager dependency path required by script.
- Verdict: PASS for execution model constraint.

## End-to-End Expectations (fullstack)
- FE-BE real path tests exist through nginx proxy (`repo/tests/Integration/run_e2e_tests.php`).
- Partial compensation status: present and useful, but frontend unit depth remains limited.

## Test Coverage Score (0-100)
- **Score: 84/100**

## Score Rationale
- High positive:
  - Near-complete endpoint coverage.
  - Real HTTP/no-mock integration strategy.
  - Broad auth/permission/edge-case assertions.
- Deductions:
  - One endpoint lacks deterministic invocation evidence.
  - Frontend unit testing is present but shallow for fullstack expectations.
  - Some weak/conditional observability assertions.

## Key Gaps
1. Deterministic coverage gap for `GET /api/v1/payments/reconcile/issues`.
2. Frontend unit suite does not directly test `frontend/assets/js/app.js` bootstrap behavior.
3. Over-reliance on monolithic integration script reduces test isolation/maintainability.

## Confidence & Assumptions
- Confidence: high for static route/test mapping; medium-high for behavioral sufficiency (no runtime execution).
- Assumption: route declarations in `repo/backend/route/app.php` are canonical and complete.

## Test Coverage Final Verdict
- **PARTIAL PASS**

---

# README Audit

## README Location Check
- Found at required location: `repo/README.md`.

## Hard Gate Evaluation

### Formatting
- PASS: clean markdown structure with sections and table (`repo/README.md`).

### Startup Instructions (backend/fullstack)
- PASS: includes `docker-compose up --build -d` (`repo/README.md:41`, `repo/README.md:61`).

### Access Method
- PASS: explicit URL/ports for frontend/API/MySQL (`repo/README.md:46-48`).

### Verification Method
- **FAIL (Hard Gate)**:
  - README does not provide explicit manual verification workflow such as concrete curl/Postman requests or step-by-step UI validation flow with expected outcomes.
  - Existing “Testing” section describes running test script, not manual runtime verification method (`repo/README.md:56-76`).

### Environment Rules (Docker-contained, no runtime installs/manual DB setup)
- PASS:
  - Declares Docker-only requirement and no local PHP/Node/DB tooling required (`repo/README.md:31-34`).
  - No prohibited runtime install commands found.

### Demo Credentials (auth exists)
- **FAIL (Hard Gate)**:
  - Auth clearly exists (login endpoints and role-based access in project).
  - README provides credentials for only two roles (`admin`, `scoped_user`) (`repo/README.md:84-86`).
  - Requirement asks credentials for all roles; this is incomplete.

## Engineering Quality
- Tech stack clarity: good.
- Architecture explanation: concise but adequate.
- Testing instructions: present and clear.
- Security/roles documentation: partial (insufficient role credential coverage).
- Workflow clarity: moderate; verification workflow is under-specified.
- Presentation quality: good.

## High Priority Issues
1. Missing hard-gate verification method with explicit manual proof steps (API/UI expected outcomes).
2. Missing hard-gate complete demo credential set for all roles supported by the system.

## Medium Priority Issues
1. README does not clearly enumerate role matrix (all supported roles and intended capabilities), making credential completeness unverifiable.

## Low Priority Issues
1. No troubleshooting section for common startup/test failures.

## Hard Gate Failures
1. Verification Method: FAIL.
2. Demo Credentials (all roles): FAIL.

## README Verdict
- **FAIL**

## README Final Verdict
- **FAIL**
