# Limited-Release Readiness Implementation Record

Status: Implemented fail-closed; production release evidence remains open

Implemented: August 14, 2026

Authority: `DEC-067`

## Outcome

The limited-release readiness control layer is implemented without activating customer AI. The deployment remains safe when the new evidence tables are empty: both deployment guards remain off, all AI capability and role controls remain off, human-only remains on, no pilot grant is created, and the Admin readiness result is `BLOCKED`.

This build adds evidence and operational controls. It does not authorize a pilot, process a production conversation with a model, publish the held pricing entry, or change pricing, payment, Stripe, Caregiver payout, or Care Request domain behavior.

## Delivered controls

- Additive, versioned, content-free readiness-evidence records with actor, observation, expiry, supersession, and retention fields.
- Content-free incident records with critical and warning severity, deduplication, resolution evidence, and no automatic re-enable behavior.
- An Admin **Release readiness** surface that computes `BLOCKED` or `READY FOR EXPLICIT APPROVAL`, displays critical incidents and warnings, and contains no activation, control, or pilot-grant action.
- A persistent red Admin banner only for unresolved critical incidents. Performance warnings remain visible and alert operations without falsely claiming that a capability was stopped.
- Both-Administrator in-app and email notifications for human handoffs, automatic stops, operations-notification failures, and monitoring warnings.
- A plan-only-by-default operations-alert command. A dispatched test remains `Pending` until a person confirms actual receipt in both Administrator inboxes.
- A read-only release-preflight command that never changes controls, grants, evidence, or runtime flags.
- A five-minute health monitor for conversation/tool P95, notification-delivery failures, and the daily provider-cost stop.
- Conversation and pilot daily cost/turn ceilings that fail safely into human support.
- A one-command isolated rehearsal that refuses production and uncommitted tracked changes, uses synthetic SQLite/browser data, optionally exercises the frozen live-provider corpus, records content-free evidence tied to the exact commit, and destroys the fixed temporary database in `finally`.
- A dedicated HMAC `safety_identifier`; the provider receives no raw LoLo user ID.
- Responses API requests remain stateless with `store:false`, strict JSON Schema, parallel tools disabled, no hosted tools or provider-side files/vector stores, a 900-token output ceiling, and at most one retry.
- Versioned Luna-low pricing and cached-input accounting based on the official catalog checked August 14, 2026.
- The approved provider/privacy checklist, staff/rollback runbook, and five-person older-adult usability study kit.

## Schema and deployment safety

Migration `2026_08_14_100600_create_ai_support_readiness_tables` creates only:

- `ai_support_readiness_evidence`
- `ai_support_incidents`

It does not drop, truncate, rename, or rewrite an existing table. Existing user, support, Care Request, KB, payment, and legacy-destruction evidence data are untouched. Deploying the migration creates no evidence row, incident, control version, or grant.

The new provider safety secret is intentionally absent from source control. Production may deploy safely without it while both customer runtime guards remain false. A dedicated secret of at least 32 characters is required before any later provider-enabled rehearsal or explicitly approved pilot.

## Verification evidence

### Deterministic and build evidence

| Check | Result |
| --- | --- |
| Limited-release focused feature tests | 8 passed, 43 assertions |
| Complete AI Support feature slice | 76 passed, 629 assertions |
| Complete Laravel application suite | 691 passed, 5,097 assertions |
| Laravel formatting | Pass |
| Production Vite build | Pass; existing large-chunk advisory only |
| Readiness page authorization | Full Administrator only; Family forbidden |
| Readiness activation isolation | No activation, control, or grant action exists on the readiness page |
| Plan-only rehearsal | No process, provider call, database mutation, or report write |

### Isolated combined rehearsal

Final accepted local working-tree run: August 14, 2026 at `2026-08-14T13:34:41Z`.

| Metric | Result |
| --- | --- |
| Browser flow | Pass |
| Frozen corpus | `interactive-evals-v1` |
| Prompt/schema | `interactive-support-v3` |
| Candidate | `gpt-5.6-luna-low` |
| Cases | 56 passed, 0 failed, 0 hard failures |
| Extraction | 27/27 fields, 100% |
| Input/cached/output tokens | 69,786 / 69,618 / 13,980 |
| Estimated provider cost | `$0.01820196` |
| P50/P95 provider latency | 2,930 / 5,468 ms |
| Temporary database | Verified destroyed |
| Failed safe case IDs | None |
| Content-free result hash | `7e7bae4428bacaa57d25bb26ad57f54db3c6d4ec003149b8642955a29e742894` |

The 5,468 ms P95 is above the approved five-second conversational warning target across a sufficient sample. It is not a critical correctness failure and does not invalidate the model-quality gate, but it must remain a visible performance warning and be remeasured on the exact release commit and production-like infrastructure.

An earlier provider attempt failed TLS verification because local Windows PHP had no CA bundle. Verification was fixed by supplying Git's installed CA bundle to the rehearsal process; certificate verification was never disabled. The next live run produced one stochastic hard failure out of 56 and was rejected. A diagnostic repeat passed 56/56, and the final combined browser/provider run above passed 56/56. The rejected results are retained as evidence that one aggregate success must not erase model variability. Rehearsal must be repeated on every governed model, prompt, schema, KB, or corpus change.

The report is content-free and retained locally under the private storage disk. It contains safe identifiers, aggregate metrics, grader codes, timings, costs, and hashes only. It contains no transcript, assembled prompt, model answer, credential, payment data, medical record, or production-derived fixture.

### Exact deployed-commit repeat

The rehearsal was repeated from clean deployed commit `003c7ccd09249be1fa6c03b731431c7126bb7778`. It passed the browser gate, all 56 provider cases with zero hard failures, all 27 extraction fields, the cost ceiling at `$0.01787196`, and verified temporary-database destruction. Its content-free result hash is `885cfc733fafa4aa8e2634a3d17d785c5b2404c998428868071f7d3a988cc421`. Provider latency was 3,173 ms P50 and 5,531 ms P95, so the performance warning remains open.

Subsequent production and accessibility execution is tracked in the [limited-release readiness execution log](32-limited-release-readiness-execution-log.md).

## Gates intentionally still open

The Admin result must remain `BLOCKED` until all required evidence is effective and there is a separate explicit release decision. Open work includes:

- dedicated provider project/restricted credential and data-sharing evidence;
- provider no-training, retention, destination/contract, deletion, and optional ZDR-request evidence;
- downstream cache/index/replica/export/backup extinction plus restore/re-deletion evidence under `DEC-058`;
- production operations-alert dispatch and actual receipt confirmation by both Administrators;
- a staff-operated exact-release-commit rehearsal and rollback/human-takeover drill;
- five qualifying older adults completing the six-task protocol, with at least 27/30 unassisted tasks and every universal comprehension condition passing;
- accessibility evidence for 200% zoom, screen reader, keyboard/focus, contrast, and touch targets;
- exactly two named Family pilot users, planned 14-day dates, and review ownership, without creating grants yet;
- remeasurement of conversational latency because the accepted local run crossed the warning target;
- explicit limited-release approval after the preflight reports `READY FOR EXPLICIT APPROVAL`.

## Deployment and rollback

The normal `deploy.sh` path may apply this additive build while customer AI remains fail-closed. After deployment, an Administrator may inspect and progressively record evidence, but must not create a pilot grant or enable a runtime control until the later release decision.

If the Admin readiness page or monitor has an application defect, roll back the code while retaining the two additive evidence tables. If a future enabled capability is automatically stopped, resolving the incident never re-enables it; restoration requires a separate audited control change after review.

## Next work

1. Deploy and production-retest the Family navigation reflow remediation recorded in the execution log.
2. Configure the dedicated provider project/secret and record only content-free evidence.
3. Run the production operations-alert receipt check and the staff rollback drill.
4. Conduct the five-person older-adult study and complete the remaining accessibility checks.
5. Name the two planned Family users without granting access.
6. Run the read-only preflight and return to Product for the explicit release decision.
