# Regular Care And Rebooking Spec

## 1. Objective

Design a smoother way for families to book the same caregiver again, especially when a trusted relationship already exists.

The target case is:

> Don wants Caroline, who already cared for Linda, to continue on a regular schedule without reposting a marketplace request.

Primary business outcomes:

- Increase repeat bookings after a completed shift.
- Reduce time from "I want the same caregiver again" to confirmed booking.
- Increase caregiver retention by making regular clients feel distinct from new marketplace leads.
- Reduce duplicate request creation for existing family-caregiver relationships.
- Make recurring care easier to start, change, pause, and resume.

## 2. Scope

In scope:

- Family rebooking after a completed, hired, or reviewed shift.
- Direct booking of a previously hired caregiver.
- Recurring care plan setup with the same caregiver.
- Caregiver accept, decline, and suggest-different-time flows.
- Dashboard, message, request detail, and caregiver inbox UX updates.
- Product vocabulary, screen behavior, state rules, notifications, and metrics.

Out of scope for the first implementation:

- Multi-caregiver scheduling across agencies.
- Medical care plan documentation.
- Insurance claims, benefits, or payer coordination.
- Automated payroll or tax treatment changes.
- Full calendar sync with external calendars.

## 3. Current-State Diagnosis

The current platform is strong for first-time matching:

1. Family posts a request.
2. Caregivers browse, apply, or accept an invitation.
3. Family shortlists, chats, hires, and completes a booking.
4. Both sides can review and message.

The main friction is that repeat care still feels like the original marketplace funnel.

Observed issues:

- The family dashboard is organized around requests, applicants, shifts, and messages.
- The completed Don and Caroline relationship lives inside a filled request.
- The natural user intent is "book Caroline again", but the UI pattern is "post another request" or "open the filled request".
- Rebooking is conceptually a direct relationship action, but the current model behaves more like cloning a public request and inviting the caregiver.
- Caregiver work surfaces mix new opportunities and regular-client continuity.
- Messages allow Don to ask Caroline about next month, but there is no structured booking composer in the conversation.

## 4. Product Principle

Marketplace requests are for finding trust.

Rebooking is for preserving trust.

The UX should make those paths feel different.

## 5. Target Mental Model

Use two product paths:

### 5.1 Find Someone New

Use when the family does not have a known caregiver or wants multiple options.

Flow:

1. Post request.
2. Receive applicants.
3. Shortlist and chat.
4. Hire caregiver.
5. Complete shift.

Primary noun: `Care request`.

### 5.2 Continue With Someone Known

Use when the family has a caregiver they already trust.

Flow:

1. Select known caregiver.
2. Reuse last care details.
3. Pick one-time or recurring schedule.
4. Send direct offer.
5. Caregiver accepts or suggests changes.
6. Booking or care plan becomes active.

Primary noun: `Care plan`.

## 6. Core Concepts

### 6.1 Care Relationship

A relationship between:

- Family user
- Care recipient
- Caregiver

Example:

`Linda Harris + Caroline Hill`

Purpose:

- Collect history across requests and bookings.
- Power relationship-first dashboard cards.
- Make repeat booking independent from the old request pipeline.

Recommended statuses:

- `active`
- `inactive`
- `paused`
- `ended`
- `blocked`

### 6.2 Care Plan

A reusable agreement for ongoing care with a known caregiver.

Contains:

- Recipient
- Caregiver
- Location
- Task list
- Care notes
- Schedule pattern
- Rate
- Payment method
- Cancellation policy
- Emergency contact
- Access notes

Recommended statuses:

- `draft`
- `pending_caregiver`
- `active`
- `change_requested`
- `paused`
- `ended`
- `cancelled`

### 6.3 Direct Offer

A private offer sent from a family to a specific caregiver.

Unlike a public request:

- No applicant competition.
- No public marketplace listing.
- One caregiver is asked to accept, counter, or decline.

Recommended statuses:

- `draft`
- `sent`
- `accepted`
- `countered`
- `declined`
- `expired`
- `cancelled`

### 6.4 Shift

An individual scheduled visit generated from a direct one-time booking or recurring care plan.

Care plans create shifts over time. The family should not need to create each shift manually unless they want an extra visit.

## 7. Product Vocabulary

Use human labels:

- `Book Caroline again`
- `Keep Caroline weekly`
- `Set up regular care`
- `Same as last visit`
- `Send to Caroline`
- `Caroline suggested a new time`
- `Care plan active`

Avoid internal or marketplace-first language in repeat flows:

- Avoid `repost`
- Avoid `clone request`
- Avoid `applicant`
- Avoid `shortlist`
- Avoid `filled request`
- Avoid `publish to marketplace` unless fallback is explicit

## 8. Entry Points

### 8.1 Family Dashboard

Show repeat-care cards above the request pipeline when relationships exist.

Card example:

```text
Linda + Caroline
Last visit: May 25, 9:00 AM
Care: Companionship, meals, light housekeeping

Primary CTA: Book Caroline again
Secondary: Message
Tertiary: View history
```

If the last review was positive, use that as context:

```text
You rated Caroline 5/5.
Book the same care again in under a minute.
```

### 8.2 Completed Shift

After family confirms or reviews a shift, show a prominent next step:

```text
Want Caroline again?
Use the same recipient, address, tasks, and rate.

Primary CTA: Book Caroline again
Secondary: Set up weekly care
```

This should appear before support tools.

### 8.3 Message Thread

When family and caregiver are in a hired conversation, show a structured action beside chat:

```text
Create booking from this conversation
```

Suggested smart prompt:

```text
Don asked about Monday and Wednesday mornings. Create a recurring care plan?
```

### 8.4 Caregiver Profile

For previously hired caregivers, replace generic CTA hierarchy with relationship actions:

```text
Book Caroline again
Message Caroline
View past care
```

### 8.5 Family Request Detail

On filled or reviewed requests, show a relationship continuation panel:

```text
Continue care with Caroline
Same recipient, same tasks, same address.

Primary CTA: Book again
Secondary: Make recurring
```

Do not hide this inside Support.

## 9. Family Workflow: One-Time Rebooking

Goal:

Don books Caroline again for one extra visit in under 60 seconds.

Flow:

1. Don clicks `Book Caroline again`.
2. System opens a focused booking sheet.
3. Defaults are copied from last successful booking:
   - Recipient
   - Address
   - Tasks
   - Task notes
   - Access notes
   - Rate
   - Payment method
   - Emergency contact
4. Don picks date and time.
5. Don reviews summary.
6. Don sends direct offer to Caroline.
7. Caroline accepts.
8. Shift is scheduled.

Family booking sheet sections:

1. `Who is this for?`
   - Pre-filled recipient.
   - Option to change recipient only if family has multiple saved recipients.
2. `When should Caroline come?`
   - Same time next week.
   - Pick date and time.
   - Make this recurring.
3. `Same care details`
   - Tasks shown as compact chips.
   - Expand to edit.
4. `Rate and payment`
   - Rate shown.
   - Payment method readiness shown.
5. `Send offer`
   - Confirmation summary.

Primary CTA:

```text
Send to Caroline
```

Success state:

```text
Offer sent to Caroline.
We will let you know when she accepts or suggests a different time.
```

## 10. Family Workflow: Recurring Care Plan

Goal:

Don sets up regular care with Caroline without creating repeated requests.

Flow:

1. Don clicks `Keep Caroline weekly` or `Set up regular care`.
2. System pre-fills the plan from the last shift.
3. Don chooses recurrence:
   - Weekly
   - Every 2 weeks
   - Custom days
4. Don selects days and times.
5. Don chooses start date and optional end date.
6. Don reviews plan summary.
7. Don sends plan to Caroline.
8. Caroline accepts or suggests changes.
9. Once accepted, plan becomes active and upcoming shifts are generated.

Recommended schedule controls:

- Segmented control: `One-time`, `Weekly`, `Custom`
- Day chips: `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat`, `Sun`
- Time range inputs
- Start date
- End condition:
  - No end date
  - End after date
  - End after number of visits

Example summary:

```text
Regular care with Caroline

Recipient: Linda Harris
Schedule: Monday and Wednesday, 9:00 AM - 12:00 PM
Starts: June 1, 2026
Rate: $30/hr
Estimated weekly total: $180
Tasks: Companionship, meals, light housekeeping, medication reminders
Payment: Visa ending 4242
```

Primary CTA:

```text
Send care plan to Caroline
```

Success state:

```text
Care plan sent.
Caroline can accept or suggest another time.
```

## 11. Caregiver Workflow: Direct Offer

Goal:

Caroline can understand Don's offer and respond in one screen.

Caregiver direct offer card:

```text
Don wants to book you again
Linda Harris
Monday and Wednesday, 9:00 AM - 12:00 PM
$30/hr

You cared for Linda on May 25.

Primary CTA: Accept
Secondary: Suggest different time
Tertiary: Decline
```

Caregiver response options:

### Accept

Result:

- Offer becomes accepted.
- Booking or care plan becomes active.
- Family is notified.

Confirmation:

```text
Accepted. Your next visit with Linda is scheduled.
```

### Suggest Different Time

Caroline can propose:

- Different day
- Different time
- Different start date
- Optional note

Copy:

```text
Suggest a time that works better.
Don can accept or edit it.
```

### Decline

Require a lightweight reason:

- Schedule conflict
- Too far
- Rate does not work
- Not a fit
- Other

Optional note.

Result:

- Family sees fallback options.
- Caregiver does not receive duplicate reminders.

## 12. Counterproposal Workflow

When Caroline suggests a change, Don should see a clear diff.

Example:

```text
Caroline suggested a different schedule

Original:
Monday and Wednesday, 9:00 AM - 12:00 PM

Suggested:
Tuesday and Thursday, 9:30 AM - 12:30 PM

Note:
I can keep mornings, but Monday is no longer available.

Primary CTA: Accept Caroline's suggestion
Secondary: Edit and resend
Tertiary: Decline
```

Rules:

- Show the changed fields only.
- Preserve unchanged care details.
- Do not create a public request during counterproposal.
- If Don edits, return status to `pending_caregiver`.

## 13. Fallback Workflow

If Caroline declines, does not respond, or is unavailable, offer graceful recovery.

Family options:

```text
Caroline is unavailable.

Primary CTA: Invite backup caregivers
Secondary: Post this as a new request
Tertiary: Pick another saved caregiver
```

If no backup exists:

```text
Find similar caregivers
```

Recommended matching behavior:

- Rank caregivers similar to Caroline by task comfort, location, reliability, and availability.
- Preserve the same care plan draft.
- Do not force Don to re-enter details.

## 14. Dashboard Design

### 14.1 Family Dashboard

Priority order:

1. Urgent next action.
2. Regular care relationships.
3. Upcoming shifts.
4. Recent messages.
5. Request pipeline.

When Don has an existing Caroline relationship, the dashboard should lead with:

```text
Continue care

Linda + Caroline
Last visit completed
Next step: Book again or set up regular care
```

Card actions:

- `Book again`
- `Make recurring`
- `Message`

Avoid showing only marketplace metrics like applicants and waiting applicants when a known caregiver relationship is the most valuable next action.

### 14.2 Caregiver Dashboard

Priority order:

1. Needs response.
2. Upcoming regular clients.
3. Today's shifts.
4. New opportunities.
5. Earnings.

For Caroline:

```text
Regular clients

Linda Harris
Don wants Monday and Wednesday mornings

Primary CTA: Review offer
```

## 15. Care Relationship Detail Screen

A new relationship screen should show all continuity in one place.

Header:

```text
Linda + Caroline
Regular care relationship
```

Sections:

- Next shift
- Active care plan
- Recent messages
- Last visit recap
- Care details
- Payment status
- History

Primary actions:

- `Book extra visit`
- `Change schedule`
- `Pause plan`
- `Message`

Use this screen instead of forcing families back into old filled requests.

## 16. Care Plan Detail Screen

Family view:

- Plan status
- Schedule
- Recipient and caregiver
- Tasks and notes
- Upcoming visits
- Payment readiness
- Change history
- Cancellation policy
- Support entry point

Caregiver view:

- Plan status
- Schedule
- Recipient context
- Tasks and notes
- Upcoming visits
- Earnings estimate
- Change request actions
- Support entry point

Primary CTA depends on state:

| State | Family primary CTA | Caregiver primary CTA |
| --- | --- | --- |
| Draft | Send to caregiver | Not visible |
| Pending caregiver | View offer status | Accept |
| Countered | Review suggestion | Waiting for family |
| Active | Change schedule | View upcoming shifts |
| Paused | Resume plan | View pause details |
| Ended | Book again | View history |

## 17. Messages Integration

The message thread should support structured booking actions without becoming noisy.

Recommended components:

- Booking suggestion chip:
  - `Create care plan`
  - `Book extra visit`
- Inline offer status:
  - `Offer sent`
  - `Accepted`
  - `Caroline suggested a new time`
- Link to care plan detail.

Message examples:

```text
Offer sent: Weekly care with Caroline
Mon and Wed, 9:00 AM - 12:00 PM
```

```text
Caroline accepted your care plan.
Next visit: Monday, June 1 at 9:00 AM.
```

## 18. Payment UX

Payment should be handled before offer activation when required, but it should not interrupt the emotional flow.

Rules:

- If payment is ready, show a simple confirmation.
- If payment is missing, show it as a required step before sending.
- For recurring care, authorize the plan setup and capture per completed shift.
- Show estimated weekly total for recurring plans.
- Show clear cancellation policy before sending.

Payment copy:

```text
Payment ready
Your saved payment method will be used after each completed visit.
```

Missing payment copy:

```text
Add payment to send this offer.
Caroline will not receive it until payment is ready.
```

## 19. Notifications

### 19.1 Family Notifications

- Caroline accepted your booking.
- Caroline suggested a new time.
- Caroline declined this offer.
- Caroline has not responded yet.
- Your regular care plan starts tomorrow.
- Your care plan was paused.
- Your care plan was changed.
- Payment needs attention before the next visit.

### 19.2 Caregiver Notifications

- Don wants to book you again.
- Don sent a regular care plan.
- Don accepted your suggested time.
- Don edited the care plan.
- Upcoming visit with Linda tomorrow.
- Care plan paused.
- Care plan ended.

### 19.3 Reminder Timing

For direct offers:

- Immediate notification on send.
- Reminder after 12 hours.
- Expire after 72 hours by default.

For recurring plan shifts:

- Family reminder 24 hours before visit.
- Caregiver reminder 24 hours before visit.
- Caregiver day-of reminder 2 hours before visit.

## 20. State Rules

### 20.1 Eligibility To Rebook

A family can rebook a caregiver when:

- The caregiver was previously hired by this family.
- The caregiver profile is active and eligible for marketplace work.
- The prior application or booking is not under unresolved dispute.
- The family account is active.

Optional stricter rule:

- Require at least one completed booking before "regular care" is offered.

### 20.2 Direct Offer Expiration

Default expiration:

- 72 hours for one-time direct offer.
- 72 hours for recurring care plan offer.

After expiration:

- Family can resend.
- Family can edit and resend.
- Family can invite another caregiver.

### 20.3 Care Plan Shift Generation

For recurring plans:

- Generate a rolling window of upcoming shifts.
- Recommended first window: next 4 weeks.
- Generate more shifts automatically as time passes.
- Do not generate beyond the plan end date.

### 20.4 Change Requests

Family can request:

- Schedule change.
- Rate change if platform allows negotiated rates.
- Task change.
- Location change.
- Pause.
- End plan.

Caregiver can request:

- Schedule change.
- Pause.
- End plan.
- Support review.

Any material change should create a confirmation step for both sides.

Material changes:

- Rate
- Schedule
- Location
- Recipient
- Core task list
- Cancellation terms

## 21. UX Design Guidelines

### 21.1 Hierarchy

Repeat care screens should lead with:

1. People.
2. Next visit or plan.
3. Primary action.
4. Operational details.

Avoid leading with:

- Request ID.
- Applicant counts.
- Marketplace pipeline metrics.
- Old request status.

### 21.2 Visual Treatment

Use calm, high-trust surfaces:

- Relationship card: white or lightly tinted surface.
- Active care plan: primary dark command surface only when operational.
- Alerts: reserved for payment, disputes, or imminent schedule issues.

The relationship card should feel warmer and more personal than generic request cards, but still utilitarian.

### 21.3 CTA Rules

Every repeat-care screen must expose exactly one primary action.

Examples:

- `Book Caroline again`
- `Send to Caroline`
- `Accept offer`
- `Review Caroline's suggestion`
- `Resume care plan`

Secondary actions:

- `Message`
- `Edit details`
- `View history`
- `Invite someone else`

### 21.4 Mobile Rules

On mobile:

- Primary CTA must be visible without deep scrolling.
- Booking sheet should use stacked sections.
- Schedule selection should avoid dense calendar grids at first.
- Use quick choices:
  - `Same time next week`
  - `Tomorrow`
  - `Pick date`
  - `Make recurring`
- Keep task lists collapsed by default if copied from last visit.

### 21.5 Copy Rules

Use relationship language:

- `Caroline already knows Linda's routine.`
- `Use the same care details.`
- `Send a private offer to Caroline.`

Do not over-explain the mechanics.

Avoid:

- `Clone`
- `Republish`
- `Application`
- `Shortlist`
- `Filled`
- `Marketplace-ready`

### 21.6 Empty States

If no repeat caregiver exists:

```text
No regular caregiver yet.
After your first completed visit, you can book the same caregiver again from here.
```

If no upcoming shift exists for an active relationship:

```text
No next visit scheduled.
Book Caroline again or set up regular care.
```

## 22. Information Architecture

Recommended navigation updates:

Family:

- Dashboard
- My Care
- Post Request
- Find Caregivers
- Billing
- Notifications
- Messages

`My Care` can contain:

- Regular care
- Upcoming shifts
- Requests
- Past care

Caregiver:

- Dashboard
- Work Inbox
- Regular Clients
- My Shifts
- Earnings
- Notifications
- Messages

If adding new nav items is too much for MVP, keep current navigation but add regular care modules inside dashboard and work inbox.

## 23. Analytics

Track these events:

- `rebook_cta_viewed`
- `rebook_started`
- `rebook_prefill_used`
- `direct_offer_sent`
- `direct_offer_accepted`
- `direct_offer_countered`
- `direct_offer_declined`
- `direct_offer_expired`
- `care_plan_started`
- `care_plan_activated`
- `care_plan_paused`
- `care_plan_resumed`
- `care_plan_ended`
- `repeat_booking_completed`

Core metrics:

- Completed shift to rebook CTA click rate.
- Rebook start to direct offer sent rate.
- Direct offer acceptance rate.
- Median time from rebook start to caregiver acceptance.
- Percent of repeat bookings created without a new public request.
- Recurring plan activation rate.
- First 30-day retention for care plans.
- Caregiver regular-client retention.

## 24. Admin And Operations

Admin should be able to see:

- Active care relationships.
- Active care plans.
- Pending direct offers.
- Counterproposals.
- Paused plans.
- Plans with payment issues.
- Plans with repeated caregiver declines.

Ops actions:

- Resend reminder.
- Cancel direct offer.
- Pause care plan.
- End care plan.
- View message context.
- View booking history.
- Escalate to support ticket.

## 25. Trust And Safety

Rules:

- Do not allow direct booking if caregiver is suspended.
- Do not allow recurring care plan activation during unresolved dispute.
- Show clear cancellation and payment terms before sending.
- Maintain audit trail for schedule, rate, location, and task changes.
- Keep emergency contact and access notes visible but not overexposed.
- Require explicit confirmation when location or recipient changes.

## 26. Accessibility

Baseline:

- Minimum 44px touch targets.
- Clear focus states.
- All icon-only controls require accessible labels.
- Schedule chips must be keyboard selectable.
- Counterproposal diffs must not rely on color alone.
- Status text must be explicit.
- Time and date formats must be readable and localized.

## 27. MVP Release Plan

### Phase 1: Surface Existing Rebook Intent

Goal:

Make the current rebook path discoverable.

Changes:

- Add `Book Caroline again` CTA on completed shift, review success, dashboard, and message thread.
- Rename any internal-facing rebook language to user-facing relationship language.
- Preserve current request clone behavior behind the scenes if needed.

Success:

- Families can find rebooking without opening Support.

### Phase 2: Same-As-Last-Time Direct Offer

Goal:

Let Don send a private one-time offer to Caroline without a public request.

Changes:

- Add prefilled booking sheet.
- Add direct offer status.
- Add caregiver accept, decline, suggest time.
- Add family fallback if caregiver declines.

Success:

- One-time repeat booking can be completed without applicants or shortlist.

### Phase 3: Recurring Care Plan

Goal:

Support weekly or custom recurring care.

Changes:

- Add care plan object and screen.
- Add recurring schedule setup.
- Generate upcoming shifts.
- Add pause, resume, end.
- Add plan-level notifications.

Success:

- Don can set up ongoing care with Caroline once.

### Phase 4: Regular Clients Experience

Goal:

Make ongoing care feel first-class for both sides.

Changes:

- Family `My Care` area.
- Caregiver `Regular Clients` area.
- Relationship detail page.
- Plan change history.
- Backup caregiver suggestions.

Success:

- Regular care is no longer managed through old request pages.

## 28. Acceptance Criteria

### Family

- Don can start rebooking Caroline from dashboard, completed shift, message thread, or caregiver profile.
- Don can reuse previous details without re-entering recipient, tasks, address, or notes.
- Don can choose one-time or recurring schedule.
- Don can review price and payment status before sending.
- Don can see whether Caroline accepted, declined, countered, or has not responded.
- Don can accept Caroline's suggested change.
- Don can fallback to another caregiver without retyping care details.

### Caregiver

- Caroline can distinguish direct repeat offers from new marketplace opportunities.
- Caroline can accept a repeat offer in one screen.
- Caroline can suggest a different time.
- Caroline can decline with a reason.
- Caroline can see upcoming recurring client shifts separately from new opportunities.

### System

- Direct offers do not create public applicant competition.
- Recurring plans generate upcoming shifts.
- Payment readiness is checked before activation.
- Major care plan changes are auditable.
- Suspended or ineligible caregivers cannot receive new direct offers.

## 29. QA Checklist

- Rebook CTA appears after completed and reviewed shifts.
- Rebook CTA does not appear for unresolved disputes.
- Prefill uses the most recent successful booking.
- Family can edit copied details before sending.
- Caregiver can accept, decline, and counter.
- Family can accept counterproposal.
- Offer expiration works.
- Payment missing state blocks sending or activation clearly.
- Recurring plan creates correct upcoming shifts.
- Paused plan stops future shift generation.
- Ended plan no longer creates shifts.
- Mobile layout keeps primary CTA visible.
- Long caregiver, family, and recipient names do not break cards.
- Time zones and daylight saving changes are handled for recurring shifts.

## 30. Open Questions

- Should recurring care require one completed shift first, or is a hired/shortlisted caregiver enough?
- Should rate changes require caregiver approval every time?
- Should families be able to invite multiple preferred caregivers to one recurring plan as backups?
- Should expired direct offers be automatically converted into suggested marketplace requests?
- How many upcoming shifts should be generated at once?
- Should recurring plans support skipped holidays at launch?
- Should caregiver availability block offer sending or only warn the family?

## 31. North Star Experience

Don should be able to say:

> Caroline worked well with Mom. I want her every Monday and Wednesday morning.

The product should answer:

> Great. Here is the same care plan, already filled in. Choose the schedule, send it to Caroline, and we will handle the rest.

