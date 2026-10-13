# Family Schedule: List / Calendar

Local page: http://127.0.0.1:8028/family/care/schedule

List remains the default, including its existing progressive loading. The Calendar toggle opens at the nearest upcoming visit and offers previous month, next month and Today controls. Both views use the same care-recipient and care-type filters; the URL retains the selected view, month and day.

The desktop calendar previews visit times, recipients and caregivers. Smaller screens show a compact date grid with visit counts. Selecting a date reveals every visit for that date below the grid, with full times, care type, recipient, caregiver photo or initials, location, visit reference and status. “View visit” opens that specific visit, including individual occurrences of recurring care. Payment issues retain their existing “Fix payment” action.

The calendar queries all matching bookings in the displayed date range independently of list pagination. It uses the existing family-account scope, upcoming status rules and application timezone. Overnight visits appear on each date they overlap, with an exclusive midnight end; monthly totals count each booking once. Only already-created bookings appear. Historical visits remain in History.

No care records, recurrence rules, pricing, payment flows or booking statuses were changed.

## Verification

- New calendar feature tests: 10 tests, 83 assertions passed using SQLite `:memory:`.
- Existing shared schedule and progressive loading tests: 2 tests, 22 assertions passed.
- PHP formatting and frontend production build passed.
- Read-only browser checks and screenshots: see `validation.json` and `qa.mjs`. Browser interactions are restricted to login, view/filter changes, month/date selection and read-only navigation.

## Screenshots

| View | Capture |
| --- | --- |
| Existing list, desktop | [1440 px](screenshots/list-1440.png) |
| Calendar, desktop | [1440 px](screenshots/calendar-1440.png) |
| Calendar, tablet | [768 px](screenshots/calendar-768.png) |
| Calendar, mobile | [390 px](screenshots/calendar-390.png) |
| Calendar, narrow mobile | [320 px](screenshots/calendar-320.png) |
| Empty selected date, narrow mobile | [320 px](screenshots/calendar-empty-320.png) |

Local Maria Bennett data is used for screenshots. These are existing bookings; no sample visits were inserted into the live local database.
