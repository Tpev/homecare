# Production Deployment, Verification, and Next-Phase Tracker

Status: Active tracker

Last updated: August 14, 2026

Owner: Product and engineering

## Executive state

The currently deployed model-independent AI Support foundation is failing closed. Human support remains the only customer-facing support behavior in that revision. The repository candidate now contains the interactive model-call path, but it remains disabled by default: there is no active exact-user pilot grant, no published KB content, and no enabled user-visible AI control.

The deployment is a safe foundation release, not an intelligent-agent release. The interactive runtime/request candidate is now implemented and has deterministic, browser, accessibility, migration, cost, and live Luna-low evidence, but it is not yet recorded as deployed or authorized for a pilot. Product explicitly skipped production-conversation shadowing under `DEC-047`; no production model use or user-visible AI is thereby authorized.

Candidate evidence: [interactive assistant implementation and release evidence](24-interactive-assistant-implementation-and-release-evidence.md).

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
| Interactive request candidate | `61fca0341a467e2cb2ea65f559bd81c7c268658f`; implementation, tests, governed content, model evaluation, browser evidence, and release record; not yet deployed or pilot-authorized |
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
| `CONTENT-AIS-001` | Correct two grammatical strings found in `KB-SUP-002` and `KB-FAM-004`, then revalidate repository and production Drafts | Closed August 14, 2026; both production Drafts and repository content revalidated | None |
| `KB-SUP-001` | Human transfer without repetition | Validated production Draft verified | Not published |
| `KB-SUP-002` | Emergencies and non-medical support | Corrected and revalidated production Draft verified | Not published |
| `KB-SUP-003` | English-only intelligent support | Validated production Draft verified | Not published |
| `KB-FAM-001` | Family dashboard orientation | Validated production Draft verified | Not published |
| `KB-FAM-002` | Existing care requests and status | Validated production Draft verified | Not published |
| `KB-FAM-003` | Open the normal new-request form | Validated production Draft verified | Not published |
| `KB-FAM-004` | Family Account roles and access | Corrected and revalidated production Draft verified | Not published |
| `KB-FAM-005` | Family account/profile orientation | Validated production Draft verified | Not published |
| `KB-CGV-001` | Caregiver dashboard and onboarding orientation | Validated production Draft verified | Not published |
| `KB-CGV-002` | Caregiver work inbox orientation | Validated production Draft verified | Not published |
| `KB-CGV-003` | Caregiver shift orientation | Validated production Draft verified | Not published |
| `KB-CGV-004` | Caregiver account/profile orientation | Validated production Draft verified | Not published |
| `DEC-012` | Choose least-cost runtime candidate/challengers through measured offline evaluation | Accepted: Luna low baseline; Mini low challenger; 556/556 calls, zero hard/critical failures, 99.64% quality each; Luna 83.95% lower measured cost | Offline runtime baseline complete; no production authority |
| `KB-AIS-001` | Author, source, and validate the initial governed KB set | Initial production Draft import and editorial corrections complete; expanded 12-entry package and 60 linked evaluations complete in the repository | Expanded package still requires production Draft import, review, and selective non-pricing publication |
| `EVAL-AIS-001` | Build versioned offline evaluation corpus/runner and critical regression gates | Complete: v4 corpus/runner, isolation controls, 556-call current-candidate release comparison, exact checksums, and accepted baseline recorded | Re-run on every governed model/prompt/schema/KB change |
| `DEC-014` | Close suppressed/diagnostic TTLs and downstream/provider/backup extinction rules | Closed by `DEC-058` | Destination/configuration and deletion evidence still block production data |
| `DEC-015` | Approve human ownership and truthful response promise | Closed by `DEC-057`: both admins alerted; either may claim; no time/business-hours/queue promise | Implementation and rehearsal still block pilot |
| `INT-AIS-001` | Approved interactive Family build contract | Implemented; deterministic/browser/live-model evidence pass | Provider/privacy, production-like rehearsal, older-adult usability, named pilot, and release approval |
| `INT-AIS-002` | Expanded 12-entry interactive KB package | Repository manifest and 60 linked cases complete; isolated Draft import verified | Production Draft import, review, and selective non-pricing publication |
| `EVAL-INT-001` | Frozen 56-case interactive runtime gate | Prompt v3: 56/56, 100% extraction, zero hard failures, P95 4.769 seconds | Re-run on any governed model/prompt/schema/KB/corpus change |

## Current phase status

| Phase | Status | Meaning |
| --- | --- | --- |
| Phase 0 | Deployed; operational extinction tail open | Runtime retired and primary data destroyed; backup/derived extinction still tracked |
| Phase 1 foundation | Deployed | Admin controls, exact-user grants, governed KB workspace, contracts, handoff, evidence, and retention foundations exist |
| Phase 1 content/evaluation | Complete; offline Luna-low baseline accepted under `DEC-012` | 12 validated Drafts, 70 fixtures, 556/556 current-candidate calls, zero hard/critical failures, exact evidence recorded; nothing is published and runtime remains off |
| Phase 2 shadow | Skipped by `DEC-047` | No invisible processing of production conversations will be performed |
| Phase 3 grounded answers | Implemented; release blocked | Requires reviewed applicable KB publication, provider/privacy, staff-account evidence, usability, monitoring, exact grants, and release approval |
| Phases 4-6 | Implemented behind independent controls; release blocked | Navigation, Family context/intake, drafts, recap, and one-time/recurring confirmed publication have evidence but no production authority |

## Agreed next-work order

1. Complete final repository review, commit, and push the implementation candidate to `master` when authorized.
2. Deploy through the unchanged `deploy.sh` with both AI deployment guards false; verify migrations and public invisibility.
3. Dry-run and apply the expanded KB import as validated Drafts, then review and selectively publish only the non-pricing entries required by the pilot.
4. Produce provider/retention, staff-account, human-handoff, monitoring, cost-stop, and rollback evidence; do not build shadow mode.
5. Complete the five-person representative older-adult study and comprehension/accessibility gates.
6. Continue tracking `OPS-EXT-001` without repeating primary destruction.
7. Request an explicit release decision before activating the first two exact Family grants; enable recurring publication only after the one-time gate.

All product packages for the declared build are closed. The [readiness ledger](14-build-readiness-ledger.md) now tracks delivery evidence rather than product interviews.

## Next implementation milestone

Complete **fail-closed deployment and limited-release evidence** without enabling a production user prematurely.

Required outcome:

- Deploy and smoke-test the implemented intake, context, draft, recap, publication, and 24/7 handoff capabilities with deny-by-default controls.
- Import the expanded KB/evaluation package as Drafts and close the remaining production portions of `DEC-058`, `DEC-064`, `DEC-065`, and `DEC-066`.
- Preserve zero production conversation processing until a visible named-user pilot release is explicitly approved.
- Preserve zero user-visible model calls, zero active grants, Draft-only KB content, and disabled AI controls.

The accepted product package authorizes engineering, not release. No production transcript processing, exact-user grant, or user-visible pilot is authorized by documentation alone.
