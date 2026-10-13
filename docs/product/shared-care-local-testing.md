# Shared recurring care: local test guide

The local MVP is integrated into the existing Laravel/Livewire application.

## Open the demo

- Care home: http://127.0.0.1:8020/family/requests
- Week with gaps: http://127.0.0.1:8020/family/requests?week=2026-09-28
- The direct schedule URL still works: http://127.0.0.1:8020/family/shared-care/1
- Family: `john.shared@example.test`
- Caregivers: `alice.shared@example.test`, `ben.shared@example.test`, `carla.shared@example.test`
- Password for all four fictional demo accounts: `LocalCare2026!`

Use the account menu to log out and change roles, or use a separate private browser session.

The preview uses `database/shared-care-local.sqlite`, a separate session cookie and simulated Stripe payments. Email uses Laravel's array transport. The launcher enables verification/SMS bypass and disables Slack operations notifications and the AI provider. It does not change `.env` or migrate the configured MySQL database.

## Start or restart

From PowerShell in `C:\Users\Epicpp\homecare`:

```powershell
& .\tools\start-shared-care-local.ps1
```

Leave that terminal running. Stop with Ctrl+C. To choose a different port:

```powershell
& .\tools\start-shared-care-local.ps1 -Port 8021
```

The launcher migrates only its isolated preview database and creates missing demo records. Restarting preserves your changes. Frontend assets have been built; after changing styles, run `npm.cmd run build`.

## Current scenario after browser verification

John requests Monday–Saturday, 8 am–2 pm, starting September 21, 2026, in America/New_York.

1. Alice covers Monday, Wednesday and Friday.
2. Ben covers Tuesday, Thursday and Saturday through September 27. His assignment was ended from September 28 during testing. His first week's visits remain; subsequent visits remain in cancellation history.
3. Carla has applied for Tuesday and Thursday starting September 29. Her application is waiting for John to review and hire.
4. Alice accepted an extra visit on Sunday, October 4, 10 am–noon.

On a newly created preview database, only Alice is hired and Ben is waiting for review. The scenario above reflects the browser tests already performed on this machine.

## Suggested walkthrough

### John: review coverage and hire selected days

The main **Care** page opens the recurring care calendar directly. Move to September 28–October 4. Tuesday, Thursday and Saturday need a caregiver; the extra Sunday visit belongs to Alice. Select a visit to see its details; use **Agenda** for a list. Narrow screens automatically use an agenda layout.

Choose **Review replies** or Carla's **Review reply** link, then **Hire Carla**. Review her Tuesday/Thursday schedule, first date and first-visit estimate. Confirm. Saturday should remain missing and recruitment should remain open.

### Caregiver: offer only some days

Sign in as Carla and open http://127.0.0.1:8020/care-requests/1/apply before hiring her. Open **Your reply** to edit the application. Select available weekdays and submit. The first available date accounts for Ben's retained visits. An application is an offer; it does not create a booking.

Once a caregiver is hired, the original request opens their **Recurring Clients** screen. Their visits and schedule belong to their own agreement.

### John: remove one caregiver

On **Caregivers**, choose **Change caregiver**. The selected date is the first date without that caregiver. The dialog lists dated visits and accepted extras that will be cancelled. Earlier visits stay intact. Closing the dialog makes no change.

- Leave **I still need care** checked to keep gaps visible.
- Choose whether to reopen recruitment using the request's existing privacy setting.
- Uncheck **I still need care** to waive that caregiver's recurring days from the chosen date.

Other caregivers' agreements and historical payment records remain intact. Ending an assignment cannot be used to extend its existing end date.

### John: cancel, skip and restore a date

- Select a booked visit, open **Change this visit**, then **I need a different caregiver**. Review before cancelling that visit and keeping its hours open for replacement.
- **I don’t need this visit** opens a review before waiving only that date.
- **I need care again** restores the date as a requirement. Ask a caregiver to accept it; cancelled visits and payments are not silently revived.

These exceptions survive later visit generation, including dates beyond the current rolling booking window.

### Request extra care or one-time cover

Use **Add a visit**, or select an open date and choose **Ask my care team**. Select a caregiver, check the date/hours and estimate, and send the request. Agreements ending before the chosen date are excluded from the picker.

The date shows **Waiting for reply** until acceptance. Sign in as the caregiver, open **Recurring Clients**, and accept or decline under **Recurring care changes**. Acceptance creates a separate visit and payment lifecycle without changing the repeating schedule. A pending offer can be withdrawn before asking another caregiver.

Overlapping visits, stale offers, already-filled dates, past dates and dates after the caregiver's assignment ends are rejected. Repeated acceptance does not create another booking. Asking the same caregiver back after cancelling a visit creates a new booking and retains the old cancellation/payment history.

### Payments and history

Each visit belongs to one caregiver and keeps its own payment record. Missing care has no booked service charge. Upcoming visits outside the authorization window show **Card check before visit**; authorization/failed payment/paid states are shown independently of coverage.

Open **Payments**, then a visit's arrow for the existing payment, timesheet, dispute and support controls. An unresolved authorization release or a captured payment needing refund review stays visible on the original cancelled visit. Replacement care does not inherit that payment. This MVP does not add a new automatic refund or cancellation-fee policy.

The existing background jobs still handle rolling visit generation and payment preparation. To run those against the isolated demo explicitly, use a separate PowerShell terminal:

```powershell
& .\tools\start-shared-care-local.ps1 -PrepareOnly
php artisan homecare:generate-regular-care-visits
php artisan homecare:prepare-regular-care-payments
```

## MVP boundaries

- A caregiver offers whole requested weekday shifts. Splitting a single day's hours, alternating weeks and multiple simultaneous caregivers are not included.
- Extra/cover offers go to caregivers with an existing active agreement. Recruiting a new person uses the root request's available recurring weekdays.
- Repeating schedule edits and bulk pause/resume are intentionally unavailable for shared care. Use individual-date exceptions or end an assignment. The older single-caregiver controls cannot silently override this schedule.
- Adding recurring days to an already-hired caregiver's agreement and rehiring that same original application after ending it need a later agreement-amendment flow. One-time extras are supported.
- There is no automatic matching, emergency replacement guarantee or new payment policy. Existing payment/support workflows remain responsible for financial resolution.
- This is a local prototype with SQLite and payment bypass. Live Stripe and multi-user MySQL concurrency require staging verification before deployment. No deployment was performed.

## Implementation and validation

`CareArrangement` holds shared demand, `CareArrangementNeed` holds dated requirements, and existing `CarePlan` records hold each caregiver agreement. The root request stays available while recurring weekdays remain unassigned. Internal child requests preserve the existing visit/payment workflow.

Automated coverage includes partial hiring, duplicate actions, conflicting applications, cross-family caregiver conflicts, ownership, future removals, skip/restore, cancelled-visit suppression, extras, withdrawn offers, same-caregiver replacement history and payment ownership.

The main regression run passed 149 tests / 1,252 assertions. The final focused run passed 60 tests / 448 assertions after the UI and future-date exception changes (including 17 shared-care integration tests). The Vite build passed. Browser verification covered family hiring, future removal, gaps, caregiver day selection, extra request and caregiver acceptance.

The broader design and future edge cases are in `recurring-care-selected-days-mvp-spec.md` and `recurring-care-lifecycle-and-edge-cases.md`. Those documents describe more than this MVP ships.

The September 18 UI refresh adds the recurring calendar to the Care home, a selected-visit panel, caregiver/change/extra dialogs, and explicit unfilled care in the existing month calendar. Other care and account actions remain accessible on the home page. It retains the existing booking and payment services. Focused care, calendar and navigation checks passed 43 tests / 256 assertions, and the production asset build passed. Browser checks covered the cover-request dialog and live price, pending request/withdrawal, caregiver-change review, the month calendar's unfilled date, and responsive layouts at 390px and 320px. After-start no-show handling still opens the existing visit help flow; automatic partial-shift replacement is not added by this UI refresh.
