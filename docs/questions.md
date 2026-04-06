# Required Document Description: Business Logic Questions Log

## 1. JWT or server session
Question: Prompt text: "Identity supports JWT or server sessions." The requirement allows two options but does not force one.
My Understanding: Either approach is acceptable as long as protected routes enforce authentication consistently and token/session expiry is enforceable.
Solution: Chose a standards-compliant approach where either JWT-style bearer flow or server session semantics can satisfy the requirement if auth enforcement remains consistent.

## 2. Password complexity details beyond minimums
Question: Prompt text: "at least 10 characters with one letter and one number." It does not define special characters or case rules.
My Understanding: Only explicit constraints should be enforced: length >= 10, includes letter, includes number.
Solution: Implemented validation only for the explicitly required minimum complexity.

## 3. Fuzzy matching method for search
Question: Prompt text: "minor typos still find matches," but no algorithm or threshold is specified.
My Understanding: Any deterministic fuzzy strategy is acceptable if typo tolerance is demonstrable.
Solution: Used deterministic fuzzy matching behavior so typo-tolerant search can be verified.

## 4. Synonym source and governance
Question: Prompt gives example "garbanzo" -> "chickpea" but does not require external dictionary sources.
My Understanding: A local synonym dictionary/table is acceptable and better aligned with offline-first constraints.
Solution: Implemented local synonym mapping for search term normalization.

## 5. Ranking formula definitions
Question: Prompt requires ranking by popular, time-saving, budget, and low-calorie, but no exact scoring model is provided.
My Understanding: Deterministic mode-based ordering is sufficient.
Solution: Implemented mode-specific ranking logic for each required ranking type.

## 6. Immediate UI feedback transport mode
Question: Prompt requires immediate capacity feedback but does not mandate WebSocket/SSE vs request-response.
My Understanding: Synchronous API checks are acceptable if users receive near-real-time availability feedback.
Solution: Used API-driven slot-capacity checks for immediate booking feedback.

## 7. Repeated no-show threshold/window
Question: Prompt says repeated no-shows trigger blacklisting but does not define threshold count or time window.
My Understanding: A clear, documented policy (N no-shows within X days) is required.
Solution: Defined and applied a deterministic no-show threshold/window policy in implementation.

## 8. No-show timing baseline
Question: Prompt defines no-show at 15 minutes after slot start, but timezone baseline is not explicit.
My Understanding: Rule should be evaluated as slot_start + 15 minutes in local business context.
Solution: Applied deterministic timing logic based on slot start plus grace period.

## 9. WeChat-compatible emulator protocol scope
Question: Prompt requires a local WeChat Pay-compatible emulator but does not define complete protocol fields/states.
My Understanding: A deterministic local contract is acceptable if signature verification and idempotency are enforced.
Solution: Implemented local gateway order/callback flow with HMAC verification and idempotency keyed by transaction reference.

## 10. Reconciliation missed-order criteria
Question: Prompt requires flagging missed orders and repair support, but matching rules are not fully specified.
My Understanding: Compare gateway-paid records and captured payments using stable business identifiers.
Solution: Implemented mismatch detection based on stable order/payment linkage and surfaced repair flow.

## 11. Re-authentication UX flow for critical finance actions
Question: Prompt requires re-authentication but does not specify inline password vs secondary token.
My Understanding: A short-lived one-time reauth token is a valid and auditable approach.
Solution: Implemented critical-action reauth using short-lived one-time token flow.

## 12. Service radius unit ambiguity
Question: Prompt example uses "8-mile dispatch radius" but does not globally fix storage unit.
My Understanding: Choose one canonical unit and document conversions consistently.
Solution: Standardized radius computation unit and kept API/UI behavior consistent with chosen unit model.

## 13. Dispatch note payload format
Question: Prompt requires printable dispatch note but does not define exact template fields.
My Understanding: Include essential booking/customer/pickup fields in a stable printable structure.
Solution: Implemented a deterministic printable dispatch-note payload containing core operational fields.

## 14. Quiet-hours timezone interpretation
Question: Prompt says quiet hours are 9:00 PM-8:00 AM without explicit timezone source.
My Understanding: Quiet hours should follow store-local business time.
Solution: Implemented quiet-hours policy using deterministic time-window enforcement strategy aligned to business context.

## 15. Daily marketing cap reset boundary
Question: Prompt requires max 2 marketing messages per day but does not define day boundary (UTC vs local midnight).
My Understanding: Day boundary should follow local business day per store context.
Solution: Implemented daily cap checks using deterministic day-boundary logic.

## 16. Magic-byte validation strictness
Question: Prompt requires magic-byte checks but does not define full signature library or fallback policy.
My Understanding: Enforce known signatures for allowed file types and reject mismatches.
Solution: Implemented strict magic-byte checks for accepted MIME types with reject-on-mismatch behavior.

## 17. Signed URL semantics
Question: Prompt requires 5-minute signed URLs but does not state single-use vs reusable-within-TTL.
My Understanding: Reuse within TTL is acceptable unless explicitly revoked.
Solution: Implemented token validation with strict expiry window and authenticated access controls.

## 18. Sensitive data masking standard
Question: Prompt requires masked UI/exports but does not define exact masking pattern.
My Understanding: Deterministic partial masking should balance usability and data protection.
Solution: Implemented consistent deterministic masking policy for sensitive fields.

## 19. Alert thresholds for anomalies
Question: Prompt requires anomaly alerts but does not define all numerical thresholds.
My Understanding: Thresholds should be configurable with documented defaults.
Solution: Implemented alert generation with configurable threshold behavior.

## 20. CSV export schema
Question: Prompt requires CSV export but does not prescribe exact columns or encoding.
My Understanding: Provide stable header schema and UTF-8 output for compatibility.
Solution: Implemented deterministic CSV export with fixed column headers and standard encoding.

