# PantryPilot API Specification (High-Level)

## 1. API Conventions
- Base path: /api/v1
- Transport: HTTP over local network
- Content type: application/json
- Auth header for protected routes: Authorization: Bearer <token>

Response envelope:
- success: boolean
- message: string
- data: object or array
- timestamp: ISO datetime

Error envelope:
- success: false
- message: string
- errors: optional list/object
- timestamp: ISO datetime

## 2. Authentication and Identity
### POST /identity/register
- Purpose: register account.
- Auth: public.
- Request:
  - username: string
  - password: string
  - display_name: string (optional)
  - phone: string (optional)
  - address: string (optional)
- Success: 201 with created user id.
- Errors: 422 validation or conflict-like user exists.

### POST /identity/login
- Purpose: login and issue token/session.
- Auth: public.
- Request:
  - username: string
  - password: string
- Success: 200 with token and user profile.
- Errors:
  - 401 invalid credentials
  - 401 temporary lockout

## 3. Recipe and Tag APIs
### GET /recipes
- Purpose: list recipes with pagination/filters.
- Auth: required.

### GET /recipes/search
- Purpose: smart search with ranking.
- Auth: required.
- Query params (common):
  - ingredient
  - prep_under
  - step_count_max
  - cookware
  - difficulty
  - max_calories
  - exclude_allergens
  - max_budget
  - tags
  - rank_mode: popular|time-saving|budget|low-calorie
  - limit
- Success: 200 with recipe list.

### POST /recipes
- Purpose: create recipe.
- Auth: required (write permission).

### GET /tags
- Purpose: list tags.
- Auth: required.

### POST /tags
- Purpose: create tag.
- Auth: required (write permission).

## 4. Booking APIs
### GET /bookings
- Purpose: list bookings with pagination metadata.
- Auth: required.

### POST /bookings
- Purpose: create booking.
- Auth: required.
- Core rules:
  - max 7 days ahead
  - min 2-hour cutoff
  - capacity and scope enforcement
- Success: 201 booking summary.
- Errors:
  - 409 capacity conflict
  - 422 validation rule failures

### GET /bookings/recipe/:recipeId
- Purpose: recipe detail for booking flow.
- Auth: required.
- Errors: 404 not found, 403 out-of-scope.

### GET /bookings/slot-capacity
- Purpose: immediate slot capacity feedback.
- Auth: required.
- Query:
  - pickup_point_id
  - slot_start
  - slot_end

### GET /bookings/today-pickups
- Purpose: today's operational pickup queue.
- Auth: required.

### POST /bookings/check-in
- Purpose: mark arrival/check-in.
- Auth: required (write permission).

### POST /bookings/no-show-sweep
- Purpose: classify overdue no-shows.
- Auth: required (approve permission).

### GET /bookings/:bookingId/dispatch-note
- Purpose: printable dispatch note payload.
- Auth: required.
- Errors: 404 not found, 403 out-of-scope.

### GET /pickup-points
- Purpose: list active pickup points.
- Auth: required.

## 5. Operations APIs
### GET /operations/campaigns
### POST /operations/campaigns
- Purpose: list/create campaigns.
- Auth: required.

### GET /operations/homepage-modules
### POST /operations/homepage-modules
- Purpose: view/update homepage modules.
- Auth: required.

### GET /operations/message-templates
### POST /operations/message-templates
- Purpose: view/save message templates.
- Auth: required.

### GET /operations/dashboard
- Purpose: operational dashboard metrics.
- Auth: required.

## 6. Payment and Reconciliation APIs
### GET /payments
### POST /payments
- Purpose: list/create payments.
- Auth: required.

### POST /payments/gateway/orders
- Purpose: create pending local gateway order.
- Auth: required.

### POST /payments/gateway/callback
- Purpose: gateway callback ingest.
- Auth: public route with signature verification.
- Headers:
  - X-Signature
- Rules:
  - HMAC-SHA256 validation
  - idempotency by transaction_ref

### POST /payments/gateway/auto-cancel
- Purpose: cancel expired pending gateway orders.
- Auth: required.

### POST /payments/reconcile
### POST /payments/reconcile/daily
### POST /payments/reconcile/repair
### POST /payments/reconcile/close
- Purpose: reconciliation lifecycle.
- Auth: required (approve permission for sensitive operations).

### POST /payments/refund
### POST /payments/adjust
- Purpose: fund-related operations.
- Auth: required.
- Rule: requires valid reauth_token.

## 7. Notification APIs
### GET /notifications/events
### POST /notifications/events
- Purpose: event stream create/list.

### POST /notifications/preferences/opt-out
- Purpose: set marketing opt-out for current user.

### POST /notifications/messages
- Purpose: send in-app message.
- Rules:
  - scope validation for non-admin sender
  - marketing cap and quiet-hour controls

### GET /notifications/inbox
- Purpose: current user's inbox.

### POST /notifications/messages/:id/read
### POST /notifications/messages/:id/click
- Purpose: mark read/click for own message only.

### GET /notifications/analytics
- Purpose: sent/read/click analytics (scope-aware).

## 8. File Governance APIs
### GET /files
- Purpose: list files in allowed scope.

### POST /files/upload-base64
- Purpose: upload file content.
- Request:
  - filename
  - mime_type
  - content_base64
  - owner_type (optional)
  - owner_id (optional)
  - watermark (optional)
- Rules:
  - type allowlist
  - max 10MB
  - magic-byte verification
  - SHA-256 fingerprint

### GET /files/:id/signed-url
- Purpose: generate signed download URL (short-lived).

### GET /files/download/:id?token=...
- Purpose: authenticated/signed file fetch.
- Rules:
  - token must match
  - token must be unexpired

### POST /files/cleanup
- Purpose: lifecycle cleanup (180 days).

## 9. Reporting APIs
### GET /reporting/dashboard
- Purpose: KPI dashboard summary.

### GET /reporting/anomalies
- Purpose: anomaly metrics and alerts.

### GET /reporting/exports/bookings-csv
- Purpose: CSV export payload (base64-encoded content).

## 10. Administration APIs
### GET /admin/users
### GET /admin/audit-logs
- Purpose: user and audit visibility.

### POST /admin/reauth
- Purpose: issue one-time reauth token for critical finance actions.

### GET /admin/roles
### POST /admin/roles
### GET /admin/permissions
### GET /admin/resources
### POST /admin/grants
### POST /admin/user-roles
- Purpose: RBAC administration.

### POST /admin/users/:userId/enable
### POST /admin/users/:userId/disable
### POST /admin/users/:userId/reset-password
### POST /admin/users/:userId/scopes
- Purpose: account lifecycle and scope administration.

## 11. Common Status Mapping
- 200: success.
- 201: resource created.
- 401: unauthenticated/invalid token/login failure.
- 403: forbidden by role/scope/object ownership.
- 404: resource does not exist.
- 409: conflict (for example booking slot contention).
- 422: validation or business-rule failure.
