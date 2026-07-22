# LoLo Regular Care Product and UX Specification

Status: Proposed
Audience: Product, design, engineering, operations, support
Primary users: Older adults receiving care, family coordinators, caregivers

## 1. Product decision

LoLo will use one recurring-care model:

- A `CareRequest` finds or invites a caregiver.
- A `CarePlan` records the ongoing agreement between the family and caregiver.
- A `CareBooking` represents one real visit on one date.
- A `CareBookingPayment` represents payment protection for one visit.

The UI calls the ongoing agreement **Regular care**. It calls each occurrence a **Visit**.

Users should never need to understand the terms care plan, occurrence, generated request, authorization, or capture.

## 2. Product goals

1. An 82-year-old user can understand who is coming, when, where, and whether anything needs attention.
2. A caregiver only sees visits that are real and confirmed in the operational visit list.
3. Payment is handled visit by visit. Future visits are never charged as one package.
4. Schedule changes are predictable and cannot silently alter a visit that is already close to starting.
5. Every recurring operation is idempotent, auditable, and recoverable by support.

## 3. Core language

Use:

- Regular care
- Repeats every week
- Next visit
- Upcoming visits
- Payment confirmed
- Payment needs attention
- Skip this visit
- Pause regular care
- End regular care

Avoid:

- Recurrence
- Occurrence
- Care plan ID
- Generated booking
- Authorized or captured in primary family-facing copy
- Projected visit
- Payment intent

## 4. Family creation flow

### Step 1: Frequency

Question: **How often do you need help?**

- One visit
- Regular visits

Supporting text under Regular visits: "Choose the days once. We will keep the same schedule until you change it."

### Step 2: Regular schedule

Show only these fields:

- First day
- Days of the week
- Start time
- Duration, starting at 1 hour in 30-minute increments
- End choice: "Until I stop it" or "End on a date"

The first day must match one of the selected weekdays. If it does not, the UI automatically moves it to the next matching day and explains the change in plain language.

### Step 3: Care details

Reuse the same recipient, location, tasks, and care-note questions as one-time care. Do not repeat questions already answered on the user's profile or a prior visit.

### Step 4: Review

Use one plain-language summary:

> Caroline will visit Don every Monday and Wednesday, starting Monday, July 20, from 9:00 AM to 12:00 PM at 123 Main Street.

Show:

- Caregiver, if already selected
- Recipient
- First visit
- Repeating schedule
- Location
- Estimated price for one visit
- Card on file

Payment copy:

> You are not paying for every future visit today. LoLo confirms your card before each visit and charges the final amount after each visit.

Primary action: **Confirm regular care**

Secondary action: **Go back**

## 5. Hiring and caregiver agreement

### Caregiver applied to a public regular request

The caregiver has already agreed that the published schedule can work. When the family hires them:

1. Create and activate the `CarePlan`.
2. Create the upcoming concrete visits.
3. Show both parties a final schedule confirmation.

Do not require a second caregiver acceptance unless the family changed the schedule after the caregiver applied.

### Family directly invites a known caregiver

The caregiver receives a regular-care offer with:

- Family and recipient name
- Exact days, start time, duration, and first date
- General location before acceptance and full address after acceptance
- Tasks and care notes
- Earnings for one visit

Actions:

- **Accept regular care**
- **Suggest a different schedule**
- **Decline**

A counteroffer must show the old and new schedule side by side for the family. The family has one primary action: **Accept new schedule**.

## 6. Family home experience

The dashboard's main care card is always about the next real visit:

> Caroline is coming Tuesday, July 21 at 9:00 AM
>
> 9:00 AM to 12:00 PM - 123 Main Street
>
> Payment confirmed. No action needed.

Primary action: **View visit**

Secondary action: **Message Caroline**

Small tertiary link: **Manage regular care**

Never show KPI-style counts when a human sentence can communicate the same information.

## 7. Family regular-care screen

### First viewport

Show, in this order:

1. Next confirmed visit
2. Caregiver photo and name
3. Exact date, time, and location
4. Payment state in plain language
5. One primary action based on current state

Possible primary actions:

- View visit
- Confirm payment
- Review hours
- Review schedule change

### Upcoming visits

Show the next three visits as simple rows. Every row must be a real `CareBooking`.

Each row contains:

- Full date, such as "Tuesday, July 21"
- Time range
- Caregiver name
- Status: Confirmed, Payment needed, Changed, or Cancelled
- **View** action

Do not display calculated dates as if they were booked. If the system has not created a visit, it should not appear in this list.

### Manage regular care

Keep management actions behind one clearly labeled section:

- Change future schedule
- Skip one visit
- Pause regular care
- End regular care

Destructive actions require a confirmation screen that says exactly what will and will not happen.

## 8. Schedule-change rules

### Skip one visit

- Cancels only the selected future booking.
- Keeps the regular schedule active.
- Cancels an uncaptured authorization for that visit when appropriate.
- Immediately notifies the caregiver.

### Change future schedule

- Family selects an effective date.
- Existing completed or in-progress visits never change.
- A visit inside the cancellation-policy window is handled separately.
- Day, time, or duration changes require caregiver acceptance.
- Notes and access-detail changes notify the caregiver but do not require acceptance.

### Pause regular care

- User chooses the pause start date and optional return date.
- Future bookings in the paused range are cancelled.
- No new bookings are generated in the paused range.
- Both parties see the resume date or "Paused until you resume."

### End regular care

Confirmation copy:

> Regular care will end after [date]. Your visit on [next visit date] will [still happen / be cancelled].

The user must explicitly choose whether an already confirmed next visit remains or is cancelled when policy permits.

## 9. Caregiver experience

### Work inbox

Use the inbox for decisions only:

- New regular-care offer
- Family schedule-change request
- Payment issue that blocks a confirmed visit
- Cancellation or pause requiring awareness

Do not show an active plan and its next visit as competing work items.

### My visits

This is the caregiver's operational source of truth.

Every real upcoming visit shows:

- Recipient
- Exact date and time
- Full address
- Expected earnings
- Tasks and access notes
- Payment protected or Family action needed
- Message family

The caregiver receives a 24-hour reminder and a one-hour reminder for every confirmed visit.

### Check-in rules

- Check-in opens 30 minutes before scheduled start.
- Standard check-in remains available until two hours after scheduled start.
- Earlier or later check-in requires an explicit support/admin override.
- Check-in is blocked when payment is not protected, unless an authorized admin override is recorded.
- The screen explains why the action is unavailable and what happens next.

## 10. Payment behavior

Payment belongs to a visit, not to the regular-care agreement.

1. A valid reusable payment method is required when regular care is activated.
2. Create future visit records without charging or holding funds for the whole series.
3. Attempt manual authorization 48 hours before each visit.
4. If the visit begins within 48 hours, authorize immediately.
5. Use the scheduled estimate plus the configured authorization buffer.
6. If 3DS or another customer action is required, mark only that visit as Payment needed and notify the family immediately.
7. Remind the family while action is outstanding.
8. Caregiver submits actual worked time after checkout.
9. Family approves the hours, or the existing 24-hour auto-approval rule applies when there is no dispute.
10. Capture the final visit amount.
11. Transfer the caregiver amount to the connected account.
12. Retry delayed transfers without recapturing the family payment.

Family-facing states:

- Card ready
- Payment confirmed for this visit
- Confirm your card for this visit
- Payment completed
- Refund in progress

Caregiver-facing states:

- Payment protected
- Family action needed - do not start yet
- Hours awaiting family review
- Earnings processing
- Earnings sent

## 11. System scheduling rules

- Materialize a rolling six-week window of `CareBooking` records.
- Run the replenishment job daily and after every plan creation or schedule change.
- Use a database uniqueness constraint for plan plus occurrence start time.
- Generation must be idempotent and safe to retry.
- Store the service timezone on the plan. Build local visit times in that timezone, then store timestamps consistently.
- Honor `starts_on`, `ends_on`, selected weekdays, pauses, skipped dates, and plan status.
- Never generate beyond `ends_on`.
- Keep `next_booking_id` reconciled to the earliest non-cancelled future visit.
- Reconcile plan health after every booking payment webhook or client confirmation.
- A payment problem affects the relevant visit. The plan remains active unless operations intentionally pause it.

## 12. Notifications

Required events:

- Regular care offered
- Offer accepted, countered, or declined
- Schedule changed
- Visit skipped or cancelled
- Regular care paused, resumed, or ended
- Payment confirmation required
- Payment confirmed
- Visit reminder at 24 hours
- Visit reminder at one hour
- Caregiver checked in
- Timesheet ready for review
- Timesheet auto-approved
- Payment captured
- Caregiver transfer sent or delayed

Every notification links to the exact plan or visit that needs attention.

## 13. Accessibility and older-adult UX requirements

- Minimum 18px body text on core care screens.
- Minimum 48px touch targets.
- One obvious primary action per section.
- Full dates and 12-hour times; avoid numeric-only dates and 24-hour time.
- Never communicate status by color alone.
- Avoid all-caps status text except very short badges.
- No icon-only actions for critical care or payment commands.
- Preserve entered information after validation errors.
- Put the error next to the field and explain how to fix it.
- Avoid nested cards, dense dashboards, KPI counters, and hidden horizontal scrolling.
- Confirm destructive changes with plain consequences, not generic "Are you sure?" copy.

## 14. Operational and admin requirements

Admin needs a regular-care view containing:

- Plan state
- Family, recipient, and caregiver
- Schedule and timezone
- Next six concrete visits
- Payment state per visit
- Notification history
- Schedule-change history
- Pause, resume, end, regenerate, and payment-retry controls
- Recorded reason and actor for every override

Admin must be able to run recurrence generation and payment preparation in dry-run mode.

## 15. Existing-customer recovery plan

The first production customer must be migrated as a controlled one-customer operation.

### Step A: Read-only audit

Identify:

- Original recurring request
- Hired caregiver and application
- Current booking and its real scheduled date
- Whether a `CarePlan` already exists
- Stripe payment state and authorization expiry
- Dates verbally or manually promised to the family and caregiver
- Whether any visit already occurred or was paid

Do not create, cancel, authorize, capture, or transfer anything during this audit.

### Step B: Human confirmation

Before changing data, contact both parties and confirm:

- Next visit date and time
- Weekly days and duration
- Service location
- Whether care should continue until cancelled or end on a date

The confirmed human schedule wins over any bogus projected date currently displayed.

### Step C: Choose the migration path

If there is a recurring request but no `CarePlan`:

1. Create a plan from the confirmed schedule.
2. Keep the original request as the source request.
3. Link the existing booking to the plan when it represents a valid real visit.
4. Preserve its payment and audit history.
5. Generate only missing future visits after that booking.

If a `CarePlan` already exists:

1. Repair its schedule and timezone from the confirmed information.
2. Reconcile status from the actual booking and payment state.
3. Point `next_booking_id` to the earliest real future visit.
4. Generate missing visits without duplicating the existing booking.

If the only booking is already completed:

1. Preserve it unchanged as historical evidence.
2. Generate the next future visit from the confirmed schedule.

### Step D: Payment safety

- Preserve any valid authorization attached to the same real upcoming visit.
- Do not authorize visits outside the 48-hour window.
- Do not create a second PaymentIntent for an already protected booking.
- Cancel an obsolete uncaptured authorization only after confirming its visit is cancelled or replaced.
- Never alter captured payment history during migration.

### Step E: Validate and notify

After migration, verify as family, caregiver, and admin:

- Same next visit on all three screens
- Correct address and timezone
- Correct caregiver
- No duplicate visits
- Correct payment state
- Correct upcoming reminders

Then send both parties a plain confirmation containing the next visit and repeating schedule.

## 16. Temporary operating procedure before the new system ships

Until recurrence generation is deployed:

- Treat only rows in `care_bookings` as confirmed visits.
- Ignore projected dates on the regular-care page.
- Create each upcoming visit through the normal one-time booking workflow.
- Confirm the visit manually with both parties.
- Verify payment protection before telling the caregiver the visit is confirmed.
- Keep at least one upcoming visit created, but avoid authorizing it more than 48 hours early.

## 17. Release acceptance criteria

The feature is ready when automated tests prove:

1. Hiring a recurring-request applicant creates one plan and multiple future bookings.
2. A second and third visit progress through check-in, timesheet, capture, and payout independently.
3. Re-running generation never creates duplicates.
4. End dates, pauses, skipped visits, and schedule changes are honored.
5. 3DS blocks only the affected visit and recovers plan/visit UI after confirmation.
6. Expired authorization is detected before care, not after care.
7. Family and caregiver show the same next visit.
8. Check-in timing and payment guards work with admin override.
9. Existing one-time requests continue to behave unchanged.
10. The production customer's migrated history and payments remain intact.
