# Production Deployment, Verification, and Next-Phase Tracker

Status: Active tracker

Last updated: August 20, 2026

Owner: Product and engineering

## Executive state

AI Support is operating in **Pilot only** mode for the two exact Family users approved under `DEC-071`. The Administrator **Live for everyone** switch remains off. Human Only remains the immediate emergency-stop path, and human transfer remains available in the same support conversation.

### Current source/deployment separation — August 20, 2026

- Production evidence in this tracker remains authoritative only for Batches 1–5 and their recorded corrections.
- Batches 6–9 are source-complete but their new KB packages, exact-pilot capability activation, and authenticated production audit remain pending.
- Batch 10 Family goal-guided journeys are source-complete at the [implementation record](56-family-goal-guided-journeys-batch-10-implementation-record.md): 10 versioned goals, 48 / 48 care-choice cases, 233 AI Support tests / 5,815 assertions, 324 / 324 catalog/mapping validation, 127 isolated Family tests / 4,898 assertions, frontend/Blade builds, and one 390×844 Chromium journey pass.
- Batch 10 added no production mutation, KB publication, pilot grant, Availability change, model/provider change, Caregiver behavior, or payment-policy change.
- The next release operation is one normal `deploy.sh` deployment for Batches 6–10, Batches 6–9 governed publication and exact-two-user activation, then a combined authenticated audit with cleanup. Availability must remain **Pilot only** unless Product separately selects **Live for everyone**.

The repository now contains guided-assistance Batch 1 for saved payment methods and Batch 2 for Family overview, requests/applicants, visits/change requests, submitted hours/care-payment attention, profiles, messages, regular-care attention, and history. Production availability is independent of deployment and remains Pilot only unless an Administrator deliberately changes it.

The registry-driven [Family Batch 1-2 evaluation harness](40-family-batch-1-2-evaluation-harness.md) passes all 40 declared rows, 120 representative phrasings, 10 collision cases, and 29 isolated application tests with 355 assertions. The complete AI Support feature regression also passes 133 tests with 1,174 assertions. The harness makes zero provider calls and does not use production data. Commit `0655b5b54e12ff8abffb6d00dcb81b723bd4a504` is deployed and its production `--plan` inventory passes.

Authenticated live QA reconfirmed Pilot only with exact Family users `19` and `282`, then found that background polling reclaimed a resolved chat immediately after **Start a new conversation**, blocking the first new message. The server-owned reset-state correction was deployed and verified beyond the polling interval; no Batch 1/2 journey is credited from the original blocked attempt.

After deployment, the reset remained stable beyond polling and the live Family overview answer returned current-state guide buttons. The first new-ticket response then exposed a split Alpine state and four null pending-message errors; subsequent visible typing could leave Send disabled until reload. The grouped client correction now passes a two-message/no-reload Chromium regression with zero page errors. A natural “yes do it” follow-up to Billing guidance also transferred safely to human because it was not linked to the latest action; keep that conversational gap separate from any authority to auto-confirm a write.

Historical foundation and interactive evidence remain in the [interactive assistant implementation record](24-interactive-assistant-implementation-and-release-evidence.md) and [production deployment audit](25-production-interactive-deployment-audit.md). Historical readiness gates no longer control availability under `DEC-072`.

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
| Interactive request deployment | `61fca0341a467e2cb2ea65f559bd81c7c268658f`; authenticated production UI and fail-closed behavior verified August 14, 2026; exact server `HEAD` is not exposed by the inspected pages; not pilot-authorized |
| Interactive release evidence | `e8694a8ee71e5de7382c159058efca709d60f264` |
| Interactive KB production import | `KB-CARE-001` through `KB-CARE-012` imported and authenticated-audited August 14, 2026: 12 creations, 12 validation passes, 0 failures, 24 total Drafts, 0 published |
| Settings cleanup and governed publication | `4ac0f07` deployed and Shadow absent; 23 reviewed non-pricing Version 1 entries published; `KB-CARE-006` remains Draft; both guards off, human-only on, zero grants |
| Legacy primary data | Guarded destruction reported complete with content-free evidence |
| Legacy external extinction | Open until every derivative and containing backup is verified extinct or reaches its controlled final expiry |
| Customer AI runtime | Code present but unavailable through both disabled deployment guards and deny-by-default controls |
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

## August 15 limited-release checkpoint

- Standard production deployment completed at `25fcff94ebb6afd233cb62a0161fd885359e8b20`.
- The configured OpenAI key authenticated through the exact standard destination using the content-free current-key verifier. Project-scoped key form was observed; exact project identity was deferred under `DEC-068`; sharing and retention settings were not inferred; the external `$25` alert was treated as optional.
- The following read-only preflight remained `BLOCKED` at 12 of 21 checks passing, with zero incidents, both deployment guards off, only human-only enabled, and zero non-revoked grants.
- Product scheduled the prepared two-Family-user pilot window to start August 15, 2026 and expire August 29, 2026. The candidates remain safe production IDs `282` and `19`; reviewers remain full Administrators `1` and `18`.
- The first browser submission of the dated evidence made no change because the existing Admin session expired. After a fresh full-Administrator login, the configured credential, application cost controls, and exact pilot-user/window records were each recorded as Passed and set to expire August 29.
- The authoritative Admin state is now 15 of 21 checks passing, six required checks open, zero incidents, zero warnings, both deployment guards off, only human-only enabled, and zero non-revoked grants.
- Direct read-only profile checks reconfirmed Family IDs `282` and `19` as `NOT ENABLED`, blocked by the runtime deployment guard, with grant history `0`; no grant form was submitted.
- The focused last-mile release path passes 22 tests and 123 assertions, including exact-user non-inheritance, expiry/revocation, higher-control denial, emergency/24/7 transfer, takeover precedence, confirmation invalidation, idempotent publication, and human fallback.
- The schedule is not activation: no grant has been created and the remaining provider controls/contract, extinction/restore, staffed rollback, real screen-reader, and older-adult study gates still require evidence before an explicit release decision.

### Accepted Option B release-policy implementation

Product accepted `DEC-070` Option B. The new package keeps the six unresolved items visibly Deferred rather than Passed, allows them to satisfy only the exact initial-pilot scope, and keeps expansion Blocked. It hard-enforces Family IDs `19` and `282`, bundle `family_support_v1`, maximum two expiring grants, the August 15-29 window, one-time commit/tool only, and a persisted exact-deployed-commit release decision before any grant or exposure-opening stored control.

Deployment of this package still creates no grant, changes no stored control, leaves both deployment guards off, and performs no provider call. Production must run the bounded deferral command and both preflight scopes before Product makes the separate explicit release decision.

## Interactive deployment production audit

The initial read-only portion was performed August 14, 2026 with an authenticated full-Administrator session followed by the dedicated production editorial Family test account. A later authorized Admin session performed only the governed KB lifecycle changes recorded in the [publication verification](26-production-kb-publication-and-settings-verification.md); it created no message, request, grant, control version, or assignment.

- Admin overview, pilots, KB, editor, activity, and settings surfaces render.
- Both deployment guards and every AI capability/role control are off; human-only is on; there are zero active grants; 23 non-pricing entries are published and the held pricing entry remains Draft.
- The customer support dialog remains human-only and fits a 390-pixel viewport with no horizontal overflow and 44-pixel visible controls.
- A fresh authenticated flow produced zero current application console errors.
- All 12 `KB-CARE-*` entries are present and validated with authoritative sources and 60 linked evaluations; 11 are Published and held `KB-CARE-006` remains Draft.
- The import activity contains 12 successful creation events and 12 successful validation events with no failure.
- The permanently denied `shadow_enabled` control is absent from production Settings after deployment of `4ac0f07`.

Full evidence and next actions: [production interactive assistant deployment audit](25-production-interactive-deployment-audit.md).

## Known limitations and follow-ups

| ID | Work item | Status | Blocks |
| --- | --- | --- | --- |
| `OPS-EXT-001` | Verify and close every legacy derived destination and containing-backup extinction record | In progress | Formal Phase 0 destruction closure |
| `UI-AIS-001` | Replace stale overview copy **Knowledge base: Foundation pending** with live KB counts/state | Closed; production verified August 14, 2026 | None |
| `PROD-KB-001` | Exercise create/edit/validate/publish/pause/withdraw/delete with an authorized disposable test entry and retain content-free evidence | Pending controlled production test after initial Draft import | Production mutation verification |
| `DEC-016` | English-only intelligent support across every phase | Accepted August 13, 2026 | Closed; governs KB, evaluation, unsupported-language behavior, and release |
| `SCOPE-AIS-001` | First Family answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-032` | Family KB and evaluation authoring unblocked |
| `SCOPE-AIS-002` | First Caregiver answer topics and semantic navigation targets | Accepted August 13, 2026 through `DEC-033` | Caregiver KB and evaluation authoring unblocked |
| `KB-PACK-AIS-001` | Twelve-entry initial KB inventory and five-case-per-entry evaluation structure | All 12 Version 1 entries validated and Published; 60 linked cases present | Customer AI remains disabled by independent gates |
| `CONTENT-AIS-001` | Correct two grammatical strings found in `KB-SUP-002` and `KB-FAM-004`, then revalidate repository and production Drafts | Closed August 14, 2026; both production Drafts and repository content revalidated | None |
| `KB-SUP-001` | Human transfer without repetition | Validated Version 1 Published | Customer AI disabled |
| `KB-SUP-002` | Emergencies and non-medical support | Corrected, revalidated, and Published | Customer AI disabled |
| `KB-SUP-003` | English-only intelligent support | Validated Version 1 Published | Customer AI disabled |
| `KB-FAM-001` | Family dashboard orientation | Validated Version 1 Published | Customer AI disabled |
| `KB-FAM-002` | Existing care requests and status | Validated Version 1 Published | Customer AI disabled |
| `KB-FAM-003` | Open the normal new-request form | Validated Version 1 Published | Customer AI disabled |
| `KB-FAM-004` | Family Account roles and access | Corrected, revalidated, and Published | Customer AI disabled |
| `KB-FAM-005` | Family account/profile orientation | Validated Version 1 Published | Customer AI disabled |
| `KB-CGV-001` | Caregiver dashboard and onboarding orientation | Validated Version 1 Published | Customer AI disabled |
| `KB-CGV-002` | Caregiver work inbox orientation | Validated Version 1 Published | Customer AI disabled |
| `KB-CGV-003` | Caregiver shift orientation | Validated Version 1 Published | Customer AI disabled |
| `KB-CGV-004` | Caregiver account/profile orientation | Validated Version 1 Published | Customer AI disabled |
| `DEC-012` | Choose least-cost runtime candidate/challengers through measured offline evaluation | Accepted: Luna low baseline; Mini low challenger; 556/556 calls, zero hard/critical failures, 99.64% quality each; Luna 83.95% lower measured cost | Offline runtime baseline complete; no production authority |
| `KB-AIS-001` | Author, source, validate, and selectively publish governed KB | 24 validated Version 1 entries and 120 entry-linked evaluations; 23 non-pricing entries Published; `KB-CARE-006` held as Draft | Customer AI remains disabled pending release gates |
| `EVAL-AIS-001` | Build versioned offline evaluation corpus/runner and critical regression gates | Complete: v4 corpus/runner, isolation controls, 556-call current-candidate release comparison, exact checksums, and accepted baseline recorded | Re-run on every governed model/prompt/schema/KB change |
| `DEC-014` | Close suppressed/diagnostic TTLs and downstream/provider/backup extinction rules | Closed by `DEC-058` | Destination/configuration and deletion evidence still block production data |
| `DEC-015` | Approve human ownership and truthful response promise | Closed by `DEC-057`: both admins alerted; either may claim; no time/business-hours/queue promise | Implementation and rehearsal still block pilot |
| `INT-AIS-001` | Approved interactive Family build contract | Implemented; deterministic/browser/live-model evidence pass | Provider/privacy, production-like rehearsal, older-adult usability, named pilot, and release approval |
| `INT-AIS-002` | Expanded 12-entry interactive KB package | Validation, sources, and 60 cases verified; 11 non-pricing entries Published; `KB-CARE-006` remains Draft; no publication failure | Customer AI remains disabled pending release gates |
| `EVAL-INT-001` | Frozen 56-case interactive runtime gate | Prompt v3: 56/56, 100% extraction, zero hard failures, P95 4.769 seconds | Re-run on any governed model/prompt/schema/KB/corpus change |
| `PROD-KB-INT-001` | Import `KB-CARE-001` through `KB-CARE-012` into production | Closed August 14, 2026; authenticated audit verified all records, validations, sources, evaluations, events, and unchanged publication/activation state | None |
| `PROD-UI-AIS-002` | Remove permanently denied shadow control from the Admin selector | Closed in production August 14, 2026; state list and selector omit it while server denial remains | None |

## Current phase status

| Phase | Status | Meaning |
| --- | --- | --- |
| Phase 0 | Deployed; operational extinction tail open | Runtime retired and primary data destroyed; backup/derived extinction still tracked |
| Phase 1 foundation | Deployed | Admin controls, exact-user grants, governed KB workspace, contracts, handoff, evidence, and retention foundations exist |
| Phase 1 content/evaluation | Complete; offline Luna-low baseline accepted under `DEC-012` | 23 reviewed entries Published, held pricing entry Draft, 120 linked KB cases plus runtime corpora |
| Phase 2 shadow | Skipped by `DEC-047` | No invisible processing of production conversations will be performed |
| Two-user Family pilot | Active under `DEC-071` and simplified by `DEC-072` | Exact Family pilot only; Everyone remains off; Administrator can stop with Human Only |
| Guided assistance Batches 1-2 | Implemented; mass regression and production inventory passing | 40 declared Family rows; deploy the resolved-chat correction, then resume real-browser QA |
| General availability | Off | Only an explicit Administrator Availability change selects Live for everyone |

## Agreed next-work order

1. Deploy the grouped stable-Alpine and null-safe pending-message correction through the normal `deploy.sh` flow; confirm Availability remains Pilot only.
2. Deliberately return the current pilot conversation from human ownership to automation, or use a fresh eligible pilot chat; do not bypass takeover ownership.
3. Prove two consecutive production messages work without reload and with a clean application console.
4. Exercise every Batch 1/2 guide destination in a real browser for member authorization, mobile/reflow, keyboard/focus, refresh, missing/stale target recovery, and screen-reader behavior.
5. Add a bounded affirmative-follow-up design that can re-offer or launch the latest Read/Guide action but can never confirm a care, payment, timesheet, or other write from “yes.”
6. Review actual two-user pilot interactions for wording or state failures and add every meaningful failure to the frozen corpus.
7. Then begin Batch 3 safe prefill; do not relabel Batch 2 Guide rows as completed actions.

Continue tracking `OPS-EXT-001` as an operational data-retirement item without letting it reintroduce release-gate workflow.

## Next implementation milestone

Complete the **real-browser Batch 1/2 QA slice**, then design Batch 3 safe prefill against the 324-row Family registry.

Required outcome:

- Every registered Batch 1/2 target is exercised through actual chat action, navigation, arrival, semantic focus/highlight, and safe recovery.
- Pilot availability remains exactly two users; deployment does not switch Everyone on.
- Browser findings become frozen regression cases before Batch 3.
- Batch 3 starts with narrow reversible non-secret prefill and retains normal UI validation/save authority.

The [simplified availability contract](37-simplified-availability.md), [Family coverage registry](38-family-intent-action-coverage-registry.md), [guided-assistance contract](39-app-aware-guided-assistance.md), and [mass-test harness](40-family-batch-1-2-evaluation-harness.md) are the current operating documents. Older readiness packages are retained only as history.
