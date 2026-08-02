# LoLo Care notification inventory

This inventory describes the production notification paths after the 2026 notification review. `Email + in-app` means both channels are enabled by default and can be changed in the recipient's notification center. SMS and push are not offered.

## Marketplace, care, visit, and payment events

| Event | Recipient | Trigger | Delivery | Useful context included |
|---|---|---|---|---|
| `family_welcome` | Family | Family account registration | Email + in-app | First steps and a direct path to create care |
| `caregiver_welcome` | Caregiver | Caregiver account registration | Email + in-app | Remaining setup checklist and profile link |
| `caregiver_onboarding_reminder_24h` | Incomplete caregiver | Profile is still incomplete after 24 hours | Email + in-app | Remaining checklist and setup link |
| `invitation_sent` | Family | Family invites a specific caregiver | Email + in-app | Caregiver, request, schedule, care tasks, budget |
| `invitation_received` | Caregiver | Family invites that caregiver directly | Email + in-app | Care recipient first name, schedule, duration, approximate location, tasks, budget, response deadline |
| `matching_request_reminder` | Matched caregiver | An open request matches caregiver service area/profile | Email + in-app | Request, schedule, duration, approximate location, tasks, budget |
| `invite_accepted` | Family | Invited caregiver accepts | Email + in-app | Caregiver and request context |
| `invite_declined` | Family | Invited caregiver declines | Email + in-app | Caregiver and request context |
| `new_applicant` | Family | Caregiver applies to a request | Email + in-app | Caregiver, proposed rate, request, schedule, tasks |
| `application_submitted` | Caregiver | Application is submitted | Email + in-app | Request, schedule, location, tasks, budget |
| `care_request_withdrawn` | Affected caregiver | Family withdraws an open request | Email + in-app | Request and schedule context |
| `caregiver_hired` | Caregiver | Family selects caregiver | Email + in-app | Confirmed care/visit or regular-plan context |
| `hire_confirmed` | Family | Caregiver selection completes | Email + in-app | Caregiver and confirmed care context |
| `message_received` | Other conversation participant | New marketplace message | Email + in-app | Sender and care-request conversation |
| `booking_change_requested` | Other visit participant | Family or caregiver proposes cancellation/reschedule | Email + in-app | Current visit, proposed change/time, reason |
| `booking_change_accepted` | Requester | Other participant accepts the change | Email + in-app | Updated visit details |
| `booking_change_declined` | Requester | Other participant declines the change | Email + in-app | Current visit and requested change |
| `shift_reminder_24h` | Family and caregiver | Scheduled reminder about one day before a visit | Email + in-app | People, exact visit time, care location, tasks |
| `shift_starting_soon` | Family and caregiver | Scheduled reminder about one hour before a visit | Email + in-app | People, exact visit time, care location, tasks |
| `shift_started` | Family | Caregiver checks in | Email + in-app | Caregiver and visit details |
| `shift_completed` | Family or caregiver | Visit is completed/submitted | Email + in-app | Scheduled time, recorded care time, people, location |
| `shift_cancelled` | Other visit participant | Visit is cancelled | Email + in-app | Cancelled visit, time, people, location |
| `timesheet_auto_approved` | Family and caregiver | Eligible submitted hours pass the review window | Email + in-app | Recorded time and visit context |
| `review_received` | Reviewed family/caregiver | Other participant submits a review | Email + in-app | Correct review semantics and visit context |
| `payment_authorized` | Family | Card authorization succeeds | Email + in-app | Authorized amount and visit |
| `payment_authorization_failed` | Family | Card authorization cannot complete | Email + in-app | Visit and a direct billing action |
| `payment_action_required` | Family | Overage or approved adjustment needs payment action | Email + in-app | Amount due, visit, and billing action |
| `payment_captured` | Family | Completed visit payment is captured | Email + in-app | Net amount billed and visit |
| `payment_refunded` | Family and affected caregiver | A refund or payout reversal is applied | Email + in-app | Amount returned/adjusted and visit |
| `payout_transferred` | Caregiver | Earnings transfer succeeds | Email + in-app | Payout amount, visit, earnings link |
| `payout_transfer_failed` | Caregiver only | Family payment succeeds but Stripe transfer fails | Email + in-app | Pending payout, visit, automatic-retry explanation, earnings link |

Pre-hire opportunity emails deliberately use only approximate city/state/ZIP. Exact care addresses are included only for confirmed family/caregiver visit participants.

## Regular care and collaborative time events

| Event | Recipient | Trigger | Delivery | Useful context included |
|---|---|---|---|---|
| `regular_care_offered` | Caregiver | Family offers a regular-care schedule | Email + in-app | Family, plan, days/times/timezone, rate |
| `regular_care_accepted` | Other plan participant | Caregiver accepts or family accepts counteroffer | Email + in-app | Plan, people, schedule, rate |
| `regular_care_countered` | Family | Caregiver proposes a different schedule | Email + in-app | Plan and proposed schedule context |
| `regular_care_declined` | Family | Caregiver declines offer | Email + in-app | Plan and caregiver |
| `regular_care_schedule_change_requested` | Caregiver | Family requests a future schedule update | Email + in-app | Existing plan, proposed schedule/effective date, note |
| `regular_care_extra_visit_requested` | Caregiver | Family requests an additional planned visit | Email + in-app | Plan, proposed date/time, note |
| `regular_care_schedule_change_accepted` | Family | Caregiver accepts requested update | Email + in-app | Plan and accepted update |
| `regular_care_schedule_change_declined` | Family | Caregiver declines requested update | Email + in-app | Plan and unchanged schedule |
| `regular_care_visit_skipped` | Caregiver | Family skips one occurrence | Email + in-app | Skipped visit and continuing plan |
| `regular_care_paused` | Family and caregiver | Operations pauses a plan | Email + in-app | Plan, people, schedule |
| `regular_care_resumed` | Family and caregiver | Operations or scheduled resume reactivates a plan | Email + in-app | Plan and resumed schedule |
| `regular_care_ended` | Affected family/caregiver | Family or operations ends a plan | Email + in-app | Plan and whether a confirmed next visit remains |
| `regular_care_payment_attention` | Family | Regular-care authorization needs attention | Email + in-app | Plan/visit and billing action |
| `time_correction_requested` | Family | Caregiver reports corrected visit hours | Email + in-app | Corrected start/end/break, total time, reason, estimated charge |
| `time_correction_changes_requested` | Caregiver | Family asks caregiver to revise a correction | Email + in-app | Proposed hours and family response context |
| `time_correction_resubmitted` | Family | Caregiver submits a revised correction | Email + in-app | Updated hours and financial preview |
| `time_correction_approved` | Caregiver | Family approves corrected hours | Email + in-app | Approved duration and estimated earnings |
| `time_correction_payment_action_required` | Family | Approved correction cannot finalize without billing action | Email + in-app | Corrected time, estimated charge, billing action |
| `time_correction_applied` | Family and caregiver | Corrected hours and payment records are finalized | Email + in-app | Final time and financial context |
| `time_correction_escalated` | Family and caregiver | Either participant requests LoLo Care review, or safe automation requires it | Email + in-app | Preserved correction and review state; support ticket alerts operations |
| `time_correction_withdrawn` | Family | Caregiver withdraws correction | Email + in-app | Visit and withdrawn hours |
| `completed_extra_visit_submitted` | Family | Existing regular caregiver reports an unscheduled completed visit | Email + in-app | Date, start/end/break, care time, care notes, estimated charge |
| `completed_extra_visit_changes_requested` | Caregiver | Family asks for changes to that report | Email + in-app | Reported visit and family response context |
| `completed_extra_visit_resubmitted` | Family | Caregiver revises the report | Email + in-app | Updated time, notes, estimated charge |
| `completed_extra_visit_approved` | Caregiver | Family approves report | Email + in-app | Approved care time and estimated earnings |
| `completed_extra_visit_disputed` | Caregiver | Family says visit did not happen | Email + in-app | Preserved visit report and LoLo Care review state |
| `completed_extra_visit_withdrawn` | Family | Caregiver withdraws report | Email + in-app | Visit and no-payment status |
| `completed_extra_visit_applied` | Family and caregiver | Approved extra visit and payment records finalize | Email + in-app | Final time and financial outcome |
| `completed_extra_visit_payment_action_required` | Family | Approved report needs billing action | Email + in-app | Visit, estimated charge, billing action |
| `completed_extra_visit_escalated` | Family and caregiver | Either participant requests LoLo Care review | Email + in-app | Preserved report and review state; support ticket alerts operations |

## Support, account, manual, and operations email paths

| Notification | Recipient | Trigger | Delivery | Notes |
|---|---|---|---|---|
| `support_ticket_created` | Shared operations list; each admin in-app | A user creates a support ticket, including escalation-created tickets | Branded operations email + admin in-app | Category, priority, opener, subject, initial description, queue link; in-app delivery does not duplicate shared email |
| `support_ticket_reply` | Ticket opener or assigned admin | Other side posts a public reply | Email + in-app | Ticket number, subject, category, priority, conversation link |
| Email verification | Account owner | Verification is requested | Email | Branded security email, expiry, secure link and fallback URL |
| Password reset | Account owner | Password reset is requested | Email | Branded security email, expiry, secure link, ignore-if-unrequested guidance |
| Caregiver community email | Caregiver/test recipient | Explicit `lolo:send-caregiver-launch-email` command | Email | Evergreen community guidance; historical dedupe key is retained for safe retries |
| New family registration | Shared operations list | Family registers | Operations email | User ID, name, email, phone, account link |
| New caregiver/user registration | Shared operations list | Non-family user registers | Operations email | User ID, role, contact details, account link |
| Caregiver ready for review | Shared operations list | Caregiver finishes required onboarding | Operations email | Caregiver, profile status, review link |
| New care request | Shared operations list | Care request is committed | Operations email | Request ID/type/status/location and admin link |
| Callback request | Shared operations list | Family/voice flow requests a callback | Operations email | Contact details, best time, need, source, CRM link |
| Voice call report | Shared operations list | Voice call report is accepted | Operations email | Caller, outcome, care need, follow-up status, summary/transcript |

## Shared presentation and safety rules

- Customer-facing email uses the LoLo Care warm logo, cream background, evergreen headings/footer, coral action, responsive table layout, plain-text alternative, support link, raw fallback URL, and role-specific notification-preference link.
- Event labels, action labels, tone, role visibility, and next-step guidance are centralized in `MarketplaceNotificationPresentation` and covered for every event key.
- Email and in-app delivery retain per-user deduplication and delivery logging. New support-ticket observers run after database commit.
- Notification centers show only implemented channels. Legacy SMS/push preference values are forced off until real providers exist.
- Subjects and sender names use `LoLo Care`; Stripe descriptions for future charges also use `LoLo Care`.
