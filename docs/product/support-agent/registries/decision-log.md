# Decision Log

Status: Active registry

Last updated: August 13, 2026

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
- Decision: New AI support behavior is deny-by-default in every live environment. A live user without an active pilot grant must see the existing human-support experience with no AI entry point, label, greeting, reply, suggestion, navigation, draft, or action. The server must not make a customer-facing model call for that user. Deployment or missing configuration must resolve to AI off. Invisible shadow evaluation may run only under a separately approved privacy scope and must have no user-visible output or side effect.
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

### `DEC-013` — First one-time request workflow may progress to confirmed publication

- Status: Accepted through `DEC-010`
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: The one-time non-medical care-request workflow begins as reversible Class C draft assistance in Phase 5 and may progress to Class D publication in Phase 6 only after its deterministic preview, action-specific server-bound confirmation, authorization, idempotency, authoritative receipt, safety, usability, shadow, limited-cohort, and rollback gates pass. Approval to build or pilot drafting does not automatically enable publication; the Class D commit remains independently disabled until Phase 6 release approval.
- Rationale: `DEC-010` already approved the bounded draft-to-confirmed-publication architecture. Separate phase and release gates preserve a safe draft-first rollout without leaving the product decision ambiguous.
- Affects: `CARE-REQUEST-001` through `CARE-REQUEST-003`, Phases 5 and 6, capability flags, confirmation, evaluation, and release approval

### `DEC-016` — English-only intelligent support

- Status: Accepted
- Accepted: August 13, 2026
- Decision owner: Product
- Decision: LoLo's intelligent support agent supports English only across offline evaluation, shadow, pilot, and any later released phase. It does not translate or answer in another language. When the user writes in a language the agent cannot safely treat as English, automation uses approved simple English wording to explain the limitation and offer transfer to human support; it does not promise that the human will respond in another language. Adding another intelligent-support language would require this decision to be superseded, authoritative language-specific sources, a governed translated KB, dedicated evaluations, usability/accessibility evidence, and a separate release approval.
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

## Decisions still required

| ID | Question | Needed before | Owner |
| --- | --- | --- | --- |
| `DEC-012` | What is the first evaluated runtime model/configuration? | Offline baseline | Engineering/product |
| `DEC-014` | What exact TTLs remain for suppressed/diagnostic data and what maximum extinction rules apply to analytics, exports, providers, replicas, caches, indexes, and backups under `DEC-024`? | Shadow production data | Privacy/operations |
| `DEC-015` | What business-hours promise and escalation SLO can support staff? | User-visible rollout | Support operations |
