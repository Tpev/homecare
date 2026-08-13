# Build Readiness and Remaining Validation Ledger

Status: Phase 0-1 foundation deployed fail-closed; Phase 0 extinction closeout and later validations remain active

Last updated: August 13, 2026

Owner: Product and engineering

## Executive answer

The approved Phase 0-1 foundation slice has been implemented and deployed to production without enabling customer-facing AI, real-conversation shadowing, semantic navigation execution, care-request drafting, or Class D commits. Its implementation evidence and limitations are recorded in [the Phase 0-1 build record](16-phase-0-1-foundation-build-record.md); production evidence and active follow-ups are recorded in [the production deployment and next-phase tracker](17-production-deployment-and-next-phase-tracker.md).

Six product-validation packages remain before the complete first one-time-request pilot can reach Phase 6. One package contains two retention choices, so the ledger represents seven individual product decisions. `READY-VAL-002` was resolved by the English-only `DEC-016`, and `READY-VAL-003` was resolved for the first Family package by `DEC-032`. The remaining packages are deliberately scheduled at the latest safe gate rather than blocking offline work.

This count covers product decisions requiring owner validation. It does not count implementation work, tests, evaluations, privacy inventories, usability studies, production access checks, or release evidence as separate product questions; those are delivery requirements created during the relevant phase.

## Count by gate

| Gate | Product validations still required | Count | Can engineering proceed before them? |
| --- | --- | ---: | --- |
| Phase 0-1 foundation | Implemented and deployed fail-closed; Phase 0 backup/derived-target extinction remains an operational closeout item | 0 product decisions | Complete for the declared deployed foundation scope; not complete as an intelligent-agent release |
| Before offline model baseline and first capability approval | Runtime model/configuration | 1 | Family KB and evaluation authoring may proceed under `DEC-016` and `DEC-032`; model selection still requires measured evidence |
| Before shadow processing of production conversations | Remaining retention package: short-lived output/diagnostic TTLs and downstream provider/analytics/export/backup extinction rules | 1 package / 2 decisions | Yes for local/offline work; no production conversation data until resolved |
| Before any user-visible named-user pilot | Staffed-hours/ownership promise and human-response SLO | 1 | Yes for offline and shadow work |
| Before Phase 5 care-request drafting | Required-field/profile-prefill contract | 1 | Yes for lower-risk answers/navigation |
| Before Phase 6 confirmed care-request publication | Confirmation lifetime; notification/operations behavior | 2 | Yes for drafting; Class D commit remains disabled |
| **Total remaining** | **Six later validation packages** | **6 packages / 7 individual decisions** | Continue offline work only within the declared gates |

## Implemented Phase 0-1 build slice

The first engineering slice contains no user-visible production AI and no new care-request publication:

1. Verify and stabilize the existing human support conversation and administrator workflow.
2. Retire the legacy AI care-request copilot and implement the separately controlled `DEC-011` data-destruction procedure. Production execution of destructive steps still requires verified environment targets and its own operational authorization.
3. Establish deny-by-default master/capability controls and prove non-granted users see and invoke no AI behavior.
4. Build exact per-user pilot grants in the existing admin user experience, including expiry, immediate revocation, audit, and effective-state display.
5. Build the governed KB admin workspace with draft, edit, validate, self-review/approve, publish-now, pause, supersede, bounded withdrawal/deletion, version history, and retention visibility.
6. Defined versioned context, event, retention, semantic-navigation, handoff, and confirmation contracts without enabling customer-facing model execution.
7. Added deterministic tests for authorization, isolation, KB lifecycle, retention clocks, deletion/hold behavior, handoff, and confirmation boundaries.

The slice explicitly excludes customer-facing AI replies, shadowing real conversations, semantic navigation execution, care-request drafting, and all Class D commits.

## Production state after deployment

The production smoke test on August 13, 2026 verified that:

- The public site showed no AI assistant.
- Unauthenticated requests to every `/admin/ai-support*` workspace redirected to login.
- The authenticated overview reported the deployment guard off, customer state failing closed, master off, user-visible off, human-only on, and zero active exact-user grants.
- The Family and Caregiver user-profile views each exposed the correct role-specific exact-user grant workflow without bypassing higher-level controls.
- The KB index and draft editor loaded with zero production entries; no production KB mutation was performed during verification.
- The canonical human support queue and ticket detail remained available, including human messages, internal notes, ownership, and compact AI evidence.
- No application console error occurred during the inspected flow.

Two tracked limitations remain: the overview's KB summary copy still says **Foundation pending** even though the governed workspace is deployed, and create/edit/publish/delete behavior was not mutation-tested in production because the smoke test was intentionally read-only.

## Resolved validation packages

### `READY-VAL-002` — Product language (`DEC-016`)

Resolved August 13, 2026: intelligent support is English only in every phase. Non-English input does not trigger translation or an unverified answer; approved simple English copy offers human transfer without promising another-language human service.

### `READY-VAL-003` — First Family answer and navigation scope (`DEC-032`)

Resolved August 13, 2026: the initial Family package contains only human-help/emergency limitation, dashboard orientation, existing request basics/status, navigation to the normal manual new-request form, Family Account roles/access, and account/profile orientation. Answers and navigation remain separately phased. No write or care-request drafting/publication is authorized. The companion first Caregiver read-only scope was subsequently accepted under `DEC-033`; it does not add another validation package to this Family one-time-request readiness count.

## Remaining validation packages

### `READY-VAL-001` — Initial runtime baseline (`DEC-012`)

Choose the least costly evaluated model/provider/configuration candidate and the challenger set. This is needed before measuring the offline baseline, not before building model-independent controls.

### `READY-VAL-004` — Remaining retention (`DEC-014`)

Resolve two questions before production-data shadow:

1. TTLs for suppressed/undelivered output and restricted detailed diagnostics.
2. Maximum extinction for linkable analytics, manual exports, providers, replicas, caches, indexes, and backups.

### `READY-VAL-005` — Human operations (`DEC-015`)

Name the owner during and outside staffed hours and approve truthful response/SLO copy. Transfer remains immediate and automation stops; LoLo does not show queue status.

### `READY-VAL-006` — Care-request draft contract

Approve the exact domain-required fields and whether authorized profile values need field-by-field confirmation or only clear final review. Do not inherit legacy copilot completeness rules.

### `READY-VAL-007` — Confirmation lifetime

Approve the short server-bound validity window and invalidation rules for a Class D preview. The accepted 24-hour preview storage ceiling is not a validity window.

### `READY-VAL-008` — Request notification and operations behavior

Approve which existing user/caregiver/admin notifications and operational events follow an agent-created request. No notification may imply an outcome not proven by the authoritative domain receipt.

## Engineering decisions that do not require another product interview

Engineering may resolve these within approved boundaries and document the result:

- Exact schema/table layout
- Domain-service composition, provided it does not reuse the legacy copilot path or weaken policy
- Transactional outbox/event durability design
- Idempotency implementation
- Job scheduling and retry mechanics
- Admin component organization within the accepted UX
- Test runner and evaluation harness implementation

Any choice that changes user-visible scope, authorization, confirmation, retention, source truth, human ownership, or release gates returns to the decision log.

## Evidence still required during delivery

These are not pre-build questions, but they block their declared release stage:

- Current production configuration and legacy-data inventory
- Context-field purpose/redaction inventory
- Authoritative initial KB entries and evaluation cases
- Deterministic authorization, isolation, confirmation, deletion, and concurrency tests
- Offline repeated-run model evaluations
- Shadow review before user-visible answers
- Older-adult mobile/accessibility usability evidence
- Named-user pilot list, support readiness, monitoring, cost budget, and rollback rehearsal
- Zero unresolved critical failures for the released scope

## Readiness conclusion

The model-independent Phase 0-1 foundation is deployed and remains deny-by-default. The remaining six validation packages should be completed in the order above. None is implicitly approved by the foundation deployment, and no real conversation may be sent to a model until its declared gate is complete. Phase 0 also remains operationally open until every legacy-data derivative and containing backup reaches verified extinction under the recorded restore controls.
