# Build Readiness and Validation Ledger

Status: Approved interactive build implemented and evaluated; limited-release evidence open

Last updated: August 14, 2026

Owner: Product and engineering

## Executive answer

There are **zero remaining product interviews blocking engineering** for the declared initial intelligent-support build. The Phase 0-1 foundation is deployed fail-closed, the consolidated interactive scope is approved through `DEC-047` through `DEC-066`, and the runtime/request implementation now has deterministic, browser, and live-model evidence recorded in [the implementation record](24-interactive-assistant-implementation-and-release-evidence.md).

This is implementation readiness, not limited-release approval. Production/user-visible AI remains off. Provider/privacy, older-adult usability, production-like operations/rollback rehearsal, named pilot users, and explicit limited-release approval remain required before any release switch or grant is activated.

Phase 0 also retains one independent operational tail: every legacy-data derivative and containing backup must reach verified extinction or controlled final expiry. Do not repeat the completed primary destruction.

## Product-decision count

| Gate | Unanswered product decisions | Engineering status |
| --- | ---: | --- |
| Model-independent foundation | 0 | Deployed fail-closed |
| Offline model and governed initial content | 0 | Complete; Luna-low baseline accepted; content remains non-user-visible |
| Production-data retention and provider maximums | 0 | Periods accepted; destination/configuration evidence still required |
| Human transfer and 24/7 handling | 0 | Implemented and deterministically tested; operations rehearsal required |
| Family request intake, draft, recap, and publication | 0 | Implemented; deterministic, browser, and model evidence pass |
| Exact-user pilot sequence and release gates | 0 | Contract accepted; release approval still required after evidence |
| Caregiver initial answer/navigation scope | 0 | Runtime boundaries implemented and deterministically tested; release evidence required |
| **Total** | **0** | **Engineering may begin within the approved boundaries** |

## Deployed foundation and implementation candidate

The deployed Phase 0-1 slice provides:

1. Existing canonical human-support conversation and Admin workflow.
2. Retired legacy AI care-request runtime and guarded destruction procedure.
3. Deny-by-default deployment, master, role, capability, tool, and human-only controls.
4. Exact per-user pilot grants with expiry, revocation, and audit.
5. Governed KB draft/edit/validate/self-approve/publish/pause/supersede/withdraw/delete workflow.
6. Versioned context, event, retention, navigation, handoff, and confirmation foundations.
7. Deterministic authorization, isolation, retention, handoff, and confirmation-boundary tests.
8. Admin conversation evidence surfaces.

The current repository candidate adds the customer runtime, semantic navigation, authorized context, private drafts, deterministic recap, confirmed one-time/recurring publication, receipt, cost stop, provider adapter, and direct handoff without changing the production fail-closed state. It is not yet recorded as deployed or released.

## Resolved product packages

| Package | Resolution |
| --- | --- |
| Runtime baseline | `DEC-012`: Luna-low is the least-cost passing offline baseline; Mini-low remains challenger |
| Language | `DEC-016`: English only |
| Family and Caregiver initial read scope | `DEC-032` and `DEC-033` |
| Shadow mode | `DEC-047`: skipped; use offline, staff-account, usability, and exact-user pilot evidence |
| Interactive Family request scope | `DEC-048`: recommend/select, context, one-time/recurring draft, recap, confirmed publication; 24/7 human only |
| Pricing truth and implementation hold | `DEC-049`: $30/hour customer truth; no pricing/payment code change or live quote until reconciliation |
| Authorized Family context | `DEC-050` |
| Minimum progressive flow | `DEC-051` |
| Recap and direct publication | `DEC-052` |
| Draft persistence | `DEC-053`: private seven-day autosave/resume |
| Recap modification | `DEC-054` |
| Confirmation | `DEC-055`: 30 minutes with one-step fresh recap |
| Publication effects | `DEC-056`: ordinary workflow plus restricted internal provenance |
| Human operations and 24/7 summary | `DEC-057` |
| Retention/extinction periods | `DEC-058` |
| Temporal, ambiguity, and ownership rules | `DEC-059` |
| Receipt and failure behavior | `DEC-060` |
| Admin evidence | `DEC-061` |
| Expanded KB and role scope | `DEC-062` |
| Pilot sequence | `DEC-063` |
| Accuracy and elder usability | `DEC-064` |
| Cost/performance | `DEC-065` |
| Automatic stop and rollback | `DEC-066` |

The normative consolidated source is [the approved build contract](23-interactive-assistant-approved-build-contract.md).

## Engineering decisions that do not require another product interview

Engineering may decide and document:

- Exact schema and index layout
- Service and domain composition that does not reuse legacy copilot code
- Transactional outbox/event durability mechanics
- Idempotency storage and reconciliation implementation
- Queue/job and retry mechanics within the approved retry ceilings
- Component organization and internal API boundaries
- Evaluation runner implementation and fixture organization
- Safe redaction implementation that meets the approved evidence contract

Return to Product for any change to user-visible scope, authorization, role/data access, confirmation, retention, source truth, pricing truth, human ownership, pilot size, release order, hard gates, or automatic stop conditions.

## Delivery evidence completed

- Runtime, Family/Caregiver isolation, exact-user private transcripts, navigation, context, draft, recap, confirmation, publication, receipt, handoff, retention, observability, and kill switches implemented
- 12-entry expanded KB manifest and 60 entry-linked evaluation cases implemented with governed import and selective publication controls
- Frozen 56-case live Luna-low v3 gate: 56/56, 100% extraction, zero hard failures, P95 4.769 seconds
- Final full deterministic Laravel suite: 682 passing with 5,051 assertions after all hardening; final transfer/draft/runtime slice passes 22 tests with 159 assertions
- Isolated fresh migration/seed and new recap-to-live-request Playwright flow pass
- Same-account exact-user privacy, Admin evidence, 200% text, responsive mobile, keyboard, offline retry, and established human support regressions pass
- Production asset build and feature-scoped formatting pass
- Fail-closed production deployment and authenticated Admin/Family browser audit pass
- Expanded production KB import verified: 12 interactive Version 1 Drafts, 60 linked evaluations, 24 successful create/validate events, zero failures, zero publication, and zero activation
- Shadow control removed from the Admin operator UI in `4ac0f07` while permanent server denial remains; 67 AI Support tests and the 682-test full suite pass
- Production verified with Shadow absent, 23 reviewed non-pricing entries Published, `KB-CARE-006` held as Draft, 23 successful publication events, zero publication failures, zero grants, and all AI gates off

Exact metrics, report checksum, audit corrections, and the deploy/rollback sequence are in [the implementation record](24-interactive-assistant-implementation-and-release-evidence.md).

## Limited-release evidence still required

These are implementation/release tasks, not unanswered product decisions:

### Privacy and operations

- Complete context-field purpose/source/redaction inventory
- Provider no-training and retention configuration evidence
- Cache/index/replica/export/backup destination inventory
- Automated deletion, downstream extinction, and restore/re-deletion exercises
- Human alerting, takeover, and 24/7 handoff rehearsal
- Phase 0 derived-target/backup extinction closeout

### Knowledge and evaluation

- Governed publication complete for 23 reviewed non-held entries; keep `KB-CARE-006` outside ordinary retrieval until `DEC-049` is released
- Re-run the frozen Luna-low gate after any governed model, prompt, schema, KB, or corpus change
- Keep the pricing entry outside ordinary answer retrieval until `DEC-049` is released

### Usability and pilot

- Five-person representative older-adult mobile/desktop/accessibility study
- Staff-operated production-like test-account results
- Exact initial two-user Family pilot list and 14-day grants
- Monitoring, alert, cost, latency, abort, and rollback rehearsal
- Review process for every pilot interaction and request
- Explicit limited-release approval after all evidence passes

## Build order

1. Common runtime, canonical transcript integration, governed retrieval, and human handoff.
2. Semantic navigation.
3. Family context reads and care-path recommendation.
4. Shared one-time/recurring draft and recap foundations.
5. One-time confirmed publication and exact-user pilot.
6. Recurring publication after the one-time gate.
7. Separately controlled Caregiver answers/navigation.

The payment/pricing reconciliation is a separate project. Do not modify Stripe, customer fees, authorization buffer, Caregiver payout, or account overrides in this build.

## Readiness conclusion

The declared implementation is complete and deployed fail-closed. Twenty-three reviewed non-pricing entries are Published and the pricing entry remains a validated Draft. Production/user-visible controls must remain off. Limited release still requires the open provider/privacy, operations, usability, named-user, monitoring, and explicit approval gates. Payment/pricing code remains outside this project.
