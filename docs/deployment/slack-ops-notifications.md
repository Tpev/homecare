# Slack operations notifications

LoLo Care can post two operational events to a private Slack channel:

- a family publishes a new, open care request;
- a family hires a caregiver for a request.

Slack delivery is additive and disabled by default. It runs through the Laravel queue after the associated database transaction commits. A Slack timeout or rejection is retried and cannot roll back or interrupt the family request, hiring, booking, regular-care, or payment workflow.

## Information included

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

Queue workers must be running for lifecycle notifications. If the connection test succeeds but genuine notifications do not arrive, inspect the standard queue worker and failed-job status without printing environment variables or the webhook URL:

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
