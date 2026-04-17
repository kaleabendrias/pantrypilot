# Test Coverage Audit

## Scope and Method
- Audit mode: static inspection only.
- Runtime actions: none (no tests/scripts/containers executed).
- Primary evidence sources:
  - [repo/backend/route/app.php](repo/backend/route/app.php)
  - [repo/tests/Integration/run_api_tests.php](repo/tests/Integration/run_api_tests.php)
  - [repo/tests/Integration/run_e2e_tests.php](repo/tests/Integration/run_e2e_tests.php)
  - [repo/tests/Integration/run_reconcile_tests.php](repo/tests/Integration/run_reconcile_tests.php)
  - [repo/tests/Unit/domain_tests.php](repo/tests/Unit/domain_tests.php)
  - [repo/tests/Unit/service_tests.php](repo/tests/Unit/service_tests.php)
  - [repo/tests/Unit/service_logic_tests.php](repo/tests/Unit/service_logic_tests.php)
  - [repo/tests/Unit/bootstrap.php](repo/tests/Unit/bootstrap.php)
  - [repo/frontend/tests/app.test.js](repo/frontend/tests/app.test.js)
  - [repo/run_tests.sh](repo/run_tests.sh)

## Project Type Detection
- Declared in README top section: fullstack.
- Evidence: [repo/README.md#L3](repo/README.md#L3).

## Backend Endpoint Inventory
Source: [repo/backend/route/app.php](repo/backend/route/app.php)

1. GET /
2. POST /api/v1/identity/login
3. POST /api/v1/identity/register
4. POST /api/v1/identity/rotate-password
5. GET /api/v1/recipes/search
6. GET /api/v1/recipes
7. POST /api/v1/recipes
8. GET /api/v1/tags
9. POST /api/v1/tags
10. GET /api/v1/bookings/recipe/:recipeId
11. GET /api/v1/bookings/slot-capacity
12. GET /api/v1/bookings/today-pickups
13. POST /api/v1/bookings/check-in
14. POST /api/v1/bookings/no-show-sweep
15. GET /api/v1/bookings/:bookingId/dispatch-note
16. GET /api/v1/bookings/pickup-points
17. GET /api/v1/pickup-points
18. GET /api/v1/bookings
19. POST /api/v1/bookings
20. GET /api/v1/operations/campaigns
21. POST /api/v1/operations/campaigns
22. GET /api/v1/operations/homepage-modules
23. POST /api/v1/operations/homepage-modules
24. GET /api/v1/operations/message-templates
25. POST /api/v1/operations/message-templates
26. GET /api/v1/operations/dashboard
27. POST /api/v1/payments/gateway/orders
28. POST /api/v1/payments/gateway/callback
29. POST /api/v1/payments/gateway/auto-cancel
30. GET /api/v1/payments/reconcile/batches
31. GET /api/v1/payments/reconcile/issues
32. POST /api/v1/payments/reconcile/daily
33. POST /api/v1/payments/reconcile/repair
34. POST /api/v1/payments/reconcile/close
35. POST /api/v1/payments/reconcile
36. POST /api/v1/payments/reauth
37. POST /api/v1/payments/refund
38. POST /api/v1/payments/adjust
39. GET /api/v1/payments
40. POST /api/v1/payments
41. GET /api/v1/notifications/events
42. POST /api/v1/notifications/events
43. POST /api/v1/notifications/preferences/opt-out
44. POST /api/v1/notifications/messages/:id/read
45. POST /api/v1/notifications/messages/:id/click
46. POST /api/v1/notifications/messages
47. GET /api/v1/notifications/inbox
48. GET /api/v1/notifications/analytics
49. POST /api/v1/files/upload-base64
50. GET /api/v1/files/:id/signed-url
51. GET /api/v1/files/download/:id
52. POST /api/v1/files/cleanup
53. GET /api/v1/files
54. GET /api/v1/reporting/dashboard
55. GET /api/v1/reporting/anomalies
56. POST /api/v1/reporting/anomalies/generate
57. GET /api/v1/reporting/exports/bookings-csv
58. GET /api/v1/admin/users
59. GET /api/v1/admin/audit-logs
60. POST /api/v1/admin/reauth
61. GET /api/v1/admin/roles
62. POST /api/v1/admin/roles
63. GET /api/v1/admin/permissions
64. GET /api/v1/admin/resources
65. POST /api/v1/admin/grants
66. POST /api/v1/admin/user-roles
67. POST /api/v1/admin/users/:userId/enable
68. POST /api/v1/admin/users/:userId/disable
69. POST /api/v1/admin/users/:userId/reset-password
70. POST /api/v1/admin/users/:userId/scopes

## API Test Mapping Table
Coverage criterion used: exact METHOD + resolved PATH through real HTTP request path to the app route handler.

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| GET / | No | unit-only / indirect | none | route only: [repo/backend/route/app.php#L16](repo/backend/route/app.php#L16) |
| POST /api/v1/identity/login | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests, run_reconcile_tests | [repo/tests/Integration/run_api_tests.php#L271](repo/tests/Integration/run_api_tests.php#L271) |
| POST /api/v1/identity/register | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L1598](repo/tests/Integration/run_api_tests.php#L1598) |
| POST /api/v1/identity/rotate-password | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2231](repo/tests/Integration/run_api_tests.php#L2231) |
| GET /api/v1/recipes/search | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L387](repo/tests/Integration/run_api_tests.php#L387) |
| GET /api/v1/recipes | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L2253](repo/tests/Integration/run_api_tests.php#L2253) |
| POST /api/v1/recipes | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2263](repo/tests/Integration/run_api_tests.php#L2263) |
| GET /api/v1/tags | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2283](repo/tests/Integration/run_api_tests.php#L2283) |
| POST /api/v1/tags | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2293](repo/tests/Integration/run_api_tests.php#L2293) |
| GET /api/v1/bookings/recipe/:recipeId | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L448](repo/tests/Integration/run_api_tests.php#L448) |
| GET /api/v1/bookings/slot-capacity | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2520](repo/tests/Integration/run_api_tests.php#L2520) |
| GET /api/v1/bookings/today-pickups | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L667](repo/tests/Integration/run_api_tests.php#L667) |
| POST /api/v1/bookings/check-in | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2527](repo/tests/Integration/run_api_tests.php#L2527) |
| POST /api/v1/bookings/no-show-sweep | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L777](repo/tests/Integration/run_api_tests.php#L777) |
| GET /api/v1/bookings/:bookingId/dispatch-note | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1489](repo/tests/Integration/run_api_tests.php#L1489) |
| GET /api/v1/bookings/pickup-points | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L580](repo/tests/Integration/run_api_tests.php#L580) |
| GET /api/v1/pickup-points | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2303](repo/tests/Integration/run_api_tests.php#L2303) |
| GET /api/v1/bookings | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L616](repo/tests/Integration/run_api_tests.php#L616) |
| POST /api/v1/bookings | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L356](repo/tests/Integration/run_api_tests.php#L356) |
| GET /api/v1/operations/campaigns | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L703](repo/tests/Integration/run_api_tests.php#L703) |
| POST /api/v1/operations/campaigns | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L687](repo/tests/Integration/run_api_tests.php#L687) |
| GET /api/v1/operations/homepage-modules | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L726](repo/tests/Integration/run_api_tests.php#L726) |
| POST /api/v1/operations/homepage-modules | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L713](repo/tests/Integration/run_api_tests.php#L713) |
| GET /api/v1/operations/message-templates | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L753](repo/tests/Integration/run_api_tests.php#L753) |
| POST /api/v1/operations/message-templates | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L736](repo/tests/Integration/run_api_tests.php#L736) |
| GET /api/v1/operations/dashboard | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L680](repo/tests/Integration/run_api_tests.php#L680) |
| POST /api/v1/payments/gateway/orders | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L794](repo/tests/Integration/run_api_tests.php#L794) |
| POST /api/v1/payments/gateway/callback | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L805](repo/tests/Integration/run_api_tests.php#L805) |
| POST /api/v1/payments/gateway/auto-cancel | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1794](repo/tests/Integration/run_api_tests.php#L1794) |
| GET /api/v1/payments/reconcile/batches | Yes | true no-mock HTTP | run_reconcile_tests | [repo/tests/Integration/run_reconcile_tests.php#L103](repo/tests/Integration/run_reconcile_tests.php#L103) |
| GET /api/v1/payments/reconcile/issues | Yes | true no-mock HTTP | run_api_tests, run_reconcile_tests | [repo/tests/Integration/run_reconcile_tests.php#L125](repo/tests/Integration/run_reconcile_tests.php#L125) |
| POST /api/v1/payments/reconcile/daily | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L905](repo/tests/Integration/run_api_tests.php#L905) |
| POST /api/v1/payments/reconcile/repair | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1408](repo/tests/Integration/run_api_tests.php#L1408) |
| POST /api/v1/payments/reconcile/close | Yes | true no-mock HTTP | run_api_tests, run_reconcile_tests | [repo/tests/Integration/run_reconcile_tests.php#L193](repo/tests/Integration/run_reconcile_tests.php#L193) |
| POST /api/v1/payments/reconcile | Yes | true no-mock HTTP | run_api_tests, run_reconcile_tests | [repo/tests/Integration/run_reconcile_tests.php#L150](repo/tests/Integration/run_reconcile_tests.php#L150) |
| POST /api/v1/payments/reauth | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1616](repo/tests/Integration/run_api_tests.php#L1616) |
| POST /api/v1/payments/refund | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1418](repo/tests/Integration/run_api_tests.php#L1418) |
| POST /api/v1/payments/adjust | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1434](repo/tests/Integration/run_api_tests.php#L1434) |
| GET /api/v1/payments | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L1224](repo/tests/Integration/run_api_tests.php#L1224) |
| POST /api/v1/payments | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L915](repo/tests/Integration/run_api_tests.php#L915) |
| GET /api/v1/notifications/events | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L2358](repo/tests/Integration/run_api_tests.php#L2358) |
| POST /api/v1/notifications/events | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1624](repo/tests/Integration/run_api_tests.php#L1624) |
| POST /api/v1/notifications/preferences/opt-out | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1310](repo/tests/Integration/run_api_tests.php#L1310) |
| POST /api/v1/notifications/messages/:id/read | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1476](repo/tests/Integration/run_api_tests.php#L1476) |
| POST /api/v1/notifications/messages/:id/click | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2674](repo/tests/Integration/run_api_tests.php#L2674) |
| POST /api/v1/notifications/messages | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1281](repo/tests/Integration/run_api_tests.php#L1281) |
| GET /api/v1/notifications/inbox | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L2083](repo/tests/Integration/run_api_tests.php#L2083) |
| GET /api/v1/notifications/analytics | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1256](repo/tests/Integration/run_api_tests.php#L1256) |
| POST /api/v1/files/upload-base64 | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L931](repo/tests/Integration/run_api_tests.php#L931) |
| GET /api/v1/files/:id/signed-url | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L954](repo/tests/Integration/run_api_tests.php#L954) |
| GET /api/v1/files/download/:id | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L958](repo/tests/Integration/run_api_tests.php#L958) |
| POST /api/v1/files/cleanup | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1101](repo/tests/Integration/run_api_tests.php#L1101) |
| GET /api/v1/files | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1034](repo/tests/Integration/run_api_tests.php#L1034) |
| GET /api/v1/reporting/dashboard | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L267](repo/tests/Integration/run_api_tests.php#L267) |
| GET /api/v1/reporting/anomalies | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1199](repo/tests/Integration/run_api_tests.php#L1199) |
| POST /api/v1/reporting/anomalies/generate | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2370](repo/tests/Integration/run_api_tests.php#L2370) |
| GET /api/v1/reporting/exports/bookings-csv | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1186](repo/tests/Integration/run_api_tests.php#L1186) |
| GET /api/v1/admin/users | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L335](repo/tests/Integration/run_api_tests.php#L335) |
| GET /api/v1/admin/audit-logs | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1833](repo/tests/Integration/run_api_tests.php#L1833) |
| POST /api/v1/admin/reauth | Yes | true no-mock HTTP | run_api_tests, run_reconcile_tests | [repo/tests/Integration/run_api_tests.php#L1403](repo/tests/Integration/run_api_tests.php#L1403) |
| GET /api/v1/admin/roles | Yes | true no-mock HTTP | run_api_tests, run_e2e_tests | [repo/tests/Integration/run_api_tests.php#L2378](repo/tests/Integration/run_api_tests.php#L2378) |
| POST /api/v1/admin/roles | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1881](repo/tests/Integration/run_api_tests.php#L1881) |
| GET /api/v1/admin/permissions | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2384](repo/tests/Integration/run_api_tests.php#L2384) |
| GET /api/v1/admin/resources | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L2390](repo/tests/Integration/run_api_tests.php#L2390) |
| POST /api/v1/admin/grants | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1870](repo/tests/Integration/run_api_tests.php#L1870) |
| POST /api/v1/admin/user-roles | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1873](repo/tests/Integration/run_api_tests.php#L1873) |
| POST /api/v1/admin/users/:userId/enable | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1511](repo/tests/Integration/run_api_tests.php#L1511) |
| POST /api/v1/admin/users/:userId/disable | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1503](repo/tests/Integration/run_api_tests.php#L1503) |
| POST /api/v1/admin/users/:userId/reset-password | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1515](repo/tests/Integration/run_api_tests.php#L1515) |
| POST /api/v1/admin/users/:userId/scopes | Yes | true no-mock HTTP | run_api_tests | [repo/tests/Integration/run_api_tests.php#L1528](repo/tests/Integration/run_api_tests.php#L1528) |

## API Test Classification
1. True No-Mock HTTP
- [repo/tests/Integration/run_api_tests.php](repo/tests/Integration/run_api_tests.php)
- [repo/tests/Integration/run_e2e_tests.php](repo/tests/Integration/run_e2e_tests.php)
- [repo/tests/Integration/run_reconcile_tests.php](repo/tests/Integration/run_reconcile_tests.php)
- Evidence of real HTTP layer usage:
  - file_get_contents('http://127.0.0.1' . $path) in [repo/tests/Integration/run_api_tests.php#L96](repo/tests/Integration/run_api_tests.php#L96)
  - file_get_contents('http://web' . $path) in [repo/tests/Integration/run_e2e_tests.php#L35](repo/tests/Integration/run_e2e_tests.php#L35)
2. HTTP with Mocking
- None detected in integration API tests.
3. Non-HTTP (unit/integration without HTTP)
- [repo/tests/Unit/domain_tests.php](repo/tests/Unit/domain_tests.php)
- [repo/tests/Unit/service_tests.php](repo/tests/Unit/service_tests.php)
- [repo/tests/Unit/service_logic_tests.php](repo/tests/Unit/service_logic_tests.php)
- [repo/frontend/tests/app.test.js](repo/frontend/tests/app.test.js)

## Mock Detection
### API tests
- No jest.mock, vi.mock, sinon.stub, or DI override patterns found in API integration test files.
- Result: no API tests classified as HTTP test with mocking.

### Non-HTTP tests (explicitly mocked/faked contexts)
- Backend unit tests define fake/stub repository and facade classes, then invoke services directly (bypassing HTTP):
  - class stubs in [repo/tests/Unit/service_logic_tests.php](repo/tests/Unit/service_logic_tests.php)
  - additional fakes in [repo/tests/Unit/bootstrap.php](repo/tests/Unit/bootstrap.php)
- Frontend unit tests stub global browser/runtime APIs:
  - DOM shim and storage shims in [repo/frontend/tests/app.test.js](repo/frontend/tests/app.test.js)
  - fetch stub in [repo/frontend/tests/app.test.js#L154](repo/frontend/tests/app.test.js#L154)

## Coverage Summary
- Total endpoints declared: 70
- Endpoints with HTTP tests: 69
- Endpoints with TRUE no-mock HTTP tests: 69
- HTTP coverage: 98.57%
- True API coverage: 98.57%
- Uncovered endpoint: GET /

## Unit Test Summary
### Backend Unit Tests
- Test files:
  - [repo/tests/Unit/domain_tests.php](repo/tests/Unit/domain_tests.php)
  - [repo/tests/Unit/service_tests.php](repo/tests/Unit/service_tests.php)
  - [repo/tests/Unit/service_logic_tests.php](repo/tests/Unit/service_logic_tests.php)
- Modules covered (evidence-driven):
  - Services: Identity, Booking, Payment, File, Notification, AuthToken, Crypto
  - Domain policies: password, login lock, booking, payment, recipe publish
  - Infrastructure interactions via stubs/fakes
- Important backend modules not clearly unit-tested:
  - API controllers under [repo/backend/app/controller/api/v1](repo/backend/app/controller/api/v1)
  - Middleware auth/authorization paths under [repo/backend/app/middleware](repo/backend/app/middleware)
  - Repository concrete implementations under [repo/backend/app/repository](repo/backend/app/repository)

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files detected:
  - [repo/frontend/tests/app.test.js](repo/frontend/tests/app.test.js)
- Frameworks/tools detected:
  - Node-executed custom test harness (suite/assert) with DOM/fetch shims
- Components/modules covered:
  - [repo/frontend/assets/js/modules/api.js](repo/frontend/assets/js/modules/api.js)
  - [repo/frontend/assets/js/modules/auth.js](repo/frontend/assets/js/modules/auth.js)
  - [repo/frontend/assets/js/modules/recipes.js](repo/frontend/assets/js/modules/recipes.js)
  - [repo/frontend/assets/js/modules/bookings.js](repo/frontend/assets/js/modules/bookings.js)
  - [repo/frontend/assets/js/modules/ops.js](repo/frontend/assets/js/modules/ops.js)
  - [repo/frontend/assets/js/modules/finance.js](repo/frontend/assets/js/modules/finance.js)
  - [repo/frontend/assets/js/modules/admin.js](repo/frontend/assets/js/modules/admin.js)
  - [repo/frontend/assets/js/app.js](repo/frontend/assets/js/app.js)
- Important frontend components/modules not tested deeply:
  - Browser rendering behavior in real browser engine (tests rely on custom shim, not real DOM)
  - Network behavior with real backend responses (fetch is stubbed in unit tests)
- Mandatory verdict:
  - Frontend unit tests: PRESENT

### Cross-Layer Observation
- Backend testing depth is substantially higher than frontend unit depth.
- There is FE↔BE path testing via nginx proxy, but frontend unit tests remain shim-driven and less behavior-faithful than backend API coverage.

## API Observability Check
- Endpoint visibility: strong (method + path explicit in test code).
- Request input visibility: strong (body/query parameters explicit in many tests).
- Response content visibility: medium-strong (status + JSON contract + field assertions are common, but some tests assert mostly status/success flags).

## Tests Check
- [repo/run_tests.sh](repo/run_tests.sh) is Docker-based and orchestrates API, unit, reconcile, frontend unit, and FE-BE tests via container commands.
- No mandatory local package manager setup required in script.
- Result: Docker-based test entrypoint requirement satisfied.

## Test Coverage Score (0–100)
- Score: 92

## Score Rationale
- Strong positives:
  - Near-complete endpoint coverage across real HTTP tests.
  - API tests are mostly true no-mock HTTP through real network stacks.
  - Broad success/failure/permission/validation/idempotency coverage.
- Deductions:
  - One uncovered declared endpoint: GET /.
  - Frontend unit tests are present but rely heavily on custom shims and stubs.

## Key Gaps
1. Uncovered route GET / (service health/root endpoint).
2. Frontend unit tests do not execute in real browser/DOM runtime; behavior confidence depends on shim accuracy.
3. Unit tests bypass controller + middleware layers (expected for unit scope, but leaves some boundary risks to integration coverage).

## Confidence and Assumptions
- Confidence: high for endpoint mapping and README gate checks.
- Assumptions:
  - Route declarations in [repo/backend/route/app.php](repo/backend/route/app.php) represent the complete backend endpoint surface.
  - Dynamic path calls with concrete IDs are accepted as coverage of parameterized routes.

## Test Coverage Audit Verdict
- Verdict: PARTIAL PASS (strong overall, with one uncovered endpoint and frontend-depth caveat).

---

# README Audit

## README Location Check
- Required file exists: [repo/README.md](repo/README.md)

## Hard Gate Evaluation
### Formatting
- PASS: clear markdown structure, headings, code blocks, tables.

### Startup Instructions
- PASS: includes docker startup command for fullstack/backend.
- Evidence: [repo/README.md#L33](repo/README.md#L33)

### Access Method
- PASS: includes URLs/ports for frontend/backend/MySQL.
- Evidence: [repo/README.md#L39](repo/README.md#L39)

### Verification Method
- PASS: includes API verification via curl and UI verification flow.
- Evidence:
  - API verification section starts at [repo/README.md#L119](repo/README.md#L119)
  - UI flow checks at [repo/README.md#L205](repo/README.md#L205)

### Environment Rules (Docker-contained)
- PASS: README states Docker-only prerequisites; no npm/pip/apt/manual DB setup instructions as required startup path.
- Evidence:
  - Docker-only prerequisites at [repo/README.md#L25](repo/README.md#L25)

### Demo Credentials (Auth conditional)
- PASS: credentials and role matrix are documented.
- Evidence:
  - Seeded credentials table at [repo/README.md#L65](repo/README.md#L65)
  - Role/permission matrix at [repo/README.md#L79](repo/README.md#L79)

## Engineering Quality Review
- Tech stack clarity: strong.
- Architecture explanation: strong and specific to services/proxy/runtime.
- Testing instructions: strong (single script + detailed sequence).
- Security/roles explanation: strong (credentials + role matrix + scope behavior).
- Workflow clarity: strong manual verification with concrete command examples.
- Presentation quality: high readability.

## High Priority Issues
- None found.

## Medium Priority Issues
1. Manual verification examples rely on jq on host shell without explicitly noting it as optional convenience, which may reduce out-of-box reproducibility for minimal Docker-only hosts.

## Low Priority Issues
1. Mixed docker-compose and docker compose command styles across repository docs/scripts can create minor operator confusion.

## Hard Gate Failures
- None.

## README Verdict
- PASS

---

## Final Combined Verdicts
1. Test Coverage Audit: PARTIAL PASS
2. README Audit: PASS
