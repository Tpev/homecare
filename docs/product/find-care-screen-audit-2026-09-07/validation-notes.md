# Lifecycle and fixture validation

Reviewed against the local synthetic database, the application source, and captured desktop/mobile evidence on September 7, 2026. Screens are independent state examples, not one real customer's chronological history. No production or external account data was used. This audit changed fixtures, capture tooling, and documentation; it did not change the application UI.

## What the current lifecycle actually shows

| Conceptual stage | Verified fixture state | Observed family UI |
| --- | --- | --- |
| Waiting for applications | Open request; no applicants or invitations | Waiting state and caregiver discovery/invitation controls. |
| Inviting | Open request; two pending invitations; no applications | Waiting for responses, with invitation details available. |
| Choosing a caregiver | Open request; applied and/or shortlisted candidates | Compare/review profiles, hire, chat, and invite additional people. The one-candidate example renders a focused “Ready to choose” card. |
| Hired, before the shift | Filled request; scheduled booking; authorized payment | Before-visit details, caregiver, time, location, payment, and visit-management actions. |
| During the shift | In-progress booking, recorded start/GPS, authorized payment | “Care is happening now,” caregiver check-in, live visit details, chat, end-visit and help actions. |
| After the shift | Completed booking; submitted timesheet; family has not confirmed | “Review hours,” submitted duration, estimated payment, approve/pay and question-hours actions. |
| Reviewing the timesheet | Completed booking; submitted timesheet; family has not confirmed | The same current UI state as “After the shift.” These are retained as two conceptual steps in the tracking inventory. |
| Leaving a caregiver review | Reviewed booking; family-confirmed timesheet; captured payment; no family review | Final visit record/receipt plus the star-rating and comment form. |
| Closed after completion | Reviewed booking; captured payment; two reviews | Final record/receipt and the family/caregiver feedback. No empty review form. The backend booking status is `reviewed`; “Closed” is the audit's conceptual stage. |
| Closed without a hire | Cancelled request; no booking | A distinct request-closure path. |
| Cancelled hired visit | Filled request; cancelled booking and payment | A distinct cancelled-visit path; it should not be interpreted as completed care. |

Evidence: [in-progress visit](evidence/OT-in_progress-shift-desktop.txt), [after shift](evidence/OT-after_shift-shift-desktop.txt), [timesheet review](evidence/OT-timesheet_review-shift-desktop.txt), [leave review](evidence/OT-leave_review-shift-desktop.txt), [closed record](evidence/OT-closed-shift-desktop.txt), and their `RC-` counterparts.

The normal caregiver completion method sets `completed_at` and `timesheet_submitted_at` together (`app/Livewire/Caregiver/ApplyToCareRequest.php`, `completeBooking`). Both after-shift fixtures use that submitted, family-unconfirmed state, and all eight corresponding tab screenshots were refreshed and verified. There is no separate “waiting for the caregiver to submit” stage in the normal completion flow.

## Confirmed application counts

Read-only database validation confirms the same counts for both one-time and recurring requests:

| Fixture | Applicant count | Invitation count | Visibility |
| --- | ---: | ---: | --- |
| `waiting` | 0 | 0 | Public |
| `invitations` | 0 | 2 | Public |
| `applicants` | 2 | 0 | Public |
| `single_applicant` | 1 | 0 | Public |
| `many_applicants` | 4 | 0 | Public |
| `private_waiting` | 0 | 0 | Private |

`single_applicant` contains one shortlisted candidate. `many_applicants` contains one shortlisted and three applied candidates. The four distinct caregivers are Sofia Martinez, James Wilson, Aisha Robinson, and Oliver Chen. The read-only checks are reproducible with [validate-fixtures.php](capture/validate-fixtures.php) under the isolated audit environment.

## Real UI observations to carry into the redesign

- **The saved care profile precedes the visit interface.** A rich “About Ellie” profile fills the initial desktop viewport and extends further on mobile before the request/visit header, stage actions, and tabs. This is visible in the screenshots and is not a capture-scroll error. The viewport, focused and full-page captures serve different purposes here.
- **A recurring occurrence is labeled “One-time care” in request detail.** This follows the actual application's generated-occurrence representation: `request_type=one_time`, `is_system_generated=true`, with a linked recurring plan. It is a labeling observation, not evidence that the audit accidentally captured an unrelated one-time request. The first marketplace recurring hire remains a recurring source request.
- **Pending recurring plans are presented as active.** Plan 2 is verified as `pending_caregiver` with `payment_status=unchecked` and no next booking. Its captured page says “Recurring care is active” and “Your regular schedule is active. The next visit is being prepared.” The template's status badge only distinguishes paused/ended from its default active label. This is a real missing status distinction in the current screen, not an incorrectly seeded active plan.
- The normal care-list presentation labels that pending plan “Waiting for Sofia” and links to the same plan-detail route, so the pending/active discrepancy is reachable from normal navigation (`app/Services/Family/FamilyCarePresentationService.php`).
- **Paused recurring care has contradictory copy.** Plan 3 is paused and its badge says “Care is paused,” but the no-next-visit message still says the regular schedule is active. Its management action correctly changes to “Resume recurring care.”
- **Ended recurring care is correctly distinguished.** Plan 4 renders “Recurring care ended” and removes the recurring-care management controls. Past-visit and care-story navigation remain available.
- **A pending time correction coexists with a “Confirmed” receipt.** The correction examples have no `family_confirmed_at` and correctly show “Waiting for family review” with the proposed hours/amount and approval action. The receipt below nevertheless says “Timesheet — Confirmed / Confirmed by family.” The template's active-correction branch suppresses the ordinary timesheet-review flag and then selects the final-receipt display. This is contradictory current copy; the approval was not submitted during capture. See [one-time correction evidence](evidence/OT-time_correction-shift-desktop.txt) and `resources/views/livewire/family/manage-care-request.blade.php` around lines 77, 284 and 1427.

Evidence: [active plan](evidence/RC-plan-active-desktop.txt), [pending plan](evidence/RC-plan-pending-desktop.txt), [paused plan](evidence/RC-plan-paused-desktop.txt), [ended plan](evidence/RC-plan-ended-desktop.txt). Status rendering is in `resources/views/livewire/family/regular-care-show.blade.php` around lines 18, 35 and 101.

## Fixture artifacts and capture caveats

- Synthetic visits are positioned relative to the seed time to expose every lifecycle state simultaneously. Some displayed visit times are early morning, and some example occurrence dates do not follow the sample Monday/Wednesday/Friday schedule. Do not treat those timestamps as user behavior or scheduling defects.
- The active plan deliberately connects several independently staged visits and points its next booking at the scheduled example. Consequently, a current/in-progress example can appear under “Later visits” before the explicit next-visit date. This chronological inconsistency is fixture construction; it is not sufficient evidence of a production ordering bug.
- Names, care details, GPS coordinates, reviews, messages, amounts, and payment statuses are synthetic. Authorized/captured payment rows illustrate UI branches and do not represent real processor operations. External payment flows were not exercised.
- Expanded recurring management panels, invitation drawers, dependent creation fields, filter results, and review drafts were captured only after their intended UI state appeared. Their final evidence includes the relevant fields and controls.
- This validation is about state meaning and visible content. It does not establish that hiring, payment capture, schedule changes, reviews, or cancellations were successfully submitted end to end.

## Capture validation results

All 24 targeted view IDs in [recapture-ids.json](recapture-ids.json) now have successful version-4 evidence on both devices. The eight after-shift tab views and 16 interactive views below passed, along with three default-screen disclosure-visibility checks: **54 device checks passed, none pending, none failed**. The read-only checker is [validate-captures.mjs](capture/validate-captures.mjs).

| IDs | Verified visible state on desktop and mobile |
| --- | --- |
| `OT-invite-drawer`, `RC-invite-drawer` | Invitation dialog and “Search by caregiver name” field. |
| `RC-plan-panel-extra`, `RC-plan-panel-schedule`, `RC-plan-panel-pause`, `RC-plan-panel-end` | Selected panel heading and its date/time/note fields or confirmation controls. |
| `RC-create-empty` | Recurring schedule heading and weekday controls; no stale one-visit fields. |
| `OT-create-details`, `RC-create-details` | Contact name/relationship/phone/email; recurring selected-day “Starts at,” “Visit length,” and “Last day” controls. |
| `OT-create-self-private`, `RC-create-self-private` | Self-care selection, private explanation, and “Create private request” button. |
| `OT-create-saved-profile` | Eleanor's saved details, public visibility explanation, and “Publish request” button. |
| `OT-invite-search-certifications`, `RC-invite-search-certifications` | “No caregivers match your search and CPR with LoLo verification.” for Sofia with required CPR and verified-only filter; loading complete. |
| `OT-review-form-draft`, `RC-review-form-draft` | “Selected rating: 5/5” and the draft comment; neither review was submitted. |

The public ready-to-publish forms, recurring saved-profile form, invitation message confirmations, reschedule drafts, time-correction approval previews, empty review forms, and mobile navigation menu showed their intended state. The many-applicant screenshots also explicitly say “4 caregivers replied” and show four distinct candidate cards.

The final inventory excludes descendants of closed native `<details>` from default visible controls/headings. This was verified for closed certification filters, optional creation details, and recurring-plan care instructions. Expanded-screen evidence documents those contents separately. The mobile extra-visit panel and desktop invitation drawer were also visually inspected after capture.
