# Recurring care: one continuing home, with dated visits inside it

September 8, 2026. **Navigation proposal only. No application or concept implementation in this pass.** This refines the recurring navigation in V1/V2; it preserves the [data and action map](data-preservation-map.md) and the original [175-state baseline](../master-tracker.md).

## The actual problem

The family creates an ongoing care arrangement, but the interface changes the meaning of the page after hiring. The original recurring-request page becomes the first dated visit. The recurring plan lives elsewhere, and subsequent visits have their own generated request pages. The family has to infer which level they are managing.

V2 improved the appearance and added a parent breadcrumb, but still gave the plan and every visit competing workspace structures. Caregivers became Visits when switching contexts; recruitment, care details and Help reappeared at different levels. A breadcrumb helps orientation, but it does not resolve an unclear default home.

**Recommended change:** keep “Recurring care for Ellie” as the family's continuing home from publication through recruitment, hiring and ongoing care. Dated visits open within that arrangement's context. Their purpose is to handle one date's care, hours, payment or issue.

## Source evidence and constraints

| Current behavior | Evidence | Consequence for the proposal |
|---|---|---|
| Publishing marketplace recurring care creates an open recurring CareRequest and opens its request page; private requests open the applicants/invite context. | `CreateCareRequestWizard.php:686–714,755–763` | Before hiring, the continuing home is backed by the request. Display “Finding a caregiver”; do not pretend an active plan or confirmed visits already exist. |
| Marketplace Hire activates a CarePlan, links it to the source request, attaches the first booking to that original request, generates later occurrences and prepares payment. The UI refreshes the request and returns without navigating to the plan. | `CarePlanService.php:314,402–455`; `ManageCareRequest.php:280–304` | The same URL currently changes scope from recurring request to first visit. After hiring, the family-facing default should remain the arrangement, with the first visit displayed inside it. |
| Dated recurring Schedule cards route their main action to the parent plan. | `FamilyCarePresentationService.php:198–246` | A date-specific action must open that exact visit/action; general arrangement entry opens the continuing home. |
| First-visit and payment links deliberately use the original request page, which also holds recruitment/chat context. | `regular-care-show.blade.php:83,94`; `manage-care-request.blade.php:411–415` | Do not implement this by redirecting every old source-request URL to the plan. Preserve explicit visit links, applicants and conversations; navigation must distinguish the user's intent. |
| The repeat-care composer with an existing caregiver is a separate path: it requires an eligible source, sends a pending proposal and redirects directly to the plan. | `RegularCareComposer.php:54–56,121` | Both entry paths can share the same continuing home, but their next states differ. “Waiting for Sofia to accept” is appropriate to this proposal path; do not add an extra acceptance step to marketplace hiring without a separate business-rule decision. |

These are source-confirmed findings. No hires, messages, payments or plan changes were executed for this refinement.

## Proposed family journey

| Moment | Where the family lands | What they see |
|---|---|---|
| Create recurring care | Creation flow | Recipient/help, weekly pattern, start/end choice, location, price estimate and visibility. The final review explicitly says this is recurring care. |
| Publish | **Recurring care for Ellie** | “Finding a caregiver,” requested schedule, applications/invitations and next useful action. The care identity and main navigation now remain stable. |
| Choose a caregiver | Caregivers, inside the same care | Candidate review and full hiring terms. Original replies, invitations and conversations stay associated with this care. |
| Confirm a marketplace hire | Overview of the same care | Caregiver, agreed schedule, first actual visit and precise payment readiness. If card confirmation is needed, show that action for the first visit instead of claiming everything is ready. |
| Invite an existing caregiver through repeat care | Overview of the same care | “Waiting for Sofia to accept,” proposed schedule and response state. No confirmed-visit claim while the proposal is pending. |
| Return on another day | Overview of the same care | Current/next booked visit, all items requiring attention, and a clear route to all visits. |
| Open a visit | Dated detail inside that care's context | Actual caregiver, date/time, relevant care brief, task/notes record, hours/payment and issues for that date. |
| Finish reviewing that visit | Remain on the result, with a clear return | “Wednesday’s hours approved” with actual payment outcome; Back to visits restores the previous list/filter/scroll. An explicit “Review next” can advance to another pending item. Do not silently move to a different visit. |

The title should stay **Recurring care for Ellie**. The caregiver is a secondary fact once chosen; using the caregiver as the only identity before/after hire makes the arrangement feel like a different object. A coverage visit must show its actual assigned caregiver even when the plan's regular caregiver is someone else.

## One main navigation level

For this recurring-care home, propose **Overview · Caregivers · Visits · Care details** in that fixed order throughout recruitment and ongoing care.

- **Overview:** the current situation, next action, live/next confirmed visit and every unresolved attention item. Recruitment is promoted before hiring; dated care is promoted afterwards.
- **Caregivers:** applicants, invitations and selection before hire. After hire, chosen caregiver first, with recruitment history still accessible. It does not turn into a different tab when the plan activates.
- **Visits:** upcoming, needs attention, past and cancelled visits, plus access to the weekly schedule. Before hiring, show the requested pattern and clearly marked proposed dates; no fake visit records or payment actions.
- **Care details:** the original recurring request, agreed/current schedule context, care instructions and profile information. Distinct historical visit snapshots remain attached to each visit.

**Message** and **Get help** remain consistently reachable contextual actions. Full support/ticket/incident/dispute records remain available through the relevant care/visit context. This changes their entry location, not their availability or permissions.

This supersedes the earlier recurring plan tabs “Overview · Visits · Care details · Help” and the duplicated full workspace on every recurring visit. It does not require adding a Visits list step to ordinary one-time care.

## A visit is a detail view, not another care setup

Opening Wednesday should say **Wednesday, September 9 · Visit for Ellie**, with a visible **Back to recurring care / Back to visits** route and the parent care identity retained.

On desktop, open a readable visit panel beside the list when the task is short. Use a larger focused view for hours/correction review, with the parent context and Back preserved. On mobile, use a dedicated dated detail page; avoid a narrow panel or small nested modal for a long financial decision.

The visit contains its own actual date, assigned caregiver, notes/tasks, time evidence, charge/receipt, corrections and support history. It can use sections/disclosures for long records. It does **not** repeat the whole applicant-search/hiring interface or another four-tab care workspace.

Relevant care instructions and contacts must remain accessible from the visit. They must resolve to the version attached to that visit, not silently inherit a later plan/profile revision. The actual visit can differ from the recurring pattern in date, time, caregiver, rate or care instructions.

## Routing follows the user's intent

| Entry/action | Destination |
|---|---|
| Open “Recurring care for Ellie” from My care | Continuing care Overview |
| Return after marketplace hiring | Same care Overview; first-visit payment step if actually required |
| Open a dated calendar card | Exact dated visit, with parent context |
| Open “Review Wednesday’s hours” notification | Wednesday's hours review, not the generic plan home |
| Open “Fix payment” for Friday | Friday's payment action, not the first visit or plan-wide billing |
| Open original applicants / conversation link | Correct care's recruitment history or existing request-specific conversation |
| Back from visit / action review | Actual previous care tab/list/scroll and launching control; preserve drafts |
| Reload/share/bookmark a visit | Same dated record; direct access must work without first visiting the plan |

Parent-first means the default home is the arrangement. It must not add a detour to a date-specific notification or task.

## Keep plan actions and visit actions visibly separate

| This visit | Recurring care |
|---|---|
| Change Wednesday's time | Change the weekly pattern from an effective date |
| Skip/cancel Friday | Pause care for a period |
| Review this visit's hours and payment | View all visits needing attention |
| Report an issue about this visit | Get help with the ongoing arrangement through supported routes |
| Close this dated visit's outstanding work | End the recurring plan, with the current keep-next/cancel-next choice |

Show the scope in the action title and preview the affected dates before confirmation. Completing or cancelling one visit leaves the plan's independent state intact. Ending a plan can retain a confirmed visit and outstanding payment/review work; those records stay reachable.

Forecast dates and actual bookings must remain distinct. The weekly pattern can be ongoing while only a limited horizon is booked. Explain that distinction instead of presenting every future date as confirmed.

## Data and transition safeguards

Keep the request, plan, first booking, generated bookings and conversations as their existing records. A common family-facing home is a presentation/navigation proposal; it is not authorization to merge records or erase the original request.

Preserve all fields/actions in the [32-family preservation map](data-preservation-map.md), particularly the original application, invitation history, source request, profile/agreement versions, individual visit overrides, charge/approval/correction states, all messages, timestamps and existing access boundaries.

Handle activation/payment failure truthfully. A page transition must not create a second plan, duplicate bookings or lose the saved selection/draft. An active plan, unconfirmed card authorization and unfinished visit can coexist. No new acceptance, charging or cancellation rule is proposed here.

## Validate this direction before another visual refinement

Prototype the navigation as one continuous journey: publish recurring care, review applicants, confirm the caregiver, find the first visit, review a later visit and return. The key test is whether the family can always answer **“Am I changing one visit or my ongoing care?”**

Include the existing-caregiver proposal path, two pending visit decisions, a later visit with a different time/caregiver, direct payment-notification entry, activation/card-confirmation interruption and an ended plan with a retained visit. Verify desktop/mobile Back behavior, deep links, drafts and all information destinations.

The next visual iteration should demonstrate this continuous journey. V2 remains a useful branding/contrast reference; its separate plan/visit workspaces should not be treated as the settled navigation design.
