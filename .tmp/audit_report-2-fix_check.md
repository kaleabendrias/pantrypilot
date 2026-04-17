# Audit Report 2 Fix Check (Static-Only)

Date: 2026-04-17
Reference report: `.tmp/audit_report-2.md`
Method: Static code/documentation re-check only (no runtime, no Docker, no test execution).

## 1. Overall Conclusion
- Result: **Conditionally Passed**
- Interpretation:
  - All previously reported **High/Medium** issues from `.tmp/audit_report-2.md` are now fixed in code/docs.
  - The previous **Low** issue about seeded credentials is **intentionally retained** per reviewer workflow requirement, and is documented in README as requested.

## 2. Issue-by-Issue Fix Status

| Original Issue ID | Original Severity | Current Status | Fix Check Result | Evidence |
|---|---|---|---|---|
| H-1: Potential cross-scope data exposure in notification events | High | Updated | **Fixed** | `repo/backend/app/service/NotificationService.php:44`, `repo/backend/app/service/NotificationService.php:51`, `repo/backend/app/service/NotificationService.php:58`, `repo/backend/app/service/NotificationService.php:65` |
| H-2: Sensitive field encryption lacked authenticity/integrity (CBC without AEAD/MAC) | High | Updated | **Fixed** | `repo/backend/app/service/CryptoService.php:10`, `repo/backend/app/service/CryptoService.php:24`, `repo/backend/app/service/CryptoService.php:30`, `repo/backend/app/service/CryptoService.php:72`, `repo/backend/app/service/CryptoService.php:76` |
| M-1: Critical finance audit events omitted request-origin context | Medium | Updated | **Fixed** | `repo/backend/app/service/PaymentService.php:245`, `repo/backend/app/service/PaymentService.php:262`, `repo/backend/app/service/PaymentService.php:281`, `repo/backend/app/service/PaymentService.php:301`, `repo/backend/app/repository/AdminRepository.php:42` |
| M-2: Migration README stale vs actual migration set | Medium | Updated | **Fixed** | `repo/backend/database/migrations/README.md:11` |
| L-1: Seeded role credentials should be separated/hardened for production | Low | Intentionally retained | **Not fixed by design (accepted exception)** | `repo/README.md:79`, `repo/README.md:85`, `repo/README.md:86` |

## 3. Validation Notes Per Issue

### H-1 Notification scope isolation
- Previous risk: non-admin event queries could include nullable scope rows through permissive fallback logic.
- Current state:
  - Non-admin query path explicitly documents NULL rows as admin-only.
  - Fallback clauses now enforce `whereNotNull(...)` for store/warehouse/department when no explicit scope/user scope exists.
- Static conclusion: the prior nullable-scope leakage branch appears closed.

### H-2 Crypto integrity protection
- Previous risk: AES-CBC-only model allowed confidentiality without built-in authenticity.
- Current state:
  - AEAD path implemented with AES-256-GCM using nonce + auth tag (`g2:` envelope).
  - Decrypt path verifies GCM tag and rejects tampered payloads.
  - Legacy CBC decrypt remains for backward compatibility only.
- Static conclusion: integrity/authenticity gap has been addressed for current encryption path.

### M-1 Finance audit forensic context
- Previous risk: fund-critical audit records lacked request-origin context.
- Current state:
  - Finance critical actions pass `ip`, `userAgent`, `requestId` into audit calls.
  - Audit persistence includes `ip_address` and metadata enrichment hooks.
- Static conclusion: forensic context propagation is materially improved.

### M-2 Migration documentation consistency
- Previous risk: migration README omitted latest schema alignment migration.
- Current state:
  - README now includes `202603270007_complete_schema_alignment.sql` and its purpose.
- Static conclusion: stale migration-doc issue resolved.

### L-1 Seeded credentials policy
- Previous recommendation: separate production-hardening from dev bootstrap due to seeded defaults.
- Current state:
  - Seeded credentials remain documented in README and unchanged.
- Interpretation for this review:
  - This is intentionally kept as-is for reviewer login workflow, per explicit instruction.
  - Treat as accepted operational exception (not as unresolved defect for this cycle).

## 4. Final Fix Judgment
- If judged with strict literal closure of every prior item: **No** (L-1 intentionally retained).
- If judged with approved reviewer exception on seeded credentials: **Yes** (all material High/Medium issues fixed; Low item accepted by design).

## 5. Exception Statement (Requested)
- Seeded passwords are kept and documented in README intentionally for review convenience.
- No recommendation is made here to remove them for this specific review cycle because the requirement explicitly requests they remain as they are.
