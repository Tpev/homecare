# Production Deployment, Verification, and Next-Phase Tracker

Status: Active tracker

Last updated: August 14, 2026

Owner: Product and engineering

## Executive state

The model-independent AI Support foundation is deployed to production and is failing closed. Human support remains the only customer-facing support behavior. There is no production model-call path, no active exact-user pilot grant, no published KB content, and no enabled user-visible AI control.

The deployment is a safe foundation release, not an intelligent-agent release. Work now moves from infrastructure and governance into scope decisions, approved knowledge, offline evaluation, and only later controlled shadowing.

## Deployment record

| Item | Production record |
| --- | --- |
| Deployment path | Direct `master` deployment through the existing `deploy.sh` workflow |
| Foundation commit | `decf24f66bb0848c65e63aceb05101256881b79b` |
| MySQL migration recovery | `b5df2f5a48e8eae9b27f9e33b930b7665cf53612` |
| Retention default-off guard | `338a9db25d98eff6ce096a92dda05d4a1878bee2` |
| Foundation deployed branch state | `master` and `origin/master` at `338a9db25d98eff6ce096a92dda05d4a1878bee2` before the foundation deployment |
| Phase 1A implementation | `688de776dc0fa9a18ebe9c8108ac298eec73db70`; browser-visible behavior and imported records verified August 14, 2026 |
| Phase 1A documentation | `d6bcdb14e160186664ff11cf419d334081f0e9e8` |
| Legacy primary data | Guarded destruction reported complete with content-free evidence |
| Legacy external extinction | Open until every derivative and containing backup is verified extinct or reaches its controlled final expiry |
| Customer AI runtime | Unavailable by deployment guard and absent from this phase |
| Retention execution schedule | Off by default; destructive daily execution requires explicit environment enablement |

### Migration incident and recovery

The first production attempt at `2026_08_13_100300_create_ai_support_knowledge_base_tables` failed because MySQL generated a foreign-key identifier longer than its 64-character limit. Commit `b5df2f5` replaced it with the explicit short name `kbvd_version_fk` and added fail-closed recovery for only the four new, empty, partially created KB tables. Recovery refuses to drop any of those tables if one contains data. The successful loading of the deployed KB pages provides production evidence that the repaired schema is available.

## Read-only production smoke test

Performed August 13, 2026 with an authenticated full-administrator session. No setting, pilot grant, KB entry, support message, assignment, status, or other production record was changed.

| Area | Evidence | Result |
| --- | --- | --- |
| Public invisibility | Public home page loaded with no AI assistant or AI control | Pass |
| Unauthenticated isolation | Overview, pilots, KB, activity, and settings URLs each returned `302` to `/login` without a session | Pass |
| Overview | Runtime guard off; customer state failing closed; master off; user-visible off; human-only on; zero active grants | Pass |
| Settings | Safe defaults visible for master, visibility, shadow, human-only, both roles, and support capability | Pass |
| Family pilot control | Exact-user Family bundle, dates, reason, confirmation, and higher-control warning present on a user profile | Pass, no grant created |
| Caregiver pilot control | Separate exact-user Caregiver bundle and role state present on a caregiver profile | Pass, no grant created |
| Pilot list | No current grant; grants do not inherit across accounts | Pass |
| KB index | Search/lifecycle/role filters and create-draft entry point loaded; zero entries | Pass, read-only limitation |
| KB draft editor | Answer, scope, Family/Caregiver roles, registered semantic targets, sources, boundaries, evaluations, and create action loaded | Pass, no draft created |
| Activity | Compact content-free audit view loaded; zero events | Pass |
| Human support | Queue and existing tickets loaded across statuses | Pass |
| Conversation evidence | Canonical messages, internal notes, human owner, explicit responder labels, and compact AI-evidence panel loaded | Pass |
| Automated-event state | Inspected ticket reported `human only` and no automated interaction events | Pass |
| Browser health | No application console errors during the inspected flow | Pass |

The browser emitted an existing Meta Pixel availability warning. It did not affect the application flow and is not an AI Support release blocker.

## Phase 1A production Draft audit

Performed August 14, 2026 with an authenticated full-Administrator session. No production record or setting was changed during the audit.

| Area | Evidence | Result |
| --- | --- | --- |
| Overview and fail-closed state | 12 working, 12 Draft, 0 published, 0 paused, 0 overdue; runtime guard off; customer state failing closed | Pass |
| Controls and pilots | All stored controls absent at safe defaults; human-only on; 0 active/scheduled/expiring grants; pilot list empty | Pass |
| Complete inventory | All 12 approved stable IDs present as Version 1 Drafts | Pass |
| Per-entry governance | All 12 report validation passed, five evaluation IDs, one expected role-aware target, and non-empty sources | Pass |
| Evidence totals | 60 linked entry-level evaluation IDs; 33 authoritative source records | Pass |
| Audit events | 12 successful Draft creations and 12 successful validation runs; no failed event | Pass |
| Unauthenticated isolation | All five AI Support Admin surfaces returned `302` to `/login` without a session | Pass |
| Public invisibility | Public home returned `200` and contained no AI-support marker | Pass |
| Current browser health | Final current-page console check clean; all audit routes loaded | Pass with observation |

The reused browser session contained two earlier Livewire `503` messages and one dialog-state error that did not recur during this completed audit. Investigate only if the condition recurs outside a deployment or maintenance interval.

The exact evidence, artifact checksums, and two Draft-only editorial findings are retained in the [Phase 1A governed content build record](20-phase-1a-content-build-record.md).

## Known limitations and follow-ups

| ID | Work item | Status | Blocks |
| --- | --- | --- | --- |
| `OPS-EXT-001` | Verify and close every legacy derived destination and containing-backup extinction record | In progress | Formal Phase 0 destruction closure |
| `UI-AIS-001` | Replace stale overview copy **Knowledge base: Foundation pending** with live KB counts/state | Closed; production verified August 14, 2026 | None |
| `PROD-KB-001` | Exercise create/edit/validate/publish/pause/withdraw/delete with an authorized disposable test entry and retain content-free evidence | Pending controlled production test after initial Draft import | Production mutation verification |
| `DEC-016` | English-only intelligent support across every phase | Accepted August 13, 2026 | Closed; governs KB, evaluation, unsupported-language behavior, and release |
| `SCOPE-AIS-001` | First Family answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-032` | Family KB and evaluation authoring unblocked |
| `SCOPE-AIS-002` | First Caregiver answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-033` | Caregiver KB and evaluation authoring unblocked |
| `KB-PACK-AIS-001` | Twelve-entry initial KB inventory and five-case-per-entry evaluation structure | 12 validated production Drafts verified; 60 linked cases present | Nothing is published or user-visible |
| `CONTENT-AIS-001` | Correct two grammatical strings found in `KB-SUP-002` and `KB-FAM-004`, then revalidate repository and production Drafts | Open; Draft-only, no live-user impact | Final content polish and candidate-model evaluation |
| `KB-SUP-001` | Human transfer without repetition | Validated production Draft verified | Not published |
| `KB-SUP-002` | Emergencies and non-medical support | Validated production Draft verified; one grammatical correction pending | Not published |
| `KB-SUP-003` | English-only intelligent support | Validated production Draft verified | Not published |
| `KB-FAM-001` | Family dashboard orientation | Validated production Draft verified | Not published |
| `KB-FAM-002` | Existing care requests and status | Validated production Draft verified | Not published |
| `KB-FAM-003` | Open the normal new-request form | Validated production Draft verified | Not published |
| `KB-FAM-004` | Family Account roles and access | Validated production Draft verified; one grammatical correction pending | Not published |
| `KB-FAM-005` | Family account/profile orientation | Validated production Draft verified | Not published |
| `KB-CGV-001` | Caregiver dashboard and onboarding orientation | Validated production Draft verified | Not published |
| `KB-CGV-002` | Caregiver work inbox orientation | Validated production Draft verified | Not published |
| `KB-CGV-003` | Caregiver shift orientation | Validated production Draft verified | Not published |
| `KB-CGV-004` | Caregiver account/profile orientation | Validated production Draft verified | Not published |
| `DEC-012` | Choose least-cost runtime candidate/challengers through measured offline evaluation | Pending scope/corpus | Offline runtime baseline |
| `KB-AIS-001` | Author, source, and validate the initial governed KB set | Production Draft import and browser audit complete; two editorial corrections pending | Grounded-answer evaluation and later shadow readiness; publication remains separately prohibited |
| `EVAL-AIS-001` | Build versioned offline evaluation corpus/runner and critical regression gates | 60 linked fixtures plus 10 critical cross-entry regressions implemented; deterministic validation passes | Measured candidate-model runs remain pending |
| `DEC-014` | Close suppressed/diagnostic TTLs and downstream/provider/backup extinction rules | Pending later discussion | Any production-conversation shadowing |
| `DEC-015` | Approve staffed-hours ownership and truthful human-response promise/SLO | Pending later discussion | Any user-visible pilot |

## Current phase status

| Phase | Status | Meaning |
| --- | --- | --- |
| Phase 0 | Deployed; operational extinction tail open | Runtime retired and primary data destroyed; backup/derived extinction still tracked |
| Phase 1 foundation | Deployed | Admin controls, exact-user grants, governed KB workspace, contracts, handoff, evidence, and retention foundations exist |
| Phase 1 content/evaluation | Production Drafts verified; editorial cleanup and measured runtime evaluation next | 12 validated Drafts, 60 linked fixtures, 10 cross-entry critical regressions, and fail-closed controls verified; nothing is published |
| Phase 2 shadow | Blocked | Requires Phase 1 evidence plus `DEC-014`; no real conversations may go to a model yet |
| Phase 3 grounded answers | Blocked | Requires shadow evidence, `DEC-015`, named grants, usability, monitoring, and release approval |
| Phases 4-6 | Not started | Navigation, drafting, and confirmed execution remain independently gated |

## Agreed next-work order

1. Close `CONTENT-AIS-001`: correct the two Draft-only grammatical strings in the repository manifest and production Admin Drafts, rerun validation, and record the new checksum.
2. Define the least-cost candidate model/configuration matrix and a disabled-by-default offline adapter for the approved synthetic corpus.
3. Run every candidate on the identical 70-case corpus, including at least five runs for every model-dependent critical case, and retain hard failures, quality, latency, tokens, and estimated cost.
4. Continue tracking `OPS-EXT-001` without repeating primary destruction; it remains independent from local/offline content authoring.
5. Accept `DEC-012` only from measured candidate results. Keep every production model and user-visible control disabled.
6. Complete model-dependent Phase 1 evaluation evidence and a release-readiness record without authorizing shadow or user visibility.
7. Decide `DEC-014` before any production-data shadow processing.
8. Run a controlled, non-user-visible shadow phase only after its explicit release gate.
9. Decide `DEC-015` and complete older-adult usability/support readiness before any named-user visible pilot.

The later care-request draft, confirmation lifetime, and notification/operations packages remain in the readiness ledger and do not block the next offline answer/navigation work.

## Next implementation milestone

Complete **Phase 1B — measured offline runtime selection** after the two Draft-only wording corrections.

Required outcome:

- Correct and revalidate the two editorial findings without changing lifecycle state.
- Build a provider/model adapter that remains unavailable to production runtime paths.
- Compare least-cost candidate configurations on the identical approved synthetic corpus.
- Reject any configuration with one critical hard failure.
- Record quality, latency, tokens, estimated cost, exact versions/checksums, and the recommended baseline under `DEC-012`.
- Preserve zero production transcript processing, zero user-visible model calls, zero active grants, and disabled AI controls.

Completion of this milestone selects an offline runtime baseline under `DEC-012`; it does not authorize shadowing, production transcript processing, or a user-visible pilot.
