# Regular care: apply for selected days

Status: Draft for discussion, revised September 18, 2026. Product specification only; not implemented.

Working scope: caregivers select days, families hire for those days, and the original request stays open for the remainder. An availability-only alternative is described at the end. This scope is a recommendation, not a recorded user decision.

Read with [Shared regular care: lifecycle and edge cases](C:/Users/Epicpp/homecare/docs/product/recurring-care-lifecycle-and-edge-cases.md). That companion defines the ongoing care model, removal/replacement, payments, missing-care visibility, extra visits, and the 70-case release checklist. Its lifecycle rules take precedence over any abbreviated description here.

## 1. Product decision

A caregiver can apply for one or more of the weekly days in a regular-care request. Each selected day means the full published shift, repeated every week. The family can hire different caregivers for different days of the same request.

The family still posts one request. A persistent care schedule retains the family's needs; each caregiver has their own regular-care agreement and visits. The schedule gives the family an overview of assigned days and dates still needing someone. Closing a recruitment request does not end care or remove an unmet need.

Success: John can get Monday-Saturday, 8am-2pm covered even when no individual caregiver can commit to all six days.

## 2. First-version boundaries

Include:

- Selection of specific weekdays when applying to a regular-care request.
- Clear display of selected days on the application and family applicant card.
- Hiring for exactly the days in that application.
- Multiple hires on a request, with one caregiver per requested daily shift.
- A weekly assignment summary and continued recruitment for unassigned days.
- Editing selected days before hiring, with the family able to see the change.
- Ending a caregiver's assignment with a clear effective date and visit/payment preview.
- Dated missing-care visibility, temporary replacement and the existing accepted-extra-visit flow, as specified in the lifecycle companion.

Defer:

- Splitting one daily shift between caregivers or proposing different hours.
- Alternating weeks, one-off date availability inside recurring applications, and "any two of these four days."
- Automatic team suggestions, assignment optimisation, and a team chat.
- Automatic substitutes or automatic invitations when a caregiver leaves; explicit requests for dated cover are included.
- A new pricing model, bundled payments, or a maximum-team-size setting.

One-time requests keep their existing behaviour. Existing profile availability remains separate: this selection is the caregiver's offer for this particular request.

## 3. Example journey

John posts Monday-Saturday, 8am-2pm, repeating weekly from the requested start date.

1. Alice selects Monday, Wednesday and Friday. Her application says: "I can do Monday, Wednesday and Friday, 8am-2pm every week."
2. John sees Alice's selected days before choosing her. Applying and shortlisting do not reserve days.
3. John reviews "Hire Alice for Monday, Wednesday and Friday" and confirms. Her agreement and visits contain only those days.
4. The request stays open and says: "Alice is scheduled for Monday, Wednesday and Friday. You still need someone for Tuesday, Thursday and Saturday."
5. Ben applies for Tuesday, Thursday and Saturday. John hires him for those days.
6. The request stops accepting applications once all requested shifts have an assignment. John can still open each caregiver's regular-care arrangement.

| Day | Time | After Alice is hired | After Ben is hired |
| --- | --- | --- | --- |
| Monday | 8am-2pm | Alice | Alice |
| Tuesday | 8am-2pm | Still looking | Ben |
| Wednesday | 8am-2pm | Alice | Alice |
| Thursday | 8am-2pm | Still looking | Ben |
| Friday | 8am-2pm | Alice | Alice |
| Saturday | 8am-2pm | Still looking | Ben |

John may start an arrangement for some days while he continues recruiting. The confirmation must name the remaining days so he understands what he is agreeing to.

## 4. Caregiver experience

On the request preview, add: "You can apply for some or all available days." Keep the complete requested schedule visible, with days already assigned identified separately. Hours shown as available work must reflect the remaining open shifts.

Above the existing application note, show:

> **Which days can you do every week?**
>
> Choose the days when you can work the full hours shown. You can apply for some or all available days.

Use labelled checkboxes, one per requested weekday, showing that day's actual hours. Start with none selected; provide a "Select all available days" shortcut. Do not make the application depend on colour alone, and keep the selector usable with a keyboard and on a narrow mobile screen.

For example:

```text
[x] Monday       8:00am-2:00pm
[ ] Tuesday      8:00am-2:00pm
[x] Wednesday    8:00am-2:00pm
[ ] Thursday     8:00am-2:00pm
[x] Friday       8:00am-2:00pm
[ ] Saturday     8:00am-2:00pm

Your selection: Mon, Wed, Fri · 18 hours/week
These are the days you are offering to work every week.

[Send application]
```

Requirements:

- At least one currently unassigned requested day must be selected.
- Selection means all checked days together. "I am available Monday, Wednesday and Friday but only want two days" is outside this version.
- Show a summary of the selected days, hours, request date range, and first applicable visit date before submission.
- Use each day's published hours; the existing schedule can have different hours on different weekdays.
- Retain the optional note. Do not require caregivers to repeat their availability in prose.
- After submission, show the submitted selection and allow updates while the application is eligible and not hired.
- An update records a new application version and notifies the family that the offered days changed. It does not reset a shortlisted application to a new application.
- After hiring, direct schedule changes through the regular-care agreement; editing an application cannot change booked visits.

An invitation to apply should lead to the same day selector. A direct regular-care agreement offer keeps its explicit schedule and acceptance flow.

## 5. Family experience

Keep request creation as it is. Add a short review explanation: "Caregivers can apply for some or all days. You choose who to hire for each part of your schedule."

Each applicant card shows the offered days prominently:

> **Alice**
>
> Can do Mon, Wed, Fri · 8am-2pm
>
> 3 of your 6 requested days · 18 hours/week
>
> [Review and hire] [Message]

Applicants who offer all currently open days can have an "All remaining days" label. Keep partial applicants equally actionable.

The hiring review shows the caregiver, exact selected schedule, date range, first applicable visit, existing price information, and days that will remain unassigned. Use a specific confirmation action such as "Hire Alice for these 3 days."

For this version, the family accepts the application's whole selection. If John wants Alice for only Monday and Wednesday, he messages her to update the application. The system must not silently reduce or expand her offer. This also handles applicants whose selected days overlap with a later hire.

After confirmation, keep the request overview accessible with:

- A simple row for each requested day: hours, caregiver name or "Still looking."
- A clear sentence naming the days still needing someone.
- A link to manage each hired caregiver's regular care.
- Access to remaining applicants and invitations while shifts remain open.

Call this the **Weekly schedule**. Assignment describes the ongoing arrangement; actual visit confirmation and payment state remain on the individual visit. Do not describe every future visit as confirmed merely because a weekday has an assignment.

## 6. Rules and edge cases

| Situation | Required behaviour |
| --- | --- |
| No days selected | Block submission: "Choose at least one day you can do every week." |
| Day not requested or already assigned | Reject it on the server, even if an old screen permits selection. Refresh the available days. |
| Multiple people apply for Monday | Allow all applications. Only a successful hire assigns Monday. |
| John hires Alice for three days | Keep the request open and keep other pending applications; do not mark everyone else not selected. |
| Bob applied for Mon/Tue before Alice took Mon | Preserve Bob's original offer, mark the conflict, and require Bob to update it before hiring. Do not automatically turn it into a Tuesday-only offer. |
| An application changes while John is reviewing it | Require John to review the current version before confirming. |
| Request days, times or date range change before hiring | Invalidate affected offers for hiring and ask caregivers to reconfirm. Copy edits that do not affect the agreement need not invalidate day selections. |
| Two family members hire overlapping applicants simultaneously | Exactly one succeeds for the overlapping shift. The other sees the updated schedule. |
| All requested shifts have assignments | Close recruitment and mark remaining pending applicants not selected using the existing notification conventions. Preserve application history. |
| A requested start date has passed | Show the next eligible selected date under the existing scheduling rules. Do not generate past visits or change the original request's start date for other applicants. |
| One visit is skipped or cancelled | Keep the repeating assignment. If John no longer needs that visit, record a care-not-needed exception. If the person cannot attend but care is still needed, show the dated gap and offer replacement. |
| One agreement is paused | Distinguish caregiver absence from a family care pause. Caregiver absence preserves demand and permits dated temporary cover; care not needed suppresses the relevant requirements. Show any resume date and check replacements before restoring visits. |
| One agreement ends | Keep history and required care, show future gaps immediately, and offer replacement recruitment with the existing privacy setting. Retained final visits and already accepted extras must be reviewed explicitly; replacements cannot overlap them. |
| John stops looking for the remaining days | Close recruitment only, with clear copy. Existing agreements and visits continue under their normal controls. |
| Billing or activation needs attention | Show the actual agreement/visit state. Do not show a failed activation as a successful assignment, or payment-required visits as confirmed. |

After the first hire, the original recruitment posting remains a historical snapshot. Requirement changes belong to the persistent care schedule with effective dates, while agreement changes record each caregiver's commitment. Adding new recurring days can initially use a linked separate request, but it must appear in the same care schedule. A caregiver reducing their days leaves visible gaps unless John explicitly removes those care needs. See the lifecycle companion for proposal, pause and change rules.

Keep the request's timezone throughout. Summaries and weekly hours derive from the selected slots, not from the full request or the viewer's device timezone.

## 7. Implementation notes from the current project

These are proposed changes, not existing capabilities.

The current application stores a note and rate but no selected schedule. Recurring activation copies the full request schedule, marks the request filled, marks other pending applicants not selected, and creates one care plan for the source request. A database unique constraint enforces one plan per source request. The request is also reused as the first visit. Consequently, this feature needs changes beyond adding checkboxes.

Proposed model:

- Introduce a persistent family care-schedule relationship retaining required days, effective versions, dated needs and exceptions. Keep the request as its recruitment snapshot.
- Add selected weekdays and an immutable offered-schedule snapshot/version to each recurring application. The snapshot includes selected daily hours, request date range and timezone; it is what the family reviews.
- Use the existing per-caregiver `CarePlan` for each successful hire, with only that application's selected slots and an explicit source-application link.
- Allow a recruitment request to link to multiple plans. Replace the source-request uniqueness assumption with an appropriate idempotency constraint per accepted offer/application version. A later rehire requires a new version. Lock and revalidate the shared requirements and application at confirmation.
- Derive missing care from the persistent requirements and exceptions, and assignments from accepted agreements and their effective dates. Pending applications never count as assigned. Link replacement bookings to the original dated need so they cannot duplicate coverage.
- Treat an open request with some assignments as "Still looking" with a progress summary; a new database request status is not necessarily needed.
- Preserve visit-level scheduling and existing pricing/payment behaviour. Repeated confirmation must return the same assignment and visits.

Before implementation, resolve how recruitment requests and dated visit requests are separated. The current `attachSourceRequestAsFirstVisit()` lookup can find an existing booking by request alone; reusing it for a second caregiver risks attaching the wrong visit. Likewise, singular `care_plan_id` and `booking` readers cannot become the source of truth for a multi-caregiver request. Preserve existing links for old records while introducing the new relationship deliberately.

Primary code surfaces:

- `app/Models/CareRequestApplication.php` and application storage migration.
- `app/Livewire/Caregiver/ApplyToCareRequest.php` and its Blade view.
- `resources/views/livewire/family/partials/recruitment-applicants.blade.php` and request overview/header.
- `app/Livewire/Family/ManageCareRequest.php` and `app/Services/Marketplace/CareRequestHiringService.php`.
- `app/Services/RegularCare/CarePlanService.php` and `CarePlanOccurrenceService.php`.
- `app/Models/CareRequest.php`, request lifecycle/policies, and discovery visibility.
- `database/migrations/2026_07_16_090000_upgrade_regular_care_system.php` as evidence of the existing constraint; use a new migration for changes.
- Notifications, admin views, and AI-assisted hiring paths that currently assume one hire closes the whole request.

Legacy applications without selected-day data keep their original full-schedule meaning. Label them as whole-schedule applications, do not infer partial availability from notes, and require reconfirmation if their original schedule cannot be established or is stale. Existing plans and visits must remain intact.

## 8. Acceptance criteria

1. A caregiver can select Mon/Wed/Fri on a Mon-Sat request, submit, reopen, and see that same selection. John sees it on the applicant card and hiring review.
2. John hiring that application creates only Mon/Wed/Fri regular care and visits. The request remains discoverable for Tue/Thu/Sat, and other pending applications remain available.
3. Hiring a second caregiver for Tue/Thu/Sat completes the requested weekly assignments and closes recruitment. Each caregiver sees only their own work and agreement.
4. Empty selections, unrequested days, filled days, stale offers and overlapping concurrent hires are rejected with actionable messages.
5. Repeated confirmations create no duplicate plans, assignments or visits and never return another caregiver's plan.
6. A pre-hire application update appears to the family; it cannot silently change a confirmed agreement or bypass review already open in another session.
7. Different per-day hours and the first applicable selected date are preserved. Selection summaries and weekly hours use the selected schedule.
8. Every hiring path, including AI-assisted hiring, applies the same selected-day rules.
9. Existing one-time requests, legacy whole-schedule applications, and existing regular-care agreements retain their behaviour.
10. Payment-required, paused, ended and individually cancelled visits/agreements are represented accurately without accidentally recruiting overlapping permanent care.
11. Removal, replacement, extra visits and payment recovery satisfy the lifecycle companion's release scenarios. Checking off weekdays alone is not sufficient for release.

Product checks after release: track partial applications, applications per recurring request, time to first assignment, time to all days assigned, and which weekdays remain unassigned. Distinguish first hire from full weekly coverage in existing funnel reporting.

## 9. Smaller alternative and next ideas

If the immediate scope is only to collect availability, ship the selector, application summary and family card first. Label these days as **offered**, not assigned. Partial applications must not use today's full-schedule hire action; route them through a reviewed manual agreement process or leave hiring unavailable until that process exists. That release helps conversations but does not itself solve six-day coverage.

Small follow-ups after the working flow: a family filter for a still-open day, clearer reminders to update conflicting applications, and an "All remaining days" summary. Automated team assembly and shift splitting can wait until the simple flow is being used.
