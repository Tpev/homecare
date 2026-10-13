# Recurring home visual review

September 8, 2026. Read-only inspection of the freshly captured local application:

- [Active plan, desktop viewport](../screenshots/RC-plan-active-desktop-viewport.png)
- [Active plan, full mobile page](../screenshots/RC-plan-active-mobile-full.png)
- [Pending plan, mobile viewport](../screenshots/RC-plan-pending-mobile-viewport.png)

Final sample recheck after the priority fix:

- [Active Overview, full mobile page](../screenshots/RC-plan-active-overview-mobile-full.png)
- [Countered Overview, desktop viewport](../screenshots/RC-plan-countered-overview-desktop-viewport.png)
- [Countered Overview, full mobile page](../screenshots/RC-plan-countered-overview-mobile-full.png)

The four tabs fit on mobile without clipped labels. The full recipient identity, recurring schedule, explicit plan status, caregiver/help actions, current dated visit, location/address line 2, authorization status, safety information and history links are visible in their intended sections. No obstructing support bubble or overlapping footer is visible. The pending screen states that the schedule is an offer and that visits appear after acceptance/bookings; it does not label the offer active or present forecasts as confirmed visits.

The initial images revealed a hierarchy problem: attention rows were chronological, placing an optional caregiver review before two outstanding hours approvals, with the current live visit below the queue. Root authorized a narrow presentation fix. The final Active Overview mobile sample now visibly places Current visit before the queue and both Review hours rows before Leave a review. All items, statuses, fields and actions remain. The focused home suite passes 9 tests / 126 assertions, including both live and paused priority and unchanged persisted status/approval checks. This sample verifies the correction; the separate baseline coverage report governs completion of all refreshed IDs.

The countered desktop/mobile samples correctly show New schedule proposed and Proposed dates · not booked yet. The differing proposed slots are Tuesday 9:00–11:00 AM and Thursday 1:00–3:30 PM; the displayed September 15, 17 and 22 dates match those weekdays/times. The original offer pattern remains visible in the header, while the counterproposal is named in the response card. No generated current/next-booking card is falsely shown. The acceptance button is visible without clipping or an obstructing support overlay. The added fixture's prior completed source visit is distinct from generated recurring bookings.

The active fixture shows a September 7 booking still in progress in a September 8 capture. Its date and status come from the retained synthetic baseline. Do not change business records merely to make the screenshot resemble a chronological live customer history; the semantic fixture/status check should distinguish preserved synthetic state from actual clock-dependent copy.

This inspection does not cover every plan tab, modal, terminal state or keyboard path. Those require the remaining capture evidence and validation. Counterproposal acceptance was not submitted during screenshot review. The follow-up change was limited to display order and extracting the same current/next card markup; no business service or CSS changes were made.
