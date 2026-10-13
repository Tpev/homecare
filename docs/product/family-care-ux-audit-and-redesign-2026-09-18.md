# Family care: first UX audit and proposed redesign

Date: September 18, 2026. Status: design proposal, not an implemented application change.

The family should have one place to answer: **Is John getting the care he needs, who is coming, and what do I need to do next?** The current experience makes them assemble that answer from requests, individual caregiver plans, a shared schedule, and a separate calendar. The next iteration should unify that experience before adding more features.

## Scope and evidence

Reviewed the local John demo at `http://127.0.0.1:8020`: Care overview, Care request list, Schedule calendar, request creation, and the shared-care implementation. Inspected the services behind coverage, visit requests, and cancellation. This was an expert first pass, not a usability study. No actual no-show was simulated; urgent-flow limitations below are code findings. No production records, payments, or communications were changed.

The local demo has Mon–Sat care, 8am–2pm. Alice covers Mon/Wed/Fri. Ben's arrangement ends from September 28. Carla has a pending application. This makes the week of September 28 useful for checking whether the family can see uncovered care.

### What is wrong today

| Priority | Evidence | Consequence | Required change |
| --- | --- | --- | --- |
| Critical | In the existing calendar, September 29 says “0 visits” and “No upcoming visits on this date.” The shared schedule has a six-hour care need that day. | A missing caregiver looks like a day without any care requirement. | Every schedule view must include requested care, whether booked or not. |
| High | Care overview shows Carla's reply and Alice's next visit, but does not surface the missing Tue/Thu/Sat care in the following week. | A family can believe everything is arranged after checking the home page. | Show unresolved care first, with exact dates and a next action. |
| High | Care request displays “3 arrangements”: Alice's plan, Ben's plan, and the original six-day request. The shared-care link is separate. | The database structure becomes the family's navigation problem. | One John care destination; individual agreements become details within it. |
| High | Shared-care creation happens inside the first hire. | There is no consistent shared-care home from publishing through partial coverage. | Create the persistent care destination when the request is published. |
| Critical | `cancelNeed()` rejects started/past needs; `requestVisit()` requires a future start and an exact match to the need's original interval. | At 8:15am, an 8am–2pm no-show cannot simply be replaced for 9am–2pm through this flow. | A visit issue flow and support for remaining-hour coverage are prerequisites for urgent replacement. |
| High | An extra/replacement visit is requested through an existing active caregiver plan. | If the existing team cannot help, the family has no complete quick-replacement path here. | Include eligible new caregivers and an explicit unavailable/help outcome. |
| Medium | Request creation describes recurring care with “a familiar caregiver”; the funnel centers on finding and hiring. | The promise does not explain that different people can cover different days. | Explain shared coverage in plain language and land on the same care home after posting. |
| Medium | Caregiver removal, visit cancellation, finding cover, recruitment, extras, and payment details live in different controls/views. | A worried family has to choose the correct administrative operation before describing their problem. | Start from the day or the person and ask what has changed. |

There is already a calendar. We should extend and integrate it rather than introduce another destination. The current separation of care needs from caregiver bookings is also useful and should be retained behind the simpler UI.

### Source anchors

- `resources/views/livewire/family/regular-care-index.blade.php:18` — separate shared-care entry; `:41` — arrangement count/list.
- `app/Livewire/Family/CareSchedule.php:117` and `:166` — booking-based list/calendar data.
- `resources/views/livewire/family/partials/care-schedule-calendar.blade.php:92` — empty-date message.
- `resources/views/livewire/family/requests-index.blade.php` — attention and next-visit cards; no shared-need gap summary in the reviewed version.
- `app/Services/RegularCare/SharedCareService.php:106` — creation during hire; `:238` — future-only cancellation; `:264–278` — future-only visit requests, exact intervals, existing plans, and pending-offer checks.
- `resources/views/livewire/family/create-care-request-wizard.blade.php:128` — singular-caregiver description.

Line numbers refer to this local working tree and can move. A further source-backed risk needs scenario testing: the upcoming-booking query has a time/grace cutoff. An unresolved missed visit must stay visible after that cutoff rather than silently disappear into history.

## Design basis

Keep status visible, use family language, offer an understandable recovery path, and avoid making people remember where a function lives. These choices follow [Nielsen Norman Group's usability heuristics](https://www.nngroup.com/articles/ten-usability-heuristics/). Familiar navigation, clear steps, and controls visibly related to their subject also align with [W3C's cognitive accessibility guidance](https://www.w3.org/TR/coga-usable/).

Choose 44px touch targets as a product target, readable text, keyboard access, visible focus, labels beyond color, and an agenda alternative to the calendar. This is a design target, not a claim that WCAG AA requires 44px everywhere; [WCAG 2.2's minimum target criterion](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) specifies 24px with exceptions.

Working assumptions to validate with families: people may coordinate remotely or while working; several relatives may help; phone calls happen outside the app; caregivers sometimes forget to check in; and users return after interruptions. The UI must work without assuming the family is physically present, technically confident, or available to monitor it continuously. These are hypotheses for this product, not findings from interviews conducted here.

## One stable care home

Use **My care** as the primary destination, with Messages and Help accessible globally. Keep caregiver browsing accessible from finding care. Inside My care, show the recipient selector only when needed.

For John, the page contains, in order:

1. **What needs action.** An unresolved visit today comes first. Then imminent gaps, offers that fill them, and ordinary administration. Every item names the date, hours, current situation, and next action.
2. **John's schedule.** The coming week on desktop; a readable agenda on mobile, with a calendar option. Each day says who is coming or which hours need someone.
3. **Relevant details on demand.** Open a visit to see contact, care instructions, changes, and its payment. Open My caregivers for ongoing assignments. Payments and history remain accessible secondary views within the same care context.

Do not expose “dispatch,” source requests, child requests, or recruitment flags to families. Operations can have a dispatch view that uses the same underlying care facts. Renaming navigation alone is insufficient: all views and notifications must read the same coverage state.

For multiple recipients, the home should still show urgent issues across all people; selecting John must not hide an unresolved urgent issue for someone else without a visible indicator. Show the care location's timezone clearly when the coordinator is elsewhere.

## Calendar rules

The calendar represents **required care plus its current coverage**, not just bookings. “No care planned” and “Caregiver needed” must never look the same.

| Family-facing state | Meaning and action |
| --- | --- |
| Caregiver needed | Required hours have no accepted assignment. Find cover. |
| Waiting for a reply | An offer was sent; those hours remain unfilled. See who was asked, when, and how to change course. |
| Booked · Alice | Alice accepted those exact hours. Open the visit. |
| Hasn't checked in | Expected attendance has not been confirmed. This is an uncertainty, not a proven no-show. Check or get help. |
| Care in progress | Supported by the attendance record. Show who and since when. |
| Completed | Care ended; hours and payment remain accessible. |
| Covered outside LoLo | Family reports that they or another provider will cover these hours. No LoLo booking is implied. |
| Care not needed | The family intentionally removed this requirement for the stated dates. Restore if needed. |

Keep attendance, assignment, and money as separate underlying states, even if the display prioritizes one message. A booked visit can have a payment issue or unconfirmed attendance. An offer is never coverage. A canceled booking does not erase the need or the old payment record.

Show partial hours explicitly: **“Alice, 9am–2pm · 8am–9am not covered.”** A full-day green tile would be misleading. Past unresolved incidents stay in attention until resolved; the family should not have to search History to discover an absence.

Use a week view to understand the repeating pattern. Retain month navigation for looking ahead, with text/counts for gaps. A single click opens the day's details in context; no drag operation is required. On mobile, the same data becomes an agenda rather than seven compressed columns. Summaries have a clear date range: “Next 7 days: Thursday still needs care,” not an unqualified “fully covered.”

## Everyday journeys

### Creating care and hiring for some days

Use three plain sections: who needs help, when, and review. Reuse saved recipient/location details but show them for verification. Explain: “Caregivers can offer some or all of these days.” Before publishing, show Mon–Sat, 8am–2pm, start/end conditions, address, and timezone.

Publishing opens John's care home immediately. The requested visits appear as needing a caregiver. Alice's reply says **“Alice can cover Monday, Wednesday and Friday”** and previews the affected dates, start date, rate, and remaining gaps. After acceptance, the page shows Alice on those dates and Tue/Thu/Sat still needing care. Recruiting for the remaining days continues without a separate switch.

If a caregiver offers only specific dates, show those dates rather than promising ongoing weekday coverage. A later start, end date, or holiday exception must be visible before acceptance. Matching availability is a suggestion until the caregiver has actually agreed.

### Adding an extra visit

Choose **Add a visit** from John's home or an empty date. Ask for date, hours, and any changed instructions/location. Offer familiar caregivers first, then eligible alternatives. Show the price and whether this is only an availability request. The pending extra appears on the calendar immediately. Make “This visit only” the default; recurring expansion requires an explicit separate choice and preview.

If the requested hours overlap an existing need, guide the user to extend or replace that visit rather than silently create duplicate care. Different locations, overnight hours, and skill requirements are rechecked.

### Someone leaves the regular team

Open My caregivers → Ben → **Change Ben's visits**. Offer clear scope: one date, selected dates, or all visits from a chosen date. Default to keeping John's care needs. Show which dates become uncovered before confirmation and preserve all unaffected assignments and historical visits.

After confirmation, show **“Tuesday, Thursday and Saturday need a caregiver from October 1.”** Let the family request ongoing cover with the existing schedule prefilled. A replacement for this Thursday must not accidentally promise every future Thursday. If the caregiver initiates the departure, surface the same affected-date preview automatically.

## When something goes wrong

### Caregiver has not arrived

At the expected time plus the configured check-in grace period, show **“Ben hasn't checked in for today's 8am–2pm visit.”** Keep it reachable from today's visit even before an automated alert. Do not automatically accuse Ben of a no-show, assess a penalty, or mark care delivered.

The visit offers **Contact Ben**, **He is here**, and **Find someone else**. Remote coordinators can say they cannot confirm. A family-reported arrival is labeled as such until attendance is reconciled. The family can begin finding cover while the original attendance/payment issue remains open.

Replacement flow:

1. Confirm the hours still needed, recipient, location, and essential requirements. Prefill the remaining interval; do not make the family re-enter John's whole request.
2. Show familiar eligible caregivers first, then eligible backups. Distinguish confirmed availability from someone we can ask. An arrival estimate is not a guarantee.
3. Show exact hours, earliest arrival, price, and any unfilled interval. Example: Alice can take 9am–2pm; the first hour remains missed/uncovered.
4. Confirm a single replacement booking only after availability and payment checks. Revalidate at confirmation to prevent stale offers or simultaneous relatives creating duplicate bookings.
5. Show who is now coming and when, stop conflicting invitations, and preserve the original visit and incident history. Make care instructions available to the accepted caregiver with appropriate access.

If nobody is available, say so and retain the uncovered hours. Offer the real available help/contact channels and their actual service hours. “Help requested” is different from “A coordinator is working on it”; name an owner only after acknowledgment. Do not promise instant replacements or round-the-clock staffing without the operational capacity. If the reported situation indicates immediate danger, provide the appropriate emergency escalation rather than imply that a marketplace search is emergency assistance.

### Caregiver cancels, leaves early, or the original arrives later

A future cancellation immediately reopens the affected care need. A departure during the visit opens the remaining interval. If the original caregiver arrives after backup was arranged, show the conflict and coordinate one clear outcome; do not automatically pay two people for the same intended coverage or silently cancel someone already traveling. Preserve actual attendance and resolve charges under the agreed policy.

The family can also choose **I have arranged care elsewhere** or **Care is no longer needed**, with an explicit interval/date scope. Neither should erase the original visit's history or imply an automatic refund.

## Payments without making the family an accountant

Show the price and material cancellation consequences when making a decision. Keep receipts and detailed status under the relevant visit and in Payments. Avoid raw authorization/capture/plan terminology on the main schedule.

A replacement has its own price and payment record. The original visit remains visible with its actual state: for example “Charge under review” or “Refund pending,” only when supported by the system. Never announce a refund merely because a no-show was reported. If confirming a replacement may temporarily create an additional hold, disclose the actual amounts and expected release information before confirmation.

Searching for help should not require first resolving the original dispute. Booking still needs a supported payment path. A failed payment should show a clear action and whether the visit is at risk; it must not become a hidden missing-care problem. Actual no-show fees, caregiver compensation, temporary holds, urgent premiums, and service guarantees require product/operations policy decisions. This proposal does not set those policies or change the existing payment integration.

## Exception checklist

| Situation | Required behavior |
| --- | --- |
| No caregiver has been hired yet | Same care home; every requested interval visible as unfilled. |
| Caregiver accepts only two of six days | Fill only agreed dates/hours; remaining four stay open. |
| Offer has a later start or temporary end | Preview dates; do not represent the entire recurring need as covered. |
| Caregiver can do only part of a shift | Explicit partial coverage or an honest unsupported-state handoff; never mark the whole shift booked. |
| Extra visit crosses midnight | Show both dates, local times, and price; detect overlaps on both dates. |
| Daylight saving/timezone difference | Store and display the intended local schedule and resolve ambiguous times explicitly. |
| Caregiver drops one date | Reopen that date only; retain the recurring assignment. |
| Caregiver leaves permanently | End assignments from the effective date; keep needs, other caregivers, history, and current incidents. |
| Caregiver has no network/forgot check-in | Unconfirmed attendance can be corrected with attributed evidence; no automatic misconduct finding. |
| Family cannot reach recipient | Explicit uncertainty and contact/help options; do not force a false present/absent answer. |
| Late arrival or early departure | Record actual time and any uncovered interval; reconcile money separately. |
| Family changes their mind during search | Withdraw invitations where possible; explain any accepted booking consequences before cancellation. |
| Two caregivers reply / two relatives confirm | Atomic assignment; revalidate availability and scope; show the winning confirmed result to everyone. |
| Offer expires or arrives after replacement | No silent booking; explain expiry/conflict and allow a fresh request if needed. |
| Existing team all unavailable | Eligible backup route and honest help fallback; never a dead-end empty select. |
| Backup cancels too | Reopen remaining care and preserve the whole chain; no “resolved” label. |
| Original arrives after backup confirmed | Surface conflict; coordinate attendance, cancellation, and charges explicitly. |
| Hospital stay or holiday pause | Date-range preview; pause needs as well as invitations; resume on the stated date. |
| Family covers it themselves | Mark external coverage with source/time; do not create a paid visit. |
| Family cancels by mistake | Restore the need easily; explain that previous caregiver availability may need reconfirmation. |
| Requirement/address changes | Recheck caregiver suitability, travel, price, and acceptance; retain prior instructions in history. |
| Qualifications lapse / safeguarding concern | Prevent unsuitable assignment and provide an appropriate human path; do not let “quick” bypass eligibility. |
| Payment failure or unresolved old charge | Keep care and money states visible independently; offer a supported resolution before a new booking commits. |
| Stale browser/offline retry | State freshness visible when necessary; safe retries and idempotent confirmation; never duplicate visits/charges. |
| Multiple recipients/authorized relatives | Correct recipient and permissions; attribute changes; preserve urgent cross-recipient visibility. |
| Notifications are missed or disabled | Every unresolved action remains discoverable in My care; notifications deep-link to the same context. |
| Care changes beyond generated visits | Display the planning horizon and future recurring assignment end dates; don't imply unlimited guaranteed coverage. |
| Entire care arrangement ends | Preview scope and consequences; stop future invitations/visits appropriately; keep records and unresolved money/issues accessible. |

## Implementation order

**First: unify the truth and the entry point.** Create the arrangement at publication, build a shared family-facing care/coverage read model, feed overview/list/calendar from it, and fold source and caregiver plans into one recipient-care destination. Extend the existing calendar. Preserve old deep links through redirects or contextual entry so messages still work.

**Second: complete the essential recovery path.** Add visit issues, attendance uncertainty, remaining-hour requirements, replacement assignment with concurrency checks, payment review, and a real help fallback. Current whole-interval and future-only guards need a deliberate domain change; do not simply remove them. Keep historical booking/payment references intact.

**Third: simplify routine management.** Add in-context extra visits, caregiver departure with affected-date preview, pauses, and offers described by the gaps they fill. Audit caregiver notifications and operational ownership alongside family screens.

Defer automatic matching/booking, broad simultaneous invitation broadcasts, sophisticated recurring exceptions, and automatic standby fees. Start with explicit family confirmation and a small understandable set of choices. If limited parallel availability requests are introduced for urgent care, separate them from booking offers, add expiry/withdrawal behavior, and enforce one accepted assignment per intended coverage interval.

## Validation before implementation is considered complete

Run a small formative study with approximately 6–8 family coordinators across mobile confidence and remote/in-person care situations, plus caregiver and support reviews. These are proposed participants and goals, not research already performed.

Ask without teaching navigation: create Mon–Sat care; accept a two-day offer; identify every remaining gap; add Sunday; remove Ben from next week but keep the care; handle a missing caregiver at 8:15am; explain what a 9am replacement does and does not cover; pause for hospital care; explain original versus replacement charges.

Measure successful completion without help, correctness of coverage understanding, accidental scope changes, and comprehension of charges. Target reaching the relevant problem action within two taps from My care; this is not a promise of replacement arrival time. Include keyboard and screen-reader review, 320px/mobile layouts, long names, multiple recipients, and color-independent states.

Acceptance checks for the application:

- The published six-day need is visible before any hire. Hiring Alice for three days leaves exactly the other three unfilled in every view.
- A future cancellation or departure cannot erase a still-needed interval or regenerate the canceled assignment.
- A missed visit stays in attention after its start/end and after refresh until someone resolves the issue.
- Replacement at 9am for an 8am–2pm need shows the missed first hour and retained original booking/payment history.
- Pending, expired, rejected, and withdrawn offers never count as confirmed coverage.
- Concurrent acceptance, retries, and stale clients cannot create unintended overlapping care or duplicate charges.
- Every consequential change previews dates, hours, people, price implications, and whether it affects one visit or ongoing care.
- Family, caregiver, and any operational view agree on the same visit/issue/coverage state.

## Concept provided with this audit

The interactive concept demonstrates one care home, calendar/agenda switching, selected-day details, no-check-in handling, and a replacement review that preserves an uncovered first hour. It uses fictional scenarios and prices, has no connection to the demo database, and performs no booking, payment, or communication. It is a testable design hypothesis, not evidence that the proposed domain flows are implemented.

Concept checks completed: reviewed the desktop layout at 1024px and mobile reflow at 320px; confirmed no horizontal overflow at the narrow size; walked through the replacement confirmation and retained original-visit issue; switched to agenda; requested an extra Sunday visit with changed hours and verified the matching estimate and unfilled/pending calendar state. No browser errors were reported in these checks. This is limited prototype verification, not application regression testing or a complete accessibility audit.
