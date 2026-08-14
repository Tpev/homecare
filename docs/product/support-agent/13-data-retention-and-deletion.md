# Data Retention, Deletion, and Legal Holds

Status: All initial-build periods accepted; destination implementation and extinction evidence remain open

Established: August 13, 2026

Owner: Security/privacy with product and operations

Required approvers for exact periods: Security/privacy, product, operations, engineering

## Purpose

Define how LoLo minimizes, retains, expires, deletes, and proves deletion of data created or touched by the intelligent support agent. This specification implements `DEC-024` through `DEC-031` and the final downstream/provider limits accepted in `DEC-058`.

This document is not a legal opinion and does not invent a universal retention number. Before production, LoLo must validate the duration matrix against its contracts, jurisdictions, tax/accounting duties, litigation and security obligations, privacy notices, subprocessors, and actual support operating needs.

## Accepted governing rule

1. Keep customer-authored and customer-visible text only as long as a documented operational, contractual, or legal purpose requires it.
2. Keep smaller audit metadata, action receipts, pilot-grant history, and KB version evidence longer only when the separate purpose and period are documented.
3. Do not retain duplicate full-content copies merely because storage is available or the content could someday be useful.
4. Automatically delete expired records and their identifiable derivatives.
5. Show authorized administrators the expected deletion date, completed deletion state, and any active legal/security hold.
6. Apply a hold only to the narrow data and time period it actually covers; record who authorized it and when it must be reviewed or expire.
7. Make backup and provider deletion part of the retention design, not an untracked exception.
8. Keep content-free deletion evidence without retaining the deleted content itself.

## Current LoLo baseline

Repository review on August 13, 2026 found:

- The [LoLo Privacy Policy](../../../resources/legal/privacy-policy.txt) uses a purpose-based rule: personal information is retained as reasonably necessary for platform operation, records, transactions/support, legal and contractual duties, disputes, agreement enforcement, fraud, abuse, and security. It states that periods vary by information type, relationship, obligations, and operational need; it does not set exact periods.
- The [support live-chat specification](../support-live-chat-spec.md) says chat messages use the same retention and deletion rules as support tickets.
- Current support-ticket messages store full message bodies, and ticket activities store actions plus metadata. Message deletion cascades with ticket deletion.
- No automatic support-ticket retention/purge job or exact support-ticket retention schedule was identified in the repository review.
- The Privacy Policy says LoLo does not intend users to submit medical records or other regulated health information unless a specifically approved workflow and safeguards exist; inadvertently submitted information may be permanently deleted.

The accepted `DEC-026` rule closes the support-transcript policy gap, and `DEC-058` closes the remaining agent-specific periods. Production data remains blocked until every actual destination is inventoried, configured, tested, and proven to meet those limits.

## Data architecture principles

### One canonical copy of conversation text

The ordinary support ticket and message history is the canonical customer-visible transcript under `DEC-001`. Agent systems reference message IDs. They do not create a parallel transcript or indefinite copies in:

- Agent-run rows
- Structured event payloads
- Analytics
- Logs and error monitoring
- Handoff summaries
- Search indexes or embeddings
- Test fixtures
- Data exports
- Provider-request archives

Where a temporary copy is technically required for processing, it receives its own short-lived class and automatic deletion. A derived summary is still customer data when it can be linked back to a person or conversation.

### Metadata is not automatically harmless

User IDs, conversation IDs, timestamps, routes, reason codes, grant history, KB use, tool receipts, and hashed arguments can still be personal or sensitive. Each field needs a purpose, access rule, retention class, and deletion/hold behavior. Hashing an identifier does not make it anonymous when LoLo can reconnect it.

### Domain records remain authoritative

The agent does not delete valid care requests, bookings, payment/accounting records, family-account records, or other domain records merely because the conversation or agent event that helped create them expires. Each authoritative domain record follows its own approved legal and operational schedule. Agent events reference domain receipts and later degrade to a content-free marker if the referenced evidence lawfully expires.

### Canonical transcript clock and deletion boundary

Under `DEC-026`, the transcript is retained while the support ticket is open or in progress. Final resolution records an authoritative retention-start timestamp and computes deletion for 12 calendar months later. Reopening before deletion clears that pending date; the next final resolution starts a new 12-month period.

The implementation must use a dedicated, authoritative retention lifecycle rather than assuming every historical `resolved_at` or `closed` state is complete and reliable. The expiry operation removes all conversation-bearing content, including:

- Content-derived subject text
- Initial ticket description
- Public user, AI, and human message bodies
- Private message bodies and ticket-level administrator notes
- Generated handoff summaries
- Search/index documents, embeddings, caches, analytics fragments, diagnostic copies, and exports governed by the transcript class

Deletion does not cascade into valid linked domain records. A minimal tombstone can retain stable IDs, lifecycle timestamps, deletion rule/version, deletion result, and non-content relationship/result references only for the audit period approved later under `RET-DEC-003`.

## Retention-class decision matrix

Every period in this matrix is approved. A missing destination mapping or unenforced deletion path fails closed rather than becoming an engineering default.

| Class | Examples | Canonical location | Accepted treatment | Exact period / trigger |
| --- | --- | --- | --- | --- |
| `RET-CONTENT-001` Canonical support transcript | User and AI public messages, human replies, private support notes | Support tickets/messages | One canonical copy; visible only under support permissions; delete content automatically unless a narrow hold applies | **12 calendar months after the most recent final resolution** under `DEC-026`; reopening resets the clock |
| `RET-TRANSIENT-001` Model input/context copy | Constructed prompt, retrieved snippets, recent transcript context, semantic page state | In-memory/request path and provider processing | Under `DEC-025`, send only what the turn needs and create no LoLo-side persistent copy of the complete assembled request; provider treatment must match contract/configuration | **No LoLo persistence beyond request processing.** Provider extinction remains open under `RET-DEC-005` |
| `RET-TRANSIENT-002` Candidate output before delivery | Unvalidated model output, suppressed reply, intermediate structured result | In memory; short-lived orchestration store only if technically required | Never add to canonical transcript unless delivered; delete immediately after validation, suppression, failure, or reconciliation | **Absolute maximum one hour** under `DEC-058` |
| `RET-EVENT-001` Structured interaction events | Capability route, KB IDs/versions, reason codes, navigation result, policy denial, latency/cost | Agent event store | Store minimized observable metadata and references, never copied message bodies, KB bodies, complete model requests, or chain-of-thought | **24 calendar months after the conversation's most recent final resolution** under `DEC-027`; reopening resets the clock |
| `RET-ACTION-001` Unconfirmed preview content | Rendered preview and reversible proposed-action state | Short-lived preview store | Delete at cancellation, replacement, invalidation, or expiry; do not treat storage time as preview validity | **As soon as invalid; absolute maximum 24 hours after creation** under `DEC-028` |
| `RET-ACTION-002` Confirmed-action evidence | Actor/capability references, preview hash, confirmation, idempotency reference, outcome, authoritative receipt link | Agent evidence plus domain audit/record | Compact proof only; no rendered preview, message text, unrestricted arguments, credentials, or copied domain content | **24 calendar months after authoritative commit** under `DEC-028`; domain record keeps its own schedule |
| `RET-DIAG-001` Detailed diagnostics | Error snapshot, provider trace, safe request fragment | Restricted diagnostic store | Redact at collection; no full transcript, complete prompt, credentials, payment data, or unrestricted tool payloads | **Seven calendar days** under `DEC-058` |
| `RET-GRANT-001` Pilot access history | Grant, scope, start, expiry, revocation, actor, minimized reason | Pilot grant/audit store | Retain while scheduled/active and long enough to prove dependent AI eligibility; no transcript or care content | **24 calendar months after expiry/revocation** under `DEC-029`, extended only while retained evidence depends on it |
| `RET-KB-001` Never-released KB versions | Draft/approved working versions never used in production | Versioned KB store | Permanently deletable through approved admin workflow only when no protected dependency exists | **Immediate on authorized deletion** under `DEC-030` |
| `RET-KB-002` Released KB versions | Released body/facts/exclusions, KB-held source copies, authoring/review notes, compact lifecycle audit | Versioned KB store | Retain while published/paused and for bounded historical evidence; dependency extension only until evidence expires | **36 calendar months after permanent supersession/withdrawal/final retirement** under `DEC-030` |
| `RET-KB-003` Released-version tombstone | Stable IDs, lifecycle timestamps/status, replacement, policy version, deletion result, minimized actor/action references | Versioned KB tombstone store | No answer, facts, source copies, notes, or customer content; stable ID is never reused | **24 additional calendar months after full-version deletion** under `DEC-030`, then delete/de-identify; reserve ID permanently |
| `RET-ADMIN-001` Effective AI control versions | Master, capability, tool, model route, human-only, and other production control states | Immutable/minimized control audit | Retain while effective and long enough to prove dependent interactions; no transcript/KB body copies | **24 calendar months after replacement/deactivation** under `DEC-029`, extended only while retained evidence depends on it |
| `RET-ADMIN-002` Failed/denied/cancelled AI control attempts | Actor, target, attempted scope, reason/result, timestamp | Immutable/minimized control audit | Compact attempt evidence only | **24 calendar months after attempt** under `DEC-029`, extended only while retained incident/hold evidence depends on it |
| `RET-ANALYTICS-001` Product metrics | Cost, latency, completion, error counts | Analytics store | Delete linkable raw analytics after 30 days; retain only de-identified aggregate metrics afterward | **30 days linkable; 24 calendar months de-identified aggregate** under `DEC-058` |
| `RET-EXPORT-001` Manual exports | Approved audit or incident export | Controlled export location | Purpose-bound, encrypted, access-logged, explicit expiry; never an unmanaged retention bypass | **Seven days by default; maximum 30 days** only with documented incident/legal/security/contractual authority under `DEC-058` |
| `RET-BACKUP-001` Backups and replicas | Database backups, snapshots, replicas, caches, indexes | Infrastructure/provider systems | Invalidate caches/indexes immediately; replicas extinguish within 24 hours; backups within 35 days; restored systems reapply deletions before exposure | **24 hours for cache/index/replica extinction; 35 days for backups/snapshots** under `DEC-058` |
| `RET-HOLD-001` Held records | Records under legal, regulatory, contractual, fraud, security, or incident hold | Original store plus hold registry | Suspend only covered deletion; preserve normal access restrictions; review or expire the hold | Set by authorized hold record, not indefinite by default |
| `RET-DELETE-001` Successful deletion evidence | Rule/class, policy version, job/run or operator, timestamps, necessary counts, outcome, restricted request/exception reference | Minimized audit store | Retain no deleted content, prompt, message body, KB/source copy, unrestricted argument, credential, or reversible value | **36 calendar months after successful deletion** under `DEC-031` |
| `RET-DELETE-002` Failed/incomplete deletion evidence | Rule/class, failure/retry state, timestamps, safe destination/result metadata | Minimized audit store | Retain through remediation without copying affected content | **Until resolved, then 36 calendar months** under `DEC-031` |
| `RET-DELETE-003` Hold/exception evidence | Hold/exception reference, scope/class, authority, lifecycle timestamps, release result | Restricted hold/audit store | Retain while active; no covered content in the evidence record | **While active, then 36 calendar months after release** under `DEC-031` |

## Deletion engine requirements

Every persistent record class must support enough state to enforce its rule. The physical schema may vary, but the system must be able to determine:

- Retention class and policy version
- Canonical owner/record and creation timestamp
- Lifecycle trigger such as conversation closure, grant revocation, or incident resolution
- Computed `delete_at` or the reason it cannot yet be computed
- Hold state and authorized hold reference
- Last deletion attempt, result, and next retry
- Derivative, search, analytics, cache, export, provider, and backup destinations that also need extinction

The deletion service must:

1. Run automatically on a defined schedule and be safe to retry.
2. Support a dry-run report before a new or changed policy is activated.
3. Delete or irreversibly de-identify every in-scope derivative, not only the primary row.
4. Preserve referential integrity without preserving customer content; use content-free tombstones where historical linkage is required.
5. Emit content-free counts, policy version, execution time, operator/job identity, failures, retries, and approved exceptions.
6. Alert on overdue deletion, repeated failure, unexplained hold, destination drift, or an inaccessible provider copy.
7. Reapply deletion manifests before a restored backup or replica becomes accessible.
8. Prove that a disabled or removed feature is not continuing to write new data into an expired class.

## Legal and security holds

A hold is an exception workflow, not a retention policy. Creating one requires:

- Stable hold ID
- Authorized requester and approver
- Legal/security/contractual reason category without unnecessary sensitive narrative
- Exact data classes, subjects, conversations, and date scope
- Start time
- Review or expiry date
- Access restrictions
- Release actor, date, and reason

The admin UI shows a non-sensitive hold indicator and review date to authorized users. The deletion engine skips only matching records, reports the skip without copying content, and recomputes deletion immediately when the hold ends. If law or counsel requires an open-ended hold, the record still requires a scheduled review date.

## User deletion and correction requests

Privacy-right requests and account deletion do not bypass authorization or erase records that LoLo is legally required or permitted to retain. The request workflow must:

1. Verify the requester's identity and authority without exposing another person's data.
2. Inventory canonical records and every agent-specific derivative.
3. Apply approved deletion, de-identification, restriction, or documented exception by data class.
4. Prevent a deleted record from reappearing through search indexes, analytics, caches, providers, exports, backups, or restore.
5. Preserve only the minimum evidence needed to prove request handling and lawful exceptions.

Exact response times and jurisdiction-specific rights remain governed by LoLo's approved privacy process and applicable law, not by model output.

## Provider and subprocessor requirements

Before any model, retrieval, logging, analytics, error-monitoring, or backup provider receives production data, LoLo must document:

- Exact fields sent and purpose
- Whether the provider stores inputs, outputs, metadata, abuse-monitoring copies, or backups
- Configurable retention and the selected setting
- Whether customer data is used to train or improve models/services
- Data region and subprocessors where relevant
- Deletion API/process and maximum extinction time
- Incident notification and export/access controls
- Contractual treatment of legal holds and account termination

A provider's default retention is not automatically LoLo's approved retention. If the provider cannot meet the selected class, reduce the data sent, change configuration/provider, or do not release the affected capability.

## Admin experience

Authorized admins can see, where relevant:

- Retention class and policy version
- Lifecycle trigger and expected deletion date
- Whether the record is canonical, derivative, aggregated, or a tombstone
- Active hold and review/expiry date
- Completed deletion marker when historical linkage remains
- Deletion failure or overdue state with an operational action

Normal admins cannot extend retention by editing a deletion date. An authorized policy change creates a versioned retention decision; a valid hold uses the hold workflow. Exports always show their own deletion date.

## Testing and release gates

Production data storage is blocked until every persistent class has an approved period and trigger. Required tests include:

- Boundary tests immediately before and after expiry
- Lifecycle-trigger computation and timezone handling
- Idempotent repeat deletion
- Cascade and derivative deletion across primary store, search, cache, analytics, and provider destinations
- Hold create, narrow match, non-match, review, release, and recomputed deletion
- Backup restore with pre-access re-deletion
- Revoked pilot and withdrawn KB evidence behavior after related content expires
- Admin deletion-date and hold visibility authorization
- No full message text in structured events, analytics, logs, notifications, or content-free deletion evidence
- Failure alerting, retry, reconciliation, and proof that no record becomes silently immortal

Release evidence must include a data-flow inventory, approved matrix, sample dry run, failure/retry exercise, restore exercise, and provider/subprocessor configuration evidence.

## Current gaps and blockers

1. Exact periods are closed by `DEC-058`; each actual provider and destination must still prove it can meet them.
2. Every production provider and downstream destination still needs an extinction inventory and restore/re-deletion exercise before a named-user pilot processes real conversations.
3. LoLo must confirm whether current published privacy notices and contracts need amendment before provider processing or customer-facing AI is released.
4. The Phase 0-1 implementation provides database holds and narrow deletion skipping, but a complete authorized hold-management UI, failure alerting, downstream deletion adapters, and restore reconciliation remain later operational work.

Implemented foundation evidence is recorded in [the Phase 0-1 build record](16-phase-0-1-foundation-build-record.md). These foundations and retention decisions do not authorize model calls or customer-facing AI.

## Retention decision status

No retention-period interview remains open for the approved initial build. Production use still requires destination inventory, provider configuration evidence, automated deletion tests, and restore/re-deletion evidence.

## Accepted retention decisions

| ID | Decision | Accepted |
| --- | --- | --- |
| `RET-DEC-001` / `DEC-025` | LoLo does not persist a duplicate complete model request/prompt/context payload. Customer and delivered assistant text live only in the canonical support transcript; agent evidence stores compact approved metadata and version references. | August 13, 2026 |
| `RET-DEC-002` / `DEC-026` | Retain the unified support transcript while open and for 12 calendar months after its most recent final resolution; reopening resets the clock; expiry deletes conversation content unless a narrow hold applies. | August 13, 2026 |
| `RET-DEC-003A` / `DEC-027` | Retain compact, content-free interaction events for 24 calendar months after the conversation's most recent final resolution; reopening resets the clock. | August 13, 2026 |
| `RET-DEC-003B` / `DEC-028` | Delete unconfirmed preview content as soon as invalid and never later than 24 hours after creation; retain compact confirmed-action evidence for 24 calendar months after commit. | August 13, 2026 |
| `RET-DEC-003C` / `DEC-029` | Retain pilot grants while scheduled/active and AI control versions while effective, then for 24 calendar months; retain failed/denied/cancelled attempts for 24 months; extend only for retained dependencies. | August 13, 2026 |
| `RET-DEC-003D` / `DEC-030` | Keep released KB versions for 36 calendar months after final retirement, extended only for retained dependencies; then retain a content-free tombstone for 24 additional months and permanently reserve the stable ID. | August 13, 2026 |
| `RET-DEC-003E` / `DEC-031` | Retain successful content-free deletion evidence for 36 calendar months; retain failure evidence until resolved plus 36 months and hold/exception evidence while active plus 36 months. | August 13, 2026 |
| `RET-DEC-004` / `DEC-058` | Suppressed output is memory-only where possible and at most one hour; redacted diagnostics retain seven days; linkable analytics 30 days and de-identified aggregates 24 months. | August 14, 2026 |
| `RET-DEC-005` / `DEC-058` | Caches/indexes/replicas extinguish within 24 hours, exports default to seven and never exceed 30 days without documented authority, provider retention is shortest available and at most 30 days with no training, and backups extinguish within 35 days with pre-access re-deletion on restore. | August 14, 2026 |

## Source basis

- [LoLo Privacy Policy](../../../resources/legal/privacy-policy.txt), especially Data Retention, state privacy rights, and healthcare-adjacent information.
- [LoLo support live-chat specification](../support-live-chat-spec.md), including the requirement that chat and support tickets share retention/deletion rules.
- [FTC, Protecting Personal Information: A Guide for Business](https://www.ftc.gov/business-guidance/resources/protecting-personal-information-guide-business), which recommends inventorying personal information, retaining only what the business needs, writing a retention policy, and securely disposing of data no longer needed.
- [NIST Privacy Framework](https://www.nist.gov/privacy-framework/privacy-framework), a voluntary framework for managing privacy risk across the data-processing lifecycle.
