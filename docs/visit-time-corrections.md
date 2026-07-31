# Visit time corrections

Visit time corrections let an assigned caregiver propose missing or corrected hours for one exact booking. The owning family can approve the proposal or ask for a new immutable version. The workflow applies equally to one-time bookings and individual regular-care occurrences; it never changes the parent care-plan schedule or another occurrence.

## Product and UX specification

### Caregiver

- Eligible exact-visit screens expose **Fix visit time**; an expired regular-care occurrence uses **Add missed hours** as the primary action and retains **Get LoLo help** as the support path.
- The caregiver selects what happened, enters actual start/end and unpaid break, explains the correction, and confirms these are real care hours.
- A review step compares scheduled, app-recorded, and requested time and shows worked duration plus estimated earnings before submission.
- My Visits shows an action-required badge and links back to the exact occurrence. A change request reopens the form with the family's note and creates a new immutable version when resubmitted.

### Family or care receiver

- The exact Visit screen presents pending hours prominently with caregiver, booking/date/timezone, reason, explanation, duration, and scheduled/app/requested comparison.
- The family sees one plain-language final amount. It does not show refund, transfer, capture, or Stripe internals.
- The primary action is **Approve [duration] and pay [amount]**, followed by an explicit financial confirmation. The family may instead ask the caregiver to change the proposal with a required note, or get help from LoLo.
- Care History shows the correction status and immutable version. A regular-care plan shows a banner linking to the exact occurrence that needs attention.

### Lifecycle

`pending_family` → `changes_requested` → new `pending_family` version, or `pending_family` → `approved_processing` → `applied`.

Payment confirmation moves an approved request to `payment_action_required` without losing the family's approval. Settled money moves it to `approved_admin_required`; user escalation or an unresolved timeout moves it to `escalated`. A caregiver may withdraw only while collaboration is pending. Superseded proposals remain immutable and visible to admin.

### Eligibility and safety

- Supports scheduled visits after their start, in-progress/paused visits, and completed/reviewed visits, including one-time, regular, and extra care-plan occurrences.
- Rejects cancelled, disputed, no-show, future, reversed, excessive, or overlapping time proposals.
- Uses the care-plan timezone for plan occurrences and the established application fallback for one-time care; overnight and DST boundaries are validated server-side.
- Routine collaboration never marks a booking disputed. Manually proposed hours never auto-approve, and an active correction blocks the existing 24-hour auto-approval under a booking row lock.

### Responsive and accessible behavior

- At mobile widths, comparison cards stack, inputs use at least 16px text, actions are full width with 48px targets, and no horizontal table is introduced.
- At desktop widths, the three-way comparison is side by side and financial impact stays adjacent to approval.
- The workflow uses semantic headings, fieldsets, labels, visible focus, `aria-live` status regions, inline validation, and focus restoration when panels or confirmation states close.

## Rollout

The workflow is off by default:

```dotenv
MARKETPLACE_TIME_CORRECTIONS_ENABLED=false
```

Deploy the additive migration and application code while the flag is off. After migration and smoke checks, set the flag to `true` through the normal production configuration process and rebuild the configuration cache. Turning the flag off hides new user entry points and stops reminder processing; existing records and audit evidence remain intact.

Optional limits are documented in `.env.example`. Defaults are a 72-hour self-service application window, a 16-hour maximum corrected duration, reminders around 12 and 24 hours, and escalation around 48 hours.

## State and money safety

- A submitted proposal does not modify the booking or payment.
- Every revision is a new row linked through `supersedes_id`.
- An active proposal blocks the 24-hour timesheet auto-approval, including under the final row lock.
- Family approval is recorded before processing. Unsettled bookings use the existing booking payment service and an immutable correction ledger.
- If card confirmation is needed, time approval remains saved in `payment_action_required`.
- Captured, transferred, refunded, already family-confirmed, or out-of-window visits create one normal-priority `time_correction` support ticket after agreement. User actions do not refund or reverse settled money.
- Original schedule, app timestamps, check-in/out GPS, and event evidence are retained in the immutable snapshot and existing booking audit fields.

## Operations

`homecare:process-time-corrections` is scheduled hourly and is safe to run repeatedly. It sends idempotent reminders and creates at most one support ticket per correction. Admin support shows every proposal version, both parties’ notes, the original event/GPS evidence, current payment state, and the existing protected correction controls.
