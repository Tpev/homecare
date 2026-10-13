# Recurring care: current screen and information inventory

Audit date: 2026-09-07. Evidence: current repository Blade, Livewire, presentation services, lifecycle helper and existing E2E specs. This is a source inventory, not proof that a screenshot has been captured. The master tracker and capture manifest are the authority for screenshot completion.

Scope: family finding care, from a recurring request or a repeat booking offer through the recurring relationship and individual visits. Continuous/around-the-clock care is a separate care pattern and is recorded only where its entry point appears. All screens below require desktop and mobile evidence. The shared app shell and shared request/visit contents are covered in the companion inventories; the recurring differences and additional screens are enumerated here.

## 1. The two levels of recurring care

There are two different records and two different lifecycles:

1. **Arrangement/plan:** a weekly agreement with a caregiver, which can be pending, active, paused or ended.
2. **Dated visit:** each occurrence has its own request, booking, check-in, time approval, payment, review and closed record. Finishing a visit does not finish the arrangement.

The marketplace route is `/family/requests/create` → a recurring request at `/family/requests/{requestId}` → hire → plan plus dated visits. Hiring recurring care calls `activateFromRecurringRequest`; it updates the request in place and stays on request management rather than redirecting to the plan. Source: `app/Livewire/Family/ManageCareRequest.php:258–309`; `app/Services/RegularCare/CarePlanService.php:314–450`.

The repeat-care route is an eligible previous booking → `/family/requests/{requestId}/book-again` → **Set up recurring care** → `/family/requests/{requestId}/regular-care` → direct offer → `/family/care/{planId}`. Eligibility requires an owned request, a booking and hired application, no existing plan, and a booking that is neither cancelled nor disputed. The service permits a scheduled/in-progress source as well as completed care; ordinary rebooking UI imposes a later-stage gate. Source: `app/Services/RegularCare/CarePlanService.php:82–102`; `resources/views/livewire/family/book-again.blade.php:109`; `resources/views/livewire/family/manage-care-request.blade.php:237–245`; `app/Livewire/Family/RegularCareComposer.php:42–79,120–121`.

**Identity rule for screenshots:** generated recurring visit requests are stored with `request_type=one_time`, `is_system_generated=true` and `care_plan_id`; their booking has `plan_visit_kind=regular`. Classify the audit variation by plan membership, not only by the visible care-type badge. Source: `app/Services/RegularCare/CarePlanOccurrenceService.php:384–439`.

## 2. Practical capture matrix

Every row is a distinct screen/visual branch; capture desktop and mobile. For rows listing tabs, capture every listed tab independently. Panel/disclosure rows need an expanded capture in addition to the default page. Native confirmation dialogs require a separate record of the dialog text if the screenshot tool omits browser dialogs.

| Local ID | User stage | Route / state | Capture content and fixture requirement |
|---|---|---|---|
| RC-C01 | Create | `/family/requests/create`; choose Recurring care | Empty/default creation form and weekly schedule selected. |
| RC-C02 | Create | Same, populated | At least two weekdays with different times/durations; ongoing recurrence; all essential fields; weekly price estimate. |
| RC-C03 | Create | Same, `recurring_end_choice=date` | Last day field, start-date adjustment message if triggered, optional notes/contact expanded. |
| RC-C04 | Create | Same, `is_private=true` | Invite-only explanation and Create private request action. |
| RC-C05 | Create | Same, no existing profile, quick profile open | Two simple-profile questions and sharing acknowledgment. |
| RC-C06 | Create | Same, saved profile / Me branch | Saved care profile card(s), selected-state/change action, saved information branch, self recipient display. |
| RC-W01 | Waiting / inviting | `/family/requests/{id}?tab=applicants`, then `tab=overview` | Open recurring source request, no applied/shortlisted candidate; suggestions, invitation controls, weekly care details. |
| RC-W02 | Waiting / inviting | Same tabs, private request | Private badge/copy and invitation flow without marketplace discovery. |
| RC-W03 | Reviewing applicants | Same tabs | Open recurring source request with applied and shortlisted candidates, profiles, conversations and hire eligibility. |
| RC-W04 | Selected / setup pending | Source request `tab=overview`, `tab=applicants` | Filled request with hired application and no linked booking; distinct setup-pending fallback. |
| RC-O01 | Create repeat agreement | `/family/requests/{eligibleId}/regular-care` | Composer with saved card and populated multi-day schedule. |
| RC-O02 | Create repeat agreement | Same, billing not ready | Payment method required and Open billing. |
| RC-O03 | Waiting for direct offer reply | `/family/care?type=regular&view=arranging`; plan detail; care story | Pending caregiver plan, no real visits yet. Preserve current detail-page copy even if misleading. |
| RC-O04 | Counteroffer | Same arranging view and countered plan detail | Counter schedule exists. Current detail Blade has no accept-counter control; do not invent one. |
| RC-H01 | Hired / waiting | `/family/care/{planId}` | Active arrangement, next scheduled visit, later visits, card/payment protection. |
| RC-H02 | Hired / payment attention | Same | Next payment requires family action and at least one later visit with a contrasting payment state. |
| RC-H03 | Hired / preparing visits | Same with no next booking | Next-visit preparation alert and later-visits empty or forecast branch. |
| RC-H04 | Hired / visit view | `/family/requests/{occurrenceId}`; `shift`, `applicants`, `support`, `overview` | Scheduled occurrence, related plan ID, hired caregiver, scheduled times/address, payment state. |
| RC-D01 | During visit | Same four occurrence tabs | Booking `in_progress`; recorded check-in, tasks, notes/location and payment. |
| RC-D02 | During visit / paused | Same four occurrence tabs | Booking `paused`, pause start/reason and elapsed pause values. This differs from a paused plan. |
| RC-A01 | After visit / hours pending | Same four occurrence tabs | Completed/reviewed booking with `family_confirmed_at=null`; worked/break time, approval amount, task report. |
| RC-R01 | Leaving review | Same occurrence `shift` | Confirmed completed booking with no family review; rating picker and comment form. |
| RC-R02 | Closed visit | Same four occurrence tabs | Confirmed/reviewed booking plus family/caregiver feedback and final payment record; recurring arrangement still active. |
| RC-R03 | Corrected timesheet | Plan attention + occurrence `shift` | Pending-family time correction, then payment-action-required correction. |
| RC-R04 | Unscheduled reported visit | `/family/care/{planId}#completed-extra-visit-{reportId}` | Pending reported extra visit and financial estimate. |
| RC-R05 | Extra report outcomes | Same report card | Payment-action-required, changes-requested, applied, disputed/escalated/failed branches. |
| RC-M01 | Ongoing changes | Active plan, Add an extra visit open | Date/start/duration/note form. |
| RC-M02 | Ongoing changes | Active plan, Change future schedule open | Weekday selection, per-day start/end, effective date/note. |
| RC-M03 | Ongoing changes | Active plan, pending schedule change | Waiting-for-caregiver count and current bookings retained. |
| RC-M04 | Ongoing changes | Active plan, Pause recurring care open | Pause date, optional return date, consequences and controls. |
| RC-M05 | Ongoing changes | Paused plan | Paused badge, Resume recurring care action and existing relevant visits. |
| RC-M06 | Closed arrangement | Active plan, End recurring care open | Default keep-next explanation, cancel-next checkbox, confirmation. |
| RC-M07 | Closed arrangement | Ended plan, next visit retained | Ended badge, retained next booking, no manage controls. |
| RC-M08 | Closed arrangement | Ended plan, all future visits cancelled | Ended badge, no next visit, remaining historical links. |
| RC-M09 | Closed offer/request | Declined/expired/cancelled plan; withdrawn source request | Past arrangements card, plan detail/current implementation, withdrawn source tabs. |
| RC-I01 | Cross-stage navigation | `/family/care?type=regular` | Current arrangements with finding-care request and active/pending/paused plan cards. |
| RC-I02 | Cross-stage navigation | Same, `view=past` | Ended/cancelled/declined/expired arrangement cards and closed note. |
| RC-I03 | Cross-stage navigation | Same, empty filter result / >8 results | Empty state; load-more count and control. |
| RC-S01 | Hired / during | `/family/care/schedule?type=regular` | Today/this week/later sections with scheduled, live and payment-attention occurrences. |
| RC-S02 | Hired / during | Same, empty / >8 visits / `type=extra` | Empty and load-more branches; extra visit classification. |
| RC-T01 | Progress / closed | `/family/care/journey/regular/{planId}` | Plan story with timeline, connected upcoming and completed visits, care details, task chips. |
| RC-T02 | Waiting / closed | Same, pending or ended plan | No upcoming visits/current closed status; preserve actual timeline wording. |
| RC-P01 | After / closed | `/family/care/history?plan={planId}` | Summary, populated records, plan filter and default disclosure state. |
| RC-P02 | After / closed | Same, a visit disclosure expanded | Actual time, payment record/refunds, tasks, links to full record/help/plan. |
| RC-P03 | After / closed | Same with custom dates / correction / empty filters | More filters on mobile, custom From/To, correction badge/detail, empty state. |

Use fixture values with nonempty task names, address line 2, care notes, caregiver reviews, caregiver photo, conversation, recipient profile and contact data where safe, so conditional information is visible. Use anonymized fixture records, not real family details. Plan status, booking status, time confirmation, payment status, review existence and report status are separate axes and should be recorded in the screenshot manifest.

Existing capture patterns are in `tests/e2e/specs/regular-care-flow.spec.ts:14–45` (1440×1000 and 375×812; 768×900 additionally), `tests/e2e/specs/family-request-create.spec.ts:28–54` (different times by weekday), and `tests/e2e/specs/time-correction-flow.spec.ts:83–86`. Existing test fixtures expect an E2E Recipient/E2E Ready Caregiver recurring plan with a payment-attention next visit; these tests do not prove the fixture exists in the currently running database.

## 3. Creation: every visible information field and action

Source: `resources/views/livewire/family/create-care-request-wizard.blade.php:1–650`; option lists `app/Livewire/Family/CreateCareRequestWizard.php:156–174,1329–1340`.

The page is a long form, not a multi-page wizard or tabs. Capture full page and opened disclosures.

- **Header/status:** optional AI-prepared alert; session status; Request care kicker; “What kind of care would help?”; explanatory sentence about one visit or regular schedule and care through payment.
- **Before publishing:** readiness sentence/count; Person, Help, Time, Address readiness. Desktop cards include Ready/Needed badge and resolved name, task list, schedule or location/missing-data help; mobile reduces these to chips.
- **Saved data branch:** Save time / Use saved information (optional); reuse explanation; Last request loaded / Saved details loaded and schedule-check reminder; Use last request; Use saved details. Mobile uses a collapsed disclosure while desktop displays the controls.
- **Care pattern:** One visit and Recurring care radio cards, selected checkmark, description per pattern. Conditional Around-the-clock care card, explanatory copy and Set up link if feature access enables it.
- **Who and what:** section helper; Who is receiving care?; Me / A family member choices. Self branch displays Care recipient name and statement that care is for the poster. Other branch contains Family member receiving care and Relationship to you (with example placeholder).
- **Saved care profile:** optional heading/helper; each available profile's display name, relationship or Care recipient fallback, last-reviewed date, selected visual treatment; Change action. No-profile branch offers Add a simple care profile.
- **Simple care profile expanded:** title/helper; Skip; “What should a caregiver know?” textarea; “What helps care go well?” textarea; sharing acknowledgment checkbox and text that exact address, contact details and birth date are excluded.
- **Help needed:** every available active task label as a selectable checkbox tile, selected state, validation. Task labels come from the database, so the captured record must enumerate the actual labels visible rather than assume a fixed list.
- **More details if you want disclosure:** Optional chip and preparation helper; Anything important for the caregiver? textarea; Care notes textarea; Home access textarea; Timing note input. Add another contact checkbox/helper reveals Contact name, Relationship, Phone, Email (optional).
- **Recurring care schedule:** helper; Which days each week?; all seven named weekdays; What time on each day?; variable-times helper; one row per selected day with weekday name, derived Ends at time or Choose a start and length; Starts at time input; Visit length dropdown. Length options are 1 hour through 12 hours in 30-minute increments.
- **Schedule dates:** When should this schedule begin? date; minimum-date restriction; possible adjusted-start message; How long should this repeat? choices Until I stop it / End on a date; conditional Last day; computed Schedule summary.
- **Where:** Street address; Apartment, unit, or gate (optional); City; State dropdown; ZIP.
- **Review and publish:** helper; Make this request private checkbox. Public text allows eligible marketplace applicants plus direct invites. Private text says hidden from marketplace, invitees only, and invitees chosen after creation.
- **Review cards:** For name and relationship; Help selected tasks or missing-selection text; Schedule formatted recurrence or Schedule not set; Address street and city/state/ZIP or placeholders.
- **Weekly estimate:** Estimated recurring care each week; weekly hours × care hourly rate, care amount, processing fee hourly rate and amount, Estimated total, fee footnote. Explicit statement that all future visits are not paid now and card confirmation/charging occurs per visit. Missing schedule shows Add the schedule to see the estimate.
- **Footer/actions:** Cancel; Publish request or Create private request; loading-disabled submission. Inline errors appear beside invalid fields and selections throughout.

## 4. Source request and individual-visit tabs

Routes: `/family/requests/{id}?tab=overview|applicants|shift|support`. `tab` is URL-bound; invalid/unavailable values fall back to the lifecycle's primary tab. There is no independent recurring request template. Source: `routes/web.php:360–365`; `app/Livewire/Family/ManageCareRequest.php:47–48,157–165,199–215`; `app/Support/CareRequestProgress.php:215–422`.

| Condition | Default tab | All visible tabs in display order |
|---|---|---|
| Open request, no pending candidates, no booking | applicants | Caregivers (“Invite matching people”); Care details (“Needs, address, tasks”) |
| Open request, applied/shortlisted candidates, no booking | applicants | Caregivers (“Review, chat, hire”); Care details |
| Selected/fallback without booking | overview | Care details; Caregiver (“Profile, chat”) |
| Scheduled visit | shift | Visit (“Time, location, payment”); Caregiver; Help (“Change or get help”); Care details |
| In progress / paused visit | shift | Visit; Help; Caregiver; Care details |
| Completed/reviewed, hours unconfirmed | shift | Visit; Help; Caregiver; Care details |
| Completed/reviewed, hours confirmed | shift | Visit; Caregiver; Help; Care details |
| Cancelled/no-show visit | shift | Visit; Help; Caregiver; Care details |
| Disputed visit | support | Help; Visit; Caregiver; Care details |
| Withdrawn request | overview | Care details; Caregivers (“Past responses”) |

The stage summary also contains an eyebrow, title, explanatory body, and three tiles. Waiting: Replies, Invited, Schedule. Reviewing: To review, Invited, Schedule. Selected/no booking: Status, Caregivers, Visit. Scheduled: Visit, Caregiver, Time. Live/paused: Visit, Caregiver, Started. Hours approval: Worked, Caregiver, Payment. Complete: Visit, Caregiver, Worked. Cancelled/no-show: Visit, Caregiver, Payment. Dispute: Visit, Caregiver, Status. Withdrawn: Status, Caregivers, Invited. Each tile includes a value and a help label.

The shared request inventory must be applied in full to recurring source requests and plan-linked occurrences: Care details, suggested/invited/applied/selected caregiver variants, chat, payment, scheduled/live/paused shift details, timesheet, corrections, rating/review and Help forms. Recurring-specific fields are the weekly schedule summary before booking, recurring request headline and link identity. After a dated booking exists, the headline uses recipient and visit date; schedule uses actual booking time. Source: `resources/views/livewire/family/manage-care-request.blade.php:215–245`.

**After / review / closed are not interchangeable.** `completed` or `reviewed` with no `family_confirmed_at` shows hours approval first. A confirmed booking with no family review shows Leave a caregiver review: clickable 1–5 stars, selected-rating or no-rating helper, Review comment textarea, validation, Submit review. Existing reviews render 5-star display, numeric rating and comment, plus caregiver feedback when available. Source: `resources/views/livewire/family/manage-care-request.blade.php:1457–1495,1603–1644`; `app/Support/CareRequestProgress.php:334–386`.

## 5. Direct recurring offer composer

Route `/family/requests/{sourceId}/regular-care`; there are no tabs. Source: `resources/views/livewire/family/regular-care-composer.blade.php:1–175`.

- Session status in amber alert.
- Recurring care kicker; “Have {caregiver} come again”; explanation of selecting weekly days/time and next-visit/payment handling after acceptance; Back to request.
- When should they come? heading/helper; Care name; Care days Sun–Sat checkbox tiles with selected state; Time for each day helper and different-time explanation; selected-day rows with weekday label, Starts at, Ends at; First eligible date; Optional end date; all field validation messages.
- Same care details? heading/helper; Hourly price and care hourly rate; processing-fee hourly rate; total hourly price; fee footnote; Care notes; Message to caregiver; field errors.
- Saved-card branch: Send to caregiver. Missing-card branch: Add a payment method before sending this schedule; Open billing.
- Source request side card: Request title; Caregiver full name or dash; care rate and fee; Recipient full name or dash.
- Payment side card: Ready for authorization with uppercase card brand and last four digits, or Payment method required with authorization-after-acceptance explanation.
- Desktop uses form + side column; mobile stacks the source/payment cards below the form.

## 6. Arrangements index and section tabs

Route `/family/care`. URL filters `person` (recipient name, default `all`), `view` (default `current`), `type` (default `all`). Source: `app/Livewire/Family/RegularCareIndex.php:20–37,74–272`; `resources/views/livewire/family/regular-care-index.blade.php:1–110`.

**Shared care navigation:** Overview → `/family/requests`; Arrangements → `/family/care`; Schedule → `/family/care/schedule`; History → `/family/care/history`. These are navigation links styled as tabs, not content tabs on the same route. Source: `resources/views/components/family-care-nav.blade.php:1–27`.

Every visible item:

- Optional green session alert.
- Arrangements kicker; Every care arrangement, in one place heading; desktop-only explanation of arrangements versus confirmed dates; Request care.
- Four care-navigation links with Arrangements selected.
- Status selector: Current care, Being arranged, Ongoing care, Past care, All care. Care type selector: All care types, One-time care, Recurring care; optional Continuous care. Care recipient selector appears only when more than one named recipient exists; Everyone plus names.
- Requests and relationships kicker; total count with singular/plural arrangement; Open visit schedule.
- Each request card: care type; status badge; recurring recipient headline; weekly schedule; city/state when present; closed reference note or passed-date alert when applicable; Request #; action label (Review caregivers / View request / Continue request / View details / Resolve request).
- Each plan card: Recurring care; status badge; recipient with caregiver; weekly schedule; next visit label if current; Payment authorized / Payment action needed / Payment checked before each visit; Recurring care #; Manage recurring care or Fix payment. Closed cards replace payment line with “This arrangement is closed. Its completed visits remain in History.” and View details.
- Plan badge variations: Active, Payment needs attention, Paused, Waiting for {caregiver}, New schedule proposed, Declined, Ended, Cancelled, Draft/Expired fallback.
- Conditional continuous-care card: Continuous care; capitalized status; recipient name; coordinated-care description; Manage continuous care.
- Empty: No arrangements in this view; filter/new-care helper; Request care.
- Pagination: Showing {visible} of {total} arrangements; Show more arrangements, in increments of eight.

Filter coverage: arranging includes draft/pending/countered plans plus current open/draft requests; ongoing includes active/payment-attention/paused plans and no requests; past includes ended/cancelled/declined/expired plans and withdrawn/expired requests. Filled source requests and generated occurrence requests are absent from this index. Source: `app/Livewire/Family/RegularCareIndex.php:107–113,233–270`; card values `app/Services/Family/FamilyCarePresentationService.php:97–195`.

## 7. Recurring plan detail: all content, panels and conditional states

Route `/family/care/{planId}`. **No content tabs.** Inline panel state is `managePanel=''|'extra'|'schedule'|'pause'|'end'`, not a query parameter. Opening the same panel again closes it. Source: `app/Livewire/Family/RegularCareShow.php:22,73–77`; view `resources/views/livewire/family/regular-care-show.blade.php:1–238`.

**Default/header** (`:22–44`): optional green session alert; Back to your care; Recurring care with caregiver full name; weekly schedule label; Care story; View past visits; Message caregiver if source request exists; active/paused/ended status pill. Optional care-recipient profile summary appears before reported extras and pending time-correction alerts.

**Pending corrections** (`:46–59`): Your review is needed; count of recurring visits requiring attention; one link per correction containing Visit #booking ID, scheduled month/day, corrected duration; Review corrected hours or Confirm payment, targeting occurrence `tab=shift`.

**Next visit** (`:61–102`): date weekday/month/day; scheduled start and end; Who is coming; caregiver name; For recipient; Where street address, city, state, ZIP; Payment heading. Payment branches:

- Requires family action: Payment needs attention; Confirm payment link to child request.
- Authorized/captured/transferred: Payment confirmed; No action needed.
- Otherwise: Card saved; Payment is confirmed closer to the visit.

Next actions: View visit details and, unless ended/cancelled, Skip this visit. Skip opens a confirmation whose copy differs inside the 24-hour cancellation window. If no next booking and not ended/cancelled, blue alert says the regular schedule is active and next visit is being prepared.

**Pending changes** (`:104–109`): Waiting for {caregiver}; count of change requests awaiting response; statement that current visits remain scheduled.

**Later visits** (`:111–145`): heading and explanation; at most three rows after next visit, each with weekday/month/day, start/end and caregiver. Payment needs attention, Payment confirmed (any payment object in current Blade), or Payment checked 48 hours before; optional Extra visit suffix. Real booking shows Fix payment or View details; scheduled real booking on non-ended plan also has Skip. Forecast rows have no links. Empty text: No later visits are scheduled yet.

**Manage recurring care** (`:147–215`, omitted for ended/cancelled): choose-one-change helper; Add an extra visit; Change future schedule; Pause recurring care or Resume recurring care; End recurring care.

| Inline panel | Visible information / inputs / controls |
|---|---|
| Extra (`:164–176`) | Ask for one extra visit; caregiver-acceptance prerequisite; Day; Start time; How long; Optional note; errors for date/time; Send request; Cancel. Duration choices: 1h, 1h30, 2h, 2h30, 3h, 4h, 5h, 6h, 7h, 8h. |
| Schedule (`:177–199`) | Change future schedule; current visits unchanged until caregiver accepts; New care days Sun–Sat; selected-day full name and Starts at/Ends at; per-slot validation; Start new schedule on; Optional note; days/effective-date errors; Send schedule change; Cancel. |
| Pause (`:200–206`) | Pause recurring care; visits cancelled during pause and choose-return/resume-later explanation; Pause starting; Return date (optional); date errors; Pause care; Cancel. |
| End (`:207–212`) | End recurring care?; no new visits, next confirmed visit kept by default; Also cancel the next confirmed visit checkbox; End recurring care plus confirmation; Keep recurring care. |

**Care details and instructions** (`:217–224`): native collapsed disclosure; Care location with street, optional address line 2, city/state/ZIP; Care notes or No extra notes; Tasks with every task name or No task list added.

**Mobile sticky primary bar** (`:226–237`), in priority order: Review reported hours / Confirm visit payment for first pending correction; Fix payment to protect next visit; Manage recurring care scroll anchor; View care story for ended/cancelled plan. The same fixed bar is not rendered at desktop width.

**State outcomes:** extra/schedule submission closes panel and shows a sent status; pausing closes panel and confirms pause/return date; resume confirms visits ready; ending confirms either next visit kept or cancelled; skip confirms continuation and late-window condition. Source: `app/Livewire/Family/RegularCareShow.php:79–174`.

## 8. Completed extra-visit reports on the plan

Embedded only when reports exist; up to 12 latest-version non-superseded reports. Source: `resources/views/livewire/family/partials/completed-extra-visits.blade.php:1–99`; `app/Livewire/Family/RegularCareShow.php:248–255`.

- Completed extra visits kicker; Visits reported outside the regular schedule heading; explanation that report does not change schedule and payment awaits approval; optional response validation error.
- Per report: caregiver name and “reported an extra visit”; status badge; proposed date; start/end with timezone; worked duration; break minutes.
- Financial card: Estimated charge or Net billed; amount; conditional care amount and processing fee amount.
- Why it was unscheduled reason; Caregiver explanation; optional care notes; optional Your response.
- Manual-report disclosure: no GPS/check-in verification and approval does not add the weekday to the recurring schedule.
- Conditional pricing-changed notice with current estimate and explanation of amount being approved.
- Pending-family: Note for changes, a dispute, or LoLo Care support textarea, with required-for-negative/help-actions placeholder; Approve visit and payment (duration/amount confirmation); Request changes; This visit did not happen (confirmation); Ask LoLo for help.
- Payment action required: approval preserved notice; payment-attention explanation; Open Billing & Payments; Retry payment.
- Changes requested: waiting for named caregiver's updated version; no payment statement.
- Applied: View in care history; conditional Open full visit record.
- Disputed/escalated/failed: no new payment while LoLo reviews statement.
- Anchor `#completed-extra-visit-{reportId}` automatically scrolls this report into view.

## 9. Shared care-recipient profile summary on plan and request

Source: `resources/views/components/care-recipient-profile-summary.blade.php:1–137`. Rendered when the snapshot has a preferred name; all filled fields are visible, not an accordion.

- About {preferred name}; Updated badge when snapshot changed; relationship context, age range, pronouns chips if present.
- At a glance: about, Interests and comforts, A good visit, Everyday health or memory context.
- Communication: preferences, notes or configured values.
- Support and mobility: support-area chips; Support details with label/value rows; Mobility; mobility notes.
- Routine and comfort: Usual routine; Food and drink; Personal-care preferences; Sleep and overnight; Comfort and reassurance; May feel uncomfortable when; What helps.
- Important for safety: configured safety item chips/text, highlighted amber.
- What the family is looking for: Helpful caregiver qualities; Please do; Please avoid.
- Contacts and care coordination: Full name; contact name and relationship; telephone link; email link; escalation note. If only full name exists, show it without the contact block.
- Sharing footer: Shared by {name}'s family and optional last-reviewed full date.

The component iterates supplied section data, so any future populated schema field without an explicit label can still render its value. The capture-specific visible-text record must retain actual populated strings.

## 10. Schedule tab

Route `/family/care/schedule`; filters `type=all|one_time|regular|extra|coverage`, `person=all|{recipientName}`. Source: `app/Livewire/Family/CareSchedule.php:15–28,49–107`; view `resources/views/livewire/family/care-schedule.blade.php:1–116`.

- Care schedule kicker; Your visits, organized by when they happen; desktop-only explanation of unified schedule; Request care; navigation with Schedule active.
- Upcoming visit count; filter helper; optional Showing the next {count} visits.
- Care recipient selector: Everyone and relevant names. Care type: All upcoming care, One-time visits, Recurring care visits, Extra visits, Continuous care.
- Populated timeline sections: Today (Right now; care now/today helper), This week (Looking ahead; grouped-by-day helper), Later (Looking ahead; confirmed-after-this-week helper); each count.
- Day group: Today/Tomorrow/weekday and month/day or Date pending.
- Card: Regular visit / Extra visit / other type; status badge (Scheduled, Happening now, Paused, or overriding Payment needs attention); recipient + visit-date headline; start–end time; caregiver; city/state if available; Visit #.
- Card action: Fix payment and protection-required helper, or View care. For recurring visits both route to the plan or plan story; the regular schedule card does not directly deep-link to that specific dated occurrence.
- Empty: No upcoming visits in this view; person/type/new-care helper; Request care.
- Load more: Showing {count} of {total} upcoming visits; Show the next {up to 8}.

The screenshot fixture should contain today/live, this-week and later dates to reveal all sections. Source of card values and links: `app/Services/Family/FamilyCarePresentationService.php:198–250`.

## 11. Care story for an arrangement

Route `/family/care/journey/regular/{planId}`. No local tabs other than shared care navigation. Source: `resources/views/livewire/family/care-journey.blade.php:1–130`; `app/Livewire/Family/CareJourney.php:127–238`.

- Back to Care; Recurring care type; actual plan-status badge; recipient with caregiver title; schedule subtitle; Recurring care #.
- Next step or Needs your attention block: consequence text and contextual action. Default is Manage recurring care; action inbox may replace it with the most relevant urgent action (`CareJourney.php:32–49`). Same action repeats in mobile fixed bottom bar.
- Shared Overview/Arrangements/Schedule/History navigation, with Overview active.
- The complete story kicker; How this care is progressing heading and connection explanation.
- Five timeline stages, each numbered or checkmarked, state coloring, label, detail and optional timestamp: Care proposed; Caregiver confirmed; Recurring care active; Next visit; Latest completed visit. Timeline is horizontal at large desktop width and vertical at mobile.
- Connected-visits section/helper; at most four upcoming plus two recent visits. Each row: date; time; caregiver; visit status; Payment status; Open visit link. Empty branch says No confirmed visits yet with explanation.
- Care details side card: Care recipient; Caregiver; Schedule; Open full management.
- Conditional Care tasks side card with every task name chip.

Ended plans can still have completed history. Current story query excludes upcoming visits for ended plans even if ending retained the next confirmed booking; preserve that discrepancy as evidence.

## 12. History tab and expanded visit record

Route `/family/care/history`. Direct recurring path is `?plan={planId}`; other URL parameters: `range`, `from`, `to`, `recipient`, `caregiver`, `type`, `visit`, `payment`, `q`, pagination. Source: `app/Livewire/Family/CareHistory.php:17–102`; `resources/views/livewire/family/care-history.blade.php:1–364`.

**Default page / summary** (`:22–81`): Care history kicker/title; desktop-only all-previous-care helper; Upcoming schedule; Request care; shared navigation History active. History summary; totals-reflect-all-matches helper; optional Shown by currency badge. Care provided count and recorded-care helper; Recorded hours in hours/minutes and submitted-time-only helper; Net billed per currency and captured-minus-refunds helper.

**Find a visit filters** (`:83–158`): heading/helper; Clear {count} filters when active; Search with Booking #, person, or title placeholder; More filters disclosure with active-count badge or Date/type/status/person hint. It auto-opens at ≥1024px and when advanced filters are active; otherwise collapsed on mobile.

| Filter | Every option / value family |
|---|---|
| Date range | All time; Past 30 days; Past 3 months; Past year; Custom dates |
| Custom dates | From date; To date |
| Care type | All care types; One-time; Recurring care; Extra visit |
| Visit status | All visit statuses; Completed; Awaiting hours approval; Check-in missing; Disputed; Cancelled; Caregiver no-show; Adjusted |
| Payment status | All payment statuses; Any billed care; Paid; Card authorized; Payment issue; Partially refunded; Refunded; Not charged |
| Recipient | All recipients plus available names |
| Caregiver | All caregivers plus available caregivers |
| Recurring care plan | All recurring care plans plus available plan labels |

**Previous care** (`:161–242`): matching-booking count/newest-first; Updating loading indicator. Per card: reference date or Date unavailable; care type badge; visit status; conditional Adjusted and time-correction status badges; recipient with caregiver headline; request title; Booking #. Scheduled start/end or Time unavailable, duration or Duration unavailable. Recorded care: worked duration and submitted-time helper, or no-worked-time and scheduled-not-counted helper. Payment amount and payment status badge. Primary contextual action; View care story.

**Visit, payment, and help details disclosure** (`:244–333`): visit-status explanation; optional Time correction version, status, reason, duration and family amount. Actual check-in date/time or Not recorded; Actual check-out or Not recorded; Break time; Tasks completed count/total.

Payment record: help text; captured date/time or authorized date/time; status badge; conditional Captured, Refunded and Net paid amounts. Conditional additional-worked-time amount and no-double-counting helper. Task completion snapshot lists each task with Completed / Not marked label.

Actions: contextual primary action; Open full visit record; Get help with this visit (`tab=support`); View caregiver profile when URL exists; Open recurring care plan when linked.

**Empty / pagination** (`:335–364`): No past care matches these filters; filter/upcoming-care helper; Clear filters when active; Back to Care; paginator when more than 12 matched records.

## 13. Current implementation discrepancies to preserve for redesign

These are source observations, not proposed fixes. A screenshot should show what the application renders today.

1. **Recurring occurrence appears one-time in request header.** Generation sets `request_type=one_time`; request header chooses type solely from that field. The care history/schedule classifies by `care_plan_id` and visit kind. Sources: `CarePlanOccurrenceService.php:392`; `manage-care-request.blade.php:215–222`; `FamilyCarePresentationService.php:204–210`.
2. **Pending/countered/declined/expired plan detail can claim active.** Detail recognizes only paused and ended/cancelled, unlike arrangements badges. Sources: `regular-care-show.blade.php:18–19,34–35`; `FamilyCarePresentationService.php:150–160`.
3. **Counter acceptance exists in component but has no visible button in plan detail.** Counter data is prepared but unused in current Blade. Sources: `RegularCareShow.php:60–70,268–274`; full `regular-care-show.blade.php`.
4. **Non-live plans can render projected dates beneath “Later visits … already on the calendar.”** Service falls back to weekly-date forecasts when the plan is not live; forecast rows have no booking/action. This can affect pending, ended, declined and expired plans when schedule bounds permit future dates. Sources: `CarePlanService.php:1041–1104`; `regular-care-show.blade.php:111–145`.
5. **Paused plan without next booking gets active-schedule preparation copy.** Sources: `regular-care-show.blade.php:100–101`.
6. **Later visit payment label is based on presence of a payment record, not protected-payment status.** A payment object that does not require action is labelled Payment confirmed, while the next-visit card has an explicit protected-status check. Sources: `regular-care-show.blade.php:10–15,81–89,120–128`.
7. **Ended arrangement can retain its next confirmed visit.** End panel and service default keep one scheduled future visit. Plan story's upcoming query excludes ended plans, so the story can omit that retained booking. Sources: `regular-care-show.blade.php:209–210`; `CarePlanService.php:976–1040`; `CareJourney.php:140–156`.
8. **Message caregiver goes to source request without `tab=applicants`.** The request's lifecycle default may show Visit rather than conversation. Source: `regular-care-show.blade.php:31–32`.
9. **Direct-offer composer and main recurring-create form use different schedule inputs.** Composer asks start/end; create asks start/duration and derives end. Composer permits optional end date alongside first eligible date; create uses ongoing/end-on-date radios. Sources: `regular-care-composer.blade.php:62–89`; `create-care-request-wizard.blade.php:433–515`.

## 14. Coverage discipline

- Record each viewport, route/query, request/plan/booking fixture identifier, database state and visible tab/panel/disclosure with its screenshot.
- Count “after shift awaiting hours,” “hours approved awaiting review,” “review submitted,” “closed visit,” and “ended arrangement” separately even if some share the same Blade.
- Capture each visible tab at each lifecycle stage. A whole-page screenshot captures only the selected tab's content.
- A source-listed conditional branch remains **not visually captured** until its matching screenshot exists. Do not mark the audit complete solely because this inventory lists it.
- No product code, database record, booking status, notification, invitation, payment or caregiver action was changed for this source inventory.
