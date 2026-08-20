# Visits, Submitted Hours, and Regular Care — Batch 7

Status: Source implementation complete; automated verification complete; production deployment and authenticated pilot audit pending; **Live for everyone remains off**

Implemented: August 20, 2026

Scope: `FAM-VISIT-001` through `FAM-VISIT-035` and `FAM-REGULAR-001` through `FAM-REGULAR-026`

## Outcome

Batch 7 extends the Family assistant from request creation and hiring into active care. The support conversation can read authoritative visit, submitted-hours, correction, payment, and regular-care state; navigate to exact existing workflows; and complete a bounded set of reversible or explicitly confirmed domain actions.

The assistant does not adjudicate disputes, invent schedule state, expose another household, or bypass secure payment. Exceptional safety, payment, audit-record, and caregiver-replacement cases transfer to human support in the same conversation.

## Visit and submitted-hours coverage

| Family goal | Assistant behavior |
| --- | --- |
| Find the next/current visit | Read caregiver, date/time, duration, location summary, tasks, instructions, and current status from the authorized booking |
| Message the hired caregiver | Use the exact authorized conversation and confirmed-message flow from Batch 6 |
| Request a reschedule or cancellation | Require one booking, reason, and—when rescheduling—future start/end; recap and send a pending change request |
| Cancel a scheduled visit directly | Show the visit, reason, payment effect, and whether it is inside the current 24-hour late-cancellation window; confirm and verify |
| Review a caregiver change | Compare current and proposed state; accept or reject only a current caregiver-created pending request |
| Understand check-in, late arrival, or no-show | Read authoritative state; allow no-show only while still scheduled and at least 30 minutes after start |
| Mark an in-progress visit complete | Recap, confirm, update, and verify; does not silently submit a review |
| Review submitted hours | Show scheduled versus worked time, task/note state, and current payment state |
| Approve submitted hours | Show duration, `$30/hour`, estimated amount, and payment effect; call the existing capture workflow after confirmation |
| Request or approve a time correction | Compare the current/proposed record and financial preview; confirmation either sends changes or invokes the audited correction service |
| Rate and review | Prepare 1–5 stars and optional text, recap, submit, and verify |
| Rebook the same caregiver | Create a separate future one-time request and invitation; never claim acceptance |
| Convert successful care to regular care | Prepare the separate regular-care offer and acceptance lifecycle |
| Safety, dispute, correction escalation, or completed-record alteration | Transfer with authorized booking/correction context; never make a fault or payment finding |

## Regular-care coverage

| Family goal | Assistant behavior |
| --- | --- |
| Understand plans, offers, and upcoming visits | Read the exact Family plan, its authoritative status, schedule, caregiver, and generated visits |
| Send a regular-care offer | Use an eligible hired relationship, weekly per-day schedule, start/end, care notes, and message; recap and confirm |
| Review or accept a counteroffer | Compare original and countered schedules and confirm acceptance before changing the plan |
| Skip one visit | Select one generated future booking, show any late-cancellation consequence, confirm, and preserve later regular care |
| Request an extra visit | Require exact future start/end, recap, send a pending request, and leave the weekly schedule unchanged |
| Review a completed extra visit | Read time, duration, reason, notes, and financial preview |
| Approve or request changes to completed extra care | Use the existing audited service after a financial/state fingerprint and explicit confirmation |
| Request a schedule change | Compare current and proposed weekly per-day slots and future effective date; send only after confirmation |
| Pause | Show pause date and optional return date; suppress affected future generated visits through the existing plan service |
| Resume | Require a paused live plan, recap current schedule, confirm, regenerate according to existing rules, and verify |
| End | Explicitly distinguish keeping or cancelling the next already-confirmed visit; stop future plan generation after confirmation |
| View history or message the regular caregiver | Open authorized care history or the exact conversation |
| Replace the regular caregiver or handle an exceptional payment/dispute | Transfer to a human without altering the plan |

## Deterministic information collection

The assistant accepts ordinary English for intent selection but requires unambiguous values before a consequential schedule action. Current deterministic forms are:

- visit/extra/rebook range: `YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM`;
- weekly schedule: named weekdays, `HH:MM to HH:MM`, and a future `YYYY-MM-DD` start date;
- pause: one `YYYY-MM-DD` pause date and an optional later return date;
- visit cancellation/change: an explicit reason introduced by “because” or “reason”; and
- review: a 1–5 star value and optional text after “say” or `review:`.

Malformed dates and times are rejected as missing information; they do not produce a server error or an inferred date. The UI can later replace these textual fallbacks with large date/time and choice controls without changing the tool contracts.

## Narrow confirmed tools

Visit tools:

- `visit.change-request`
- `visit.change-request.resolve`
- `visit.cancel`
- `visit.no-show`
- `visit.complete`
- `visit.hours.approve`
- `visit.time-correction.approve`
- `visit.time-correction.request-changes`
- `visit.review`
- `visit.rebook`

Regular-care tools:

- `regular-care.offer`
- `regular-care.accept-counter`
- `regular-care.schedule-change`
- `regular-care.extra-visit`
- `regular-care.skip-visit`
- `regular-care.pause`
- `regular-care.resume`
- `regular-care.end`
- `regular-care.extra-visit.approve`
- `regular-care.extra-visit.request-changes`

Every tool is off by default, belongs to `family_care_operations_v1`, and is enabled only for the existing two-user pilot by the activation command. The Everyone control is checked before and after activation and remains off.

## Human boundaries

The following intent rows transfer rather than execute an automated decision:

- `FAM-VISIT-015`, `016`, `027`, `028`, and `035` for safety, serious problems, escalation, disputes, or completed-record correction;
- `FAM-REGULAR-016`, `018`, and `026` for completed-extra disputes, exceptional failed payments, or caregiver replacement; and
- Batch 6 `FAM-MATCH-024` and `025` for profile/credential and blocking concerns.

The emergency classifier still takes precedence: immediate danger receives the 911 instruction before transfer. Human transfer never promises queue status or response time.

## Knowledge and evaluation package

The combined `marketplace-care-kb-v1` package contains:

| Area | Entries | Intent rows |
| --- | ---: | ---: |
| Applicants/messaging/hiring | 9 | 25 |
| Visits/submitted hours/problems | 14 | 35 |
| Regular care/extra visits | 9 | 26 |
| Total | 32 | 86 |

Each entry has five linked evaluations, for 160 cases total. The generated Family catalog remains 324 rows and now has 237 explicit KB mappings.

## Source verification

The focused source suite proves:

- all 86 Match/Visit/Regular intents and all 344 registered phrases route to their declared intent;
- the 32-entry/160-case governed package is complete, validates, imports idempotently, and publishes without changing availability or domain records;
- every declared write contract is registered under the new default-off pilot capability;
- applicant reads cannot cross Family Accounts;
- invitation, shortlist, exact-message, hire, no-show, visit-reschedule, and regular-care pause/resume journeys require recap/confirmation and create authoritative receipts;
- stale applicant state is rejected and idempotent reconfirmation does not duplicate the action;
- exceptional Match/Visit/Regular cases transfer without a domain write;
- activation extends only the two active configured pilot grants and keeps `general_release_enabled` off; and
- the established Batch 3 and Batch 5 regressions remain green.

The final source baseline is **204 / 204 AI Support tests with 4,231 assertions**. The isolated Family Batch 1–7 harness is **112 / 112 tests with 3,422 assertions**, including **344 / 344** Batch 6/7 phrases, **86 / 86** Batch 6/7 intents, **237 / 237** KB mappings, and **10 / 10** protected collision cases. It makes no provider call and does not use the production database.

## Deployment and pilot activation

Run the normal deployment first:

```bash
./deploy.sh
```

Publish the combined governed package:

```bash
php artisan ai-support:import-marketplace-care-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Batches 6 and 7 marketplace-care knowledge for the two-user pilot." \
  --confirm=PUBLISH-MARKETPLACE-CARE-KB
```

Activate the new capability and tools for only the two existing pilot grants:

```bash
php artisan ai-support:activate-batch7-pilot --actor-email=test@test.com
```

Then verify:

```bash
php artisan ai-support:test-family-intents --plan
php artisan ai-support:monitor-health
```

The activation command must report **Live for everyone: Off**. Deployment and publication are not claimed complete until the authenticated production audit checks at least one Batch 6 read/action, one visit read/action, one regular-care read/action, one human transfer, and Admin transcript/receipt visibility for each exact pilot user.
