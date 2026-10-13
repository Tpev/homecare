# Shared regular care: lifecycle and edge cases

Status: Product proposal for discussion, September 18, 2026. Not implemented.

This expands the selected-days application MVP to cover ongoing operation. It is the authoritative lifecycle companion to [the application spec](C:/Users/Epicpp/homecare/docs/product/recurring-care-selected-days-mvp-spec.md). Proposed rules are distinguished from current code behaviour below. The case register is a release checklist, not a claim that every possible operational event has been enumerated.

## 1. Foundational decision: retain the family's care needs

John needs Monday-Saturday, 8am-2pm. That need exists independently of Alice or Ben. Removing Alice must leave Monday/Wednesday/Friday needing a caregiver unless John explicitly says those visits are no longer needed.

Keep four concepts separate:

| Concept | Meaning | Example |
| --- | --- | --- |
| Care schedule | The recipient's required recurring days, hours, date range, timezone and one-off exceptions | Monday-Saturday, 8am-2pm |
| Caregiver agreement | One caregiver's accepted part of that schedule, with effective dates and terms | Alice: Mon/Wed/Fri until October 2 |
| Dated care need and visit | A need for a particular date; a real booking if someone has been assigned and the booking created | Monday October 5 needs someone; Monday October 12 has a visit with Carla |
| Visit payment | The original booking's estimate, authorization, final charge, refund and caregiver earnings | Payment for Carla's October 12 visit |

Recruitment is an additional activity attached to unmet needs. "Request closed" means recruitment has stopped. It says nothing by itself about whether care is still needed, a caregiver is coming, or a visit has been paid.

Product language can simply be **Care schedule**, **Caregivers**, **Visits** and **Payments**. The family should not need to understand the database structure.

Non-negotiable invariants:

1. Removing a caregiver cannot remove the family's care need implicitly.
2. One required dated shift has at most one current caregiver booking in this MVP. Previous cancelled attempts remain in history.
3. A proposal, application or invitation never counts as confirmed care.
4. A repeating assignment never guarantees that all its dated visits are ready.
5. Ending an agreement cannot erase delivered care, unpaid earnings, refunds or disputes.
6. A replacement cannot inherit another caregiver's booking identity, payment or time records.
7. A family-declared "Care not needed" exception is different from a caregiver being unavailable.
8. The entire operation must be checked again when confirmed, including availability, effective dates, permissions, price and payment prerequisites.

## 2. What John sees

The main care screen should answer: **Who is coming next? What needs my attention? What care is missing?**

Show the next real visit and urgent exceptions first. Then show a dated week, with navigation to later weeks, and a separate summary of the normal repeating schedule.

Example with Alice normally doing Mon/Wed/Fri and Ben doing Tue/Thu/Sat:

| Date | Care needed | Person | Care state | Payment/action |
| --- | --- | --- | --- | --- |
| Mon Sep 21 | 8am-2pm | Alice | Scheduled | Card confirmation needed |
| Tue Sep 22 | 8am-2pm | Ben | Confirmed | Payment protected |
| Wed Sep 23 | 8am-2pm | Unassigned | Alice unavailable; care still needed | Find cover |
| Thu Sep 24 | 8am-2pm | Ben | Confirmed | Payment protected |
| Fri Sep 25 | Not needed this date | — | Skipped by John | Any cancellation payment shown separately |
| Sat Sep 26 | 8am-2pm | Ben | Confirmed | Payment protected |
| Sun Sep 27 | Extra 10am-noon | Offered to Alice | Awaiting her answer | Not booked |

Summary: "Wednesday needs a caregiver. Confirm your card for Monday. Alice has not answered Sunday's extra-visit request."

Use separate labels for:

- **Needs caregiver**: a required shift has no current assignment.
- **Awaiting caregiver**: a particular offer is pending; care is still unconfirmed.
- **Scheduled**: a real booking exists; show payment readiness alongside it.
- **Confirmed**: the agreement is accepted, the booking remains valid, and applicable payment prerequisites are satisfied. This is not a guarantee of attendance.
- **Payment action needed**: the caregiver assignment remains, but this visit cannot start under the normal check-in rules.
- **Visit setup needs attention**: an assignment exists but a booking that should have been created is missing or failed.
- **Care not needed**: John explicitly removed or paused this date's requirement.
- **In progress / Completed / Missed**: actual visit outcomes, independent of payment settlement.

Far-future visits outside the payment window may be scheduled with "Card check before visit"; they are not failed payments. Dates outside the booking-generation window can show "Alice's regular day; visit not yet scheduled" as a schedule preview, never as a real booking.

Coverage derives from required date/time intervals minus family-declared exceptions and the current fulfilment of each interval. It cannot be calculated by counting existing bookings: that would hide visits that never got booked. Track offered, assigned and ready separately. Count distinct required shifts, not applications or the sum of caregivers' weekday counts.

An ending agreement must produce a future warning immediately: "Alice finishes October 2. Mon/Wed/Fri need someone from October 5." A replacement starting October 12 leaves October 5, 7 and 9 visible as gaps.

## 3. Removing a caregiver

Use **End care with Alice**, scoped to this care schedule. Keep **Skip one visit**, **Change Alice's days**, and **Stop all care** as separate actions.

The removal flow:

1. Select the last date Alice should provide care, or end future care immediately. Show the exact affected visits, including accepted extra visits. For a simple date-based UI, the last service date is inclusive in the care location's timezone.
2. State whether care is still needed on the released days. Default the displayed answer to "Yes, I still need these visits"; require the overall review to be confirmed. A caregiver departure always leaves demand intact.
3. Review visits to keep and visits to cancel. Name any imminent visit and its current cancellation/payment treatment. A visit already in progress is handled through an explicit stop/checkout or support process with actual time recorded.
4. If care is still needed, choose **Find a replacement** or **I'll arrange it later**. The second choice leaves visible gaps; it does not mark them covered. Publishing uses the existing audience/privacy setting.
5. Confirm the preview. End Alice's assignment at the boundary, invalidate future offers that rely on it, stop generation past that boundary, and reconcile affected bookings and payments.

Afterward:

- Ben's agreement and visits continue.
- Completed, in-progress and retained final visits remain attached to Alice.
- Pending hours, disputes, charges, refunds and earnings remain actionable.
- Alice appears under past/ending caregivers, with the last service date and history.
- John sees the unfilled dates and repeating days, plus whether recruitment is actually open.
- Notifications tell Alice exactly which visits remain and which are cancelled.

For the existing "keep the next visit" pattern, list the actual retained visit explicitly. The next visit might be an extra visit. Never infer that retaining it retains the whole week, and never assign a replacement over it.

If Alice reports she cannot attend, show the coverage problem immediately, even if cancellation or payment reconciliation is still processing. Do not display her as reliably coming because an authorization release failed. Block conflicting check-in/automatic capture while the cancellation is being reconciled; if existing services cannot release the reservation safely, replacement confirmation is support-assisted until they can.

## 4. Replacement and temporary absence

Treat these as different actions:

| Situation | Requirement | Agreement impact |
| --- | --- | --- |
| Alice cannot attend one Wednesday | Cover that date only | Alice keeps later Wednesdays |
| Alice is away for two weeks | Cover the required dates in that bounded interval | Preserve her return date and normal assignment outside the absence |
| Alice permanently stops Wednesdays | Recruit Wednesdays from the effective date | Version Alice's agreement; Mon/Fri can continue |
| John no longer needs Wednesday visits | Remove those requirements from the effective date | Cancel/reduce affected agreed visits with an explicit preview |
| John is away for two weeks | No care required in that interval | Pause relevant care and preview accepted extras separately |

In the MVP, temporary cover uses one-off dated replacement visits, including multiple explicitly reviewed dates if supported by the existing flow. Do not build automatic rotation or a substitute pool.

A replacement can be an existing team member, but that is still a new commitment. Ben doing Tuesdays does not mean he agreed to Wednesday. Request his acceptance and show his applicable price before confirming any extra or expanded assignment.

When replacing a specific visit, attach the new booking to the original required date, with a link to the cancelled booking. This fills an existing need; it does not add another six hours to required care. Cancel obsolete outstanding offers when a replacement is confirmed.

Permanent hiring must check finite replacements and retained final visits already on the calendar. For the MVP, start the new repeating assignment after the last conflicting retained visit and cover any earlier gaps separately; an explicit date exception is an optional improvement. Never duplicate a retained visit or hide the gap before the permanent start.

## 5. Payments and cost visibility

Preserve visit-level payments and existing pricing terms. Do not create a common prepaid weekly balance or divide one charge between Alice and Ben as part of this feature.

Each real visit keeps its caregiver, schedule/agreement version, accepted rate and fees, payment attempts, actual hours and settlement history. The care schedule can aggregate these for John without changing their ownership.

| Event | Product behaviour |
| --- | --- |
| Alice is hired for Mon/Wed/Fri | Create only her agreed visits. Use the existing per-visit authorization window; do not authorize the entire repeating series. |
| Ben is hired later | His bookings use the applicable reviewed terms for Ben. No Alice payment is reused. |
| An unassigned day remains | No service charge exists for that unbooked shift. Do not present the current assigned total as the cost of all requested care. |
| A visit needs card action | Flag that visit, tell John what to do, and show the caregiver that normal check-in is blocked. Other healthy visits keep their state. A shared card failure may affect several visits, each of which must be identified. |
| A future visit is cancelled before capture | Cancel/release its authorization through the existing process and show any separately applicable cancellation charge. A release is not the same as a refund. |
| Money was already captured | Use the existing refund/dispute process where appropriate. Ending care is not an automatic reversal of all prior charges. |
| Release/refund fails or is pending | Keep the money state visible and retry/reconcile it. Do not report a completed refund or promise immediate bank availability. |
| A replacement is selected | Quote and authorize the new booking independently. Show any previous hold/refund still pending and any applicable cancellation charge so John can understand overlapping account activity. |
| Delivered hours differ from scheduled hours | Use the existing timesheet, correction, family review and dispute rules. Record the service against the caregiver who actually provided it. |
| Payment was captured but caregiver transfer failed | Retry the earnings transfer; never recharge the family merely to retry a payout. |
| Alice leaves before her final hours are approved | Keep those hours and payments accessible. The end date does not settle or cancel them. |
| A dispute or refund occurs later | Keep it tied to the historical booking and caregiver; current coverage does not change automatically. |

The weekly cost view should distinguish **Scheduled estimate**, **Pending extra requests**, **Unassigned care**, **Final charges**, and **Refunds/holds pending**. Avoid a single misleading "weekly total" when only half the week has been staffed. Lock monetary amounts to the applicable accepted pricing snapshot, not whichever caregiver happens to occupy the weekday today.

Cancellation fees, notice rules, overtime treatment, authorization buffers and refund eligibility come from the applicable existing policy/terms. This feature does not invent new amounts or assume that a late-cancellation flag itself calculates a fee. If the current system cannot quote a financial consequence, show that it requires review before confirming a financially ambiguous change.

## 6. Add an extra visit

Provide **Add a visit** on the shared care schedule. John selects the date, hours and caregiver. Show the relevant existing visits first so he can choose **Change this visit** or **Find cover** if that is what he needs.

Classify the action:

- **Additional visit**: new care outside an existing required interval. Add a dated one-off need.
- **Replacement visit**: fulfil an existing uncovered need. Keep the same required occurrence.
- **Longer/changed visit**: change the existing booking with the required agreement/payment review; do not create a duplicate booking over the same hours.

Basic additional-visit flow:

1. John enters the exact date/time and chooses Alice, Ben, or the existing one-time recruitment flow for another caregiver.
2. Show the expected price under that caregiver's applicable terms and any relevant schedule conflict.
3. John submits the request. The calendar says "Extra visit requested; awaiting Alice." This is not a confirmed booking or a new weekly commitment.
4. Alice accepts or declines. Acceptance revalidates the date, conflicts, active relationship/permissions, terms and that the request has not been withdrawn or superseded.
5. On acceptance, create the single dated booking and apply the normal per-visit payment process. If card action is needed, show it before allowing the visit to start.
6. On decline/expiry, retain the visible unbooked need with actions **Ask another caregiver** and **Cancel this request**. It is not automatically removed or repeatedly offered to the same person.

Keep at most one active direct offer per extra need in this MVP. Switching the caregiver withdraws the previous offer before sending the next. Public one-time recruitment can have multiple applicants, but only one confirmed hire for the need. Offers expire no later than the start time and cannot be accepted afterward; any earlier operational response deadline must be explicit.

If the family cancels the underlying need while an offer is pending, withdraw the offer. If the regular agreement ends before the extra visit, invalidate a still-pending extra offer. An already accepted extra visit appears in the end-care preview; do not silently preserve or cancel it.

An extra visit already worked but never recorded uses the separate existing completed-extra report, family approval and payment process. Do not backdate the future-visit request or let it bypass approval. Duplicate reports and overlaps must be checked across the recipient's schedule and the caregiver's work, not only one agreement.

## 7. Other changes to ongoing care

Changing Alice's agreement changes her commitment, not John's underlying requirements. Reducing her days leaves gaps unless John separately removes those needs. Adding days to her agreement needs her acceptance and a check against Ben's assignments.

Changing the family's requirements changes what care is needed. Preserve the current accepted arrangement while a proposed change is pending. On the effective date, any requested hours without an accepted matching commitment must be visible as unconfirmed/missing. A pending extension from 8am-2pm to 8am-4pm cannot make the extra two hours appear covered. Partial-day fulfilment remains outside the MVP: resolve with an accepted whole-shift amendment or reviewed replacement, rather than automatically hiring a second person for the leftover hours.

Every change preview names its scope: **This visit**, **Alice's future visits from [date]**, or **All care for [recipient] from [date]**. The family can end selected future commitments; adding or moving a caregiver's work requires their acceptance. A departure is not conditional on another person accepting it.

For a basic implementation, keep the original recruitment posting as a historical snapshot after the first hire. Store subsequent effective requirement changes on the persistent care schedule and create/update recruitment for only its open needs. Adding a recurring weekday can initially use a linked new request; it must still appear in the same care schedule and conflict checks.

Pause actions must say whose availability changes. **We do not need care during these dates** suspends demand. **Alice is unavailable during these dates** preserves demand and creates temporary gaps. Resume must recheck existing replacements and explicit one-off exceptions; a nightly job must not restore a visit over an accepted substitute or a date John intentionally skipped.

Stopping all care applies to all caregiver agreements and future extras in the selected care schedule, not every recipient in the account. Preview all affected bookings and payments. If processing is incomplete for one agreement, show that outstanding part and avoid claiming the whole operation succeeded.

Notifications must describe the actual scope and current state:

| Event | Who needs to know | Essential content |
| --- | --- | --- |
| Caregiver leaves or a visit becomes uncovered | Family coordinators with relevant permissions; operations under existing incident rules | Exact dates/hours, what still happens, and a replacement action |
| Family ends/changes assigned care | Affected caregiver and relevant family coordinators | Effective date, kept/cancelled visits, and any outstanding decision |
| Extra/replacement offer and response | Proposed caregiver and requesting family | Exact date/time, current terms and pending/accepted/declined state |
| Payment blocks an upcoming visit | Billing-authorised family and affected caregiver | Family receives the payment action; caregiver sees readiness, not private card details |
| Replacement confirmed | Family and replacement caregiver; former caregiver if their confirmed visit changes | Confirmed assignment and cancellation scope, without revealing unrelated caregivers' financial terms |
| Refund/payout problem | Relevant payer/payee and operations | The affected payment and recovery state; no assertion that care itself has been reassigned |

Use the existing channels and delivery preferences, with idempotent retries. A failed email cannot make the care state disappear from the app. Public recruitment shows available shifts and the permitted recipient information, not other caregivers' names, private messages or financial terms.

## 8. Edge-case register

"Required" means supported automatically or explicitly routed to a visible, actionable support process before the feature is released. It does not mean every rare case needs new self-service UI.

### Applications and assignment

| ID | Case | Required result |
| --- | --- | --- |
| A01 | Empty, duplicate, malformed, unrequested or already assigned days | Validate server-side; normalize legitimate duplicates; require a nonempty valid offer. |
| A02 | Caregiver selects three days but note says "only two" | Explain selections mean all selected days; family can seek an updated offer. Do not infer a different contract from free text. |
| A03 | Several applicants cover the same day | Allow offers; count coverage only after a hire. |
| A04 | A selected day is taken after application | Show the conflicting offer and require the caregiver to revise; never silently hire its remainder. |
| A05 | Application or request changes in another browser tab | Reject stale confirmation and present the changed terms. |
| A06 | Two family members confirm competing hires | Only one assignment wins for each overlapping interval; the other sees updated coverage. |
| A07 | Same caregiver accepts overlapping work for different families | Check caregiver commitments across the platform, including repeating patterns beyond generated bookings. External availability is not known. |
| A08 | Caregiver starts later or ends earlier than John needs | Record the actual accepted dates and show gaps outside them. If custom dates are deferred, require the published dates; never silently treat limited availability as full coverage. |
| A09 | Reopen a previously filled request | Open only currently needed gaps. Preserve privacy and application history; require fresh consent where earlier offers are stale. |
| A10 | A removed, withdrawn, blocked or now-ineligible caregiver appears in old applicants | Do not auto-restore or auto-hire them. Apply current eligibility and permissions. |
| A11 | Caregiver wants to add days after being hired | Use an explicit agreed schedule amendment or new offer; do not mutate the original application into new work. |
| A12 | Fully staffed on weekdays, but a pending offer has earlier start dates | Check actual dates, not just weekday unions. Do not call the earlier gap filled. |

### Removal, absence and rescheduling

| ID | Case | Required result |
| --- | --- | --- |
| R01 | Remove one caregiver from a two-caregiver schedule | End only their relevant assignment; preserve the other and all required days. |
| R02 | End care with a final visit retained | Retained booking stays valid; replacement starts without overlap. |
| R03 | Departure is future-dated | Keep valid intervening visits and warn about upcoming gaps now. |
| R04 | Remove caregiver with an in-progress visit | Record actual care through the existing checkout/incident/support process; no retroactive erasure. |
| R05 | Caregiver cannot attend today or does not show | Flag today's unconfirmed/missed care immediately; preserve evidence and financial review; offer help finding cover. Later dates stay assigned unless separately ended. |
| R06 | Caregiver loses platform eligibility or account is suspended | Flag affected future care and stop invalid acceptance/check-in; use the existing eligibility process. Previously earned amounts stay recorded. |
| R07 | John cancels because care is no longer needed | Create the explicit date exception; do not advertise the date as a gap. |
| R08 | John cancels the person but still needs care | Keep the requirement and offer replacement. Cancellation UI must distinguish this from R07. |
| R09 | Caregiver vacation vs recipient away | Vacation preserves demand; recipient away suppresses the relevant demand. Both show dates and resume behaviour. |
| R10 | Resume earlier while temporary cover is already hired | Review conflicts; do not restore duplicate visits. Retain the substitute or explicitly cancel/change it. |
| R11 | Pending schedule change accepted after caregiver was removed | Reject the stale response; do not resurrect the agreement. |
| R12 | Alice's revised hours overlap Ben's | Check both recipient coverage and caregiver conflicts at proposal and acceptance. Reject overlap in this single-caregiver-per-shift MVP. |
| R13 | New caregiver covers only part of the departure's days | Mark only those dates/days assigned; leave the rest visibly missing and recruitable. |
| R14 | Rehire a former caregiver | Create a fresh accepted agreement/version from the new start; preserve old visits, rates and disputes. |
| R15 | John wants to stop recruitment but continue existing care | Stop advertising the gap only. Keep the gaps visible and existing visits active. |
| R16 | End all care because needs have ended | Stop future demand/generation and handle all affected caregiver bookings/extras; retain financial history and unsettled work. |

### Extra visits and changes

| ID | Case | Required result |
| --- | --- | --- |
| E01 | Extra requested outside normal days | One dated need, one explicit offer, one booking after acceptance; no recurring schedule change. |
| E02 | Extra duplicates or overlaps any existing care for the recipient | Offer to open/change that visit or fill its actual gap; block an accidental second booking. Different recipients can have parallel care with different caregivers. |
| E03 | Extra is a substitute for a cancelled regular visit | Link to the same required occurrence; do not increase requested hours twice. |
| E04 | No answer or decline | Keep it visibly unbooked; let John ask another person or withdraw the need. Never assume acceptance. |
| E05 | John changes date, hours, caregiver or price while offer is pending | Supersede the old offer and require acceptance of the new version. |
| E06 | Two caregivers respond because an older offer was withdrawn late | Only the current valid offer can create the booking; older responses explain that the request changed. |
| E07 | Offer accepted after start time or agreement termination | Reject; route already-delivered care to the completed-extra/correction flow. |
| E08 | Extra lies during a family care pause | Require an explicit dated exception restoring that need. A caregiver-unavailable pause requires a different available caregiver. |
| E09 | Accepted extra exists after caregiver's proposed end date | Include it in the removal preview, with a keep/cancel decision and conflict check. |
| E10 | Extra is at the same start time but different duration | Treat as amendment/conflict, not an idempotent repeat that silently keeps the wrong end time or price. |
| E11 | Caregiver reports an unrecorded extra after leaving | Use established report eligibility and approval rules; allow support review when ordinary eligibility has expired. Do not silently lose a historical claim. |
| E12 | Need two caregivers simultaneously or a partial-day handoff | Explicitly unsupported self-service in this version; route to review instead of bypassing single-shift conflict rules. |

### Money

| ID | Case | Required result |
| --- | --- | --- |
| P01 | Only half the requested week is hired | Show the cost of scheduled work and unassigned care separately. |
| P02 | Different caregiver rates or negotiated pair terms | Calculate from each applicable agreement; show the replacement/extra price before approval. |
| P03 | Card expired, authentication required, or authorization expired | Identify affected visits, request action and use normal start restrictions; don't unassign the caregiver automatically. |
| P04 | Shared payment method affects multiple visits | Aggregate the alert with a list of affected visits; do not imply resolving one authorization resolved every other one. |
| P05 | Cancel before a payment was created | Cancel the booking with no invented refund or service charge; apply any actual separate policy consequence. |
| P06 | Hold exists but final charge has not been taken | Release it using the established payment process; show pending/failure truthfully. |
| P07 | Charge already captured or caregiver paid | Use existing refund/dispute/accounting rules; never zero out or move the original transaction to the replacement. |
| P08 | New booking needs payment while old release is pending | Show both; any supported new authorization is a new consented operation. Route unresolved booking conflicts to support. |
| P09 | Cancellation near start may incur a fee | Show actual applicable policy treatment; no new blanket "free cancellation" promise. |
| P10 | Caregiver leaves with unapproved time, overtime or a dispute | Finish those workflows on the original visit. Ending the schedule is not approval or a waiver. |
| P11 | Capture failed or outcome is unknown after timeout | Reconcile the existing operation before retrying; do not issue another charge blindly. |
| P12 | Payout failed after successful family payment | Retry payout only. Family payment status remains accurate. |
| P13 | Duplicate/out-of-order payment webhook or retry | Idempotent accounting; no duplicated charge/refund/payout and no rollback of newer care states. |
| P14 | Price changes for future work | Effective version and required consent; historical and already agreed visits keep their terms unless explicitly amended under existing rules. |
| P15 | Completed visit underpaid/overpaid or partly disputed | Use corrections/partial refund/review; coverage history and actual caregiver identity remain intact. |

### Calendar, system operation and access

| ID | Case | Required result |
| --- | --- | --- |
| S01 | Missing visit was never generated | Compare requirements and assignments against bookings; show setup failure/unfilled need rather than a blank calendar. |
| S02 | View beyond generation/payment horizons | Label repeating previews and pending card checks accurately; do not create fake confirmed bookings or premature failures. |
| S03 | DST change, device timezone difference, midnight, month/year boundary | Use service-location timezone and explicit end dates. Reject or ask to resolve invalid/ambiguous local times; preserve elapsed-time billing under current rules. Reject unsupported overnight recurring patterns. |
| S04 | End date, holiday, or first day does not match selected weekdays | Honor inclusive service dates and explicit exceptions. Holidays repeat normally unless changed; show the first actual visit and reject ranges with no valid occurrence. |
| S05 | Same recipient has another request/agreement | Detect overlapping care across their schedules, not just within the current request. Check caregiver work across families as well. |
| S06 | Notification fails | State changes remain visible in the app; retry notification delivery without repeating the mutation or money operation. |
| S07 | One part of a multi-visit cancellation fails | Show completed and pending actions, preserve an operation record, and retry only unfinished steps. Stop unwanted generation immediately. |
| S08 | Two users amend/remove/accept simultaneously | Scope locks/version checks to shared requirements and affected commitments; reject stale commands after revalidation. |
| S09 | Background generation races with pause/end/replacement | Generation respects the latest effective state and explicit suppression/replacement records. It must never revive cancelled care. |
| S10 | Removed caregiver opens an old URL | Allow authorised history/earnings/dispute access. Retained final visits keep the information needed to deliver those visits; after their service access ends, deny new schedule mutations and newly shared recipient/access details. Unrelated caregiver rates/payment details stay private. |
| S11 | Family account has multiple coordinators/recipients | Enforce existing role permissions and show who approved what. Care changes are scoped to the selected recipient/schedule. |
| S12 | Family changes location, recipient, tasks or care needs materially | Reconfirm relevant eligibility/commitment and pricing where necessary; do not silently reuse consent for materially different work. |
| S13 | Old full-schedule applications/plans lack new fields | Preserve legacy meaning and history; reconcile before migration. Do not infer selected days from free text. |
| S14 | User archives/deletes an account or posting | Follow existing account/retention controls; no disappearance of active care obligations, unpaid visits or dispute records. |
| S15 | Family requests help during a platform/provider outage | Record an actionable pending operation/incident and show unconfirmed care honestly; do not claim a booking or charge succeeded without evidence. |

## 9. Implementation shape and current gaps

Introduce a persistent care-schedule/arrangement relationship for the family's demand, separate from per-caregiver `CarePlan` agreements. The exact model name is an engineering choice; its stable identity, required schedule and effective versions are essential. A recruitment request is a linked posting for some open requirements and retains its historical snapshot.

Minimum stored concepts:

- Requirement versions: recipient, weekly slots, effective dates, timezone, exceptions and one-off needs.
- Accepted assignments: caregiver/agreement, required slots, effective period and offer/version accepted.
- Dated requirement identities: used to connect original visits, replacements and unresolved gaps. They may be derived lazily within the display horizon, but the identity cannot depend on the currently assigned caregiver.
- Real visits: immutable caregiver/terms identity, requirement link, agreement/version, replacement link where applicable, actual-work and cancellation history.
- Payment operations on visits, plus operation/audit records for multi-step amendments and cancellations.

Keep acceptance idempotent per offer/application version; a later explicit rehire needs a new offer/version. The uniqueness constraint for current fulfilment belongs to the dated care requirement, while historical cancelled attempts remain stored. This must coexist with valid future recurring assignments and bounded absences.

Conflict checks need both recipient-level care and caregiver-level availability, including repeating commitments outside the materialized booking horizon. Adjacent visits with distinct intervals are not overlaps; an existing travel-time policy, if any, is evaluated separately. Do not imply the platform knows off-platform work.

Current implementation observations from read-only inspection:

| Observed behaviour | Consequence for this feature |
| --- | --- |
| Recurring activation copies the full request schedule and there is a unique source-request-to-plan constraint | Multiple partial hires require model, validation and lifecycle changes. |
| Source request can also serve as the first dated visit | A second caregiver must not reuse or overwrite another caregiver's source booking. |
| `endPlan()` ends immediately and optionally retains the next scheduled booking | Future effective departure and precise retained-visit previews need explicit support; don't claim they already exist. |
| `requestExtraVisit()` records a pending proposal and acceptance creates a visit | Reuse the consent pattern, adding shared-demand links, expiry/state revalidation and cross-agreement conflicts. |
| Extra-visit checks largely operate within the chosen plan | Expand checks to all relevant recipient and caregiver commitments. |
| Direct cancellation/reopening service explicitly supports one-time requests only | It cannot be reused unchanged to remove a recurring caregiver or reopen all shared days. |
| Authorization/release and care-cancellation state are separate; the payment cancellation helper can record a processor error without throwing | Care cancellation must expose financial follow-up rather than assume a successful release. |
| Plan health primarily reflects the next booking | Shared care attention must inspect all relevant upcoming needs/visits, not one caregiver's next booking alone. |
| Completed-extra reporting exists with overlap checks and approval/payment recovery | Preserve it and broaden identity/conflict checks; do not replace it with future booking creation. |

Relevant source anchors:

- [Recurring activation and agreement changes](C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanService.php:322)
- [Future extra-visit requests](C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanService.php:729)
- [Existing end-care behaviour](C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanService.php:984)
- [Extra-visit creation](C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanOccurrenceService.php:209)
- [One-time cancellation/replacement](C:/Users/Epicpp/homecare/app/Services/Booking/CaregiverCancellationService.php:26)
- [Authorization cancellation](C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentService.php:851)
- [Visit payment window](C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanPaymentWindowService.php)
- [Visit check-in prerequisites](C:/Users/Epicpp/homecare/app/Services/RegularCare/CareBookingCheckInPolicy.php)
- [Completed extra visits](C:/Users/Epicpp/homecare/app/Services/RegularCare/CompletedExtraVisitService.php)
- [Existing regular-care product spec](C:/Users/Epicpp/homecare/docs/product/regular-care-product-spec.md)

These observations are not a full audit of deployed behaviour; no code or tests were changed or run for this document.

## 10. MVP release scenarios

The following scenarios are the minimum meaningful integration/UX acceptance suite when this feature is implemented:

1. Hire Alice for Mon/Wed/Fri and Ben for Tue/Thu/Sat. Verify exact assignments, first dates, separate visits/payments and closed recruitment.
2. End Alice's care in the future, retain a named final visit, hire Carla starting one week later, and verify both the retained visit and intervening gaps remain visible without duplicates.
3. Remove Alice without replacing her and without changing care needs. Verify John's schedule still requests all six days and Ben continues.
4. Skip a date because John needs no care; then separately cancel another caregiver's date while care is still needed. Verify only the second appears as a coverage gap.
5. Have Ben accept a one-off replacement for Alice, then resume Alice after a temporary absence. Verify only the replacement occupies that date and normal later assignments resume.
6. Request a Sunday extra, withdraw/supersede it, attempt stale acceptance, then accept the current offer. Verify one real visit, one requirement, and correct payment state.
7. Request overlapping extra work against another caregiver's agreement and against a commitment beyond the generated horizon. Reject both without producing a booking or charge.
8. End Alice's agreement with pending/accepted extras, unapproved completed hours and a pending refund. Verify each follows its explicit rule and remains visible.
9. Fail an authorization for one visit, a cancellation release for another, and a payout for a completed third visit. Verify accurate independent states, no hidden gaps and no duplicate family charge.
10. Race two hires, a removal against acceptance, and a background generator against a replacement. Verify one current fulfilment per requirement and no resurrected visits.
11. Delete no history when closing recruitment or ending care. Verify actor/permission boundaries, particularly former-caregiver access and multiple family coordinators.
12. Exercise different daily hours, a timezone transition, an end date and legacy full-schedule records. Verify dates, durations, prices and labels remain accurate.

Required operational follow-through: a support-visible queue for unresolved booking/payment operations, audited manual resolution through existing tools, and alerts for imminent unfilled or unready visits. Use existing policies for notification cadence and incident handling; record any missing deadline/fee behaviour as a concrete product decision before implementation.

Keep automated team selection, partial-day staffing, open-ended substitute rotations, simultaneous two-caregiver care and pooled weekly billing outside this version. Basic UX still requires reliable removal, clear missing-care visibility, per-visit payments, and accepted extra visits.
