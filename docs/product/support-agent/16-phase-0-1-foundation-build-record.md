# Phase 0-1 Foundation Build Record

Status: Implemented, locally verified, and deployed to production fail-closed

Build and production deployment date: August 13, 2026

Owner: Product and engineering

Scope authority: Approved Phase 0-1 slice in [the readiness ledger](14-build-readiness-ledger.md)

## Outcome

LoLo now has the model-independent control, governance, evidence, and human-handoff foundations required before an intelligent support runtime can be evaluated. Those foundations are deployed in production. The existing human support experience remains the only customer-facing support behavior. AI is unavailable by deployment default, every stored control defaults safe, no model endpoint exists, and no material-action tool is registered.

This record documents implemented behavior. It does not authorize a model/provider, shadow processing of real conversations, customer-facing AI responses, navigation execution, care-request drafting, care-request publication, or broad release.

## Implemented scope

### Human support and legacy retirement

- Preserved the existing support widget, unified ticket transcript, administrator support queue/detail, unread behavior, assignment, notifications, and manual care-request flow.
- Removed the legacy `AiRequestCopilot` route, Livewire UI, AI-specific models/services/bindings/configuration, and legacy tests. No component from that implementation is an authority for the new support agent.
- Added `ai-support:destroy-legacy-copilot-data`, which is dry-run by default and requires exact environment/database confirmation, operator identity, two approval references, backup status, code version, and explicit execution.
- Added a guarded follow-up schema-removal migration that refuses non-empty legacy tables and, in production, requires successful destruction evidence.
- The original build did not execute legacy destruction. During the later authorized production deployment, the guarded primary-database destruction completed and recorded content-free evidence. Derived-target and containing-backup extinction remains open under [the execution runbook](15-legacy-copilot-execution-runbook.md).

### Deny-by-default control plane and exact-user pilots

- Added `AI_SUPPORT_RUNTIME_AVAILABLE=false` as a deployment guard separate from database controls.
- Added versioned master, visibility, human-only, role, capability, shadow, and future tool controls. Missing state resolves to the safe configured default; store failure denies eligibility.
- Added exact-user pilot grants with role-specific bundles, activation/expiry, a 14-day default, explicit no-expiry acknowledgment, immediate revocation, immutable mutation audit, renewal as a new grant, and UUID idempotency.
- Same-family membership, role membership, or another user's grant never confers access.
- Full administrators can manage grants and controls in the admin UI. Ordinary users cannot open or invoke these admin surfaces.
- Pilot revocation immediately makes the user ineligible and deletes any pending action-preview content for that actor.
- Deleting a pilot user nulls the exact-user reference; the retention job then records a fixed user-deletion retirement event and starts the grant's bounded 24-month history period, including for a former no-expiry grant.
- Shadow enablement remains locked pending `DEC-014`.

### Governed knowledge base

- Added stable KB entries and immutable versions with draft, validation, review, approval, publish-now, pause/resume, supersession, withdrawal, and governed deletion.
- Either of LoLo's two full administrators can complete the entire lifecycle alone, including self-review/approval/publication, as accepted by `DEC-023`.
- Publication requires authoritative sources, role/applicability metadata, registered semantic targets, boundary facts, and evaluation IDs.
- Retrieval returns only the current published, unexpired version applicable to the exact role, membership state, capability, and semantic target.
- Never-released dependency-free drafts can be permanently deleted. Released content withdraws immediately and follows bounded full-history and tombstone retention; stable KB IDs remain reserved.
- Added admin list/search, create/edit, validation, lifecycle, source, applicability, evaluation, version, and deletion views.

### Runtime contracts without a live model

- Added versioned eligibility, context, event, navigation, confirmation, KB, and retention contracts.
- Context assembly reauthorizes the current user against the support ticket, returns only approved identity/role, conversation references, a registered semantic screen target, and recent canonical public-message IDs, and declares the assembled content memory-only.
- No duplicate complete prompt/model request is persisted.
- Structured interaction events enforce strict field and safe-metadata allowlists and reject content-bearing or unrestricted fields.
- Semantic navigation resolves only registry IDs whose named Laravel route exists and whose role matches. Arbitrary selectors, coordinates, URLs, or target IDs are rejected.
- Material-action previews require an explicitly registered capability/tool/version, current exact-user eligibility, fresh automated ownership, a capability-specific safety-validity window, and the 24-hour absolute storage ceiling.
- Production has zero registered material-action tools in this phase. Test-only tool definitions prove the contract under explicit controls.
- Confirmation references are stored as hashes; preview payloads are encrypted; evidence is created only after an authoritative callback succeeds in the same transaction; confirmation is actor-bound and idempotent even after preview-record deletion; compact evidence contains no rendered preview or message body.

### Human handoff and ownership

- `SUP-HANDOFF-001` foundation atomically changes an automated conversation to `human_only` and emits one deterministic final automated message.
- The user sees no queue position, queue status, automated wait time, or later automated acknowledgment.
- Transfer immediately deletes/invalidate pending preview content, records a normal support activity plus a compact interaction event, and notifies administrators without message content.
- Notification failure does not undo human ownership or create another automated message.
- Automated delivery checks reread conversation ownership and exact-user eligibility immediately before delivery.
- Human administrator public replies are explicitly labeled human, force human-only ownership, invalidate pending previews, and add compact transfer evidence when taking over an automated conversation.
- Returning to automation requires deliberate full-administrator action, a reason, current exact-user eligibility, a safe open conversation, a compact event, and a clear user-visible notice.

### Retention and deletion

- The unified support transcript remains canonical while active. Final resolution records authoritative `retention_started_at` and schedules content deletion 12 calendar months later.
- Resolved-to-closed preserves the original clock. Reopening clears transcript and interaction-event deletion dates. A later final resolution starts new 12- and 24-month clocks.
- `ai-support:apply-retention` is dry-run by default. The idempotent daily `--execute` schedule exists only when `AI_SUPPORT_RETENTION_EXECUTION_ENABLED=true`; missing configuration is off so the first production deployment cannot purge existing transcripts before its dry run is reviewed.
- Expired transcript processing deletes message rows and content-bearing ticket fields while preserving the ticket tombstone and linked care requests, bookings, payments, accounts, and other domain records.
- Active narrowly scoped holds skip affected deletion. A required review date does not release a hold; optional expiry and explicit release are distinct. Expired or released holds do not silently extend retention.
- Invalid/expired preview content is cleared immediately by lifecycle services and the short-lived preview record is removed by the daily job after hold checks; compact events, confirmed-action evidence, pilot/control audit, released KB content/tombstones, and successful deletion evidence follow the accepted bounded periods.
- Deletion evidence stores only class, policy version, environment, run reference, count, result, and timestamps. It contains no deleted content.

## Administrator experience

The admin navigation contains an **AI Support** area for overview, exact-user pilots, KB, controls, and compact activity. Existing user detail pages include the exact user's pilot card.

Existing support ticket detail pages remain the complete canonical chat view and now also show:

- Human-only or automated ownership
- All compact AI interaction events for that ticket
- Actor, result, capability, KB version references, and event time when present
- Active contract and retention-policy versions
- Expected transcript deletion/completed-deletion state
- Active hold indicator with its required review date and separate optional expiry
- A deliberate return-to-automation form that still fails unless the exact opener is currently eligible

The panel never displays private model reasoning because none is stored.

## Schema and implementation map

| Area | Primary implementation |
| --- | --- |
| Legacy evidence/destruction | `legacy_copilot_destruction_runs`, `DestroyLegacyCopilotData`, guarded legacy-table drop migration |
| Controls and pilot | `ai_support_control_versions`, `ai_support_pilot_grants`, `ai_support_admin_audit_events`, control/grant/eligibility services, admin Livewire workspace |
| Knowledge base | `knowledge_base_entries`, `knowledge_base_versions`, sources/dependencies, validation/retrieval/workflow services, admin KB workspace |
| Conversation ownership | `support_tickets.responder_mode` and handoff timestamps/reason; `support_ticket_messages.responder_type`; `AiSupportHandoffService` |
| Context/navigation | `AiSupportContextContract`, `NavigationTargetRegistry`, `config/ai_support.php` target registry |
| Compact events | `ai_support_interaction_events`, `AiSupportEventRecorder`, admin ticket evidence panel |
| Confirmation | `ai_support_action_previews`, `ai_support_confirmed_action_evidence`, `AiSupportActionEvidenceService` |
| Retention | ticket retention fields, `data_retention_holds`, `data_deletion_evidence`, `ApplyAiSupportRetention`, daily schedule |

Migrations are intentionally ordered from `2026_08_13_100000` through `2026_08_13_100400`. Do not reorder or fold deployed migration history.

## Verification evidence

The deterministic suite covers:

- Legacy route/runtime absence, dry-run destruction, guarded selective deletion, and preservation of manual care/human support
- Default-off behavior, missing-control/store failure, exact-user isolation, same-family non-inheritance, role change, expiry, immediate revocation, idempotency, and audit rollback
- One-person KB lifecycle, published-only retrieval, role/applicability filtering, pause/resume, supersession, stale-edit rejection, source/route/evaluation validation, dependencies, and deletion rules
- Authorized minimized context and cross-user denial
- Registered role-aware navigation and arbitrary-target rejection
- Strict content-free event fields
- Atomic deterministic handoff, no queue language, duplicate suppression, preview invalidation, notification failure, human admin takeover, deliberate eligible return, and later automated-delivery suppression
- Registered tool/version enforcement, short preview lifetime, encryption/reference hashing, failed-commit rollback, actor binding, idempotency, compact evidence, and revocation invalidation
- Transcript/event retention clocks, resolved-to-closed preservation, reopen/restart, dry-run retention, narrow holds, expired holds, idempotent deletion, content-free evidence, and linked care-record preservation

Record the final command outputs in the completion section below after formatting and the full affected regression pass.

## Operations and rollback

- Emergency/customer rollback: set `human_only` on or disable `master_enabled`, `user_visible_enabled`, the affected role/capability/tool, or revoke the exact pilot grant. The deployment guard can also disable the runtime independently.
- Existing human support remains available regardless of AI controls.
- Do not enable `shadow_enabled`; it is code-locked until the production-data retention/provider package is approved.
- Do not populate `ai_support.tools` in production without a separately approved capability, deterministic contract/evaluations, and matching deny-by-default controls.
- Run `php artisan ai-support:apply-retention` first as a dry run. The scheduler uses `--execute`; monitor and rehearse it before production deployment.
- Run legacy destruction only through the separate runbook with exact verified targets and authorization. Schema removal occurs only after successful evidence.

## Explicit exclusions and remaining gates

The following remain unavailable:

- Any customer-facing model reply or model/provider call
- Real-production shadow processing
- Automatic or suggested semantic page movement
- Care-request conversational drafting
- Care-request preview/publication or any Class D domain write
- Caregiver operational tools
- Broad role/account/percentage rollout

Before offline runtime evaluation and later release, resolve the remaining seven packages in [the readiness ledger](14-build-readiness-ledger.md): runtime baseline, first answer/navigation scope, remaining downstream retention, staffed-hours/SLO, care-request draft fields, Class D confirmation lifetime, and request notification/operations behavior. Product language is already resolved as English only under `DEC-016`.

## Completion section

Completed August 13, 2026:

- `php vendor/bin/pint <58 affected PHP files>`: passed; 12 style issues fixed.
- `php artisan test tests/Feature/AiSupport tests/Feature/Support --stop-on-failure`: **57 tests passed, 366 assertions**.
- `php artisan test tests/Feature/Family/CareRequestFlowTest.php --stop-on-failure`: **29 tests passed, 273 assertions**; the existing manual one-time and recurring request flows remain functional.
- The two completed slices total **86 passing tests and 639 assertions**.
- `php artisan route:list --name=admin.ai-support`: seven authorized admin routes present.
- Legacy route scan: no `/family/requests/create/ai` or `AiRequestCopilot` route remains.
- Retention schedule verification: absent under the default-off configuration; when `AI_SUPPORT_RETENTION_EXECUTION_ENABLED=true`, daily `ai-support:apply-retention --execute` is registered at 03:40.
- `php artisan view:cache` followed by `php artisan view:clear`: all Blade templates compiled successfully and the verification cache was cleared.
- `git diff --check`: passed.
- The five new migrations were exercised by every `RefreshDatabase` test run. At the original local completion checkpoint they remained pending in the developer's ordinary local database; they were later deployed through the production sequence recorded below.
- After the production MySQL identifier failure was repaired, the targeted KB regression slice passed with **9 tests and 51 assertions**.
- After destructive retention execution was made default-off, the targeted retention slice passed with **5 tests and 41 assertions** and the schedule was verified absent by default and present only when explicitly enabled.

Repository-wide `php artisan test --stop-on-failure` was attempted with two-minute and ten-minute limits. Both executions reached the runner timeout before PHPUnit emitted a summary and did not print a failure. This is **not recorded as a repository-wide pass**. The affected feature, support, and manual-care regression gates above are complete and passing; CI or a longer unbuffered environment must complete the entire repository suite before the next model-enabled release gate.

## Production deployment and smoke verification

Deployed from `master` through the normal `deploy.sh` workflow on August 13, 2026. The deployed foundation consists of:

- `decf24f66bb0848c65e63aceb05101256881b79b` - Phase 0-1 foundation.
- `b5df2f5a48e8eae9b27f9e33b930b7665cf53612` - MySQL KB migration recovery and explicit short foreign-key name after the first production migration attempt exceeded MySQL's 64-character identifier limit.
- `338a9db25d98eff6ce096a92dda05d4a1878bee2` - destructive retention scheduling gated off by default unless `AI_SUPPORT_RETENTION_EXECUTION_ENABLED=true`.

The authenticated, read-only production smoke test verified the overview, settings, exact-user Family and Caregiver pilot cards, KB index/draft editor, compact activity view, human-support queue, canonical conversation, internal notes, ownership, and AI-evidence panel. Unauthenticated AI-admin URLs redirected to login; the public site showed no AI; the runtime guard was off; master and user visibility were off; human-only was on; active grants, KB entries, and AI audit events were all zero. No production setting, grant, KB entry, or support conversation was changed during the smoke test.

One non-blocking UI issue remains: the overview card says **Knowledge base - Foundation pending** although the governed KB workspace is deployed. Production KB create/edit/publish/delete mutations were not exercised because the verification was intentionally read-only and the KB is empty.

Production foundation deployment: complete and fail-closed.

Primary legacy-database destruction: complete with content-free evidence; external derived-target and containing-backup extinction remains open.

Customer-facing AI, real-conversation shadowing, and model execution: not authorized and not present.
