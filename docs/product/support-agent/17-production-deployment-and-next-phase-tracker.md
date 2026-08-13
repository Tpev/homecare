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
| Deployed branch state | `master` and `origin/master` at `338a9db25d98eff6ce096a92dda05d4a1878bee2` before deployment |
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

## Known limitations and follow-ups

| ID | Work item | Status | Blocks |
| --- | --- | --- | --- |
| `OPS-EXT-001` | Verify and close every legacy derived destination and containing-backup extinction record | In progress | Formal Phase 0 destruction closure |
| `UI-AIS-001` | Replace stale overview copy **Knowledge base: Foundation pending** with live KB counts/state | Open, non-safety | Accurate admin status only |
| `PROD-KB-001` | Exercise create/edit/validate/publish/pause/withdraw/delete with an authorized test entry and retain content-free evidence | Pending authorization and initial KB scope | Production mutation verification |
| `DEC-016` | English-only intelligent support across every phase | Accepted August 13, 2026 | Closed; governs KB, evaluation, unsupported-language behavior, and release |
| `SCOPE-AIS-001` | First Family answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-032` | Family KB and evaluation authoring unblocked |
| `SCOPE-AIS-002` | First Caregiver answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-033` | Caregiver KB and evaluation authoring unblocked |
| `KB-PACK-AIS-001` | Twelve-entry initial KB inventory and five-case-per-entry evaluation structure | All 12 definitions accepted through `DEC-046`; 60 named entry-level cases authorized | Governed record and evaluation-fixture implementation may proceed; nothing published |
| `KB-SUP-001` | Human transfer without repetition | Definition accepted August 13, 2026 through `DEC-035` | Governed draft and five evaluation cases may be authored; not published |
| `KB-SUP-002` | Emergencies and non-medical support | Definition accepted August 13, 2026 through `DEC-036` | Governed draft and critical evaluation corpus may be authored; not published |
| `KB-SUP-003` | English-only intelligent support | Definition accepted August 13, 2026 through `DEC-037` | Governed draft and five evaluation cases may be authored; not published |
| `KB-FAM-001` | Family dashboard orientation | Definition accepted August 13, 2026 through `DEC-038` | Governed draft and five evaluation cases may be authored; not published |
| `KB-FAM-002` | Existing care requests and status | Definition accepted August 13, 2026 through `DEC-039` | Governed draft and five evaluation cases may be authored; not published |
| `KB-FAM-003` | Open the normal new-request form | Definition accepted August 13, 2026 through `DEC-040` | Governed draft and five evaluation cases may be authored; not published |
| `KB-FAM-004` | Family Account roles and access | Definition accepted August 14, 2026 through `DEC-041` | Governed draft and five evaluation cases may be authored; not published |
| `KB-FAM-005` | Family account/profile orientation | Definition accepted August 14, 2026 through `DEC-042` | Governed draft and five evaluation cases may be authored; not published |
| `KB-CGV-001` | Caregiver dashboard and onboarding orientation | Definition accepted August 14, 2026 through `DEC-043` | Governed draft and five evaluation cases may be authored; not published |
| `KB-CGV-002` | Caregiver work inbox orientation | Definition accepted August 14, 2026 through `DEC-044` | Governed draft and five evaluation cases may be authored; not published |
| `KB-CGV-003` | Caregiver shift orientation | Definition accepted August 14, 2026 through `DEC-045` | Governed draft and five evaluation cases may be authored; not published |
| `KB-CGV-004` | Caregiver account/profile orientation | Definition accepted August 14, 2026 through `DEC-046` | Governed draft and five evaluation cases may be authored; not published |
| `DEC-012` | Choose least-cost runtime candidate/challengers through measured offline evaluation | Pending scope/corpus | Offline runtime baseline |
| `KB-AIS-001` | Author, source, and validate the initial governed KB set | All definitions approved; repository manifest/import and Admin-draft records pending | Grounded-answer evaluation and later shadow readiness; publication remains separately prohibited |
| `EVAL-AIS-001` | Build versioned offline evaluation corpus/runner and critical regression gates | Sixty named case definitions approved; executable fixtures/graders pending | Model selection and Phase 1 exit |
| `DEC-014` | Close suppressed/diagnostic TTLs and downstream/provider/backup extinction rules | Pending later discussion | Any production-conversation shadowing |
| `DEC-015` | Approve staffed-hours ownership and truthful human-response promise/SLO | Pending later discussion | Any user-visible pilot |

## Current phase status

| Phase | Status | Meaning |
| --- | --- | --- |
| Phase 0 | Deployed; operational extinction tail open | Runtime retired and primary data destroyed; backup/derived extinction still tracked |
| Phase 1 foundation | Deployed | Admin controls, exact-user grants, governed KB workspace, contracts, handoff, evidence, and retention foundations exist |
| Phase 1 content/evaluation | In progress | All 12 definitions and 60 named cases are approved; implement governed drafts, executable fixtures, deterministic validation, then select the runtime baseline |
| Phase 2 shadow | Blocked | Requires Phase 1 evidence plus `DEC-014`; no real conversations may go to a model yet |
| Phase 3 grounded answers | Blocked | Requires shadow evidence, `DEC-015`, named grants, usability, monitoring, and release approval |
| Phases 4-6 | Not started | Navigation, drafting, and confirmed execution remain independently gated |

## Agreed next-work order

1. Implement the approved [Phase 1 content and evaluation build plan](19-phase-1-content-and-evaluation-build-plan.md): versioned initial-content manifest, safe draft-only importer, 12 governed Admin drafts, and 60 executable evaluation fixtures.
2. Close `UI-AIS-001` so the Admin overview reports actual KB counts/state before operators inspect the imported drafts.
3. Run deterministic KB schema, source, role, target, draft-isolation, wrong-role, handoff, and critical safety validation with zero hard failures.
4. Continue tracking `OPS-EXT-001` without repeating primary destruction; it remains independent from local/offline content authoring.
5. Evaluate the least-cost runtime candidates on the identical approved corpus and accept `DEC-012` only from measured quality, hard-failure, latency, and cost evidence.
6. Complete the Phase 1 evaluation runner, critical regressions, offline baseline, and release evidence. Keep every production model and user-visible control disabled.
7. Decide `DEC-014` before any production-data shadow processing.
8. Run a controlled, non-user-visible shadow phase only after its explicit release gate.
9. Decide `DEC-015` and complete older-adult usability/support readiness before any named-user visible pilot.

The later care-request draft, confirmation lifetime, and notification/operations packages remain in the readiness ledger and do not block the next offline answer/navigation work.

## Next implementation milestone

Deliver **Phase 1A — governed content and executable evaluations** from [the approved build plan](19-phase-1-content-and-evaluation-build-plan.md).

Required outcome:

- Twelve repository-controlled initial definitions map deterministically to the existing KB schema.
- A dry-run-first, idempotent import path creates or reconciles **Draft** versions only and refuses unsafe overwrites.
- The Admin UI shows and permits normal single-operator review/edit lifecycle for all imported entries.
- All 60 named evaluation cases exist as executable fixtures with hard constraints and deterministic checks where possible.
- Draft exclusion, role isolation, registered-target validity, emergency/handoff behavior, and zero production model/user visibility remain proven.
- No entry is approved or published by the importer, and no production model call or pilot grant is enabled.

Completion of this milestone moves the program to measured model/configuration selection under `DEC-012`; it does not authorize shadowing or a user-visible pilot.
