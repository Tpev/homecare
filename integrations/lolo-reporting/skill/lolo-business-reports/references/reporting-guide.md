# Report modes and business definitions

## Short status / hot topics

Use `report --mode status` or `hot`, then read full evidence for the top relevant candidates. Include existing-customer issues separately from first-time sales. A useful customer line is: **Customer — situation — latest evidence — blocker — next step — owner/date — what needs confirmation.** Prioritize recent direct intent, unresolved matching, imminent starts, and overdue actions. Don't treat all qualified leads as hot or assume a new inbound message is positive.

For each assessment, cite stable `dataset:id` references and source dates. If quoting a staff note, say that it is a staff report. Label operational confirmation separately (e.g. a retained booking with terms, a completed visit, or recorded payment status). A plausible inference is not a verified event.

## Caregiver searches

Use `searches`. The queue considers open genuine requests, current retained bookings, active/paused/payment-attention recurring placements, shared arrangement gaps and unresolved continuous-coverage replacements. Cancelled/released or historical completed bookings do not prove current coverage. Some staff placements have no application; zero applicants does not mean nobody was hired. “Hired” application without a booking/plan needs confirmation.

Read customer-care actions, recruitment reasons, invitations, offer notes, applications and request messages. Summarize geography, schedule, required skills, active candidates, rejection/withdrawal reason, and next matching step. Missing availability data means it is unverified; approval alone doesn't prove a caregiver is taking work. Protect home-access instructions and unnecessary recipient details.

## Weekly progress and conversion

Use `weekly --days 7`, with an optional snapshot diff. The default period is a rolling interval ending at snapshot acquisition. Do not call it a calendar week without checking dates. Use the same timezone/window across comparisons.

Metrics distinguish:
- Family inquiries and distinct linked households; duplicate/merged/repeat records are not automatically new customers.
- First attempted call vs first connected contact. `last_contacted_at` may reflect unanswered activity.
- CRM converted vs first retained booking vs first completed visit. The new-customer cohort excludes households with earlier lead acquisition or delivered care in retained history; lost/deleted prior history remains unknown. Incomplete account linking biases conversion rates.
- Original demand vs system-generated requests for recurring visits.
- Scheduled visits vs actual completed minutes; missing worked minutes are disclosed.
- Caregiver registration, review submission, current approval, accepting-new-client state, and first completed work.
- Cumulative captured/refunded booking balances vs period prepaid purchases, prepaid consumption, and caregiver payouts. These are not interchangeable revenue measures. Missing spend ingestion makes CAC unknown.

The client produces core counts and candidate lists. For deeper questions, query the local `index.sqlite` in URI `mode=ro`, with `PRAGMA query_only=ON`; the `records` table stores dataset/key/data JSON and indexes key relationships. The exact field contract and meanings live in the repository's `docs/reporting/` directory. Use evidence-backed calculations and disclose denominators. Never guess fields or substitute dashboard totals.

## Lost/gained customers and CS questions

Read full chronological notes before assigning reasons: price/affordability, timing, coverage, no response, suitability, trust, competitor, resolved objection, or unknown. More than one reason may apply. Separate explicit customer statements, CS interpretation, and analyst inference. A caregiver job inquiry filed as a family lead is a classification issue, not an ordinary lost customer. A correction can invalidate an earlier conversion or “connected” event.

For each discrepancy, show the conflicting evidence and a short question that can settle it. Do not assume the staff made an error when records could reflect different stages of the process. Do not automatically rewrite stages, dates, ownership or loss reasons.

## Raw evidence limitations

The API exports all retained allowlisted rows; no last-40-activities UI limit applies. Unknown nested JSON keys are omitted and counted. Recognizable secrets and action links are redacted. Hard-deleted records and overwritten versions not kept by the application cannot be recovered. Snapshot differences establish observed change between two downloads, not the precise event time or its cause. Always disclose stale snapshots and coverage gaps relevant to the claim.
