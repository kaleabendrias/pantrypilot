# PantryPilot Fix Check Report (Against .tmp/audit_report-1.md)

## 1. Scope
- This is a static-only re-check of the exact issues listed in .tmp/audit_report-1.md.
- No runtime execution was performed.

## 2. Overall Fix Status
- Total issues checked: 6
- Fixed: 6
- Not fixed: 0
- Partially fixed: 0

## 3. Issue-by-Issue Verification

### Issue 1
- Original title: Insecure fallback secrets allow forged payment callback signatures and weak at-rest cryptographic posture
- Previous severity: High
- Current status: Fixed
- Verification:
  - Insecure hardcoded fallback secrets were removed; security config now requires non-empty env secrets and throws on empty values.
  - Evidence:
    - repo/backend/config/security.php:11
    - repo/backend/config/security.php:16
    - repo/backend/config/security.php:19
    - repo/backend/config/security.php:22
  - Additional hardening path present:
    - Docker entrypoint now generates strong random secrets when env values are empty (avoids predictable defaults).
    - Evidence:
      - repo/docker/php/entrypoint.sh:8
      - repo/docker/php/entrypoint.sh:14
      - repo/docker/php/entrypoint.sh:19

### Issue 2
- Original title: Customer-level notification permissions can expose non-self analytics/events scope
- Previous severity: High
- Current status: Fixed
- Verification:
  - Customer role grants were changed to notification_self only (no notification read/write grant for feed/analytics).
  - Evidence:
    - repo/docker/mysql/init/001_schema.sql:385
    - repo/docker/mysql/init/001_schema.sql:397
    - repo/backend/scripts/reset_test_data.php:340
    - repo/backend/scripts/reset_test_data.php:344
  - Tests now assert customer is denied events/analytics.
  - Evidence:
    - repo/tests/Integration/run_api_tests.php:2208
    - repo/tests/Integration/run_api_tests.php:2212

### Issue 3
- Original title: Public password-rotation endpoint bypasses login lockout controls
- Previous severity: High
- Current status: Fixed
- Verification:
  - Rotation route was moved out of public routes into bootstrap_routes.
  - Evidence:
    - repo/backend/config/acl.php:4
    - repo/backend/config/acl.php:11
  - Bootstrap token authentication is now required by middleware for the rotation route.
  - Evidence:
    - repo/backend/app/middleware/AuthenticationMiddleware.php:28
    - repo/backend/app/middleware/AuthenticationMiddleware.php:31
  - Rotation flow now uses one-time bootstrap token and includes lock-state check.
  - Evidence:
    - repo/backend/app/controller/api/v1/IdentityController.php:55
    - repo/backend/app/service/IdentityService.php:125
    - repo/backend/app/service/IdentityService.php:150

### Issue 4
- Original title: Test suite tolerates ambiguous customer access behavior for notification analytics
- Previous severity: Medium
- Current status: Fixed
- Verification:
  - Ambiguous assertion (200 or 403) was replaced by strict deny expectation (403).
  - Evidence:
    - repo/tests/Integration/run_api_tests.php:2119

### Issue 5
- Original title: Documentation states secrets are mandatory, but runtime config currently allows insecure fallback
- Previous severity: Medium
- Current status: Fixed
- Verification:
  - Runtime no longer allows insecure fallback defaults (throws if unresolved empty in app config).
  - Evidence:
    - repo/backend/config/security.php:16
    - repo/backend/config/security.php:19
    - repo/backend/config/security.php:22
  - Documentation was updated to match actual startup behavior with entrypoint-generated secrets for empty env.
  - Evidence:
    - repo/.env.example:6
    - repo/.env.example:24
    - repo/docker/php/entrypoint.sh:8

### Issue 6
- Original title: CORS wildcard is broad for a role-sensitive API surface
- Previous severity: Low
- Current status: Fixed
- Verification:
  - CORS was changed from wildcard allow-origin to explicit allowlist via PANTRYPILOT_ALLOWED_ORIGINS.
  - Response only emits Access-Control-Allow-Origin when origin is explicitly allowed.
  - Evidence:
    - repo/backend/app/middleware/CorsMiddleware.php:11
    - repo/backend/app/middleware/CorsMiddleware.php:24
    - repo/backend/app/middleware/CorsMiddleware.php:34
    - repo/.env.example:32

## 4. Final Conclusion
- Based on static inspection, all issues listed in .tmp/audit_report-1.md are fixed in the current repository state.
- Runtime validation is still required to confirm behavior under real deployment/runtime conditions.