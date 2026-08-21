# Family Goal-Guided Journeys — Batch 10 Implementation Record

Status: Deployed and active for the exact two-user Family pilot; authenticated production audit and both correction rechecks completed August 21, 2026; final evidence is recorded in [document 57](57-batch-10-production-audit-corrections.md)

Implemented: August 20, 2026

Owner: Product and Engineering

Authority: [Family Goal-Guided Journeys — Batch 10](54-family-goal-guided-journeys.md) and `DEC-082`

## Outcome delivered

Batch 10 adds a Family-only journey layer above the existing Batches 1–9 operating contracts. LoLo now retains one plain-language goal per automated support conversation, restores it after refresh or registered navigation, shows the current step, keeps ordinary detours attached to the original goal, and records a truthful completed, cancelled, expired, or human-owned ending.

The primary journey begins with an incomplete care need. The deterministic decision layer:

- recommends one-time care for one specific visit or date;
- recommends regular care only for an explicit weekly pattern;
- treats several non-weekly dates as separate one-time requests, starts the first, and retains the remaining dates in the encrypted journey context;
- asks exactly whether the help is for one date or repeats every week when the schedule is ambiguous;
- leaves 24/7, medical, emergency, and human-request handling in the existing higher-priority transfer paths; and
- requires an explicit button choice before starting or changing a request draft.

After selection, Batch 10 reuses the existing one-time/recurring request draft, one-question continuation, deterministic recap, 30-minute reconfirmation, idempotent publisher, verification, and receipt. A natural-language type correction clears only schedule fields incompatible with the new type and preserves compatible recipient, task, address, and note fields.

## Persistent journey contract

Migration `2026_08_20_100100_create_ai_support_goal_journeys_table` adds one encrypted, actor/account/ticket-bound journey record with:

- versioned journey type and current step;
- simple progress and terminal state;
- encrypted minimal continuation context;
- start, activity, completion, cancellation, transfer, and expiry timestamps; and
- a seven-day content-retention boundary.

Care-path and different-goal choices are encrypted, actor-bound, conversation-bound, 30-minute, atomic, and single-use. A stale, consumed, expired, cancelled, transferred, or superseded journey cannot use an old choice. The retention command clears expired journey context and invalidates remaining choice payloads while respecting Support-ticket and journey holds.

Human transfer preserves the goal and current step in the existing conversation and adds a compact internal summary. A deliberate Administrator return restores the safe journey and any uncompleted request draft. Transfer never turns page arrival, user wording, or model wording into completion evidence.

## Initial catalog

The versioned `family-goal-journeys-v1` catalog contains exactly these ten Family goals:

1. Choose care and create a request.
2. Complete a care-receiver profile.
3. Add or change a payment method.
4. Fix a payment problem.
5. Review caregivers and hire.
6. Manage a visit or submitted hours.
7. Manage regular care.
8. Find past care or book again.
9. Manage messages and notifications.
10. Talk to a person.

The catalog composes the existing 324-intent mappings, authorized readers, semantic destinations, guided tasks, preparations, confirmed tools, verifiers, and handoff service. It adds no generic database, ORM, route, DOM, selector, browser-control, or unrestricted action tool.

When the user deliberately asks for a different goal, the assistant presents four explicit choices: continue the current goal, start the new goal, say they are unsure, or talk to a person. Care-profile, payment-method, and payment-failure prerequisites may run as care-request detours and then return to the request without creating a second active journey.

## Older-adult experience

The support chat now shows the active goal, a plain `Step n of n` statement, and one next instruction both inside the open panel and in the minimized guide strip. Resume, Stop, care-choice, different-goal, and human-help actions retain at least a 44-pixel target. The implementation keeps the established latest-message, immediate composer clearing, bottom anchoring, focus preservation, Enter-to-send, mobile sheet, reflow, and accessible-label behavior.

## Knowledge and model result

No new broad KB package was created. Batch 10 reuses the source-complete 324 / 324 explicit mappings and the existing Batches 1–9 governed packages. Personalized status and completion statements still come from authorized readers, domain receipts, or fresh verifiers rather than KB prose.

The production serving model and price configuration did not change. The prompt contract advances from `interactive-support-v7` to `interactive-support-v8` only to carry the bounded active-goal context, irregular-date rule, and server-owned request-type changes. Deterministic care choice, continuation, polling, refresh restoration, cancellation, and retention make no provider call.

## Verification

All verification used local or isolated test data. No production record, pilot grant, Availability control, KB publication, payment record, or live care request was changed.

| Verification | Result |
| --- | ---: |
| Frozen care-choice corpus | 48 / 48 passed; no provider |
| Focused Batch 10 feature suite | 14 tests / 108 assertions passed |
| Complete AI Support feature suite after production-audit corrections | 238 tests / 5,846 assertions passed |
| Executable Family catalog | 324 / 324 valid |
| Explicit KB mappings | 324 / 324 valid |
| Family routing phrases | 1,017 / 1,017 passed across established, Batch 5, Batch 6/7, and Batch 8/9 corpora |
| Near-neighbor collisions | 10 / 10 passed |
| Isolated Family Batch 1–9 application regression | 127 tests / 4,898 assertions passed |
| Frontend production build | Passed |
| Blade compilation | Passed |
| Mobile Chromium Batch 10 journey | Passed at 390×844, including choice, 44px targets, refresh, correction, cancellation, and no horizontal overflow |

Focused coverage includes one-time, regular, irregular-date, ambiguous, unrelated, and 24/7 classification; explicit choice; safe type correction; persistence; different-goal choice; prerequisite detours; transfer/return; cross-account denial; cancellation; single-use actions; expiry/content deletion; and exact catalog validation. Existing request/status guidance regressions prove that operational questions such as “When is my next visit?” are not captured as new-care intake.

## State separation and next production step

| State | Batch 10 position |
| --- | --- |
| Source | Complete |
| New KB publication | None required for Batch 10; Batches 6–9 production publication remains pending |
| Deployment | Batch 10 deployed; audit correction deployment pending |
| Exact-pilot activation | Active for the exact two approved Family users only |
| Live for everyone | Not enabled and not authorized by this work |
| Production authenticated browser audit | Completed August 21, 2026; six corrections implemented and awaiting deployment/recheck |
| Caregiver AI | Deferred and unchanged |

Batch 10 was deployed and exercised through the exact two-user pilot. The audit proved care-type selection, secure payment navigation, chat composer behavior, mobile layout, deterministic 24/7 transfer, transcript visibility, and absence of a live synthetic request. It also found six state/continuation defects and one production-capability routing mismatch; all are corrected, regression-tested, deployed, and authenticated in [document 57](57-batch-10-production-audit-corrections.md). **Live for everyone** remains off.
