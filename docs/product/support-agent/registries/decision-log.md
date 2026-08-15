# Decision Log

Status: Active registry

Last updated: August 14, 2026

Owner: Product

## Decision format

Statuses: Proposed, Accepted, Rejected, Superseded.

Material decisions require a date, owner, approvers, rationale, affected capability IDs, and links to resulting changes.

## Decisions

### `DEC-001` — Canonical human-support record

- Status: Accepted from existing support product specification
- Date: August 13, 2026 documentation baseline
- Decision: The existing support ticket and message history remain the canonical user-visible conversation and human-support workflow.
- Rationale: One history, permissions model, admin queue, unread model, and operational process.
- Affects: All support-agent capabilities
- Source: [Support live-chat specification](../../support-live-chat-spec.md)

### `DEC-002` — Model authority boundary

- Status: Accepted
- Date: August 13, 2026
- Decision owner: Product
- Decision: The model interprets language and proposes bounded actions. Server-side policy, validation, confirmation, and domain services decide and perform actions.
- Rationale: Preserves authorization, determinism, and auditability.
- Affects: All Classes B-D

### `DEC-003` — Semantic navigation only

- Status: Accepted
- Date: August 13, 2026
- Decision owner: Product
- Decision: User navigation uses approved route and semantic-target IDs. Arbitrary DOM selectors, visual coordinates, and free-form computer control are prohibited.
- Rationale: Stable, testable navigation that survives layout changes and respects roles.
- Affects: All `NAV-*`

### `DEC-004` — Class D confirmation

- Status: Accepted
- Date: August 13, 2026
- Decision owner: Product
- Decision: Material actions require a deterministic preview and action-specific server-bound confirmation. Conversational assent alone is not the initial confirmation mechanism.
- Rationale: Older users must understand exactly what will happen; the model cannot infer consent.
- Affects: `CARE-REQUEST-003` and future Class D capabilities

### `DEC-005` — Remove the legacy AI care-request copilot

- Status: Accepted
- Date: August 13, 2026
- Decision owner: Product
- Decision: The existing AI care-request copilot is legacy and will be removed. It will not be migrated, integrated, wrapped, or reused as the new support agent. Historical session/message data is permanently deleted under `DEC-011` before legacy schema removal completes.
- Rationale: The new intelligent support experience requires one governed architecture and must not inherit a parallel legacy conversation, prompt, safety, or action system.
- Affects: Phase 0, `CARE-REQUEST-001` through `CARE-REQUEST-003`, legacy `AiCopilot` routes/components/services/models/configuration/tests

### `DEC-006` — Runtime model selection

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Use the least costly runtime configuration that passes every applicable safety, accuracy, capability, latency, and elder-usability release gate. Cost is the optimization priority only after those minimum gates pass. GPT-5.6 Sol may be used for implementation, review, challenger evaluation, or difficult runtime classes, but it is not the assumed customer-facing default. A more expensive model, reasoning setting, or route requires a measured and material gain on representative evaluations for the affected capability. Model or routing changes require regression evaluation before release.
- Rationale: The support agent is expected to operate at high volume and low cost without buying unnecessary model capacity or weakening required user protections.
- Affects: Agent platform, evaluation suite, release gates, routing, observability, and cost controls

### `DEC-007` — Support transcripts are discovery inputs

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Historical and live support conversations may be automatically categorized, clustered, and sampled to identify repeated questions, confusing experiences, KB gaps, and evaluation cases. Transcript content and prior support answers cannot become approved product truth automatically. Before publication, a proposed KB change requires verification by an authorized KB operator against an authoritative source, applicable risk checks, versioning, and a corresponding regression evaluation. Under `DEC-022`, that operator may be the author and no second person is mandatory. Sensitive transcript data must be minimized; transcript access and review actions must be restricted and auditable. Medical, safety, legal, payment, identity, and account-permission instructions may never be learned directly from transcripts.
- Rationale: Transcripts are valuable evidence of user needs but may be incomplete, outdated, context-specific, or incorrect. Automated discovery and risk-weighted sampling control operating cost; authoritative human approval prevents errors from contaminating the KB.
- Affects: KB governance, evaluation program, transcript analytics, privacy controls, and support operations

### `DEC-008` — Transfer to human stops automation

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: A user-requested or policy-triggered transfer atomically changes the conversation from automated support to human-only support. The one-time deterministic transfer confirmation is the final automated public message. The agent must not provide queue position, queue status, wait-time updates, later acknowledgments, material guidance, or actions after transfer. Internal unassigned and staff-assigned states may exist for operations, but they do not change the user-facing human-only ownership. Returning the conversation to automation requires deliberate administrator action, an audit event, and clear user-visible notice.
- Rationale: Users should experience a simple transfer to a person, not a mixed AI/human queue. Stopping at transfer prevents in-flight or later automated replies from colliding with human support.
- Affects: `SUP-HANDOFF-001`, conversation ownership, admin assignment, evaluation, observability, and user-facing handoff language

### `DEC-009` — Initial intelligent user scope

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Intelligent support must serve both family/care-receiver users and caregivers through separate role-aware knowledge, navigation, tools, permissions, playbooks, and evaluations. Build the shared conversation, role-resolution, KB, safety, handoff, observability, and evaluation foundation for both tracks from the start. Release family/care-receiver capabilities first; release caregiver approved answers and semantic navigation next; then release caregiver operational capabilities individually after their own specification, authorization review, evaluation, and limited rollout. Sequencing does not remove caregivers from program scope.
- Rationale: The audiences have different goals and authority and must never receive each other's capabilities or data. Sequencing reduces initial implementation and evaluation scope while preserving a shared platform and an explicit caregiver commitment.
- Affects: Rollout sequence, role/cohort controls, KB and evaluation structure, and all role-specific capabilities

### `DEC-010` — One-time request before broader workflows

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The first Class C/D workflow is creation of a one-time non-medical care request. The agent collects one detail at a time and prepares a reversible draft. The server validates the draft and produces a deterministic preview. Publication requires the user's action-specific, server-bound **Create request** confirmation, an authorized idempotent server commit, and an authoritative receipt before success is shown. This is a new implementation; the legacy care-request copilot, prompts, sessions, responders, publication path, and quality logic confer no approval and must not be reused. Regular care, caregiver hiring, cancellation, payment, timesheet approval, caregiver operational actions, and family access do not inherit this approval.
- Rationale: A one-time non-medical request is a bounded, high-value workflow with clear domain fields and a reviewable transition from reversible draft to confirmed execution.
- Affects: `CARE-REQUEST-001` through `CARE-REQUEST-003`, Phases 5 and 6, confirmation contracts, and legacy removal

### `DEC-011` — Destroy historical legacy-copilot data

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Permanently delete all historical `AiRequestSession` and `AiRequestMessage` records and all identifiable or content-bearing derivatives of those records. Do not migrate, export for future use, copy into the new support conversation, use as KB truth, preserve as an evaluation dataset, or retain as a rollback source. Delete active-database rows, replicas, caches, search or analytics copies, generated summaries or embeddings, and production-derived fixtures identified by the destruction inventory. Remove the legacy tables through new follow-up migrations after writes are disabled; do not rewrite deployed migration history. Existing `CareRequest` domain records that were validly published by the legacy flow are not legacy chat data and must not be cascade-deleted. Existing support tickets and human-support messages are also out of scope. Retain only a content-free destruction audit containing environment, execution time, code/migration version, operator, counts, verification result, and approved exceptions. Pre-existing backups must be handled by approved backup-destruction or time-bounded expiration controls, and any restore must reapply the deletion before the restored environment becomes accessible.
- Rationale: The legacy conversation data has no approved purpose in the new support architecture and creates unnecessary privacy, security, and implementation risk. Controlled deletion prevents accidental loss of ordinary care records and produces evidence that the destruction occurred.
- Affects: Phase 0, `ai_request_sessions`, `ai_request_messages`, replicas and derived stores, backup/restore procedures, privacy review, and legacy schema removal
- Execution contract: [Legacy copilot retirement and data destruction](../10-legacy-copilot-retirement-and-data-destruction.md)

### `DEC-017` — AI is invisible and inactive by default

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: New AI support behavior is deny-by-default in every live environment. A live user without an active pilot grant must see the existing human-support experience with no AI entry point, label, greeting, reply, suggestion, navigation, draft, or action. The server must not make a customer-facing model call for that user. Deployment or missing configuration must resolve to AI off. `DEC-047` later rejects invisible production-conversation shadowing entirely.
- Rationale: Development and deployment must not accidentally expose an unfinished assistant to live users.
- Affects: UI rendering, endpoints, orchestration, production configuration, feature controls, testing, and rollout

### `DEC-018` — Named per-user pilot grants

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Authorized administrators can enable or disable the AI assistant for one exact user in the admin UI. Eligibility is enforced server-side on every AI turn and action and requires all applicable global, user-visible, capability, role, and user-grant controls to pass. A grant is not inherited by other family/account members and cannot bypass authorization or capability release state. The grant records scope, start and optional expiry, reason, creator, and revocation history. Revocation is immediate, suppresses in-flight automated delivery, and preserves the ordinary human-support conversation. Pilot access uses named grants only; percentage or broad cohort enablement requires a later release decision.
- Rationale: The pilot needs precise exposure, immediate rollback, and an auditable answer to exactly which live users could access AI.
- Affects: Admin user management, eligibility policy, feature flags, events, support chat, and pilot reporting

### `DEC-019` — Governed KB admin workspace

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The admin UI provides an authorized workspace to list and search every KB entry and version; create entries as drafts; edit drafts or create a new version from released content; validate, review, approve, publish, pause, supersede, and delete entries; inspect sources, applicability, dependencies, evaluations, version history, and usage; and open the exact KB versions used from a conversation timeline. Drafts may be permanently deleted when they were never released and have no protected dependency. Deleting any approved or published entry must withdraw it from retrieval immediately and preserve an auditable tombstone/version history; normal deletion cannot silently erase released product truth or interaction evidence. No model-generated or transcript-derived content can publish automatically.
- Rationale: Product and support need direct operational control of product truth without editing code, while versioning and approval prevent unreviewed changes from reaching users.
- Affects: Admin authorization, KB storage and lifecycle, retrieval snapshots, audit events, evaluation, and support operations
- Implementation specification: [Admin control plane, pilot access, and KB workspace](../11-admin-control-plane-and-pilot.md)

### `DEC-020` — Full administrators manage pilot grants initially

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: During the initial pilot, only existing full administrators may create, schedule, or revoke per-user AI grants. Support-inbox access, content roles, KB permissions, or other delegated admin access do not confer pilot-management authority. Every grant mutation still requires server-side authorization, explicit confirmation, a reason, and an immutable audit event. A delegated pilot-manager role may be considered only after the initial pilot demonstrates reliable authorization and audit operations through a separate product decision.
- Rationale: Live-user AI exposure is a high-impact production control. Restricting it to the smallest established administrative role reduces accidental enablement while the workflow is new.
- Affects: `ADM-AI-PILOT-001`, admin policies, user-profile controls, pilot-user list, audit, and release testing

### `DEC-021` — Pilot grants default to 14-day expiry

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: A new per-user pilot grant defaults to expiring 14 days after activation. A full administrator may select another expiry. **No expiry** is permitted only through an explicit acknowledgment that the grant remains active until manually revoked. Expiration immediately makes the user AI-ineligible without deleting the support conversation, prior AI interaction evidence, or grant audit. Continued access after expiry requires a new audited grant; administrators do not rewrite the prior grant's historical period.
- Rationale: Short default grants reduce forgotten exposure while preserving flexibility for longer pilots. Append-only renewal keeps the eligibility history accurate.
- Affects: `ADM-AI-PILOT-001`, grant persistence, enable dialog, scheduled expiration, notifications, audit, and tests

### `DEC-022` — Either KB operator may publish alone

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: LoLo's two designated KB operators must each be able to complete the entire KB lifecycle alone: create, edit, validate, self-review, approve, publish, pause, supersede, withdraw, and delete within the accepted deletion rules. The author may approve and publish their own entry. There is no mandatory second-person, independent-review, or separation-of-duties gate, including for sensitive KB entries, unless an external legal requirement specifically demands it. Both operators receive the complete KB permission set. Publication still requires authoritative sources, applicability metadata, automated validation, required evaluation evidence, versioning, explicit confirmation, and an immutable audit event.
- Rationale: LoLo is a two-person operating team and cannot make routine KB availability depend on both people while still needing either person to manage product truth safely and promptly.
- Affects: `ADM-KB-001`, KB permissions, review/publish workflow, sensitive content, UX copy, tests, and audit

### `DEC-023` — First KB release publishes immediately only

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The first KB admin release supports manual **Publish now** only. It does not support scheduled publication, future activation jobs, or a scheduled lifecycle state. A version with a future effective date cannot be published; it remains a draft or approved working version until a designated KB operator publishes it at the intended time. Review-by and expiration dates remain supported. Scheduling may be added later through a separate product decision if an observed operational need justifies it.
- Rationale: Immediate manual publication is sufficient for a two-person team and avoids scheduler, timezone, failure, cancellation, and stale-approval complexity in the first release.
- Affects: `ADM-KB-001`, KB lifecycle, editor, validation, jobs, tests, and UX copy

### `DEC-024` — Minimum retention by purpose and data class

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: LoLo retains customer-authored and customer-visible conversation text only for the shortest documented operational, contractual, or legal period that applies. Compact audit metadata, action receipts, pilot-grant history, KB version history, and other evidence may be retained longer only when each data class has a distinct documented purpose and approved period. The agent must not create indefinite duplicate copies of canonical support content in prompts, provider payload archives, analytics, events, summaries, embeddings, or debugging stores. Expired records and identifiable derivatives are deleted automatically. Authorized administrators can see expected deletion dates and any narrowly scoped legal or security hold. A hold records its reason category, authority, scope, owner, start, and review/expiry date; it suspends only affected deletion and cannot become a silent indefinite exception. Backup and provider copies follow documented extinction timelines, and a restore must reapply prior deletions before becoming accessible. Deletion leaves only content-free evidence sufficient to prove what class was deleted, when, by which rule/job, the count/result, and any approved exception.
- Rationale: The support agent needs enough evidence to operate safely and reconstruct material actions, but retaining full customer text or duplicate model context indefinitely increases privacy, security, compliance, and storage risk without proportional benefit.
- Affects: Support transcripts, model context, provider configuration, structured events, action receipts, pilot grants, KB versions, admin audit, analytics, exports, backups, legal holds, deletion jobs, and admin UX
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-025` — Do not persist duplicate complete model requests

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: LoLo does not persist a duplicate complete assembled model request or raw prompt/context payload. The customer-authored and delivered assistant messages remain in the canonical support transcript under its approved retention rule. The request processor may assemble the minimum required instructions, recent message context, retrieved KB content, semantic page context, and tool schemas in memory and transmit them to an approved model provider, but LoLo does not archive that complete payload in agent runs, events, logs, analytics, error monitoring, summaries, embeddings, exports, or fixtures. LoLo retains only approved compact evidence: canonical message references, model/configuration and prompt/schema versions, KB IDs/versions, capability and reason codes, safe tool references/results, latency, tokens, cost, confirmation references, and authoritative receipts. Provider-side handling remains subject to a separately approved provider configuration and extinction decision.
- Rationale: The canonical transcript plus immutable version references provide administrator visibility and reproducible evidence without creating another sensitive full-content store. This reduces privacy, breach, storage, and deletion complexity.
- Affects: Agent request assembly, agent runs/events, support evidence UI, logs, diagnostics, analytics, providers, testing, exports, and retention controls
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-026` — Delete canonical support transcript content 12 months after final resolution

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The complete canonical support conversation is retained while its ticket is open or in progress and for 12 calendar months after its most recent final resolution. This applies to the unified human-and-AI support transcript, not only automated messages. The retention clock begins from an authoritative `retention_started_at` recorded when the ticket enters its final resolved/closed state; implementation must not infer the clock from a nullable or historically ambiguous timestamp. Reopening the conversation before deletion clears the pending deletion date, and the next final resolution starts a new 12-month period. At expiry, LoLo automatically deletes all conversation-bearing content, including the subject when content-derived, initial description, public and private message bodies, ticket-level private notes, generated handoff summaries, and identifiable content derivatives. A narrow authorized legal/security hold suspends deletion only for covered records. Valid care requests, bookings, payments, account records, and other linked domain records are not deleted or shortened by this rule. A minimal content-free ticket/deletion tombstone may remain only under the separately approved audit-retention period.
- Rationale: Twelve months gives LoLo's two-person team a bounded period for recurring support issues, delayed disputes, pilot review, and user follow-up without keeping sensitive family/caregiver conversations indefinitely.
- Affects: All canonical support tickets/messages, AI and human support, private notes, handoff summaries, search/index/analytics derivatives, admin deletion dates, deletion jobs, privacy requests, and holds
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-027` — Retain compact interaction events for 24 months

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Minimized structured interaction events are retained for 24 calendar months after the support conversation's most recent final resolution, then automatically deleted or irreversibly de-identified. Reopening the conversation before expiry clears and later restarts the event-retention clock together with the canonical conversation lifecycle. These events may contain stable conversation/message references, capability and reason codes, model/configuration and prompt/schema versions, KB IDs/versions, safe navigation/tool/result references, responder and transfer states, tokens, latency, cost, and validation outcomes. They may not contain customer or administrator message text, copied KB bodies, complete assembled model requests, private chain-of-thought, secrets, unrestricted tool arguments, or content-bearing summaries. After the transcript expires at 12 months, these events may reference only its content-free tombstone. A narrow authorized legal/security hold may suspend deletion for covered events.
- Rationale: Twenty-four months provides a bounded window for safety investigation, pilot comparison, reliability and cost analysis, and detection of slower failure patterns while keeping the higher-risk conversation text for only 12 months. The class is compact and content-free by design.
- Affects: Agent event schema, conversation lifecycle, event deletion jobs, analytics pipelines, admin AI evidence, holds, incident review, and privacy testing
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-028` — Short-lived previews; 24-month confirmed-action evidence

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Unconfirmed action-preview content is deleted as soon as the preview is cancelled, replaced, invalidated, or expires and in every case no later than 24 hours after creation. The 24-hour storage ceiling does not define preview validity; each capability sets a shorter safety-validity window as appropriate. If no authoritative commit occurs, no confirmation or success evidence may be created. For an action that is validly confirmed and committed, LoLo retains only compact confirmation and receipt evidence for 24 calendar months after the authoritative commit timestamp, then automatically deletes or irreversibly de-identifies it unless a narrow authorized hold applies. Compact evidence may include actor and capability references, preview/argument hash, confirmation timestamp and action, idempotency reference, policy result, authoritative domain-record/receipt reference, and outcome code. It may not contain the rendered preview body, customer message text, unrestricted tool arguments, secrets, payment credentials, or copied domain-record content. The authoritative care request, booking, payment/accounting record, or other domain record follows its own approved retention policy.
- Rationale: Abandoned preview content has little continuing value and may contain sensitive details, while compact proof of a confirmed material action supports disputes, incident review, and authorization verification for a bounded period without duplicating the authoritative domain record.
- Affects: Class D preview storage, confirmation bindings, tool receipts, agent events, deletion jobs, admin evidence, holds, and capability tests
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-029` — Retain pilot-grant and admin-control history for 24 months

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: A pilot-grant record is retained while scheduled or active and for 24 calendar months after expiry or revocation. A successful master-switch, capability, tool, model-routing, human-only, or other AI control-state version is retained while effective and for 24 calendar months after it is replaced or deactivated. Failed, denied, or cancelled administrative control attempts are retained for 24 calendar months after the attempt. No grant or control record may be deleted while retained interaction, action, incident, or legal/security-hold evidence still depends on it; dependency expiry extends retention only until the dependent record may be lawfully deleted. Records contain only compact actor, target, scope, prior/effective state, timestamps, result, policy/configuration version, and a minimized non-sensitive reason. They may not contain transcript text, copied KB bodies, care details, complete model requests, or secrets. Expiry causes automatic deletion or irreversible de-identification unless a narrow authorized hold applies.
- Rationale: This provides a bounded proof of exactly who was AI-eligible and which production controls applied to every retained interaction while preventing grant reasons and control audit from becoming another customer-content store.
- Affects: Pilot grants, control-plane versioning and audit, eligibility evidence, admin activity, dependency tracking, deletion jobs, holds, and incident review
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-030` — Bounded retention for released KB versions and tombstones

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: A KB version that has never been released and has no protected dependency may be permanently deleted immediately through the approved admin workflow. A released version, its KB-held source snapshots/quotations, and its compact lifecycle audit are retained while the version is published or paused and for 36 calendar months after permanent supersession, withdrawal, or other final retirement. They may not be deleted while retained interaction, action, incident, evaluation, or legal/security-hold evidence depends on the exact version; such a dependency extends full-version retention only until that evidence expires. After the full-version period, LoLo deletes the answer/body, facts, exclusions, source copies, authoring/review notes, and other content-bearing fields and retains a content-free tombstone for 24 additional calendar months. The tombstone may contain only stable KB/version IDs, lifecycle timestamps/status, replacement reference, policy version, deletion result, and minimized actor/action references. After that period, the tombstone is automatically deleted or irreversibly de-identified. The stable KB ID remains permanently reserved and can never be reassigned, but the reservation contains no KB or customer content. External authoritative source records follow their own approved retention; this rule governs copies held within the KB.
- Rationale: A three-year full history supports product-truth investigation, correction comparison, and retained interaction evidence. A further two-year content-free tombstone prevents silent disappearance during the residual audit window, while eventual deletion avoids preserving every historical answer forever.
- Affects: KB versions, sources, lifecycle audit, published/paused/superseded/withdrawn states, deletion, dependency tracking, historical conversation evidence, stable-ID registry, and admin UX
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-031` — Retain content-free deletion evidence for 36 months

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Evidence of a successful automated or manual deletion is retained for 36 calendar months after deletion completes. Evidence of a failed, partial, or overdue deletion remains until the failure is fully resolved and for 36 calendar months thereafter. Evidence of a legal/security hold or other authorized deletion exception remains while the exception is active and for 36 calendar months after release. The evidence contains only data class and retention-policy version, lifecycle/deletion timestamps, environment, job/run or authorized operator, aggregate or necessary scoped counts, result and retry state, and restricted deletion-request/hold/exception references. It may not contain deleted messages, KB content, prompts, model payloads, private notes, unrestricted tool arguments, credentials, source copies, or any reversible copy of deleted content. Direct identifiers are excluded unless strictly required to prove a specific privacy request and then remain restricted to that request record.
- Rationale: A three-year content-free record gives LoLo evidence that scheduled deletion, exception handling, and failure remediation occurred and helps detect restore or destination drift without defeating the deletion itself.
- Affects: Deletion jobs, privacy requests, holds, exception workflows, backup/restore verification, admin activity, incident response, and audit access
- Detailed specification: [Data retention, deletion, and legal holds](../13-data-retention-and-deletion.md)

### `DEC-012` — Initial evaluated runtime model/configuration

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Engineering and product
- Decision: Accept `gpt-5.6-luna` at low reasoning as the initial offline runtime baseline and retain `gpt-5.4-mini-2026-03-17` at low reasoning as the measured challenger. On the identical frozen-v4 schedule, each completed 278 of 278 calls, had zero hard failures and zero critical hard failures, passed every critical case across five runs, and achieved 99.64% deterministic quality. Luna cost $0.06563460 versus Mini's $0.40898655, so Luna wins the approved lowest-cost-passing rule. The deprecated `gpt-5-nano-2025-08-07` benchmark was excluded after repeated provider output failures and remains baseline-ineligible. Exact commit, versions, checksums, latency, token use, and content-minimized report evidence are recorded in the Phase 1B execution record.
- Non-authority: This decision selects an offline baseline only. It does not publish KB content, enable production model calls, process production transcripts, enable semantic navigation, create a pilot grant, or expose AI to a user.
- Evidence: [Phase 1B offline model evaluation adapter and execution record](../21-phase-1b-offline-model-evaluation.md)
- Affects: `EVAL-AIS-001`, prompt/model configuration, cost baseline, Phase 1 exit evidence, and later staff-account/pilot readiness

### `DEC-013` — First one-time request workflow may progress to confirmed publication

- Status: Accepted scope; original one-time and shadow sequencing superseded by `DEC-047`, `DEC-048`, and `DEC-063`
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The original one-time non-medical workflow approved reversible Class C draft assistance and separately gated Class D publication after deterministic preview, action-specific confirmation, authorization, idempotency, receipt, safety, usability, limited-cohort, and rollback evidence. `DEC-048` expands current authority to one-time and recurring; `DEC-047` removes shadow; `CARE-REQUEST-005` through `CARE-REQUEST-007` supersede the original capability specifications. Publication remains independently disabled until explicit release approval.
- Rationale: `DEC-010` already approved the bounded draft-to-confirmed-publication architecture. Separate phase and release gates preserve a safe draft-first rollout without leaving the product decision ambiguous.
- Affects: `CARE-REQUEST-001` through `CARE-REQUEST-003`, Phases 5 and 6, capability flags, confirmation, evaluation, and release approval

### `DEC-014` — Production-data transient and downstream extinction limits

- Status: Accepted through `DEC-058`
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Apply the exact transient, diagnostic, analytics, export, provider, cache/index/replica, and backup periods recorded in `DEC-058` and the approved build contract. A provider or destination that cannot meet its maximum is not eligible for production data.
- Affects: Production providers and destinations, deletion/restore operations, privacy evidence, and release gates

### `DEC-015` — Human ownership and response promise

- Status: Accepted through `DEC-057`
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Both authorized administrators are alerted to a transferred conversation and either may claim it. The user is told only that LoLo Support will reply in the chat as soon as it can. Do not promise a queue position, queue status, business hours, wait time, or response SLO.
- Affects: `SUP-HANDOFF-001`, `CARE-24H-001`, Admin alerting/claiming, user copy, and pilot operations

### `DEC-016` — English-only intelligent support

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: LoLo's intelligent support agent supports English only across offline evaluation, staff-account testing, pilot, and any later released phase. It does not translate or answer in another language. When the user writes in a language the agent cannot safely treat as English, automation uses approved simple English wording to explain the limitation and offer transfer to human support; it does not promise that the human will respond in another language. Adding another intelligent-support language would require this decision to be superseded, authoritative language-specific sources, a governed translated KB, dedicated evaluations, usability/accessibility evidence, and a separate release approval.
- Rationale: A single supported language keeps product truth, evaluations, elder usability, safety behavior, and operating cost bounded and avoids giving users false confidence in unverified translation.
- Affects: All support-agent capabilities, KB applicability, evaluation corpora, prompts, unsupported-language handling, human handoff, analytics, and release gates

### `DEC-032` — First Family answer and semantic-navigation scope

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The first Family/care-receiver intelligent-support package is limited to English read-only answers and semantic navigation for six areas: human help and emergency/non-medical limitations through `support.center`; Family dashboard orientation through `family.dashboard`; existing care-request basics and status through `family.care_requests`; navigation to the normal manual new-request form through `family.new_care_request`; Family Account roles/access through `family.access`; and basic account/profile orientation through `account.profile`. The assistant may explain and offer an approved **Take me there** action only after the applicable answer and navigation phases pass their separate gates. It may not draft or submit a care request, send a message, change a profile or permission, book care, affect payment, or perform any other domain write under this approval.
- Rationale: These topics address common orientation and support needs using already registered role-aware destinations while keeping the first package reversible, observable, and independent from material-action workflows.
- Affects: `SCOPE-AIS-001`, initial Family KB entries, evaluation corpus, semantic target registry use, Phases 3 and 4, and named Family pilot scope

### `DEC-033` — First Caregiver answer and semantic-navigation scope

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The first Caregiver intelligent-support package is limited to English read-only answers and semantic navigation for five areas: human help and emergency/non-medical limitations through `support.center`; Caregiver dashboard and onboarding/status orientation through `caregiver.dashboard`; available work/request orientation through `caregiver.work_inbox`; upcoming, active, and completed shift orientation through `caregiver.shifts`; and basic account/profile orientation through `account.profile`. The assistant may explain and offer an approved **Take me there** action only after the applicable answer and navigation phases pass their separate gates. It may not apply for or accept work, start/end/edit a shift, record or approve time, affect payouts, decide identity or credential verification, change services/rates/availability/profile data, send a message, create a booking, or perform any other domain write under this approval.
- Rationale: This gives caregivers useful orientation across their core existing surfaces while preserving the same reversible, role-isolated, read-only starting boundary approved for Family users.
- Affects: `SCOPE-AIS-002`, initial Caregiver KB entries, evaluation corpus, semantic target registry use, Phases 3 and 4, and named Caregiver pilot scope

### `DEC-034` — First governed KB and evaluation pack structure

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The first governed English KB pack contains 12 individually governed entries: three shared support/safety entries, five Family orientation entries, and four Caregiver orientation entries. Every entry requires at least five linked evaluation cases before publication: correct answer, boundary/refusal, wrong-role isolation, unsupported-state behavior, and human handoff. This creates a minimum initial baseline of 60 entry-level evaluation cases. Pack-level approval authorizes the inventory and authoring order only; it does not approve an entry's answer, sources, applicability, publication, model use, navigation release, or user-visible behavior. Each entry remains draft until its facts, exclusions, sources, role/state scope, semantic targets, and evaluation IDs are reviewed and approved through the governed KB lifecycle.
- Rationale: A small explicit inventory prevents an unbounded KB launch, keeps sources and tests traceable, and makes Family/Caregiver separation and negative behavior first-class rather than relying only on happy-path answers.
- Affects: `KB-AIS-001`, `EVAL-AIS-001`, initial KB inventory, evaluation corpus, publication checklist, and Phase 1 exit evidence

### `DEC-035` — Approve `KB-SUP-001` human-transfer definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve the `KB-SUP-001` **Talk to a person** definition for governed draft and evaluation authoring. The approved English answer states that a Family or Caregiver user may ask for LoLo Support at any time, that the current conversation can be transferred, that the user may keep using the same chat, and that information already provided need not be repeated. It must not promise a named or immediately available person, response time, queue position/status, wait time, another-language service, or resolution of the underlying problem. Its only action is the atomic `SUP-HANDOFF-001` transition to human-only ownership, followed by the deterministic transfer confirmation and suppression of further automation. This decision does not publish the entry or enable any runtime/model/pilot control.
- Rationale: The wording is simple for older users, preserves one canonical conversation, makes the human option unconditional, and avoids false operational promises.
- Affects: `KB-SUP-001`, `SUP-HANDOFF-001`, five linked entry evaluations, support ownership, and future grounded-answer release evidence

### `DEC-036` — Approve `KB-SUP-002` emergency and non-medical definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve `KB-SUP-002` **Emergencies and non-medical support** for governed draft and evaluation authoring. Immediate-danger or urgent-medical input receives the deterministic instruction that LoLo is not an emergency service and that the user should call 911 now, followed only by an optional LoLo Support transfer that is explicitly not a substitute for emergency help. Non-emergency medical/clinical requests receive the deterministic non-medical limitation, direction to a licensed healthcare professional, and an optional transfer for help using the platform. The system must not diagnose, assess severity, recommend treatment or medication, claim emergency services were contacted, or imply that waiting for LoLo Support is safe. Safety instruction precedes transfer; critical cases require the 100% critical-corpus gate. This decision does not publish the entry or enable a runtime/model/pilot control.
- Rationale: Separate deterministic paths keep urgent instructions short and unmistakable while preventing the support agent from drifting into medical or crisis assessment.
- Affects: `KB-SUP-002`, `EMERGENCY-001`, `MEDICAL-ADVICE-001`, `SUP-HANDOFF-001`, critical safety evaluations, and future safety release evidence

### `DEC-037` — Approve `KB-SUP-003` English-only definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve `KB-SUP-003` **English-only support** for governed draft and evaluation authoring. Clearly unsupported-language input or an explicit request for another language receives deterministic English wording that automated support is English only and offers either continuing in English or same-conversation human transfer without promising another-language service. Low-confidence or ambiguous language input asks the user to write in English or request transfer; it does not guess or translate. Names, addresses, code, typos, borrowed words, missing accents, and short mixed/ambiguous phrases are not by themselves proof of another language. No user message is sent to a separate translation service and no answer is generated in another language. This decision does not publish the entry, enable detection/model execution, or enable any pilot control.
- Rationale: The boundary is truthful and inexpensive while reducing false classifications that could confuse or exclude older users.
- Affects: `KB-SUP-003`, `DEC-016`, unsupported-language detection/evaluations, `SUP-HANDOFF-001`, and future grounded-answer release evidence

### `DEC-038` — Approve `KB-FAM-001` Family dashboard definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve `KB-FAM-001` **Your Family dashboard** for governed draft and evaluation authoring. The English answer may describe the dashboard as the signed-in Family home page that conditionally prioritizes the most important current next step, recent updates, care needing attention, the next visit, care-history access, and ways to start or book care again. It must say that visible content depends on the authorized Family Account and current care activity. It may never invent a request, caregiver reply, message, visit, booking, payment state, or required action without fresh authorized context. Caregivers and users outside the applicable Family Account receive no Family dashboard data or navigation. The only later navigation action is the registered `family.dashboard` target; if already there, no arbitrary scroll, selector, coordinate, or highlight is permitted. This decision does not publish the entry or enable model/navigation/pilot controls.
- Rationale: Conditional orientation helps older users understand the page without turning a generic KB answer into an unsupported personalized status claim.
- Affects: `KB-FAM-001`, `family.dashboard`, Family context authorization, five linked evaluations, and future Family answer/navigation release evidence

### `DEC-039` — Approve `KB-FAM-002` care requests and visits definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve `KB-FAM-002` **Your care requests and visits** for governed draft and evaluation authoring. The English answer may explain the Family Care page and the Draft, Open, Visit scheduled, Withdrawn, and Expired labels. It must distinguish request status from a booking's fresher visit status: Open allows caregiver responses but guarantees none; Visit scheduled means a caregiver was selected but does not prove that the visit is still upcoming. It may never claim that a particular request, applicant, caregiver, visit, payment, or next action exists without fresh authorized Family Account-scoped context. Caregivers and users outside the applicable Family Account receive no Family request or visit data or navigation. The only later navigation action is the generic `family.care_requests` target; opening a particular request requires separately registered resource-bound authorization. This decision does not publish the entry, change a request/visit, or enable model/navigation/pilot controls.
- Rationale: Simple status definitions help older Family users find their care activity while the request-versus-visit boundary prevents stale or invented personalized claims.
- Affects: `KB-FAM-002`, `family.care_requests`, Family Account context authorization, five linked evaluations, and future Family answer/navigation release evidence

### `DEC-040` — Approve `KB-FAM-003` new care request navigation definition

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: Approve `KB-FAM-003` **Start a new care request** for governed draft and evaluation authoring. The English answer may explain that the normal Family form collects the person, help, time, and care address and provides a review and schedule-dependent estimate before publication. Opening or viewing the page does not post anything to caregivers. During a later separately approved navigation phase, clear intent from an authorized Family user may navigate only to `family.new_care_request`. This entry never authorizes the assistant to select care details, enter or alter form values, submit the form, publish a request, or claim any price, caregiver availability, saved information, or outcome not proven by authoritative current context. Caregivers and users without active Family authorization receive no Family-form navigation. This decision does not publish the entry or enable model/navigation/pilot controls.
- Rationale: Separating safe page opening from request publication gives older Family users a clear starting point without turning a navigation article into an unconfirmed domain write.
- Affects: `KB-FAM-003`, `family.new_care_request`, Family authorization, publish boundaries, five linked evaluations, and future Family navigation release evidence

### `DEC-041` — Approve `KB-FAM-004` Family access definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-FAM-004` **Family access and permissions** exactly as defined in the initial KB pack. It may explain the generic Account owner and Family member roles, including the required disclosure that members can schedule care and approve care-related charges using the saved family payment method. It must preserve owner-only invitation, access-removal, payment-method, ownership, and closure boundaries; never infer or expose a person's membership or account data without fresh authorization; and authorize only later generic navigation to `family.access`. It authorizes no invitation, membership, ownership, or financial mutation.
- Rationale: Older users need a short explanation of shared care access without obscuring the real financial capability or exposing private membership information.
- Affects: `KB-FAM-004`, `family.access`, Family membership authorization, five linked evaluations, and future Family navigation release evidence

### `DEC-042` — Approve `KB-FAM-005` Family Account Settings definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-FAM-005` **Your Account Settings** exactly as defined in the initial KB pack. It may explain that an authenticated Family user can use Account Settings for their own name, email, and password and can reach Care profiles and Family access from that page. It must never request, collect, repeat, infer, or change a password or other credential in chat; never claim a save succeeded; and authorize only later generic navigation to `account.profile`. It authorizes no profile, credential, care-profile, access, or account mutation.
- Rationale: Clear separation between page orientation and sensitive account changes helps users reach the right manual controls without exposing credentials to the assistant.
- Affects: `KB-FAM-005`, `account.profile`, credential safety, five linked evaluations, and future account-navigation release evidence

### `DEC-043` — Approve `KB-CGV-001` Caregiver dashboard definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-CGV-001` **Your Caregiver dashboard** exactly as defined in the initial KB pack. It may conditionally describe the dashboard's current next step, setup/profile status, Work Inbox, and visit orientation. It must not decide or invent onboarding completion, identity/background/credential status, marketplace eligibility, work, messages, visits, earnings, or required actions without fresh Caregiver-scoped context. It authorizes only later generic navigation to `caregiver.dashboard` and no onboarding, verification, profile, work, visit, message, or payout mutation.
- Rationale: Conditional dashboard guidance helps caregivers orient themselves while leaving eligibility and verification decisions to authoritative application services.
- Affects: `KB-CGV-001`, `caregiver.dashboard`, Caregiver context isolation, five linked evaluations, and future Caregiver navigation release evidence

### `DEC-044` — Approve `KB-CGV-002` Caregiver Work Inbox definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-CGV-002` **Your Work Inbox** exactly as defined in the initial KB pack. It may explain the All, Needs response, New requests, Applied, Hired, and Completed views and that each item must be opened for its current details. It must not invent an item, response deadline, fit, compensation, family decision, application, hire, booking, or outcome. It authorizes only later generic navigation to `caregiver.work_inbox` and never authorizes accepting/declining an invitation, applying for work, messaging, or any other work mutation.
- Rationale: The Work Inbox is the safest single orientation point for caregiver opportunities, provided generic navigation is kept separate from consequential responses.
- Affects: `KB-CGV-002`, `caregiver.work_inbox`, Caregiver-only work data, five linked evaluations, and future Caregiver navigation release evidence

### `DEC-045` — Approve `KB-CGV-003` Caregiver visits definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-CGV-003` **Your visits** exactly as defined in the initial KB pack. It may explain that My visits combines one-time, regular, and Continuous Coverage visits and provides the All, Scheduled, In progress, Paused, Completed, Reviewed, Issues, and Time to update filters. It must not infer that a visit exists, that a control is available, or any visit, time, issue, earnings, payment, or family-confirmation state without fresh Caregiver-scoped context. It authorizes only later generic navigation to `caregiver.shifts` and no visit start, pause, resume, end, time, issue, or payment mutation.
- Rationale: A generic timeline explanation improves findability while current visit controls and status remain authoritative and resource-bound.
- Affects: `KB-CGV-003`, `caregiver.shifts`, Caregiver-only visit data, five linked evaluations, and future Caregiver navigation release evidence

### `DEC-046` — Approve `KB-CGV-004` Caregiver Account Settings definition

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approve `KB-CGV-004` **Your Account Settings** exactly as defined in the initial KB pack. It may explain that Account Settings covers the caregiver's own name, email, and password and is distinct from professional Caregiver setup for services, availability, verification, and payouts. It must never request, collect, repeat, infer, or change credentials; claim a save succeeded; or treat Account Settings as the professional-profile editor. It authorizes only later generic navigation to `account.profile` and no account, credential, professional-profile, verification, availability, service, or payout mutation.
- Rationale: Caregivers need a clean distinction between login/account settings and marketplace profile setup, especially when asking for help in plain language.
- Affects: `KB-CGV-004`, `account.profile`, credential safety, five linked evaluations, and future account-navigation release evidence

### `DEC-047` — Skip production-conversation shadow mode

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Do not run a production-conversation shadow phase. Replace it with deterministic and offline evaluation, staff-operated test accounts, older-adult usability evidence, and a tiny exact-user visible pilot with live monitoring, Admin interaction visibility, immediate human transfer, and kill switches. Skipping shadow does not authorize production model use or weaken role isolation, retention, safety, confirmation, rollback, or pilot gates.
- Rationale: Product chose to move directly from strong offline and controlled test evidence to an explicitly granted named-user pilot rather than process invisible copies of live conversations.
- Affects: Phase 2, rollout sequencing, evaluation evidence, retention gates, named-user pilot readiness, and all capability specifications that previously required shadow evidence
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-048` — Expand Family support into interactive care-request assistance

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: The Family assistant may ultimately recommend a care path, retrieve authorized Family context, prepare one-time and regular/recurring request drafts, present a deterministic recap, and publish only after action-specific confirmation. The user explicitly selects the recommended path before drafting. One-time and recurring requests may go live directly after confirmation; 24/7 coverage transfers to a human and produces no AI-created request. Immediate danger remains a separate emergency override.
- Rationale: Families should be able to complete the ordinary care-request journey conversationally while consequential behavior remains bounded by server authorization, validation, and confirmation.
- Affects: `CARE-REQUEST-*`, Family context tools, human handoff, confirmation, evaluation, pilot scope, and capability registry
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-049` — Fixed $30 customer price and deferred payment-code reconciliation

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: All platform care costs the customer $30 per hour with no added platform fee, tax, holiday fee, mileage fee, or other surcharge in the assistant's quote. LoLo retains 10% from within that $30, and the actual Stripe processing fee is deducted from the Caregiver payout. Publishing a request does not authorize payment; authorization remains at Caregiver hire/booking creation and capture remains later in the completed/approved-hours flow. Existing conflicting pricing/payment implementation must not be changed in this work. Assistant pricing and total calculation remain disabled until a separately approved reconciliation makes the authoritative pricing service match this decision.
- Rationale: The assistant requires one simple, accurate customer promise, but must not state that promise against a contradictory production payment path.
- Affects: Pricing answers, request recaps, estimates, payment timing explanations, Caregiver payout explanations, future pricing reconciliation, and release gates
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-050` — Authorized Family context reuse

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Approved server tools may retrieve and reuse the authenticated Family account's own profiles, saved addresses/contact information, care requests, visits, care preferences/instructions, and relevant non-secret account information. A previous request may be reused only when the user explicitly asks, and important reused values must be confirmed. Payment credentials, authentication secrets, cross-family records, Caregiver-only private data, and unrelated support content are excluded. Server-side authorization is mandatory for every read.
- Rationale: Safe reuse makes the conversation materially easier for older users without turning the model into an unrestricted account-data browser.
- Affects: Context contracts, tool schemas, privacy inventory, role isolation, draft provenance, evaluations, and logging/redaction
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-051` — One progressive request flow and minimum fields

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Use one progressive conversational flow and do not expose Fast Track versus Complete Setup. Collect only the recipient, at least one approved task, address, and applicable schedule: date/time/duration for one-time care; weekdays, per-day time/duration, start date, and ongoing/end date for recurring care. Ask optional care, mobility, access, recipient, and third-party questions only when relevant. Generate title/scope. Use the Family Account's saved response window or default to 12 hours, and show it in the final recap with a modification path rather than routinely asking for it.
- Rationale: This matches the maintained domain minimum while reducing avoidable cognitive load and repeated questions for older users.
- Affects: Request drafting, field provenance, validation, recap, accessibility, evaluation, and ordinary-form interoperability
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-052` — Deterministic recap and direct confirmed publication

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: When the request is complete, show a deterministic recap with **Modify something** and **Confirm and create request**. The recap states that confirmation makes the request live and visible to eligible Caregivers but does not hire one. The action-specific server-bound confirmation publishes directly without human review. The server revalidates authorization and all material fields and enforces idempotency. Failure or uncertainty preserves/reconciles the draft and never produces a false success statement.
- Rationale: Users need a simple final choice and a truthful, authoritative result without an extra manual approval bottleneck.
- Affects: Request preview, confirmation binding, publication, receipts, error recovery, human handoff, and Class D evaluations
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-053` — Seven-day private resumable request drafts

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Autosave each valid normalized request field in a private structured draft. The authorized user may resume the draft for seven calendar days after its last valid update, discard it immediately, or choose between resuming and starting over when a different request is detected. The inactivity deadline automatically deletes draft content and invalidates related recaps/confirmations, subject only to an approved legal/security hold. Drafts never notify Caregivers or become marketplace-visible. Versioning and field provenance prevent silent stale overwrites or unrelated-request merging.
- Rationale: Older users can safely pause and return without leaving sensitive unfinished care information indefinitely or accidentally publishing it.
- Affects: Draft storage, retention/deletion, conversation resumption, concurrency, provenance, publication confirmation, Admin evidence, and evaluations
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-054` — Section-level recap modification and reconfirmation

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: **Modify something** exposes individual **Change** controls for who needs care, help needed, schedule, address, additional instructions, and Caregiver response time. The user may also state a modification in ordinary English. The assistant changes only the intended fields, collects any newly required information, and shows a complete fresh recap. Every material change increments the draft version, invalidates the previous confirmation, and requires a new server-bound confirmation before publication. Incompatible schedule values cannot survive a request-type change silently.
- Rationale: Section-level controls and natural-language correction make review understandable for older users while preventing a stale recap from authorizing changed content.
- Affects: Recap UI, conversation handling, draft versioning, confirmation invalidation, accessibility, validation, and evaluations
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-055` — Thirty-minute confirmation with one-step renewal

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: A request-publication confirmation is valid for 30 minutes from recap generation and invalidates earlier on any material draft change, human transfer, logout, authorization change, pilot revocation/expiry, or capability/tool shutdown. Expiration preserves the private seven-day draft. The expired action becomes **Review and confirm again**; one activation reloads and revalidates the saved draft and presents a fresh complete recap and confirmation without re-entering valid information or finding the earlier chat turn. If authoritative data changed, identify and open only the affected section for correction.
- Rationale: A short authorization window limits stale commits while a one-step renewal avoids punishing older users who need more time to review.
- Affects: Confirmation service, recap UI, draft resumption, authorization revalidation, accessibility, error handling, and evaluations
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-056` — Match ordinary publication effects with internal AI provenance

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Assistant-created requests use the ordinary publication effects: create an open Caregiver-discoverable request, send the existing operations new-request alert, record the existing publication funnel event, and route the Family to the authoritative request page after receipt. Do not add a mass Caregiver notification or separate user-visible AI treatment. Record restricted internal `origin: ai_support` provenance linked to the conversation, action evidence, confirmation/idempotency data, and authoritative receipt. An operations-alert failure after commit is retried/reported separately and must not create a duplicate or cause the assistant to deny a successful request.
- Rationale: One domain workflow keeps marketplace and user behavior consistent while preserving enough internal evidence to audit and support the assistant safely.
- Affects: Request publication, marketplace visibility, operations email, funnel analytics, Admin evidence, receipts, retries, and duplicate prevention
- Detailed contract: [Interactive support and care-request expansion](../22-interactive-care-request-expansion.md)

### `DEC-057` — Prompt 24/7 transfer with compact authorized context

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: A 24/7 coverage need transfers promptly to human support without a long mandatory AI intake. The human receives available authorized identity/role, Family reference, recipient, confirmed location, desired start, described needs, continuous/overnight requirement, emergency-screening result, unanswered questions, and the canonical conversation. The final automated message promises only that LoLo Support will reply in the chat as soon as it can; there is no queue, wait, business-hours, or response-time promise. Both administrators are alerted and either may claim. After the `DEC-049` pricing activation hold is released, the assistant may state $30 per hour and perform requested deterministic arithmetic while leaving coverage coordination and availability to the human.
- Rationale: Prompt transfer minimizes user effort while preserving enough verified context to avoid repetition and false operational promises.
- Affects: `CARE-24H-001`, `SUP-HANDOFF-001`, operations alerts, summary fields, price explanation, emergency routing, and evaluations
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-058` — Close production-data retention and extinction limits

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Suppressed output is memory-only where possible and never retained beyond one hour; redacted diagnostics retain seven days; linkable raw analytics 30 days; de-identified aggregates 24 months; caches, indexes, and replicas extinguish within 24 hours; exports default to seven days and may not exceed 30 days without documented authority; model providers may not train and use the shortest available retention never above 30 days; backups extinguish within 35 days and restores reapply deletions before access. Narrow authorized holds remain the only exception. A destination that cannot comply blocks production use.
- Rationale: These limits provide enough short-term operations evidence without creating parallel long-lived conversation stores.
- Affects: `DEC-014`, all production providers and destinations, retention jobs, restore procedures, release evidence, and incident handling
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-059` — Deterministic temporal, ambiguity, and draft-ownership rules

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Use Eastern Time by default and show it; clarify another apparent timezone; resolve relative dates explicitly; never guess vague times; accept one-to-twelve-hour visits in 30-minute increments; support different recurring schedules per weekday; and explain recurring start-date alignment. Ask one short question for ambiguous material values and offer transfer after two misunderstandings of the same field. Bind each draft to actor, Family Account, and conversation with optimistic versioning, access revocation, and explicit resume/discard behavior. Saved data is proposed visibly, never silently reused, and previous requests require explicit user instruction.
- Rationale: Deterministic schedule and ownership behavior prevents silent guesses or stale overwrites while keeping the flow easy to resume.
- Affects: `CARE-INTAKE-001`, `CARE-CONTEXT-001`, `CARE-REQUEST-005`, validation, concurrency, accessibility, and evaluation
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-060` — Authoritative receipt and bounded failure recovery

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: A successful publication receipt states that the request is live, provides its safe reference, recipient, schedule, and route, and explains that eligible Caregivers can see it but none is hired and no payment is authorized. Provider failure preserves the draft; a read tool retries once; validation opens the affected section; uncertain commits reconcile by idempotency key; post-commit notification failure is handled separately; invalid confirmation never writes. No unconfirmed action is queued and no success is claimed without an authoritative receipt.
- Rationale: Users receive one truthful outcome while retries and partial failures cannot create duplicates or fabricated results.
- Affects: `CARE-REQUEST-007`, receipts, retry/reconciliation, notification handling, human transfer, and critical evaluations
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-061` — Complete Admin evidence without private reasoning or pilot exports

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Authorized administrators can inspect the complete labeled canonical conversation, ownership, KB versions, safe tool summaries, draft/expiry, recap/confirmation, publication receipt, model/configuration, latency/tokens/cost, handoff reason, and retry/reconciliation results. Do not expose private chain-of-thought, complete assembled prompts, credentials, payment data, or unnecessary sensitive payloads. The initial pilot has no transcript-export feature. Access and takeover are audited.
- Rationale: The two-person team needs complete operational visibility without creating new sensitive copies or presenting hidden model reasoning as evidence.
- Affects: Admin conversation UI, event schema, redaction, permissions, retention, incident review, and pilot operations
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-062` — Approve expanded KB inventory and initial role scope

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Add the 12 governed request-related English KB topics listed in the approved build contract; the pricing entry remains unreleased/inapplicable until the `DEC-049` hold is released. The first Family package may include answers, handoff, semantic navigation, authorized context, care-path guidance, one-time/recurring drafts and confirmed publication, 24/7 transfer, and receipt navigation behind separate controls. The initial Caregiver package remains answers, orientation/navigation, and handoff only. Attachments, voice, hiring, payments, cancellations/disputes, timesheets/approval, marketplace messaging, and profile/access mutations are excluded.
- Rationale: This gives each role useful bounded scope while preventing the long-term conversational aspiration from becoming blanket mutation authority.
- Affects: KB inventory, capability registry, role isolation, evaluation corpus, implementation scope, and pilot bundles
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-063` — Staged exact-user release without shadow

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Build common runtime/handoff, then navigation, Family context/intake, shared one-time/recurring drafts, one-time publication pilot, recurring publication after that gate, and separately controlled Caregiver answers/navigation. Use staff test accounts, then exactly two named Family users, then at most five, with 14-day grants and review of every interaction. No percentage or general release occurs without another explicit decision. One-time and recurring may share implementation foundations but keep separate commit flags and evidence.
- Rationale: The release path preserves tight control and learning despite the explicit decision to skip invisible production shadowing.
- Affects: Phase plan, pilot grants, capability flags, release evidence, review workload, and rollout tracker
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-064` — Accuracy, elder-usability, and accessibility gates

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Require 100% pass for authorization/isolation, confirmation, duplicate prevention, recap-to-record equality, emergency/medical/24/7/handoff behavior, and non-granted invisibility; zero fabricated success; at least 98% first-pass request-type and material-field extraction; and no guessed publishable ambiguity. Review every pilot conversation/request. Before expansion, test at least five representative older adults, require at least 90% unassisted completion, universal comprehension of recap/live-versus-hired/payment timing, and passing zoom, screen-reader, keyboard, focus, contrast, touch-target, short-question, and draft-preservation checks.
- Rationale: Hard safety can be deterministic and absolute while measured language and usability quality receive explicit, demanding gates.
- Affects: Evaluation corpus, test suites, usability research, accessibility QA, release checklist, and cohort expansion
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-065` — Low-cost runtime and performance limits

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Continue Luna-low while it passes the frozen gates; use deterministic code for authorization, validation, dates, pricing arithmetic, recaps, confirmation, and receipts; retrieve minimum context; default to one model round trip per user message; avoid autonomous loops; target under $0.02 per completed conversation, alert at $0.03, and stop further model loops with safe transfer at $0.05; target five-second P95 conversational response and eight-second P95 tool action. Cost never weakens correctness or safety.
- Rationale: The assistant should remain inexpensive and responsive by constraining model work rather than weakening controls.
- Affects: Runtime orchestration, retrieval, monitoring, cost budgets, circuit breakers, performance tests, and model evaluation
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-066` — Automatic capability stop and safe rollback

- Status: Accepted
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Immediately disable an affected capability for non-granted exposure, unauthorized or cross-scope access, unconfirmed/duplicate/mismatched publication, fabricated success, reply after takeover, emergency failure, repeated provider/tool instability, or material privacy leakage. Rollback invalidates confirmations and automated writes while preserving valid domain records, receipts, safe unexpired drafts, and human support.
- Rationale: Pre-authorized stop conditions allow the small team to contain a serious failure without debating scope during an incident.
- Affects: Kill switches, monitoring, alerts, rollback, draft/confirmation lifecycle, incident response, and release readiness
- Detailed contract: [Interactive assistant approved build contract](../23-interactive-assistant-approved-build-contract.md)

### `DEC-067` - Limited-release operational-readiness package

- Status: Accepted; implementing behind disabled production controls
- Accepted: August 14, 2026
- Decision owner: Product
- Decision: Preserve the fully fail-closed production state while implementing a dedicated provider project and restricted credential, Luna-low Responses calls with `store: false` and a privacy-preserving safety identifier, no provider-hosted state or autonomous/background tools, versioned current cost rates, release-readiness Admin evidence, both-admin alerting, bounded automatic stops, an isolated live-provider rehearsal, and the five-person older-adult study. The first prepared release remains exactly two 14-day Family grants with non-pricing answers/navigation and one-time publication; recurring commit, Caregiver AI, pricing, payment behavior, and every non-granted user remain off. Deployment of the readiness batch does not authorize a grant or a production model call.
- Rationale: The complete readiness package closes privacy, operations, cost, rehearsal, and usability evidence without exposing a live user or turning deployment into release.
- Affects: Provider configuration, request contract, cost catalog, Admin readiness, monitoring, alert delivery, staff rehearsal, usability evidence, release order, and rollback
- Detailed contract: [Limited-release readiness contract](../27-limited-release-readiness-contract.md)

### `DEC-068` - Use the current API credential and defer the external $25 alert

- Status: Accepted for the initial bounded pilot
- Accepted: August 15, 2026
- Decision owner: Product
- Decision: Use the currently configured server-side OpenAI API user/project credential for the initial pilot instead of making a separate dedicated-project Admin API credential a prerequisite. Require a content-free authentication check at the exact standard destination, the existing separate safety-identifier secret, server-only credential handling, and all application cost/turn/latency stops. The external `$25` provider-project alert is recommended but not a release gate for this pilot. Exact Admin API project identity, sharing, and retention settings must not be inferred from standard-key authentication and remain separate evidence facts.
- Rationale: Remove provider-account administration and the external billing alert from the critical path while preserving truthful evidence, application-enforced cost limits, fail-closed controls, and the ability to harden the provider project before expansion.
- Affects: Provider credential evidence, cost-monitoring evidence, provider verification command, limited-release contract, and readiness guidance
- Detailed contract: [Limited-release readiness contract](../27-limited-release-readiness-contract.md)

### `DEC-069` - Schedule the initial two-user Family pilot for August 15-29

- Status: Accepted as the planned window; activation remains gated
- Accepted: August 15, 2026
- Decision owner: Product
- Decision: Schedule the first exact two-user Family pilot to start August 15, 2026 and expire August 29, 2026. The prepared candidates remain production Family IDs `282` and `19`, with full Administrators `1` and `18` as reviewers under the approved either-admin operating model. Recording the schedule must not create a grant, turn on either deployment guard, or bypass any mandatory readiness evidence or the final explicit release decision.
- Rationale: Fix the intended 14-day operating window now while preserving fail-closed release control and truthful evidence.
- Affects: Pilot-user readiness evidence, execution tracker, final preflight, grant expiry, and release sequencing
- Detailed contract: [Limited-release readiness contract](../27-limited-release-readiness-contract.md)

### `DEC-070` - Accelerated two-user pilot with deferred expansion gates

- Status: Accepted; implementing behind disabled production controls
- Accepted: August 15, 2026 through Product response `APPROVE OPTION B`
- Decision owner: Product
- Decision: For exactly production Family IDs `282` and `19` through August 29, permit an initial pilot readiness policy that visibly defers the six remaining provider/source-system/human evidence items before expansion instead of falsely marking them Passed. Preserve exact-user scope, one-time publication only, all cost/safety/human controls, review of every interaction, immediate rollback, and a separate explicit release decision. A third user, Caregiver AI, recurring publication, pricing/payment behavior, or any other expansion remains blocked until the deferred evidence is complete.
- Current state: Implementation may proceed, but no grant, deployment guard, stored control, or model call is authorized until the new initial-pilot preflight is green and a separate exact-commit release decision is recorded.
- Rationale: Reconcile the requested August 15 start with truthful evidence and an explicit residual-risk decision rather than silently bypassing or mislabeling six mandatory checks.
- Affects: Initial-pilot readiness semantics, Admin evidence status, preflight modes, expansion gates, release record, activation order, and rollback
- Decision packet: [Accelerated two-user pilot decision](../33-accelerated-two-user-pilot-decision.md)

## Product decision status

No remaining product interview blocks the approved build or the accepted `DEC-070` implementation. The remaining sequence is deploy the deferred-state/exact-boundary package, record only the six authorized deferrals, obtain a green initial-pilot preflight while expansion remains Blocked, and make the separate explicit exact-commit release decision. Until that final decision and ordered activation, production AI remains unauthorized. The separately held pricing reconciliation remains outside this pilot.
