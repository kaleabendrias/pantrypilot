# PantryPilot Prompt Questions and Interpretations

Purpose of this file:
- Record what was unclear in the original prompt.
- Record how each unclear point was interpreted for implementation and documentation.

## 1. Identity and Authentication

### Q1.1 JWT or server session?
- Prompt text: "Identity supports JWT or server sessions"
- Ambiguity: It allows two options but does not force one.
- Interpretation used: Either is acceptable if protected routes require authentication consistently and logout/expiry rules are enforceable.

### Q1.2 Password complexity details beyond minimums?
- Prompt text: "at least 10 characters with one letter and one number"
- Ambiguity: No special character or uppercase/lowercase requirement is specified.
- Interpretation used: Enforce only what is explicitly required (>=10, letter, number).

## 2. Search and Ranking

### Q2.1 Fuzzy matching method is unspecified.
- Prompt text: "minor typos still find matches"
- Ambiguity: No algorithm, distance threshold, or language model is mandated.
- Interpretation used: Any deterministic fuzzy strategy is acceptable if typo tolerance is demonstrable in results.

### Q2.2 Synonym source and governance are unspecified.
- Prompt text: example "garbanzo" -> "chickpea"
- Ambiguity: No requirement for external dictionary vs local curated map.
- Interpretation used: Local synonym table/dictionary is acceptable for offline-first behavior.

### Q2.3 Ranking formulas are unspecified.
- Prompt text: ranked by popular, time-saving, budget, low-calorie
- Ambiguity: Exact scoring math is not provided.
- Interpretation used: Mode-specific deterministic ordering is acceptable (for example sort by relevant fields).

## 3. Booking and Operations

### Q3.1 "Immediate UI feedback" transport is unspecified.
- Prompt text: immediate UI feedback on remaining capacity
- Ambiguity: No requirement for WebSocket/SSE vs request-response polling.
- Interpretation used: Synchronous API capacity checks are acceptable if user gets near-real-time slot availability.

### Q3.2 "Repeated no-shows" threshold/window unspecified.
- Prompt text: repeated no-shows drive automated blacklisting
- Ambiguity: Count threshold and time window are not defined.
- Interpretation used: Define an explicit, documented policy (for example N no-shows within X days) and apply consistently.

### Q3.3 No-show timing baseline detail.
- Prompt text: no-show if not checked in within 15 minutes of slot start
- Ambiguity: Does grace period use exact slot start in store local time only?
- Interpretation used: Use slot_start + 15 minutes in local business timezone as deterministic rule.

## 4. Payment and Finance

### Q4.1 WeChat-compatible emulator scope is unspecified.
- Prompt text: "locally hosted WeChat Pay-compatible gateway emulator"
- Ambiguity: Exact protocol surface (fields, callback format, states) is not fully defined.
- Interpretation used: Implement a local deterministic order/callback contract with signature verification and idempotency by transaction_ref.

### Q4.2 Reconciliation "missed orders" criteria are unspecified.
- Prompt text: flags missed orders and supports abnormal state repair
- Ambiguity: Matching keys and mismatch rules are not fully prescribed.
- Interpretation used: Compare gateway-paid orders against captured payments using stable business identifiers and record mismatches.

### Q4.3 Re-authentication UX flow is unspecified.
- Prompt text: fund-related actions require re-authentication
- Ambiguity: Inline password check vs short-lived secondary token is not mandated.
- Interpretation used: Use a short-lived one-time re-auth token for critical actions.

## 5. Address, Distance, and Dispatch

### Q5.1 Unit ambiguity around service radius example.
- Prompt text: example "8-mile dispatch radius"
- Ambiguity: Example gives miles; implementation/storage unit not explicitly mandated globally.
- Interpretation used: Choose one canonical unit (miles or km), document it clearly, and convert consistently in API/UI.

### Q5.2 Dispatch note format is unspecified.
- Prompt text: route output is a printable dispatch note
- Ambiguity: No strict template/fields are provided.
- Interpretation used: Include booking/customer/pickup essentials in a stable printable payload.

## 6. Notifications

### Q6.1 Quiet-hours timezone is unspecified.
- Prompt text: quiet hours from 9:00 PM-8:00 AM
- Ambiguity: Store-local timezone vs server timezone is not explicit.
- Interpretation used: Enforce quiet hours in store-local business timezone.

### Q6.2 Marketing cap reset boundary unspecified.
- Prompt text: max 2 marketing messages per day
- Ambiguity: "day" boundary definition not explicit (UTC vs local midnight).
- Interpretation used: Use local business-day boundary per store timezone.

## 7. File Governance and Data Protection

### Q7.1 Magic-byte validation strictness by type is unspecified.
- Prompt text: magic-byte checks required
- Ambiguity: Exact signature set and fallback behavior are not given.
- Interpretation used: Enforce known signatures for allowed file types and reject non-matching payloads.

### Q7.2 Signed URL semantics are unspecified.
- Prompt text: short-lived signed URLs (5-minute expiry)
- Ambiguity: Single-use vs multi-use within TTL is not defined.
- Interpretation used: Token is valid until expiry unless explicitly revoked.

### Q7.3 Masking standard for exports/UI is unspecified.
- Prompt text: sensitive fields encrypted at rest and masked in UI and exports
- Ambiguity: Exact masking pattern is not given.
- Interpretation used: Apply deterministic partial masking that preserves usability while hiding sensitive portions.

## 8. Reporting and Alerts

### Q8.1 Alert thresholds are partially unspecified.
- Prompt text: anomaly alerts for oversell, refund-rate spikes, stockout rate
- Ambiguity: No exact numeric thresholds are fully provided.
- Interpretation used: Define configurable thresholds and document defaults.

### Q8.2 CSV schema is unspecified.
- Prompt text: reports export to local CSV
- Ambiguity: Required columns and encoding are not prescribed.
- Interpretation used: Provide stable, documented CSV headers and UTF-8 encoding.

## 9. Final Assumption Summary

Implementation is considered aligned if it:
- Preserves offline-first operation.
- Enforces all explicit hard constraints from the prompt.
- Uses deterministic documented choices where the prompt is intentionally open.
- Keeps security controls strict (auth, RBAC, scope isolation, IDOR prevention, auditable critical actions).

