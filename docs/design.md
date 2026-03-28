# PantryPilot Design Document

## 1. Overview
PantryPilot is an offline-first meal kit booking and recipe operations platform for regional food retail stores. It serves kiosk users (customers) and internal users (store staff, operations managers, finance admins, and system administrators) over a local network.

Primary goals:
- Enable fast recipe discovery with practical filters, synonym support, and typo tolerance.
- Support reliable slot booking with immediate capacity feedback and strict booking constraints.
- Provide operational workflows for pickup handling, no-show classification, and blacklisting.
- Provide finance workflows for local payment capture, reconciliation, and controlled fund actions.
- Enforce strong security controls (auth, authz, scope isolation, IDOR prevention, auditability).

## 2. Users and Roles
- Customer: logs in, searches recipes, books pickup slots.
- Store Staff: monitors today's pickups, checks in arrivals, runs no-show process.
- Operations Manager: configures homepage modules and message templates, tracks operational KPIs.
- Finance Admin: handles payment and reconciliation activities.
- System Administrator: manages users, roles, permissions, and data scopes.

## 3. Functional Scope
### 3.1 Identity and Access
- Username/password authentication.
- Password policy: minimum 10 characters, at least one letter and one number.
- Account lockout: 15 minutes after 5 failed login attempts.
- Authorization model: role-permission-resource.
- Data scope model: store, warehouse, department.

### 3.2 Recipe Discovery
- Search by:
  - ingredient
  - prep time under threshold
  - step count
  - cookware
  - difficulty
  - calorie ceiling
  - allergen exclusion
  - budget ceiling
  - tags
- Ranking modes:
  - popular
  - time-saving
  - budget
  - low-calorie
- Search quality:
  - synonym expansion (for example garbanzo -> chickpea)
  - fuzzy matching for minor typos

### 3.3 Booking and Pickup
- Customer chooses recipe, pickup point, and slot.
- Immediate capacity feedback from slot inventory.
- Constraints:
  - booking allowed only up to 7 days ahead
  - booking cutoff is 2 hours before slot
- Operations:
  - today pickups view
  - staff check-in workflow
  - no-show classification if not checked in within 15 minutes from slot start
  - repeated no-shows trigger temporary blacklisting

### 3.4 Payment and Reconciliation
- Local WeChat-compatible gateway emulator for offline lane.
- Gateway order lifecycle:
  - pending on create
  - auto-cancel after 10 minutes if unpaid
- Callback handling:
  - HMAC-SHA256 signature verification
  - idempotent processing by transaction reference
- Reconciliation:
  - daily mismatch detection
  - issue repair and closure workflows
- Fund-sensitive actions:
  - refund
  - adjustment
  - reconciliation close
  - all require re-auth token
- Audit:
  - tamper-evident hash chain for audit logs

### 3.5 Address and Service Area
- Fully offline validation using local ZIP+4 and admin-region data.
- ZIP+4 and region consistency enforcement.
- Distance enforcement via Haversine formula against pickup/service radius policy.
- Output for routing requirement is printable dispatch note (no external map APIs).

### 3.6 Messaging and Notifications
- In-app message center.
- Marketing controls:
  - opt-out support
  - max 2 marketing messages/day
  - quiet hours 21:00-08:00
- Analytics:
  - read rate
  - click rate

### 3.7 File Governance
- Local disk storage.
- Upload controls:
  - MIME/type allowlist
  - max size 10 MB
  - magic-byte validation
- Integrity controls:
  - SHA-256 fingerprint on file content
- Optional image watermarking.
- Downloads:
  - signed URL
  - 5-minute expiry
  - hotlink token validation
- Lifecycle:
  - cleanup after 180 days

### 3.8 Reporting
- Dashboard KPIs:
  - booking conversion
  - slot utilization
  - no-show rate
  - payment success rate
- CSV export of booking data.
- In-console anomaly alerts:
  - oversell
  - refund-rate spikes
  - stockout rate

## 4. Non-Functional Requirements
- Offline-first operation on local network.
- Deterministic startup and testing via containerized stack.
- Scope-aware data isolation across all read and write paths.
- Consistent JSON API contract and stable status semantics.

## 5. High-Level Architecture
- Frontend: Layui-based web client.
- Backend: ThinkPHP REST-style API.
- Storage: MySQL.
- Main layers:
  - Controller layer for HTTP boundaries.
  - Service layer for business orchestration.
  - Domain policies for invariant rules.
  - Repository layer for persistence and scoped querying.
  - Infrastructure adapters for filesystem, time, notifications.

## 6. Data Domains
- Identity: users, roles, permissions, resources, user roles, data scopes, sessions.
- Recipes: recipes, ingredients, cookware, allergens, tags, synonym dictionary.
- Bookings: bookings, pickup points, pickup slots, dispatch notes, blacklist.
- Finance: payments, gateway orders/callbacks, reconciliation entities, adjustments, re-auth tokens.
- Messaging: message events, inbox/messages, preferences, templates.
- Governance/Audit: attachments metadata, audit logs with chained hashes.

## 7. Security Model
- Authentication:
  - bearer token for protected routes
  - route allowlist for public endpoints only
- Authorization:
  - route-level RBAC checks by resource and permission
- Object-level controls:
  - ownership/scope checks for recipe detail, booking resources, files, and messages
- Data protection:
  - sensitive field encryption at rest
  - masked output in UI/export contexts
- Financial controls:
  - one-time re-auth token flow for critical actions
  - immutable-like chained audit evidence

## 8. Deployment Model
- Local containerized deployment with API, web, and database services.
- No external dependency required for critical business flows.
- Target usage:
  - kiosk mode in-store
  - local Wi-Fi portal for customers

## 9. Risks and Mitigations
- Risk: callback replay/duplication.
  - Mitigation: idempotency key by transaction reference and unique constraint.
- Risk: cross-scope data leakage.
  - Mitigation: centralized scope propagation and scoped repository filters.
- Risk: stale/missing files in cleanup.
  - Mitigation: resilient cleanup with explicit missing-file handling.
- Risk: abuse of finance operations.
  - Mitigation: re-auth + auditable hash chain.

## 10. Acceptance Alignment Summary
This design directly aligns with the provided PantryPilot prompt and organizes requirements into production-style modules suitable for implementation and audit.
