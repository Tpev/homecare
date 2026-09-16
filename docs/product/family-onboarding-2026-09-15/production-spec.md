# Family onboarding: production integration spec

Status: approved and implemented locally, September 16, 2026. See [implementation and rollout notes](IMPLEMENTATION.md). Production activation remains off by default.

## 1. Product contract

New families creating their own account complete the approved onboarding wizard before creating their first care request. Existing families and people registering through a family-account invitation retain their current experience.

“Once” means one completed onboarding per family account, across logins, devices, browsers, and account members. Opening the screen does not count as completing it. An unfinished attempt resumes with saved answers.

Keep the approved design: centered card, no left progress column, concise guidance, optional care notes, optional free one-hour welcome visit, and Morning / Noon / Afternoon preferences. No new introductory or marketing screens.

## 2. Findings in the current application

- Normal signup is implemented in `resources/views/livewire/pages/auth/register.blade.php`. It currently redirects either to the requests list or to request creation for a homepage quick draft / lead welcome email.
- Invitation signup has its own component, `app/Livewire/Auth/FamilyInvitationRegister.php`. It creates a temporary owner account with activity `invitation_login_created`, then redirects to invitation review. Acceptance subsequently closes that empty account and joins the shared account.
- `FamilyAccountContext::membershipFor()` can create accounts for existing users through `account_created_compatibility`. Account creation alone therefore cannot indicate new-signup eligibility.
- Both registration paths dispatch the same `Registered` event. A generic listener that enrolls every family user would include invitees.
- Signup already requires a phone number and sends a family-registration admin email through `OpsAlertService`. That mail currently sends synchronously and reports exceptions; it is not a durable delivery queue for this new form.
- `FamilyHouseholdProfile` already stores care addresses. `CareRecipientProfileService` supports saved drafts and explicit sharing/versioning. Request creation also reads a legacy recipient profile, so compatibility needs an explicit mapping.
- Request creation currently detects saved profiles, but generally requires the family to choose “Use saved information.” Its homepage quick draft is consumed from the session. Neither behavior alone provides the required automatic, resumable onboarding handoff.

## 3. Eligibility and rollout safety

Create an onboarding enrollment record explicitly inside the successful normal family-registration transaction, together with the user and family account. The registration entry point determines eligibility on the server; do not trust a client-supplied source parameter.

| Situation | Enrollment | Behavior |
| --- | --- | --- |
| New normal family signup while enrollment is enabled | Create one account enrollment | Open onboarding |
| New family signup with a homepage draft or lead welcome context | Create enrollment and preserve context | Open prefilled onboarding, then request creation |
| Existing family at rollout | No enrollment | Existing experience |
| Existing family whose account is lazily provisioned later | No enrollment | Existing experience |
| Invitation registration / acceptance / declined or expired invitation | No enrollment | Existing invitation experience |
| Family member added to an enrolled account | No separate enrollment | Shared-account experience |
| Caregiver or administrator | None | Existing experience |
| Enrolled owner returning before completion | Reuse enrollment | Resume saved step |
| Completed account | Never enroll again | Normal app; no replay |

Absence of an onboarding row means not enrolled, not incomplete. Do not infer eligibility from missing profiles, zero requests, family role alone, account creation date, or current ownership alone. Do not backfill existing accounts into pending onboarding.

A database uniqueness constraint on `family_account_id` enforces one enrollment. Keep the originating user and signup source on that record. Invitations do not trigger enrollment even while their temporary account has owner status.

Ownership transfers retain account completion. An invited member becoming the owner must not suddenly be prompted. Suspend any unfinished original-owner enrollment on transfer; support can resolve the incomplete setup explicitly without automatically restarting it for the new owner.

## 4. Routing and saving progress

- Add an authenticated `/family/onboarding` page implemented in the existing Laravel/Livewire stack.
- Authorize the enrolled originating user as the current active owner on every read and write. Derive the family account from the authenticated membership, not a submitted account ID.
- Route eligible new registrations to onboarding after the registration transaction commits. Preserve registration events, attribution, welcome-email linking, and conversion tracking.
- Apply the same eligibility decision to returning-owner login, email-verification redirects, the family landing page, and care-request creation. A direct request-creation URL cannot bypass an enrolled owner's unfinished onboarding.
- Keep logout, support, account/profile access, and invitation review/acceptance available. Invitation routing takes priority. Invited account members are never redirected by an owner's pending enrollment.
- Existing and non-enrolled users continue through their current routing. Visiting the onboarding URL directly does not enroll anyone; completed users are redirected to the normal family area.
- Save each validated step to the database on Continue. Save available edits on Back without requiring the current step to be complete. After reload/session expiration, resume the first incomplete step. Show a save error and retain current values if persistence fails.
- Use a revision number so concurrent tabs cannot silently overwrite newer answers. Only the currently submitted fields are updated; changing Me/family or Yes/No clears irrelevant submitted values.

Recommended completion boundary: the final **Create my first request** button. Revalidate the complete submission, commit it once, then redirect to request creation. Completion is independent of whether the family eventually publishes a care request.

## 5. Data model and reuse

### Onboarding record

Store the account and originating user, schema version, source, enrollment/start/completion timestamps, last completed step, revision, saved draft, completion actor, linked recipient profile, and an immutable submitted snapshot. Two normal states are sufficient: `in_progress` and `completed`; allow a documented exemption reason for exceptional administrative resolution.

The snapshot records what was submitted and supports the admin email even if the family edits their profile later. It is an audit copy, not a second editable care profile. Keep retention aligned with existing account-data handling.

### Mapping

| Wizard value | Canonical destination |
| --- | --- |
| Me / family member | Recipient profile `recipient_is_requester` |
| Recipient full name | Existing recipient profile; for Me, use the initiating family user's name |
| Relationship | Recipient profile `relationship_to_family` |
| Address | Account-scoped `FamilyHouseholdProfile` |
| What should a caregiver know? | Recipient profile `about_them` |
| What helps care go well? | Recipient profile `good_visit_notes` |
| Welcome visit Yes/No | Submitted onboarding choice; separate visit request when Yes |
| Preferred date, range, timezone | Welcome-visit preference |

Reuse `CareRecipientProfileService::saveDraft` and its revision handling. Onboarding must not bypass the existing requirements for making a profile ready to share. Optional blank notes cannot become a new mandatory obstacle. Request review must use existing caregiver-sharing controls; onboarding completion is not permission to expose every saved field to every caregiver.

Automatically prefill the first request with recipient, address, and both care-note answers. Account for the current legacy request fields explicitly rather than expecting the draft recipient profile to load automatically. Preserve the two source note fields separately even if request review presents them together.

Merge precedence for onboarding handoff: edits made in the current request form take priority; onboarding-confirmed recipient/address answers override older homepage values; preserve homepage task and schedule choices. Lead context fills remaining blanks only. A welcome visit's date/range must never populate the paid care request's schedule.

Persist the pending homepage/lead handoff with the enrolled account so it survives a new browser or expired session. Consume it only after a durable request draft/handoff has been established, not on an incidental page read. Scope automatic onboarding prefill to these newly enrolled accounts so existing-family defaults remain unchanged.

## 6. Welcome visit and text confirmation

- Store the preference as `morning`, `noon`, or `afternoon`, with the displayed range and timezone captured at submission. Current ranges: 09:00–12:00, 12:00–14:00, and 14:00–17:00.
- Use the care location's supported timezone; current operation is America/New_York. Validate on the server that the selected window starts at least 24 real hours ahead, including daylight-saving changes. Repeat validation at final submission after a long pause.
- No means no active visit request and no retained date/range in the final submission.
- Yes creates a welcome-visit request in `requested` / awaiting confirmation status. It is a preference, not a booked appointment, reserved slot, marketplace care request, paid booking, or charge.
- Show: “Our team will confirm the final time by text.” Show the signup phone number with a small edit option; validate and normalize the number before accepting a text-confirmed visit request.
- Give staff a durable admin item linked to the family, with requested date/range, contact number, and basic follow-up status. Email is an alert, not the only record of work that needs doing.
- Staff can confirm an exact start time or record cancellation/unavailability using the existing support/SMS workflow. Record who handled it and when. V1 does not need automated SMS, a capacity calendar, or automatic appointment allocation.

## 7. Submission and admin email

On final submission, lock the account/enrollment and perform one transaction that saves canonical profile/address details, the submission snapshot, the optional visit request, completion state, and an admin-notification delivery intent. If any database write fails, completion fails and no email is sent.

Use an outbox: a database record of the email that still needs sending. A worker sends it after commit. A scheduled recovery pass finds unsent records if the process dies before dispatching the job. Email failure does not send the family back through onboarding. Laravel supports dispatching queued work after a transaction commits: [Laravel 12 queue documentation](https://laravel.com/docs/12.x/queues#jobs-and-database-transactions). Transaction rollback behavior is documented in [Laravel's database guide](https://laravel.com/docs/12.x/database#database-transactions).

Reuse existing ops recipient configuration, replacement rules, and email styling. Send a separate **Family onboarding completed** email; retain the existing **New family registration** alert because registration and form completion are different events.

The completed-form email contains:

- Account ID, submitting user's name/email/phone, signup source, submission time.
- Care for Me/family, recipient name, relationship.
- Complete address, including optional apartment.
- Both care notes, with “Not provided” for unanswered optional fields.
- Welcome visit accepted/declined.
- If accepted: preferred date, named range, exact range boundaries, timezone, one-hour duration, free price, and **Awaiting confirmation by text** status.
- Link to an authenticated admin page containing the saved submission and visit follow-up.

Use a unique delivery key per onboarding submission and recipient. Double clicks, stale tabs, browser refresh, and repeated jobs must not create another submission, recipient profile, visit request, or normal duplicate alert. Track pending, accepted-by-provider, failed, and unconfirmed delivery separately; provider acceptance does not prove inbox delivery. If a provider timeout leaves acceptance uncertain, use provider idempotency/reconciliation when available and surface the ambiguity rather than blindly duplicating messages.

Store retry metadata and make exhausted failures visible to admins. Missing recipient configuration must be a visible operational error, not a silent success. Escape all user text in email/admin views. Keep full form content out of analytics, exception logs, URLs, and unrelated notification payloads.

## 8. Production acceptance checks

1. An existing family with no profile or requests is never enrolled, including after lazy account provisioning.
2. Standard new signup creates one enrollment and redirects correctly. Quick-request/lead signup retains prior answers and attribution.
3. Invitation registration, acceptance, expiry, rejection, and subsequent login never show onboarding or create onboarding mail/visit work.
4. New owners resume after refresh, logout, and login on another device. Members cannot read or mutate another account's enrollment.
5. Completion persists across sessions; direct URLs and old submissions cannot reopen the wizard or send another email.
6. Server validation covers conditional name/relationship, address, optional notes, Yes/No, range enum, phone, timezone, the exact 24-hour boundary, and a range becoming too soon while the page is open.
7. Rapid double-submit, parallel tabs, transaction rollback, queue outage, missing recipients, provider rejection, and uncertain provider delivery all have tested outcomes.
8. Request creation is prefilled without overwriting newer edits or losing draft tasks/schedules. The free visit remains separate from paid care.
9. Existing profile sharing/versioning, family access, invitation flows, and existing-family request creation remain unchanged.
10. Keyboard navigation, validation focus, loading/error states, mobile layout, and back navigation work in the integrated Livewire page.

## 9. Release plan and observability

Deploy additive schema and code with new enrollment disabled. Do not modify or bulk-complete old accounts. Verify on staging using new normal registrations and invitation registrations, then enable enrollment for new signups only.

Use separate controls for enrolling new accounts and enforcing pending onboarding. A rollback can stop new enrollments and lift redirects while retaining saved drafts, completed records, and pending admin deliveries. Re-enabling must not reset completed accounts or enroll accounts created while enrollment was disabled.

Verify the production queue worker, scheduled recovery process, recipient configuration, and email rendering before enabling. Monitor enrollment/completion counts, drop-off by step, validation/save failures, admin delivery failures, and unhandled welcome-visit requests. Analytics contain IDs, event names, steps, and timestamps, not care-note content.

## 10. Recommended small additions; defer the rest

- Include the signup phone beside the text-confirmation message so the family can correct it.
- Show onboarding status and pending welcome visits in the existing admin family view; add a filter for visits needing contact.
- Prefill known information and avoid making families enter the same facts twice.
- Keep a save-and-resume experience. Completion resets should be an exceptional audited support action, never an automatic consequence of a new wizard version.
- Defer abandoned-onboarding email/SMS campaigns, a separate scheduling platform, an onboarding builder, and automatic booking. They are not required for this release.

## 11. Accepted product decisions

1. One completed onboarding per **family account**, with resume until completion.
2. Require completion before the enrolled original owner creates a request; keep support/account/invitation flows accessible.
3. Complete and notify admins when **Create my first request** is submitted, regardless of whether a request is later published.
4. Keep the current registration email and add a separate completed-onboarding email with all submitted information.
5. Manual staff confirmation by text for the free visit, with a durable admin follow-up record.

These decisions are implemented behind the new-enrollment and enforcement controls described above.
