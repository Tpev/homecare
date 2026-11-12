# Slack operations notifications

For ordinary family requests, LoLo Care posts these operational events to the configured private Slack Ops channel:

- a family publishes a new, open care request;
- a family hires a caregiver for a request.

Slack delivery is additive and disabled by default. It runs through the Laravel queue after the associated database transaction commits. A Slack timeout or rejection is retried and cannot roll back or interrupt the family request, hiring, booking, regular-care, or payment workflow.


## Managed requests and bookings

Managed care uses the **same configured Ops webhook**. There is no CS inbox and no second Slack channel configuration. Customer notifications stay suppressed by the existing managed-care policy; caregiver notifications keep their existing behavior.

The following meaningful events generate Slack notices for managed requests and their related bookings, including recurring and selected-day descendants:

| Area | Events |
| --- | --- |
| Request | Draft, publish, takeover, details/tasks/recipient edits, withdrawal, expiration, archive and restore |
| Recruitment | New or updated application, shortlist, rejection, withdrawal, hire, other applicants closed; invitation sent, accepted, declined, cancelled or expired |
| Conversation | Caregiver and staff messages; edited messages |
| Visit | Booking created, rescheduled, check-in, pause/resume, checkout, hours submitted/approved, cancellation, replacement release, no-show and dispute changes |
| Changes | Reschedule/cancellation proposals and decisions, time corrections and reported extra care, including review/payment/escalation outcomes |
| Recurring care | Plan and payment state, schedule/counterproposal, pause/resume/end, schedule-change/extra-visit proposals and decisions |
| Payment | Card authorization, capture, failure, refund and payout state; prepaid reservation/shortfall/release, settlement, holds and payment operation state |
| Follow-up | Incident, linked support ticket/reply, internal support note, staff follow-up note |

A recurring generation run groups up to 20 newly generated visits in each notice, with a date/time and authenticated link for every visit. The generated assignment records do not produce false “new applicant” notices. Actual payment changes remain separate notices.

Page views, read markers, timestamps, unchanged saves and repeated provider updates do not create notices. Existing records are not backfilled on deployment. Ordinary family self-service requests keep their existing new-request and hired-caregiver notices.

### Example

> **Caregiver message received**
>
> **Family:** Charlie Test · #42
>
> **Request:** Afternoon companionship · #380
>
> **Caregiver:** Summer Test · #57
>
> **By:** Summer Test · Caregiver #57
>
> **Booking:** #918 · Mon, Nov 16, 2026 · 10:00 AM EST – 12:00 PM EST (America/New_York)
>
> **Event time:** Nov 12, 2026 · 10:00:00 AM EST
>
> **Next action:** Read the message and reply to the caregiver.
>
> **Open conversation** · **Open request**

Names/IDs above are illustrative. Each notice identifies the family, original request title/ID, caregiver (or unassigned), actor or automatic system update, schedule with timezone, booking ID when applicable, event time and suggested next action. Status and schedule changes include before/after details. Payment notices may include amounts and prepaid minutes, but never card credentials or provider secrets.

Buttons open the existing authenticated CS workspace, including the particular conversation, applicant, booking, recurring care or internal notes. They do not hire, charge, cancel or send messages directly from Slack. Full message text, internal notes, incident explanations, clinical/access notes and street addresses remain inside LoLo. A request's title is included, so use an operational title.

This observes **messages recorded in LoLo**. It does not ingest independent emails sent directly to a staff member's mailbox.

### Delivery and recovery

The migration creates the internal managed_care_ops_events delivery ledger. Events are recorded with the business transaction and queued only after commit. Rolled-back changes produce no Slack notice. Each event keeps its original context even if the request changes before delivery.

The existing queue worker handles delivery. The Laravel scheduler also runs ops:managed-care:dispatch every minute to recover pending delivery after a queue broker interruption. Both the worker and scheduler must be running. Recurring batches allow a brief collection window before sending.

- A successful event is marked sent; duplicate jobs do not resend it.
- Explicit retryable HTTP rejection (429/5xx) retries up to four attempts, with 10/60/300-second delays.
- Permanent rejection is marked failed.
- A timeout/unknown acknowledgement, or worker interruption during delivery, is marked uncertain. Incoming webhooks have no application idempotency key, so these are **not automatically replayed**: check Ops before any deliberate replay to avoid a duplicate.
- Event recording failures and terminal/uncertain deliveries log a warning/error without private payloads, provider response bodies or webhook credentials.
- Disabling Slack stops recording new events and pauses pending delivery. Re-enabling delivers the existing pending backlog.

After deployment, inspect delivery counts **without sending anything**:

    php artisan ops:managed-care:dispatch --status

For failures, inspect the identified ledger row and application logs locally. Correct configuration or delivery issues first. For an uncertain result, verify the channel before an operator deliberately resets that specific event to pending; never bulk replay uncertain events. The ledger is operational storage, not a new staff inbox.

Local verification (all Slack HTTP calls are faked):

    php artisan test --compact tests/Feature/Notifications/ManagedCareOpsNotificationTest.php tests/Feature/Notifications/SlackOpsNotificationTest.php

## Ordinary family notices: information included

The messages contain only the operational summary needed to identify and follow up on the event:

- family account name and email;
- caregiver account name and email for a hire;
- requested date and time or recurring schedule;
- visit duration or weekly duration;
- city, state, and ZIP code;
- an authenticated link to the request in LoLo Admin.

The webhook deliberately excludes the street address, recipient identity, date of birth, care notes, task notes, health information, home-access instructions, phone numbers, payment information, and conversation contents. Staff must open the authenticated Admin link when more detail is legitimately required.

Use a private, least-privilege Slack channel limited to LoLo personnel who are authorized to handle family and caregiver account information. Slack must not become the system of record.

## Create the Slack webhook

1. In Slack, create a private channel such as `#lolo-ops-alerts` and add only the appropriate operators.
2. Go to <https://api.slack.com/apps> and create a private app named `LoLo Care Ops` in the LoLo workspace.
3. Open **Incoming Webhooks** and activate them.
4. Select **Add New Webhook to Workspace**, choose the private operations channel, and approve the installation.
5. Copy the generated `https://hooks.slack.com/services/...` URL into a password manager temporarily.

The webhook URL is a credential. Anyone possessing it can post to the selected channel. Never put it in Git, a Codex prompt, chat, a ticket, documentation, command-line history, or a public channel. Revoke and replace it immediately if it is exposed.

## Production configuration

Production uses the protected shared Laravel environment at `/var/www/homecare-deploy/shared/.env`. Edit it interactively so the secret does not enter shell history:

```bash
sudo nano /var/www/homecare-deploy/shared/.env
```

Add:

```dotenv
SLACK_OPS_NOTIFICATIONS_ENABLED=true
SLACK_OPS_WEBHOOK_URL=https://hooks.slack.com/services/REPLACE_INTERACTIVELY
SLACK_OPS_TIMEOUT_SECONDS=8
```

Do not paste the real URL into any command shown in terminal history. Preserve the existing owner and `640` permissions on the shared environment file.

Deploy the reviewed `master` revision using the existing zero-downtime deployment workflow:

```bash
cd /var/www/homecare
./deploy.sh
```

Do not reproduce deployment steps manually. `deploy.sh` builds and validates an inactive release, refreshes configuration, atomically activates it, and gracefully restarts queue workers without putting the live site into maintenance mode.

## Verification

After a successful deployment, send the privacy-safe connection test from the active release:

```bash
cd /var/www/homecare
php artisan ops:slack:test
```

Expected terminal output:

```text
Slack operations test notification delivered.
```

The selected channel should receive a generic connection message containing no family, caregiver, or care information. Then verify the next genuine request and hire notifications against the corresponding authenticated Admin records. Do not create a fake production family request just to test Slack.

Queue workers must be running for lifecycle notifications. Managed-care recovery also requires the existing Laravel scheduler. If the connection test succeeds but genuine notifications do not arrive, inspect the standard queue worker and failed-job status without printing environment variables or the webhook URL:

```bash
cd /var/www/homecare
php artisan queue:failed
sudo systemctl list-units --type=service --all | grep -Ei 'queue|horizon|worker'
```

Inspect the actual queue unit or Supervisor program used by production; do not assume a service name.

## Disable, rotate, or respond to an incident

To stop new notifications immediately, set the following in the protected shared `.env`, then run the normal `./deploy.sh` workflow so the change is applied through the reviewed deployment path:

```dotenv
SLACK_OPS_NOTIFICATIONS_ENABLED=false
```

For rotation, create a replacement Slack webhook for the same restricted channel, replace only `SLACK_OPS_WEBHOOK_URL` interactively in the protected shared environment, deploy normally, run `php artisan ops:slack:test`, and then revoke the previous webhook in the Slack app settings.

If a webhook or channel is exposed:

1. revoke the affected webhook in Slack immediately;
2. disable notifications in the shared production environment and apply that configuration through the normal deployment workflow;
3. remove unauthorized channel members and preserve the relevant Slack and application audit evidence;
4. assess which notifications were visible, remembering that they contain account identities, service area, and schedule data;
5. create a replacement restricted channel or webhook only after access is corrected;
6. rotate, redeploy, and verify using the safe test command.

The application does not log the webhook URL or Slack response body. Permanent delivery failures log only the event type and internal request/application identifiers.
