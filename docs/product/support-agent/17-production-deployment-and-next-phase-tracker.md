# Production Deployment, Verification, and Next-Phase Tracker

Status: Active tracker

Last updated: August 13, 2026

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
| `KB-PACK-AIS-001` | Twelve-entry initial KB inventory and five-case-per-entry evaluation structure | Accepted August 13, 2026 through `DEC-034` | Entry-by-entry definition may proceed |
| `KB-SUP-001` | Human transfer without repetition | Definition accepted August 13, 2026 through `DEC-035` | Governed draft and five evaluation cases may be authored; not published |
| `DEC-012` | Choose least-cost runtime candidate/challengers through measured offline evaluation | Pending scope/corpus | Offline runtime baseline |
| `KB-AIS-001` | Author, source, validate, and publish the initial governed KB set | Pack structure approved; individual entries pending | Grounded answers and shadow readiness |
| `EVAL-AIS-001` | Build versioned offline evaluation corpus/runner and critical regression gates | Minimum 60-case structure approved; cases pending | Model selection and Phase 1 exit |
| `DEC-014` | Close suppressed/diagnostic TTLs and downstream/provider/backup extinction rules | Pending later discussion | Any production-conversation shadowing |
| `DEC-015` | Approve staffed-hours ownership and truthful human-response promise/SLO | Pending later discussion | Any user-visible pilot |

## Current phase status

| Phase | Status | Meaning |
| --- | --- | --- |
| Phase 0 | Deployed; operational extinction tail open | Runtime retired and primary data destroyed; backup/derived extinction still tracked |
| Phase 1 foundation | Deployed | Admin controls, exact-user grants, governed KB workspace, contracts, handoff, evidence, and retention foundations exist |
| Phase 1 content/evaluation | Next | Both role packages and the 12-entry structure are approved; review/author each entry and its evaluations, then select the runtime baseline |
| Phase 2 shadow | Blocked | Requires Phase 1 evidence plus `DEC-014`; no real conversations may go to a model yet |
| Phase 3 grounded answers | Blocked | Requires shadow evidence, `DEC-015`, named grants, usability, monitoring, and release approval |
| Phases 4-6 | Not started | Navigation, drafting, and confirmed execution remain independently gated |

## Agreed next-work order

1. Close the small production status defect `UI-AIS-001` and continue tracking `OPS-EXT-001` without repeating primary destruction.
2. Apply accepted `DEC-016`: all intelligent support and its KB/evaluations are English only; unsupported-language input offers human transfer without translation promises.
3. Apply accepted `DEC-032` and `DEC-033` to the role-isolated, read-only Family and Caregiver packages.
4. Apply accepted `DEC-034`: review and author the 12 governed entries individually with at least five linked evaluation cases each.
5. Evaluate the least-cost runtime candidates and accept `DEC-012` only from measured results.
6. Complete the Phase 1 evaluation runner, critical regressions, offline quality/cost/latency baseline, and release evidence.
7. Decide `DEC-014` before any production-data shadow processing.
8. Run a controlled, non-user-visible shadow phase only after its explicit release gate.
9. Decide `DEC-015` and complete older-adult usability/support readiness before any named-user visible pilot.

The later care-request draft, confirmation lifetime, and notification/operations packages remain in the readiness ledger and do not block the next offline answer/navigation work.

## Next decision to discuss

`KB-SUP-002` - emergencies and LoLo's non-medical limitation.

Proposed deterministic answer paths:

- Title: **Emergencies and non-medical support**
- Roles: Family and Caregiver
- Type: Escalation
- Sensitivity: Authenticated
- Semantic destination: `support.center`
- Immediate-danger answer: **LoLo is not an emergency service. If someone is in immediate danger or needs urgent medical help, call 911 now. I can also transfer this conversation to LoLo Support, but that is not a substitute for emergency help.**
- Non-emergency medical/clinical answer: **LoLo supports non-medical help at home. LoLo cannot provide medical advice, diagnosis, treatment, medication decisions, or clinical services. Please contact a licensed healthcare professional for medical help. I can transfer you to LoLo Support for help using the platform.**
- Must happen deterministically: show the applicable safety instruction before offering/performing human transfer; immediate-danger handling receives the critical priority reason; automation stops after transfer.
- Must not state or infer: that LoLo contacted 911; that LoLo Support is emergency or clinical help; a diagnosis, severity assessment, medication instruction, treatment recommendation, or assurance that waiting is safe.
- Required sources: `SRC-LOLO-SAFETY-001`, `SRC-LOLO-TERMS-001`, the applicable role terms, `SRC-AI-DECISIONS-001`, and the critical safety/handoff contracts.
- Initial evaluation IDs: `EVAL-KB-SUP-002-POS`, `-BOUNDARY`, `-WRONG-ROLE`, `-UNSUPPORTED-STATE`, and `-HANDOFF`, plus a critical paraphrase corpus required to pass at 100%.

Approval of this entry definition will permit governed draft and evaluation authoring only. It will not publish the entry, enable a model, or replace deterministic safety detection and routing.

No control, pilot grant, model call, or user-visible behavior changes when this documentation decision is recorded.
