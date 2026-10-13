# Delivery, timesheets and closure: UX audit and proposal

Audit date: September 7, 2026. Proposal only; no application or database changes. Covers one-time and recurring care after hiring, including every tab's information inventory, recurring management panels, payment attention, paused visits, corrections, reviews, completed records and cancellations.

The existing UI already has useful ingredients: a clear current-stage message, named caregiver, explicit approval amount, separate help, a detailed audit trail, and separate recurring-plan controls. The problem is ordering and state clarity. Those ingredients are spread across a long recipient profile, repeated status cards, changing tabs, and several versions of the same decision.

## Evidence and limits

This review visually inspected representative desktop and mobile PNGs and read the corresponding rendered text and source inventories. It used the actual first-screenful captures as well as the focused gallery images. Focused images are deliberately scrolled and cannot establish what a user sees on arrival.

Relevant source was checked in `resources/views/livewire/family/manage-care-request.blade.php`, `resources/views/livewire/family/regular-care-show.blade.php`, and `app/Livewire/Family/ManageCareRequest.php`. The [validated lifecycle notes](../validation-notes.md) distinguish real UI behavior from synthetic fixture artifacts. The [one-time inventory](../one-time-source-inventory.md) and [recurring inventory](../recurring-source-inventory.md) remain the authoritative full field/action ledger.

- Screens represent independent synthetic examples. Early-morning times and the active plan's inconsistent sample “Later visits” chronology are not evidence of production scheduling faults.
- Recurring source requests and generated occurrences really use different request-type labels. This is a verified presentation inconsistency, not a misclassified screenshot.
- Map/payment provider fallbacks were intentionally captured. They do not establish live provider failure or payment completion.
- Normal caregiver completion submits the timesheet at the same time. “After shift” and “Timesheet review” currently share a rendered state; the proposed design need not invent a waiting-for-submission step.
- No hiring, payment, review, cancellation, message or other consequential action was submitted for this review. Proposed behaviors below need later interaction validation.

## Ranked findings

### D01 — P1: Put the visit before the complete recipient biography

**Observed.** The rich saved profile is above the visit header on every management tab. [OT-timesheet_review-shift: desktop first screenful](../screenshots/OT-timesheet_review-shift-desktop-viewport.png) shows profile content rather than the hours decision. [Mobile first screenful](../screenshots/OT-timesheet_review-shift-mobile-viewport.png) shows the same profile while a fixed “Approve hours and pay $62.00” button is already actionable. Even the recurring plan's [desktop](../screenshots/RC-plan-active-desktop-focus.png) and [mobile](../screenshots/RC-plan-active-mobile-focus.png) next visit sit below the biography. This is real template order, not capture error.

**Proposal.** Open with the care recipient and caregiver, dated visit, one current state, immediate next action, and a concise important-care/safety summary when populated. Put the complete profile in **Care details**, with a “View full care profile” link from Overview. Keep the full safety instructions and contact/escalation information one action away; do not silently truncate their underlying content.

**Retain.** Preferred/full names, relationship, age range, pronouns, biography, interests, good-visit description, health/memory context, communication preferences/notes, support areas/details, mobility, routines, food, personal/overnight preferences, reassurance/triggers, safety items/notes, caregiver qualities, please-do/avoid instructions, contacts and tel/email links, escalation note, sharing attribution and last-reviewed date. Preserve the historical snapshot associated with each visit.

**Device requirement.** At 390 × 844, identity, date, state and next action must appear before unrelated profile detail. Desktop should use a compact context column rather than a full-width profile preceding work. The same information stays reachable on both sizes.

### D02 — P1: Separate ending care, approving hours/payment, and rating care

**Observed.** In-progress screens make [“The visit has ended”](../screenshots/OT-in_progress-shift-mobile-focus.png) the fixed mobile primary action. Normal hours review makes immediate payment the fixed action even before the timesheet is visible. Both controls call `completeBooking`; its live branch ends the booking, while its completed/unconfirmed branch captures payment and confirms hours. The template does not add a confirmation step to either normal action. Time-correction approval, by contrast, already has an explicit confirmation preview. This is a design-risk finding; no accidental charge was reproduced.

**Proposal.** Use distinct intents and screens:

1. During care: **Message caregiver** as the everyday action; **End visit** remains accessible and opens a short confirmation showing the visit/date and resulting end time. Explain that hours will be reviewed next.
2. After care: **Review hours** opens the summary. Show recorded check-in/check-out, breaks, total, and the care/fee/total breakdown before a clearly labeled **Approve 2h and pay $62.00** decision. Keep **Question these hours** adjacent.
3. After successful hours/payment resolution: a separate **Leave a review** prompt. Rating the caregiver must never imply approval of hours, charge money, or end a recurring arrangement.

**Retain.** Family ability to end a live/paused visit; existing timing/eligibility rules; submitted hours; check-in/out evidence; original and corrected versions; payment authorization/capture behavior; support and dispute actions. UI simplification must not introduce auto-approval, delayed payout, weekly billing or changes in eligibility. Any such policy change needs its own decision.

**Device requirement.** Mobile's fixed button opens the relevant decision context until that context is visible; payment confirmation then contains the amount and duration. Use distinct end/approve submissions, busy feedback and protection from repeated actions. Keyboard focus moves to the opened decision; returning preserves position and input.

### D03 — P1: Remove contradictory time-correction and receipt states

**Observed.** [OT-time_correction-shift](../screens/OT-time_correction-shift.md) and its recurring counterpart show “Waiting for family review,” requested time and an approval action, followed by “Visit receipt,” “Confirmed,” and “Confirmed by family.” The receipt uses the old two-hour amount while the correction requests 1h45. Current source selects the final-record branch because active corrections suppress the normal review flag; family confirmation is absent in the validated fixture.

**Proposal.** One attention card: **Review corrected hours**. Display Scheduled / Recorded / Requested time, timezone, break, reason, caregiver explanation, family response and changed charge in one comparison. Label the old value **Original recorded time**, not a final receipt. Show the current correction status consistently across Overview, Help, History and the plan. After approval, show the saved outcome; if payment still needs action, say **Hours approved · payment needs attention**.

**Retain.** All correction versions, before/after values, reason and notes, unpaid-break minutes, per-version status, care/fee/total, approval preview, change-request textarea, validation, escalation, payment-confirmation-only recovery and full evidence. Final status must follow actual confirmation/payment data rather than the absence of another banner.

### D04 — P1: Make recurring-plan state and visit identity truthful

**Observed.** [RC-plan-pending](../screens/RC-plan-pending.md) claims active although verified as waiting for the caregiver; [RC-plan-paused](../screens/RC-plan-paused.md) combines “Care is paused” with an active-schedule message. Generated occurrences such as [RC-in_progress-shift](../screenshots/RC-in_progress-shift-desktop-focus.png), [RC-after_shift-shift](../screenshots/RC-after_shift-shift-mobile-focus.png), and [RC-closed-shift](../screens/RC-closed-shift.md) say “One-time care,” while the initial scheduled recurring hire correctly says recurring.

**Proposal.** Keep a parent plan and dated visits. Visit breadcrumb: **Recurring care with Sofia → Monday, September 7 visit**. Parent state must distinguish Waiting for caregiver, Schedule proposed, Active, Payment needs attention, Paused, Declined/Expired and Ended. Show who acts next and what is currently confirmed. Differentiate generated date forecasts from real confirmed bookings. A complete visit does not imply the agreement is over.

**Retain.** Request, plan and booking references; source request; regular/extra visit kind; weekly slots/date boundaries; caregiver response/counterproposal; pending changes; saved card versus authorization/charge states; per-visit history. Do not remap underlying request records just to repair a label.

**Evidence boundary.** The pending and paused contradictions and occurrence label are visually captured. Counterproposal action discoverability and unbooked future forecasts are source findings requiring additional screenshots before implementation sign-off.

### D05 — P1: Show the exact scope of recurring changes before confirmation

**Observed.** Current panels already distinguish extra visit, schedule change, pause, resume and end. [RC-plan-panel-end](../screens/RC-plan-panel-end.md) says the next confirmed visit remains by default and offers a checkbox to cancel it. Schedule changes preserve current visits until caregiver acceptance. These are valuable behaviors that a simpler design could accidentally erase.

**Proposal.** A consistent change panel states **This visit only** or **Recurring plan** at its top, then previews specific affected dates. Ending should show “Plan ends now; Wednesday Sep 9, 10–12 still happens” when that is the actual result. Offer the existing alternative to cancel that visit. Pause should list dates cancelled and the return date/indefinite state; reschedule should show old versus proposed slots and the caregiver acceptance requirement. Keep confirmed, proposed and forecast dates visibly distinct.

**Retain.** Existing fields, notes, effective date, selected weekdays, per-day start/end, extra date/start/duration, pause/return dates, resume, end-plus-cancel-next choice, skip, late-window warning and eligibility. Preserve the retained confirmed visit in navigation even after the plan ends. Use backend-calculated outcomes; never infer fees or promise refunds from UI copy.

**Device requirement.** Desktop side panel and mobile full-screen sheet contain identical inputs and consequences. Keep the summary beside/above the confirmation action and preserve drafts after validation or back navigation.

### D06 — P2: Use stable tabs and show the current task once

**Observed.** Scheduled tabs are Visit / Caregiver / Help / Care details; live and review tabs move Help before Caregiver; disputed source states default to Help first. Scheduled screens repeat schedule/state in the heading, badges, stage card, “Right now” card, “Before the visit” section and time/caregiver cards. [OT-scheduled-shift](../screenshots/OT-scheduled-shift-desktop-focus.png) and [OT-in_progress-shift](../screenshots/OT-in_progress-shift-desktop-focus.png) show this repetition. On mobile, large two-row tabs arrive below a near-screenful header even in focused captures.

**Proposal.** Stable request/visit navigation: **Overview · Caregivers · Care details · Help**, in the same order across stages. Overview contains the immediate task and visit activity/record. Use a badge or attention card to draw attention to Help without changing its position. Parent recurring navigation: **Overview · Visits · Care details · Help**. Combine repeated status, date and explanatory cards into one compact stage summary.

**Retain.** Every existing selected-tab destination and content group, deep links, state-specific warnings, payment state, request status and reference. Lower-priority technical statuses can move to record details; meaningful pending/error statuses remain prominent. Keep a compact progress link/timeline for Care story rather than a large extra competing action.

**Device requirement.** A compact tab row must expose all four destinations without clipping at narrow width or large text. Retain selection semantics, keyboard navigation, URL/back behavior and meaningful focus after switching.

### D07 — P2: Turn the hired caregiver tab into a relationship view

**Observed.** [OT-scheduled-applicants](../screens/OT-scheduled-applicants.md) still renders “CAREGIVER SELECTION,” “READY 0 to review,” zero-invitation content and “Caregivers who replied” before the hired person. The selected caregiver's profile and prior application message are useful; recruitment empty states are no longer the family's main task.

**Proposal.** Caregivers opens with the selected person's photo/name, contact action, profile, relevant credentials and conversation context. Put **Hiring history** below, collapsed by default: original reply, other applicants, invitations and prior conversations remain available. Message caregiver should open the correct conversation directly; the recurring plan's current source-request link needs later runtime validation against this promise.

**Retain.** All profile, verification/certification, experience, rating/reliability and application information; chosen-person status; existing conversations; invitation status/history; all historically available actions subject to current eligibility. Do not delete losing applicants or silently start a new conversation thread.

### D08 — P2: Build one understandable route into help

**Observed.** Help has distinct legitimate forms but asks families to understand the system's categories. Before care, “Request cancellation or reschedule” coexists with “Need to cancel now?” After care, “Question hours” leads to “Open dispute,” while Support ticket includes “The hours look wrong,” “Payment question” and “Time correction,” alongside a separate incident form. The full forms are in [OT-scheduled-support-expanded](../screens/OT-scheduled-support-expanded.md) and [OT-timesheet_review-support-expanded](../screens/OT-timesheet_review-support-expanded.md).

**Proposal.** Begin with intent: **Change this visit**, **Hours or payment look wrong**, **Safety concern**, **Something else**. Show the appropriate existing form with visit/caregiver/date already attached. Explain whether submitting asks the caregiver, immediately cancels, opens a dispute or creates a support case. Keep urgent safety access visible during live care; remove repeated generic help explanations, not the incident route.

**Retain.** Change type/reason/proposed dates; direct cancellation and its reason/consequences; dispute reason; support subject/category/description; incident title/severity/description; evidence/context, existing statuses and prior correspondence. Preserve the distinction between a conversation, support ticket, incident and dispute even if entry is unified. Do not automatically translate all hours questions into a dispute or all safety concerns into a general ticket.

### D09 — P2: Make live activity useful without overclaiming verification

**Observed.** Live Overview repeats check-in status several times and says “Sofia is with Eleanor.” Its sidebar says GPS was captured at check-in. The source's live/paused branch does not show care task checks or the general event timeline, although later records contain both. [OT-in_progress-shift](../screens/OT-in_progress-shift.md) and [OT-paused-shift](../screens/OT-paused-shift.md) capture the respective states.

**Proposal.** A concise live summary: caregiver checked in at time, scheduled end, current visit/paused state, message action and help. Put available task updates, care notes and chronological activity under that summary; label absent updates accurately. Explain GPS as **Location recorded at check-in**, not proof of continuous attendance. Keep map access in Location/record detail.

**Retain.** Recorded timestamps, GPS availability indicator, paused state and caregiver resume responsibility, schedule, payment, full address/map link, tasks/check notes and events. Do not invent live location, completed tasks or a running duration calculation that disagrees with existing break/paused accounting.

### D10 — P2: Present hours and receipts as a clear ledger

**Observed.** The normal approval summary already names the exact total and fee, which should be kept. But the detailed actual check-out/break/task evidence is below disclosure; final receipts reduce the financial detail to total/payment status. “Payment Authorized,” “Captured” and “Payment confirmed” are used across views with different meanings. [OT-timesheet_review-shift](../screens/OT-timesheet_review-shift.md), [OT-closed-shift-expanded](../screens/OT-closed-shift-expanded.md), and [RC-plan-active](../screens/RC-plan-active.md) provide examples. Payment-attention fixtures also show a failed state beside an authorized amount; the synthetic amount alone is not proof of a processor error.

**Proposal.** Before approval, show scheduled versus recorded start/end, breaks where applicable, payable duration, hourly care amount, processing fee and total in one review card. Expand into all existing timestamps, task checks and event history. After approval, replace the decision with a receipt that preserves the calculation and actual payment lifecycle: card saved, authorized, paid/captured, action needed, refunded/partially refunded or not charged, as supported by backend state.

**Retain.** Money values and their units, rate/version, fee basis, authorization/capture/refund values and dates, errors/retry, original and corrected time, submitted/confirmed timestamps, confirmation actor, dispute state and linked support. Additional refund/time-adjustment examples require future capture. Never label authorization as a completed charge.

### D11 — P2: Make reviews a short contextual finish

**Observed.** [OT-review-form-empty desktop](../screenshots/OT-review-form-empty-desktop-focus.png) and [mobile](../screenshots/OT-review-form-empty-mobile-focus.png) show a usable five-star field and comment but generic “Leave a caregiver review,” far below receipt details. The existing completed header already suggests booking again. Recurring visit closure uses similar one-time-oriented “book again” copy even when no such button is shown.

**Proposal.** After successful hours/payment resolution, show **How was your visit with Sofia on September 5?**, five stars, optional comment according to existing validation, and one submit action. Let families return to the care overview while the existing review remains pending; make no new assumption about deadlines or editing eligibility. After submission, show their saved review and caregiver feedback, when available. One-time care can offer Book Sofia again; recurring care should link to the next visit/plan and offer an extra visit where eligible.

**Retain.** Rating 1–5, selected-rating text, full comment and current maximum, validation, stored review, review availability conditions and both sides' feedback. Preserve the former visit's care snapshot when rebooking, including the existing explicit latest-profile choice.

### D12 — P2: Give cancellation and closure a factual outcome

**Observed.** [OT-cancelled_booking-shift](../screenshots/OT-cancelled_booking-shift-desktop-focus.png) repeats “cancelled” in badge, banner, section and notice; still shows “Check-in pending”; later text pairs “Payment status: Cancelled” with “Authorized $62.00.” A cancelled request and a cancelled hired visit have different records. Closed recurring visits remain part of an ongoing relationship; ended plans remove management controls but keep care-story/history links.

**Proposal.** One outcome summary: what ended, who/when/reason when recorded, whether care occurred, and actual payment/authorization outcome. Replace “Check-in pending” with a historically accurate absence label when the visit is cancelled. Keep **Get help** and **View full record**. A cancelled one-time visit can offer a new care request/rebook path only when eligible; a cancelled recurring occurrence shows the next unaffected visit. Plan closure explicitly lists any confirmed visit still scheduled.

**Retain.** Cancellation actor/time/reason, flags and late-window context, no-show distinction, original schedule, caregiver, request/booking/plan references, payment history, old task list, all events, messages, dispute/support access and prior reviews. Request withdrawal must not be presented as completed care, and cancelled records must not claim a paid receipt without payment evidence.

## Information-preservation map

This is the proposed placement of existing information, not permission to remove unlisted conditional fields. During design handoff, every field/control in each original screen inventory needs an explicit target or an explicit unchanged placement.

| Existing information or action | Proposed location | Preserve specifically |
|---|---|---|
| Care identity and state | Compact workspace header | Recipient, caregiver, date, place, request/booking references, parent plan, one-time/regular/extra kind, separate visit/payment state |
| Current stage and primary actions | Overview attention card | Exact action/consequence, next actor, payment attention, missing check-in/no-show eligibility, pending changes, correction status |
| Schedule and address | Overview visit summary; full record/details | Scheduled/actual date/time, timezone when relevant, duration, unit/gate, complete address, map link |
| Rich recipient profile | Care details; concise important information on Overview | Every populated source-profile section, contact links, safety and escalation notes, historical snapshot and sharing metadata |
| Selected caregiver and communication | Caregivers | Full profile, credentials/verification, review/reliability data, original reply and correct conversation |
| Recruitment material | Caregivers → Hiring history | All applicants, invitations, statuses, past conversations and visible historical actions |
| Live visit | Overview → Activity | Check-in/out, GPS capture indicator, pause state, tasks and notes when supplied, help and end action |
| Timesheet/payment decision | Overview → Review hours | Submitted and corrected versions, start/end/break/duration, rate/fees/total, approval, question/change/escalate, payment-only recovery |
| Final receipt | Overview → Visit record; History | Worked time, full calculation, payment status/amounts/timestamps, family confirmation, refunds/adjustments where supported |
| Detailed evidence | Overview → Activity / Full record | Task labels/checks/notes, timestamps, event types, dispute state, change type/status/reason/proposed times and accepted/rejected outcomes |
| Help and exception actions | Help, with contextual Overview links | Change request, direct cancel, no-show when eligible, support subject/category/body, incident title/severity/body, dispute reason, existing records |
| Family review/caregiver feedback | Overview review prompt; saved Visit record | Stars, text, validation, review eligibility and each party's saved review |
| Recurring agreement | Parent Overview; Visits; Care details; Help | Status/next actor, weekly schedule, start/end bounds, caregiver, pending proposals, saved-card/payment state, care/address/task snapshot |
| Recurring visit management | Parent Visits and clearly scoped sheets | Confirmed versus forecast dates, extra visits, skip, change future schedule, pause/return/resume, end and cancel-next choice, impact and acceptance rules |
| Rebooking/history/navigation | Closed Overview, parent plan, History | One-time rebook, recurring setup/extra visit where eligible, copied-versus-latest profile, full record, Care story and all existing filters |

## Prototype acceptance checks before implementation

1. Compare both sizes against the original screen inventory for every affected tab and expanded disclosure. Every original field, value, action, validation/error and conditional branch has a destination; collapsed information is still reachable and identifiable.
2. Test the same sequence on mobile and desktop: scheduled → active → end visit → review hours → payment result → review → closed. Confirm that no rating action handles payment and no generic navigation action submits a change.
3. Walk recurring source request, generated occurrence and extra visit separately. Finish a visit without ending the plan; end a plan while keeping the next confirmed visit and still find it.
4. Check pending/active/paused/ended, card authorization versus capture, correction pending versus confirmed, payment-action-required after approved hours, and cancelled versus no-show. State labels must agree across header, record, History and plan.
5. Compare planned, recorded and corrected time with nonzero breaks and real fees. Check stale/double submission and failure/retry behavior without losing the reviewed values or draft explanation.
6. Verify Help preserves all existing paths and eligibility, including change request versus immediate cancellation. Retain cancellation/no-show consequences and safety reporting context.
7. At 390 px, narrower mobile width, text enlargement and keyboard use: no content hidden behind fixed bars, no support button covering amounts/actions, complete tab discoverability, obvious selected tab, clear focus, readable forms and preserved drafts. The existing mobile captures show the floating support button over the fee card, so its clearance deserves explicit verification.

Additional fixtures/screens needed before final redesign sign-off include true no-show eligibility/outcome, disputed state and resolution, partial/full refund, post-approval payment authentication/failure, non-pending correction versions, recurring counterproposal/declined/expired states, manually reported extra visits, and an ended plan retaining its confirmed next visit. Those are coverage work, not reasons to delay the current structural proposal.
