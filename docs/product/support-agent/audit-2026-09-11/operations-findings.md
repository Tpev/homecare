# AI Support operations, governance, and evidence audit

Audit date: September 11, 2026. Scope: numbered program records 00–62, the decision/risk registers, retained local evaluation summaries, and current control, health, provider, retention, and Admin reporting source. This is a source and retained-evidence audit. It does not establish today's production settings, KB publication versions, delivery health, provider-account configuration, or cohort. No production operation, provider call, test run, or application change was performed for this sub-audit.

## Assessment

The system has substantial operating controls: explicit availability, exact-user pilot grants, bounded domain capabilities, human ownership, immutable KB versions, content-minimized events, cost limits, retention machinery, and an executable intent/evaluation registry. The highest-value next investment is proving the current experience and making its operational state reliable, rather than adding another large capability batch.

The recorded portfolio is **324 Family intents: 74 Complete, 163 Assisted, and 87 Human transfer**. Those are declared delivery dispositions, not measured customer completion rates. A complete catalog is not a fully autonomous product. See [master coverage inventory](../44-family-chat-operator-master-plan.md), lines 122–145, and [coverage closure](../58-family-intent-coverage-closure.md), lines 11–20.

The latest detailed production acceptance record is dated **August 28**, fourteen days before this audit. It confirms deterministic stop, cancellation detours, profile explanations, and recurring recap behavior; its final line still leaves a notification `<details>` reopening correction awaiting deployment and focused retest. That is an unresolved evidence item, not proof that today's deployed code still has the defect. See [follow-up acceptance](../62-family-pilot-browser-follow-up-fixes.md), lines 125–135.

## Which operating rules are current

**DEC-072 supersedes the earlier release-readiness gates.** Current operation is Pilot only, Live for everyone, or Emergency stop/Human Only. Readiness forms, preflight commands, exact-commit approvals, and the earlier expansion checklists no longer grant or block availability. Do not revive those historical approval sequences as a new release prerequisite. [Availability contract](../37-simplified-availability.md), lines 11–17 and 55–63; [decision log](../registries/decision-log.md), lines 690–698.

The source agrees: `initial_pilot.enforced` is false, `general_release_enabled` defaults false, and eligibility reads current runtime/control/role/grant/capability/tool/conversation state. General release removes the exact-user-grant requirement; it does not remove domain authorization. [Configuration](../../../../config/ai_support.php), lines 64–128 and 129–160; [eligibility](../../../../app/Services/AiSupport/AiSupportEligibilityService.php), lines 19–102.

The earlier privacy, accessibility, source-system deletion, and staffing evidence gaps remain real unanswered questions. Removing an administrative gate does not convert an unobserved fact into a pass. Their closure should be practical operating work, with current owners and dates, not a return to the old approval machinery.

## Chronology and delivered state

| Period and records | What the evidence establishes | Limit of that evidence |
| --- | --- | --- |
| August 13–14, 00–16 | The human support conversation became the foundation. The separate legacy request copilot was removed; guarded primary deletion and fail-closed controls, pilots, KB governance, event/confirmation foundations, and retention were built. | Foundation deployment initially exposed no customer AI. Legacy downstream/backup extinction remained separately open. |
| August 14, 18–21 | Twelve initial governed entries and the frozen offline model evaluation were built. The final 556-call comparison selected Luna low on equivalent hard-gate performance and lower measured cost. | This was an initial answer/navigation corpus and historical model/prompt configuration, not proof of today's broad operational assistant. |
| August 14, 22–26 | Interactive Family intake, encrypted drafts, recap, explicit one-time/recurring publication, receipts, and deterministic handoff were implemented and deployed with controls off. Twenty-three non-pricing entries were published; publication and user access remained separate. | Code deployment, publication, and activation were deliberately distinct. |
| August 14–17, 27–36 | Provider prerequisites, monitors, alert verification, staffed synthetic safety, and an exact two-user Family pilot were recorded. A real receipt-invalidation bug was found during rollback rehearsal, fixed, and rerun. | Initial provider-account, downstream deletion, screen-reader, and older-adult evidence were incomplete. The old release workflow is now historical. |
| August 17, 37 | DEC-072 simplified availability to Pilot/Everyone/Human Only. | Current controls still require runtime/provider configuration and domain permissions. |
| August 17–19, 38–49 | Deterministic reads/guides, secure payment-method completion verification, the 324-intent registry, five preparation contracts, normalized payment/time reads, and profile/request lifecycle tools expanded the Family operating layer. Real pilot wording exposed routing, continuation, and stale-state defects. | Passing isolated suites repeatedly failed to predict some real browser and ordinary-language issues. |
| August 20–21, 50–57 | Applicant/hire, visit/regular-care, administration, exceptional human paths, and ten persistent Family goals were implemented. Master-plan and document 57 record deployment and authenticated correction rechecks for the same two-user pilot. | Individual batch documents still retain older “pending” headings; they are not a coherent current deployment ledger. |
| August 27–28, 58–62 | Eleven formerly unsupported operational paths became Assisted; navigation and a 55-entry KB realignment were updated; multiple production retests prompted deterministic fixes. | Final notification-panel retest remains unclosed in the available record. August 28 knowledge/navigation text also predates the September care UI redesign. |

Broad Family operation is implemented in source; secure card entry/authentication, many product exceptions, disputes, ownership/privacy decisions, and Continuous Coverage operations remain guided or human-owned. The Family-focused expansion deferred deeper Caregiver adaptation, while the older shared Caregiver answer/navigation bundle still exists. “Caregiver deferred” should not be read as “no Caregiver code exists.” [Master plan](../44-family-chat-operator-master-plan.md), lines 9 and 185–198; [Availability](../37-simplified-availability.md), lines 31–39.

## Actionable operational findings

### OPS-01 — A critical notification can claim containment that never happened

**Priority: high; confirmed source behavior.** The health monitor detects failed operations notification deliveries and opens a critical `operations_notification_failed` incident, but it does not call `systemStop` for this condition. The incident service's generic critical notification nevertheless says **“AI Support stopped a capability”**. An administrator could reasonably believe automation is contained when controls remain unchanged. The same incident service is used for warnings and real control stops, so severity alone is insufficient to infer containment.

Evidence: [health monitor](../../../../app/Services/AiSupport/AiSupportHealthMonitorService.php), lines 71–89; [incident notification](../../../../app/Services/AiSupport/AiSupportIncidentService.php), lines 21–28 and 87–96. Eligibility does not use an open incident as a substitute for actual control state: [eligibility](../../../../app/Services/AiSupport/AiSupportEligibilityService.php), lines 19–102.

Proposed improvement: represent incident severity and actual stop status separately. Display the affected control and its effective value; only say “stopped” after a persisted stop succeeds. Decide explicitly whether a failed human-handoff alert requires automatic containment or an operational incident, and test that choice. This audit changed neither behavior.

### OPS-02 — Product changes and KB publication can drift independently

**Priority: high; demonstrated by production history, current publication unknown.** Route existence was already insufficient to catch stale Family guidance in August. The code package and production KB publication are separate operations; document 62 specifically records a production answer still using an older published article despite corrected source. The September care UI changed request steps, labels, care navigation, and schedule presentation again.

Evidence: [KB realignment](../59-family-experience-kb-realignment.md), lines 9–13 and 70–92; [follow-up corrections](../62-family-pilot-browser-follow-up-fixes.md), lines 46–65 and 116; [KB governance](../04-knowledge-base-governance.md), lines 154–178.

Proposed improvement: maintain one compact release record with code commit, generated intent-catalog hash, prompt/schema version, required published KB versions, enabled bundles, and dated browser checks. Automatically compare visible labels/semantic targets and required published content when a workflow changes. Keep publication governed, but make its outstanding status unmistakable. Parent runtime/UX audits own the exact September mismatches.

### OPS-03 — Historical evaluation success is being asked to prove a newer product

**Priority: high for confidence in a wider rollout; evidence gap.** The retained frozen baseline verifies the documented August 14 initial corpus. A later combined rehearsal uses `interactive-support-v4`; current source declares `interactive-support-v9` and contains far more deterministic paths, tools, and guidance. Historical scores do not measure the current prompt or the complete browser journey. The mass harness explicitly does not replace real browser or screen-reader evidence.

Evidence: [model evaluation](../21-phase-1b-offline-model-evaluation.md), lines 169–206; [harness limits](../40-family-batch-1-2-evaluation-harness.md), lines 246–253; [current version](../../../../config/ai_support.php), lines 26–38. The preserved report checks are listed below.

Proposed improvement: use current isolated tests as the source baseline, then freeze a representative multi-turn corpus from current failures and unused wording. Measure fresh bounded provider evaluations separately when authorized, including failures and repeated critical cases. Add sampled human grading for answer correctness, comprehension, and appropriate action choice; lexical required/forbidden-phrase checks remain useful regression guards but do not prove natural-language quality by themselves. [Offline grader](../../../../app/Services/AiSupport/OfflineAiSupportModelGrader.php), lines 74–103 and 130–159.

### OPS-04 — Three evidence classes have expiry dates but no retention execution path

**Priority: medium; confirmed source omission, not an observed overdue-data incident.** Incidents, readiness evidence, and release decisions receive `retain_until` dates. `ApplyAiSupportRetention` does not count or prune these models, and these models do not define a `Prunable`/`MassPrunable` fallback. The scheduler's separate model-prune command covers another model. Running the AI retention job therefore cannot enforce those three stored expiry dates.

Evidence: [incident expiry](../../../../app/Services/AiSupport/AiSupportIncidentService.php), line 63; [readiness expiry](../../../../app/Services/AiSupport/AiSupportReadinessService.php), line 193; [release-decision expiry](../../../../app/Services/AiSupport/AiSupportInitialPilotReleaseService.php), line 155; [retention dispatch/counts](../../../../app/Console/Commands/ApplyAiSupportRetention.php), lines 38–80; [schedule](../../../../routes/console.php), lines 26–30.

Proposed improvement: add explicit bounded retirement rules and tests for these classes, retaining current evidence/open incidents and honoring applicable holds or dependencies. This is future retention work; the oldest records in the documented program are only from August 2026.

### OPS-05 — Retention and monitoring execution are not independently observable

**Priority: medium/high operational hardening; current production execution unverified.** Retention scheduling is explicitly off unless `AI_SUPPORT_RETENTION_EXECUTION_ENABLED` is true. The job records successful per-class deletion evidence only when records were deleted; that is not an unambiguous last-success heartbeat. The health command reports failures but does not provide an independent watchdog if its own scheduler stops. Source availability alone cannot prove the seven-day draft/journey policy or longer deletion schedules are actually running.

Evidence: [default guard](../../../../config/ai_support.php), line 9; [scheduled jobs](../../../../routes/console.php), lines 26–29; [deletion evidence](../../../../app/Console/Commands/ApplyAiSupportRetention.php), lines 408–425; [monitor failure path](../../../../app/Console/Commands/MonitorAiSupportHealth.php), lines 25–37.

Proposed improvement: expose content-free last-attempt/last-success, due/held/failed counts, oldest overdue age, and alert on a missed run. Verify production scheduling/queue delivery and a non-destructive dry run before asserting operational enforcement. Keep execution authority separate from this audit; do not run deletion merely to prove the feature exists.

### OPS-06 — Health metrics are narrower than their labels imply

**Priority: medium; confirmed metric scope.** “Conversation P95” samples successful `model_turn_completed` events, whose latency is provider latency. It excludes deterministic answers, user-visible end-to-end delivery, and failed model turns. A warning requires twenty observations within one hour; a small two-user pilot can stay below that sample even when individual interactions are slow. Notification checks inspect failed deliveries within the last hour, not queue age or emails stuck pending. These are useful monitors but not a complete user-experience or staffing dashboard.

Evidence: [health sampling and thresholds](../../../../app/Services/AiSupport/AiSupportHealthMonitorService.php), lines 20–38 and 71–89; [recorded provider latency](../../../../app/Services/AiSupport/AiSupportRuntimeService.php), lines 357–364; [threshold configuration](../../../../config/ai_support.php), lines 43–48.

Proposed improvement: report sample size and “insufficient sample” explicitly, distinguish provider/tool/end-to-end timings, add an individual slow-turn limit and queue-age/last-delivery metrics, and measure handoff-to-human-claim time internally without making an unsupported response-time promise to users. Add task start, offered action, confirmation, verified outcome, recovery, abandonment, and repeat-contact denominators. The current Admin overview's recent outcome counts are not a completion funnel. [Overview](../../../../app/Livewire/Admin/AiSupport/Overview.php), lines 36–65.

### OPS-07 — Provider-account and real-user evidence remains unclosed

**Priority: material risk-reduction work; not a resurrected DEC-070 gate.** Retained records explicitly do not prove current account sharing settings, effective provider retention, intended project/key identity, or the complete downstream deletion/restore inventory. They also do not claim a real screen-reader session or the five non-team older-adult study. A working key, `store:false`, synthetic tests, and browser screenshots prove different facts.

Evidence: [honest evidence ledger](../36-original-objective-completion-audit.md), lines 21–42 and 48–56; [provider evidence boundaries](../28-provider-privacy-and-operations-evidence-checklist.md), lines 15–47; [older-adult protocol](../30-older-adult-usability-study-kit.md), lines 9–21; [retention destination rules](../13-data-retention-and-deletion.md), lines 100–108 and 158–171.

Proposed improvement: close those factual gaps through account-owned evidence and actual sessions, recording what was observed and when. Use the existing study kit for tasks and comprehension, but treat its obsolete release-gate language as historical. The source guarantees `store:false`; this audit makes no current external provider-policy, sharing, ZDR, or residency claim.

### OPS-08 — The Everyone switch has a much broader effect than cohort expansion alone

**Priority: product/operations clarity.** The general-release control list enables both supported roles and a broad Family tool portfolio, including hire, cancellation, hours approval, recurring-care changes, messages, and access management. It is not merely adding more users to the exact currently enabled pilot capabilities. This is documented behavior, not an authorization defect.

Evidence: [Everyone contract](../37-simplified-availability.md), lines 31–39; [control list](../../../../config/ai_support.php), lines 64–126; [enable sequence](../../../../app/Services/AiSupport/AiSupportControlService.php), lines 54–63.

Proposed improvement: show a concise, accurate scope summary on Availability and verify enabled capability/tool postconditions. If gradual expansion is desired, separate audience expansion from capability-bundle expansion while retaining a simple admin experience. Do not add another hidden readiness approval system.

### OPS-09 — Cost accounting is estimated and tied to a fixed catalog

**Priority: medium before substantial traffic.** `AI_SUPPORT_MODEL` can be overridden, while token prices and the price-version string are fixed to the recorded Luna configuration. Runtime cost is calculated from the successfully parsed final response; malformed successful responses and uncertain retried requests can incur cost that this return path does not record. The historical readiness helper calling its price catalog “current” merely compares fixed constants; it does not verify provider billing rates.

Evidence: [model/rate configuration](../../../../config/ai_support.php), lines 26–44; [provider parse and cost return](../../../../app/Services/AiSupport/AiSupportOpenAiClient.php), lines 64–101; [historical price check](../../../../app/Services/AiSupport/AiSupportReadinessService.php), lines 301–305.

Proposed improvement: bind a model configuration to its matching rate catalog, report estimated and unknown-attempt cost distinctly, and periodically reconcile with provider-owned billing evidence. Preserve bounded retries and user/conversation budgets. The parent audit checked official Luna documentation on September 11 and reports that the default input/cached-input/output rates of $0.20/$0.02/$1.20 per million tokens match. This finding concerns model overrides and unrecorded attempt cost, not an incorrect current default rate.

## Retained evaluation artifacts verified

| Artifact | Verified evidence | Freshness limitation |
| --- | --- | --- |
| `storage/app/private/ai-support-evaluations/phase-1b-v4-full-e5e628e.json` | SHA-256 `5b09316ec02a55789e3c0e6e97fd3a440906a8d42dc78145ee3c0db9a7e27bdd` matches document 21. Generated August 14; initial-kb-evals-v4; application `e5e628e...`; 278 calls each for two candidates, zero hard failures, 99.64% deterministic quality each. Luna estimated total $0.0656346, P95 3,498 ms. | Initial offline prompt v4 and small support corpus; not today's interactive v9 product. |
| `storage/app/private/ai-support/rehearsal-003c7cc.json` | Exact commit `003c7cc...`, browser and 56 provider cases passed, 27/27 extraction fields, temporary DB destruction recorded, P95 5,531 ms. Nested provider `result_hash` matches document 31's `885cfc...`. | August 14, interactive-support-v3. File hash and nested result hash are intentionally different objects. |
| `storage/app/private/private/ai-support/rehearsal-c409fe3-repeat.json` | Exact commit `c409fe3...`, browser passed, 56/56 provider cases, 27/27 fields, P95 5,118 ms, estimated $0.01799844. | August 14, interactive-support-v4; above the five-second target in that measured sample. |
| `storage/app/private/ai-support/evaluations/family-batch-1-2-latest.json` | Generated August 17, 40/40 intents, 120/120 routing phrases, 10/10 collisions; shared regression passed. | The filename “latest” is not current evidence. |
| `storage/app/private/ai-support/family-intents-batch5-final.json` | Generated August 19, 45/45 selected intents, 137/137 phrases, 10/10 collisions; shared regression passed. | Earlier selected slice; does not establish Batches 6–10 or September UI compatibility. |

Historical reports deliberately omit raw prompts/answers and retain unsuccessful runs. This is useful evidence discipline. Preserve that discipline when refreshing the current corpus; do not convert old hashes or aggregate test counts into current model-quality claims. The parent audit owns newly executed September tests and browser results.

## Current regression evidence supplied by the parent audit

The parent ran the current AI Support and Support suites: **284 tests**, initially 275 passing, eight Git-related failures, and one error. All eight Git-related cases passed when rerun with an environment-only `safe.directory` configuration; this yields **283 of 284 tests verified passing**. The remaining error is a historical date-dependent fixture, not evidence of a failure in current conversation eligibility. Original output is retained locally at `.tmp/support-audit-2026-09-11/tests.log`; the parent owns the rerun log and consolidated result.

The failing `test_option_b_deferrals_are_distinct_and_satisfy_only_initial_pilot_scope` explicitly enables the historical DEC-070 policy and applies deferrals without freezing time. The command combines today's observation time with the policy's fixed August 29 expiry. On September 11, the service correctly rejects that expiry as preceding the observation. Evidence: [test fixture](../../../../tests/Feature/AiSupport/LimitedReleaseReadinessTest.php), lines 343–359; [command timestamps](../../../../app/Console/Commands/RecordAiSupportOptionBDeferrals.php), lines 79–95; [date validation](../../../../app/Services/AiSupport/AiSupportReadinessService.php), lines 164–166; [historical policy expiry](../../../../config/ai_support.php), lines 129–145.

Recommended follow-up: freeze this historical test within the accepted August 15–29 policy window and retain the validation. Do not extend an expired historical approval or weaken the production date check to make the test pass. DEC-072 superseded that gate, as documented above. No source or test file was edited in this audit.

## Documentation coherence findings

- Document 17 calls itself an active tracker but still lists Batches 6–10 as pending, while documents 44, 56, and 57 state they were deployed and rechecked on August 21.
- Document 56 has a current “deployed/rechecked” status and final paragraph, but its intermediate state table still says some deployments/publications/audits are pending.
- Document 37 still says “awaiting normal production deployment,” despite later production records describing Pilot/Everyone operation.
- Document 62's initial “pending” status needs to distinguish the deterministic fixes that its final acceptance section says passed from the final notification-panel correction that remained pending.
- Provider/privacy and study documents 28–30 still include old gate/runbook language; read them with the explicit DEC-072 supersession in document 37, not as current release instructions.

Proposed improvement: keep historical records intact, add a one-page dated current-state ledger, and mark superseded runbooks visibly. Track **source / deployed / KB published / capability enabled / browser verified / human verified** independently. Generate the mechanical portions from source and governed database metadata, with evidence date and owner for the human observations.

## Investment options

| Work package | Concrete deliverable | Acceptance measure |
| --- | --- | --- |
| Operational truth first | Correct incident containment wording/state; scheduler and delivery heartbeat; retention coverage for every declared evidence class; clear availability scope. | Injected notification/scheduler failures produce truthful admin state, a visible alert, and the declared fallback. A dry-run inventory reconciles every retention class. |
| Current-journey reliability | Refresh the code/KB/target version ledger; run current multi-turn Family journeys on the new care screens with imperfect phrasing, detours, refresh, missing resources, and human takeover. | Each tested goal ends in a verified result, a truthful assisted step, or context-preserving human ownership; zero false success. |
| Older-adult comprehension | Run the existing five-person study and a real screen-reader session on the current UI. Fix wording, confirmation, and recovery problems before increasing scope. | Record actual unassisted completion and comprehension of draft versus posted versus hired, payment consequences, and human transfer. |
| Evidence-based breadth | Instrument task funnels and review top failed/abandoned intents, then expand only the next useful domain or Caregiver slice. | Demonstrable reduction in unresolved/repeated contacts and support effort without increased corrections; measured cost per verified resolution. |

The recommended order is operational truth and current-journey reliability in parallel, followed by real-user comprehension evidence, then selective expansion. Existing narrow tools and deterministic state should be retained; more autonomous scope is not the primary missing foundation.

## Numbered record inventory

The appendix below records each numbered file's own status verbatim for traceability. A status is a claim made by that historical document, not an independently verified September 11 production fact. Conflicts and superseded instructions are interpreted as described above.


| Record | Status stated in the record (historical, not revalidated) |
|---|---|
| [Program Charter](../00-program-charter.md) | Proposed |
| [Current State and Baseline](../01-current-state-and-baseline.md) | Draft inventory |
| [Capability and Risk Framework](../02-capability-and-risk-framework.md) | Proposed |
| [System Architecture](../03-system-architecture.md) | Proposed target architecture |
| [Knowledge-Base Governance](../04-knowledge-base-governance.md) | Proposed |
| [Safety, Privacy, and Human Escalation](../05-safety-privacy-and-escalation.md) | Proposed |
| [Evaluation and Testing](../06-evaluation-and-testing.md) | Proposed |
| [Rollout and Release Gates](../07-rollout-and-release-gates.md) | Accepted phased plan; Phase 0-1 foundation deployed fail-closed |
| [Observability, Operations, and Cost](../08-observability-operations-and-cost.md) | Proposed |
| [GPT-5.6 Sol Working Agreement](../09-gpt-5-6-sol-working-agreement.md) | Active |
| [Legacy Copilot Retirement and Data Destruction](../10-legacy-copilot-retirement-and-data-destruction.md) | Accepted execution contract under `DEC-005` and `DEC-011` |
| [Admin Control Plane, Pilot Access, and KB Workspace](../11-admin-control-plane-and-pilot.md) | Accepted product requirements; implementation specification |
| [Admin UX: Pilot Access, KB Management, and Conversation Evidence](../12-admin-ux-specification.md) | Draft interaction specification grounded in accepted `DEC-017`, `DEC-018`, and `DEC-019` |
| [Data Retention, Deletion, and Legal Holds](../13-data-retention-and-deletion.md) | All initial-build periods accepted; destination implementation and extinction evidence remain open |
| [Build Readiness and Validation Ledger](../14-build-readiness-ledger.md) | Approved interactive build implemented and evaluated; limited-release evidence open |
| [Legacy AI Care-Request Copilot Retirement Runbook](../15-legacy-copilot-execution-runbook.md) | Primary production destruction executed; derived-target and backup extinction closeout remains active |
| [Phase 0-1 Foundation Build Record](../16-phase-0-1-foundation-build-record.md) | Implemented, locally verified, and deployed to production fail-closed |
| [Production Deployment, Verification, and Next-Phase Tracker](../17-production-deployment-and-next-phase-tracker.md) | Active tracker |
| [Initial Governed KB and Evaluation Pack](../18-initial-kb-and-evaluation-pack.md) | All 12 entry definitions accepted; governed records and evaluations authorized for implementation but unpublished |
| [Phase 1 Content and Evaluation Build Plan](../19-phase-1-content-and-evaluation-build-plan.md) | Historical plan completed; production Draft import, editorial corrections, and measured offline baseline verified; publication and runtime remain prohibited |
| [Phase 1A Governed Content Build Record](../20-phase-1a-content-build-record.md) | Production deployment, Draft import, and two Draft-only editorial corrections verified; nothing published |
| [Phase 1B Offline Model Evaluation Adapter and Execution Record](../21-phase-1b-offline-model-evaluation.md) | Complete; `gpt-5.6-luna` at low reasoning accepted as the offline baseline under `DEC-012`; all production and user-visible controls remain off |
| [Interactive Support and Care-Request Expansion](../22-interactive-care-request-expansion.md) | Active product contract; implementation not yet authorized |
| [Interactive Assistant Approved Build Contract](../23-interactive-assistant-approved-build-contract.md) | Approved product contract; ready for capability specification and implementation |
| [Interactive Assistant Implementation and Release Evidence](../24-interactive-assistant-implementation-and-release-evidence.md) | Implementation and fail-closed production deployment verified; limited-release approval pending |
| [Production Interactive Assistant Deployment Audit](../25-production-interactive-deployment-audit.md) | Authenticated production deployment, Draft import, Settings cleanup, and selective publication verified; limited release remains blocked |
| [Production KB Publication and Settings Verification](../26-production-kb-publication-and-settings-verification.md) | Complete; limited release remains disabled |
| [Limited-Release Readiness Contract](../27-limited-release-readiness-contract.md) | Historical; operating gates superseded by `DEC-072` |
| [Provider Privacy and Operations Evidence Checklist](../28-provider-privacy-and-operations-evidence-checklist.md) | Approved checklist; execution in progress |
| [Staff Rehearsal and Rollback Runbook](../29-staff-rehearsal-and-rollback-runbook.md) | Approved runbook; execution evidence open |
| [Older-Adult Usability Study Kit](../30-older-adult-usability-study-kit.md) | Approved protocol; participant evidence open |
| [Limited-Release Readiness Implementation Record](../31-limited-release-readiness-implementation-record.md) | Implemented fail-closed; production release evidence remains open |
| [Limited-Release Readiness Execution Log](../32-limited-release-readiness-execution-log.md) | Active; limited release remains blocked and customer AI remains disabled |
| [Accelerated Two-User Pilot Decision](../33-accelerated-two-user-pilot-decision.md) | Option B accepted; implementation in progress behind disabled production controls |
| [Initial Two-User Family Pilot Activation Record](../34-initial-two-user-family-pilot-activation-record.md) | Limited release active; activation and initial smoke evidence complete |
| [Two-User Family Pilot Operations Runbook](../35-two-user-family-pilot-operations-runbook.md) | Historical procedure superseded by `DEC-072` |
| [Original Objective Completion Audit and Expansion Work Queue](../36-original-objective-completion-audit.md) | Exact two-user Family pilot active; original preparation objective audited; expansion evidence open |
| [Simplified AI Support Availability](../37-simplified-availability.md) | Implemented; awaiting normal production deployment |
| [Family Intent and AI Action Coverage Registry](../38-family-intent-action-coverage-registry.md) | Active coverage registry; executable Batch 9 catalog generated from all 324 rows and composed into source-complete Batch 10 goal journeys |
| [App-Aware Guided Assistance](../39-app-aware-guided-assistance.md) | Batches 1 through 3 implemented in source; Batch 3 production deployment pending |
| [Family Batch 1–10 Evaluation Harness](../40-family-batch-1-2-evaluation-harness.md) | Expanded through Batch 10 journey coverage and passing in source |
| [Batch 1-2 Production Pilot Audit and Corrective Release](../41-batch-1-2-production-pilot-audit.md) | Corrective implementation verified in source; normal `deploy.sh` deployment and final production recheck pending |
| [Mobile Support-Chat UX Polish](../42-mobile-support-chat-ux-polish.md) | Deployed mobile foundation; August 19 desktop/bottom-anchor correction implemented and regression-tested locally, awaiting the normal deployment |
| [Task-First Navigation and Payment Guidance Correction](../43-task-first-navigation-and-payment-guidance-correction.md) | Implemented and locally verified |
| [Family Chat Operator Master Coverage and Delivery Plan](../44-family-chat-operator-master-plan.md) | Active master plan; Batches 1 through 10 and both August 21 production-audit corrections are deployed and authenticated for the exact two-user Family pilot; **Live for everyone remains off** |
| [Family Operations Knowledge Base Wave 1](../45-family-operations-kb-wave-1.md) | Published in production and explicitly consumed by the Batch 3 intent catalog |
| [Family Operating Layer — Batch 3A and 3B Implementation Record](../46-family-operating-layer-batch-3.md) | Implemented in source; production deployment pending |
| [Payments, Submitted Hours, and Recovery — Batch 4](../47-payment-time-recovery-batch-4.md) | Deployed to the exact two-user pilot; all 18 Batch 4 knowledge entries published; Batch 4.1 audit corrections implemented and awaiting the normal deployment |
| [Production Audit Corrections — Batch 4.1](../48-production-audit-corrections-batch-4-1.md) | Implemented, regression-tested, and ready for the normal `deploy.sh`; not an availability change |
| [Care Profiles and Request Lifecycle — Batch 5](../49-care-profiles-request-lifecycle-batch-5.md) | Deployed, published, and production-verified for the exact two-user pilot; complete care-profile and care-request lifecycle journeys passed on August 19, 2026; **Live for everyone remains off** |
| [Applicants, Messaging, and Hiring — Batch 6](../50-applicants-messaging-hiring-batch-6.md) | Source implementation complete; automated verification complete; production deployment and authenticated pilot audit pending; **Live for everyone remains off** |
| [Visits, Submitted Hours, and Regular Care — Batch 7](../51-visits-regular-care-batch-7.md) | Source implementation complete; automated verification complete; production deployment and authenticated pilot audit pending; **Live for everyone remains off** |
| [Family Administration, Communication, and Records — Batch 8](../52-family-administration-communication-records-batch-8.md) | Source implementation complete; automated verification complete; production deployment, KB publication, pilot activation, and authenticated pilot audit pending; **Live for everyone remains off** |
| [Continuous Coverage and Exceptional Support — Batch 9](../53-continuous-coverage-exceptional-support-batch-9.md) | Source implementation complete; automated verification complete; production deployment, KB publication, pilot activation, and authenticated pilot audit pending; **Live for everyone remains off** |
| [Family Goal-Guided Journeys — Batch 10](../54-family-goal-guided-journeys.md) | Source complete; Family only; Caregiver AI deferred; not deployed or production-audited; production availability remains Pilot only |
| [GPT-5.6 Sol Implementation Prompt — Family Goal-Guided Journeys](../55-family-goal-guided-journeys-implementation-prompt.md) | No top-level Status field; read as its dated record or implementation prompt. |
| [Family Goal-Guided Journeys — Batch 10 Implementation Record](../56-family-goal-guided-journeys-batch-10-implementation-record.md) | Deployed and active for the exact two-user Family pilot; authenticated production audit and both correction rechecks completed August 21, 2026; final evidence is recorded in [document 57](../57-batch-10-production-audit-corrections.md) |
| [Batch 10 Authenticated Production Audit Corrections](../57-batch-10-production-audit-corrections.md) | Both corrections deployed and authenticated in production for the exact two-user Family pilot; final recheck passed; Live for everyone remains off |
| [Family Intent Coverage Closure](../58-family-intent-coverage-closure.md) | Source complete; deployment and production KB publication pending |
| [Family Experience and Knowledge-Base Realignment](../59-family-experience-kb-realignment.md) | Deployed and published for the two-user Family pilot; August 28 browser corrections pending deployment |
| [Current Family Layout Production Browser Audit](../60-current-family-layout-production-browser-audit.md) | Source corrections implemented; production deployment and authenticated retest pending |
| [Family Pilot Browser Correction Batch](../61-family-pilot-browser-correction-batch.md) | Implemented in source; deployment and authenticated production retest pending |
| [Family Pilot Browser Follow-up Fixes](../62-family-pilot-browser-follow-up-fixes.md) | Second production browser audit completed; deterministic follow-up corrections implemented and verified in source; deployment and final authenticated retest pending |
