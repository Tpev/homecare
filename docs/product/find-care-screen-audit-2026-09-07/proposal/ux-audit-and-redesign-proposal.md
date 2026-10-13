# Find care — UX audit and redesign proposal

Reviewed September 7, 2026. **Proposal only; no application code changed.**

**Latest navigation proposal (September 8):** [One continuing recurring-care home, with dated visits inside it](recurring-navigation-proposal-2026-09-08.md). This refines the recurring plan/visit navigation below; it is a proposal, not an implemented change.

**Visual reference:** [Round two — LoLo branding, stronger contrast and interaction fixes](round-two-brand-and-ux-review.md). Includes measured hover/focus/contrast results and desktop/mobile evidence. Its separate plan/visit workspaces are no longer the recommended recurring navigation structure.

The largest improvement is to put the family's current situation and next decision first. LoLo already contains much of the information a family needs, but long recipient profiles, repeated instructions, changing tabs and overlapping status labels make it difficult to reach that information at the right moment. We can make the first view substantially simpler while preserving the complete care record.

My recommendation is a consistent **care workspace**: a compact identity/status header, one prominent current task, predictable tabs, and full details/history underneath or one clearly labeled step away. Recurring care adds a parent plan with dated visits. The plan and its visits have independent statuses and actions.

## Start here

- [Master screen tracker](../master-tracker.md): all 175 captured states, desktop/mobile evidence and grouped audit comments.
- [Screenshot gallery](../index.html): visual baseline; open **First viewport** as well as **Focused** and **Full page** when assessing hierarchy.
- [Data and action preservation map](data-preservation-map.md): 32 information/action families, original locations, proposed homes and rules that preserve meaning.
- Detailed comments: [Create, applicants and invitations](discovery-audit.md), [Visit delivery, hours and reviews](delivery-audit.md), [Recurring plans and navigation](recurring-audit.md).
- [Read-only runtime walkthrough](live-walkthrough.json): current state checks and reproduced applicant-filter failure.

The inline design concept illustrates the proposed hierarchy and tab behavior. Its sample content is illustrative; these documents and the baseline inventories define the complete scope. It does not connect to messages, hiring or payments.

## What was reviewed

The baseline contains 175 captured UI states across the seven requested stages, with 1,050 desktop/mobile viewport, focused and full-page images, plus per-screen visible text and control inventories. This audit reviewed the inventories and source branches, visually inspected representative normal and expanded states across the lifecycle, and ran a fresh read-only walkthrough of the isolated local app. The new walkthrough covered hours review, time correction, pending/paused plans, an active recurring visit, private waiting and filtering applicants on mobile.

**Evidence labels:** “Observed” means rendered screenshot/text or runtime evidence. “Source-confirmed” means verified in the implementation without executing the consequential action. “Proposed” and “likely impact” are design judgments to validate with families. The grouped tracker notes do not claim that every one of the 1,050 images received a separate visual inspection.

No hire, invitation, message, visit completion, approval, payment or plan change was submitted in this audit. Existing screenshots provide the visual baseline; the new runtime filter screenshot is functional evidence because external asset access was restricted. Fixture dates, mocked payment values and unavailable external maps are not evidence of production defects. This is a heuristic audit, not measured conversion research.

## Highest-priority comments

P1 should be resolved in the design before shipping a new layout. P2 belongs in the first structural prototype. P3 is refinement. These are product design priorities, not measured customer-impact scores.

| Priority | Finding and evidence | Proposed change | Preserve |
|---|---|---|---|
| **P1** | **The task starts below the complete recipient profile.** On [mobile hours review](../screenshots/OT-timesheet_review-shift-mobile-viewport.png), “About Ellie” fills the screen while a fixed payment button is already visible. The same profile precedes every request tab. | Start with recipient, dated visit, status, submitted hours/amount and next action. Move the full profile into Care details. Show relevant care safety/contact essentials near the active or upcoming visit. | Every profile field, source, last-reviewed date and the version attached to this care. See DISC-01 / D01. |
| **P1** | **Some labels contradict the record.** [Pending time correction](../screens/OT-time_correction-shift.md) also says “Confirmed by family.” [Pending](../screens/RC-plan-pending.md) and [paused](../screens/RC-plan-paused.md) plans use active wording. | Derive labels from separate plan, visit, hours, correction and payment states. A pending item must name who acts next. Audit the header, receipt, list and history together. | Original state values, previous approvals and correction versions; an old approval never implies approval of new proposed hours. See D03–D04 / RC-UX-02. |
| **P1** | **Filtering can remove its own controls.** Reproduced: four applicants → Status = Saved → one featured result, zero Status controls and zero filter summaries. [Runtime evidence](live-walkthrough.json); [result image](filter-single-result-mobile.png). | Use the same card model for zero, one and many results. Keep filters and Clear filters outside the results region; show “1 of 4 caregivers · Saved.” | All applicant facts, save/decline/chat/hire actions, selected filters, sort and total/result counts. See DISC-04. |
| **P1** | **Commitments lack a focused review.** Hire invokes the hiring operation directly; normal end-visit and hours/payment actions also invoke their handler directly. These are source-confirmed behaviors; no accidental payment was reproduced. | Add a complete terms review before hiring. Separate **End visit**, **Review hours and payment**, and **Leave a review** into clearly scoped interactions. Show the amount and consequence before final payment confirmation. | Existing eligibility, calculations, authorization/capture behavior and current correction confirmation flow. See DISC-02 / D02. |
| **P1** | **Private waiting suggests the family can simply wait.** [Private request with zero invitations](../screens/OT-private_waiting-applicants.md) uses public “live” guidance and presents inviting as optional acceleration. | Say “Private request created — invite a caregiver to get replies.” After sending, name the number of people invited and their actual statuses. | Public/private choice, privacy rules, invitation history and explicit send. See DISC-03. |
| **P1** | **Recurring care loses its parent/visit distinction.** Generated visits are labeled one-time; source confirms some dated Schedule actions route to the parent plan. Ending can retain a confirmed visit that is omitted from upcoming care aggregation. | Label occurrences “Recurring visit · part of [plan].” Open the exact dated visit for View care/Fix payment. Preview which dates change, remain or cancel; keep retained visits accessible after plan end. | Stored request type, plan/booking IDs, payment exceptions and current end/keep-next semantics. See D04–D05 and recurring audit. |
| **P2** | **Tabs and instructions change around the user.** Tab order varies by stage. [Hired Caregiver tab](../screens/OT-scheduled-applicants.md) still foregrounds recruitment counts and selection copy. | Stable workspace tabs; chosen caregiver and contact first after hire; past applications/invitations grouped below. Show urgency with a visible count/action, without moving tabs. | Every tab's content, deep links, eligibility and recruitment history. See D06–D07. |
| **P2** | **The available applicants sit below invitation scaffolding.** Candidate-ready screens repeat status and invitation instructions before the list. | In Caregivers, put Applicants, Invited and Find caregivers into local views with counts. Open the useful view for the state and show the first applicant immediately below its controls. | Invitations, suggestions, full candidate credentials, replies and search. See DISC-05 / DISC-08. |
| **P2** | **Creation repeats summaries and has incomplete recurring summaries.** Filled recurring create is 5,679 px tall at the captured mobile size; the selected end date is absent from its final schedule summary. | Prototype Person & help → Schedule → Location & notes → Review. Compare against a compact progressive single page. Keep the full start/end/per-weekday schedule and price visible in the final review. | All input values, optional sections, profile/reuse choices, validation, weekday-specific times and end choice. See DISC-06–DISC-07. |
| **P2** | **Live care and Help need a clearer task hierarchy.** Detailed task/event evidence is not shown by the current live branch; help options are spread between tabs, disclosures and floating support. | Show current visit state, latest recorded event/time, relevant care brief and Message/Get help first. Make existing task/record data available when present; use one contextual Help entry with distinct supported issue types. | GPS meaning, actual timestamps, pending changes, tickets, incidents, disputes and their separate histories. See D08–D09. |
| **P2** | **Completed care, hours review, payment and feedback compete.** Multiple labels and repeated records make it harder to tell what is finished. | One factual summary, then the next required decision. Compare planned/recorded/corrected time; distinguish authorized, paid, refunded and payment-needed. Offer the caregiver review afterwards. | Rates, fees, receipts, corrections, both parties' reviews, cancellation reasons and complete visit history. See D10–D12. |
| **P3** | **Candidate presentation emphasizes an unexplained score.** Cards show “Match score 124” beside concrete match reasons. | Lead with requested-time fit, experience, review count and credential status. Keep the exact score accessible under Why suggested. Standardize card vocabulary. | Exact score, reasons, credential source and verified/reported distinctions. See DISC-09. |

## What to borrow from Upwork

Your Upwork reference is useful, especially its division between selecting someone and managing an engagement. I reviewed its current public help documentation; this is not a claim to have tested a signed-in Upwork account or its conversion performance.

| Documented Upwork pattern | LoLo adaptation |
|---|---|
| Proposals support messaging, shortlisting, hiring and archiving, with organized candidate views. [Review job proposals](https://support.upwork.com/hc/en-us/articles/18010402882195--Review-job-proposals). | A predictable caregiver pipeline with all decisions available on the same card. Preserve LoLo's distinction between applications and invitations. |
| Each contract has a central workspace; Overview highlights the next action, with timesheets, messages and details available in the same context. [Client contract workroom](https://support.upwork.com/hc/en-us/articles/115003673927-How-to-use-your-contract-workroom-as-a-client). | One dated care workspace whose Overview changes with the stage. Caregiver contact is always contextual; detailed records and help have stable homes. |
| Original job posts and proposals remain accessible after work starts. [Contract workroom](https://support.upwork.com/hc/en-us/articles/17975182774163--Use-the-contract-workroom). | Keep the original request, agreed schedule, selected application and past invitations accessible after hiring and closure. |

The design should retain LoLo's care-specific requirements: recipient safety and contacts, assigned-versus-candidate sharing, scheduled physical visits, family approval and explicit recurring-change scope. Billing policy, location verification and care completion rules need to remain grounded in LoLo's existing behavior. None of these recommendations require adopting another marketplace's billing model.

## Proposed workspace

### Dated request / visit

**Overview · Caregivers · Care details · Help**, always in this order.

| Area | Contents |
|---|---|
| Compact header | Recipient, one-time/recurring context, date/time/location summary, current state and record reference; recurring visit links to its parent. Avoid stacking every historical state as an equally prominent badge. |
| Overview | Current action or “No action needed”; schedule/caregiver/payment essentials; unresolved attention items; dated visit record and activity below. One primary action does not hide other unresolved issues. |
| Caregivers | Before hire: applicants, saved candidates, invitations and discovery. After hire: chosen caregiver, profile/contact, then original application and recruitment history. Filters remain available with zero/one result. |
| Care details | Complete recipient snapshot, needs, planned tasks and notes, location/access, contacts, original request and agreed terms. Keep profile information distinct from request instructions and completed task evidence. |
| Help | Contextual hours/change/cancellation/support/incident/dispute tools and their histories. Show only supported, eligible actions. Outstanding issues also appear in Overview. |

Messages should open the existing conversation for that caregiver **and request**, with a clear return path. Keep the separate global inbox; two requests with the same caregiver must not be merged. On initial arrival, Caregivers can be selected while choosing someone; Overview becomes the useful default once hired. Direct links and the user's current tab should continue to work.

### Recurring parent plan

**Overview · Visits · Care details · Help**, always in this order.

Overview shows the agreement's actual state, selected caregiver, attention items and current/live or next booked visit. Visits shows upcoming, past and cancelled occurrences, plus explicit plan-management actions. Care details holds the agreed weekly pattern, start/end choice and care brief. Help first establishes the affected visit or existing general-support context; no plan-level dispute workflow is assumed.

Show proposed dates separately from real bookings. Plan status and visit status can differ: an active plan can have a completed visit awaiting hours review, and an ended plan can retain one future confirmed visit. The retained visit and unfinished payments/reviews must stay reachable.

Keep the existing global Overview / Arrangements / Schedule / History available during this first redesign. These cross-care views are entry points into the same records. A broader global-navigation rename should be tested separately to avoid multiplying changes.

## Each requested stage

| Stage | Family's main question | First useful content and next action | One-time / recurring difference |
|---|---|---|---|
| **Create** | What am I asking for, and what will it cost? | Person/help, schedule, location/notes, complete editable review. Confirm privacy and publish/create private request. | One date versus day-specific weekly pattern, first/last date and weekly estimate. Retain per-visit payment explanation. |
| **Waiting / inviting** | Can people find this, and who has replied? | Public/private state, actual applicants/invitation counts. Invite if needed; review applicants when available. | Same candidate workflow; recurring candidate/hire review shows the full recurring commitment. |
| **Hired / before shift** | Who is coming, when, and is everything ready? | Caregiver, date/time, location/access, relevant care notes, payment readiness. Message/change visit; no forced action when ready. | Recurring adds next visit plus parent link. Plan schedule changes clearly differ from changing this one visit. |
| **During the shift** | Has care started, and how do I reach someone? | Actual check-in and latest recorded event, planned end, task/notes when available, care essentials. Message/Get help; End visit is a deliberate secondary operation. | Same dated-visit interface; plan overview promotes the live visit. “GPS captured” never implies continuous verified tracking. |
| **After the shift** | Is the record ready, and do I need to act? | Completion and submission state; if hours are submitted, lead straight to review. | Same flow per visit; recurring agreement continues independently. |
| **Review timesheet / leave review** | Are these hours and charges right? How was the care? | Planned/recorded/proposed time, breaks if present, tasks/notes, fees/total. Question or approve hours/payment, then leave optional feedback. | Close the dated visit's work; do not end the plan. Show other pending visit decisions separately. |
| **Closed** | What happened, what was paid, and where is the record? | Factual outcome, final financial state, reviews, receipt and full record. Rebook or find care when eligible; Help remains reachable. | One-time may offer Book again. Recurring shows the next unaffected visit or the ended-plan outcome. |

**Important lifecycle simplification:** In the current normal flow, completing care also submits the timesheet. “After shift” and “Waiting for timesheet review” are therefore often the same state. Keep both audit rows as user-journey checkpoints, but do not invent an extra waiting screen or backend status. A separate screen is warranted only when the actual state requires it.

## Desktop and mobile behavior

Desktop can show the task in the main column with a compact caregiver/care-brief panel beside it. Mobile should place the task first, with the same complete information below or in clearly named destinations. The inline concept lets the reviewer compare side versus stacked placement.

- Fit the visit identity, meaningful status and beginning of the current task in the first useful view. This is a prototype target to validate at 390×844, not a measured performance improvement.
- Keep four short primary labels in fixed order. Use badges and attention rows for urgency. Do not rely on hover for dates, costs, credential status or actions.
- Make the mobile bottom action context-aware. A payment action must appear with the reviewed amount/context; reserve space so support, keyboard and safe-area controls do not cover content. Evaluate a Review hours entry before the final approval control.
- Use full mobile pages for long hire reviews and plan-change previews, preserving Back, entered values and prior filter/scroll context. Desktop may use a dialog or side panel when the content fits.
- Use readable text and roughly 44–48 px touch targets as design targets. Validate larger text, keyboard navigation, focus restoration, screen-reader labels and contrast in the prototype. No accessibility conformance is asserted by this audit.
- Prefer plain consumer labels: **visit** for the dated care event, **recurring plan** for the ongoing agreement, **hours awaiting approval**, **payment authorized**, **paid**, and **review submitted** for their separate facts. Preserve internal terms/IDs in the record where useful.

## How we avoid losing data

The [preservation map](data-preservation-map.md) links 32 information/action families to the baseline inventories. Every current field must have a proposed home; every currently available operation must remain reachable under its existing permissions and eligibility. A collapsed section with content needs a meaningful label/summary, and pending decisions stay visible.

Three distinctions need particular care:

1. **Historical snapshot versus current profile.** Keep the version shared for this request/visit. A later profile edit must not rewrite the historical record; repeating care retains the explicit latest-profile choice where supported.
2. **Plan versus dated visit.** Changing a weekly schedule, skipping a date, pausing a period and ending a plan have different effects. Preview backend-calculated affected dates, including the confirmed visit retained by default when ending.
3. **Care, time, money and feedback.** Completing a visit, submitting hours, approving a correction, capturing a payment, issuing a refund and leaving a review are independent facts. A simpler header must not collapse them into a misleading “Done.”

The visual concept intentionally shows representative content, not all 32 families. The field-level inventories remain the acceptance baseline for implementation.

## Recommended order of work

1. **Resolve state/copy and scope first in the design.** Pending correction receipts, pending/paused plans, private waiting, recurring occurrence identity, exact visit links and retained visits. These affect whether families understand what is actually true.
2. **Prototype the common workspace.** Start with applicants, hired, live and hours review on desktop/mobile. Test profile placement, fixed tab order, candidate controls and explicit hire/payment review.
3. **Prototype creation and recurring management.** Compare staged versus compact forms; include unequal weekday times, end dates, missing payment readiness, pending schedule acceptance, pause and end/keep-next previews.
4. **Check preservation and exceptions before implementation approval.** Walk the existing 175 rows, compare each field/control with its new destination, and add captures for the source-only gaps below. Keep tracker redesign status “Proposed; not implemented” until work is actually built and verified.

### Validation tasks

Ask families to identify the next action, find information and explain the consequence before acting. Record success, wrong turns, time to locate the task and confidence; the following are proposed tests, not completed user research.

| Task | What must be demonstrated |
|---|---|
| Return to a private request with no invitations | Understand that inviting is required to get replies. |
| Filter four applicants to one and zero, then clear | Find filters/reset, retained total, credentials and all decision controls without leaving the workspace. |
| Review a recurring hire with different weekday times | Correctly describe every slot, start/end bounds, estimate and per-visit payment behavior. |
| Open a live visit on mobile | Identify caregiver/check-in and find contact, relevant care instructions and help without traversing the full biography. |
| Review hours, then a pending correction | Distinguish recorded/proposed/approved time, see fee/total, question the record and understand what final confirmation does. |
| Change a date; then pause/end recurring care | Correctly identify which visits are affected and find any retained confirmed visit afterwards. |
| Find an older closed visit | Retrieve original care snapshot, messages, receipt, corrections and reviews with the right record identity. |
| Navigate with keyboard, larger text and mobile keyboard open | Reach all tabs/actions, retain visible focus and avoid covered content or accidental submissions. |

### Coverage still needed before final redesign sign-off

The current capture set is extensive but does not establish every production branch. Add purposeful examples for no-show eligibility/outcome, disputes and resolution, partial/full refunds, approved-hours/payment-authentication failure, non-pending correction versions, recurring counterproposal/declined/expired states, forecasts without bookings, manually reported extra visits, and an ended plan retaining its next confirmed visit. The detailed audits distinguish these source-only cases from captured findings.

This proposal is ready for design discussion and prototype refinement. It does not authorize or include application implementation.
