# Facebook lead welcome email

New Facebook family leads get one invitation to create a free account and post a request. The welcome panel lives near the top of **Admin → Family acquisition**. The CRM and family calling console show the email outcome and account/request milestones separately from the sales stage.

## Controls and local review

The feature defaults to paused. Edit the subject, body, reply address, and automatic sending switch in the welcome panel. Saving applies to future leads only. Preview and test use the same Blade email as the queued send. Test emails go to the signed-in administrator.

In `APP_ENV=local`, this feature always uses the log mailer. Captures have status `previewed`; they never count as sent. Other log/array mail configurations are also treated as captures. Preview links are inert; a test email links to ordinary registration without attributing a sample signup to a real lead.

## Flow

1. `FamilyLeadObserver`, after commit, calls `LeadWelcomeService::capture` for Facebook family leads from Meta, Zapier, or Sheets. Manual leads and demo imports are excluded.
2. `lead_welcome_emails` records the recipient, a snapshot of the subject/body/reply address, status and reason. A unique normalized-email hash reserves the welcome once across duplicate contact imports, including simultaneous imports. The record and opt-out survive lead deletion, so re-importing a deleted contact cannot send another welcome. Skipped leads still show why. Enabling later does not backfill old leads.
3. `SendLeadWelcomeEmail` runs on the application's default queue. An atomic database claim allows only one worker to proceed. It rechecks pause, opt-out, account existence and closed stages before sending. Preparation failures before any provider call get two retries, after 60 and 300 seconds. Those failures can also be retried by an administrator. Once the provider call begins, no automatic or dashboard retry is permitted, even if the job is replayed or its status is reset. Failover and round-robin transports are rejected because they may resend internally.
4. Signed links expire after 14 days. They save lead context in the session and prefill registration. They never authenticate a user. Existing users sign in normally; a different account cannot claim the lead. Matching family registration links the account even if it happens outside the email link.
5. The request wizard continues after registration, reusing available ZIP and care-need information. Publishing records the first open, non-system request for the linked account, including draft-to-open transitions. Account creation or publication does not change call stage or mark care started.
6. Unsubscribe requires an explicit POST from a signed preferences page, so link scanners cannot unsubscribe somebody by fetching a URL. It suppresses future welcomes for that email, including duplicate leads. Existing do-not-contact records also suppress sending.

## Operations

Run the migration before serving the new code. A queue worker must process the default queue. `sent` means the mail transport accepted the message; delivery/open/click tracking is not implemented. The dashboard measures observed account/request milestones, not causal attribution to the email.

A process interrupted after entering `sending` has an ambiguous outcome. It is marked “Delivery unconfirmed” after five minutes, and is not automatically resent. A provider exception immediately records the same outcome and appears under emails needing attention. Check provider logs before operator recovery. We prefer a possibly missed welcome to a duplicate: without provider idempotency, exactly-once delivery cannot be guaranteed. The application makes at most one provider send attempt per reserved email address.

Local review uses a separate SQLite database at `tmp/welcome-email-preview.sqlite` and a server on `127.0.0.1:8014`. Sample leads are labeled `(demo)`; their previews, registrations and request milestones are exercised locally. The additive migration is also applied to the existing local MySQL database, with sending paused by default. Existing lead data and production mail settings are not changed.

## Verification

`php artisan test --filter=LeadWelcomeEmailTest`

Covers intake/duplicates, overlapping workers, webhook replay, deletion and re-import, stale failed jobs, provider timeouts, attempted-message retry suppression, safe preparation retries, no backfill, exclusions, send-time pause/opt-out/account checks, local capture, signed links, registration and login continuation, mismatched accounts, request publication, unsubscribe, settings access, and CRM filters. Also run the family acquisition, webhook and authentication regression tests.
