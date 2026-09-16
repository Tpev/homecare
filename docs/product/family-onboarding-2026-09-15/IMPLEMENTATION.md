# Family onboarding implementation

## What is implemented

- Real Livewire wizard at `/family/onboarding`, using the approved centered design.
- Explicit enrollment in regular family registration only. No backfill, invitation enrollment, or inference from missing profiles.
- One database record per family account. Each step saves on Continue / Back; interrupted attempts resume. Revision checks prevent older tabs from replacing newer answers.
- Owner and originating-user authorization on every save. Members and former owners cannot read or modify the enrollment. Ownership transfer exempts unfinished onboarding.
- Signup, login, verification, dashboard and request-creation guards. Account, support and invitation access remain available.
- Final submission atomically saves recipient and address profiles, the submitted snapshot, a welcome-visit request if selected, completion state, and admin email delivery records.
- Both optional answers fill the simple care profile shown in request creation, without copying them into generic care notes. Either answer is enough; blank answers leave the profile optional. Posting with sharing acknowledged makes the same draft ready and attaches its version, preserving other profile details. Revision checks protect edits made elsewhere. Onboarding itself does not mark a profile ready to share.
- First request prefill preserves homepage tasks/schedule and lead context. New-family request edits are saved as Livewire updates arrive; the saved handoff is consumed when the first request is published.
- Morning / Noon / Afternoon with server-side validation at least 24 elapsed hours before the window starts, using `America/New_York` by default. UTC instants preserve daylight-saving behavior independently of the app timezone.
- Phone correction and normalization for text confirmation. Welcome visits remain free one-hour requests, separate from paid care bookings.
- Admin queue at `/admin/family-onboarding`, linked from Acquire families and the existing family-user page. Filters include incomplete forms, visits needing contact, and email problems.
- Staff can record contact, agreed time, confirmation by text, completion, cancellation or unavailability. Changes record actor, timestamp and an account activity event. Recording confirmation does not itself send an SMS; use the existing SMS inbox.

## Admin email delivery

The immutable submitted snapshot supplies every form answer and account contact detail. Emails use the existing operations recipient configuration, replacement rules and LoLo template. The existing registration alert remains separate.

Delivery intents commit with the form; jobs contain only their delivery ID. A scheduled recovery command dispatches pending work if the initial queue dispatch fails. Database claiming and per-submission/per-recipient uniqueness prevent normal duplicate sends. A ten-minute dispatch lease limits redundant queue jobs during an outage.

| Status | Meaning / action |
| --- | --- |
| pending | Waiting for a worker |
| sending | Claimed by a worker |
| accepted | Mail provider accepted the message; inbox delivery is not guaranteed |
| failed | Preparation failure or explicit SMTP rejection; automatic retry with backoff, at most five attempts |
| blocked | Missing recipients or a non-delivering log/array mailer; fix configuration and recovery resumes it |
| unconfirmed | Timeout, interrupted worker, or another uncertain transport outcome; check the provider before staff records acceptance or retries |
| superseded | Replaced a missing-recipient placeholder after configuration was repaired |

Mail content is never written to application logs by the onboarding sender. Capture mailers are blocked instead of logging care notes or claiming a delivery. Staff reconciliation requires a recorded reason. No automated resend of uncertain messages is attempted.

## Deployment and activation

Production enrollment is **off by default**. Existing accounts receive no new rows. Code and local testing do not activate production.

1. Deploy the two additive migrations before serving the new code (`php artisan migrate --force`). The second migration expands full-name capacity to match signup's 255-character limit. It preserves that capacity on rollback to avoid truncating names; the preferred display name remains bounded at 80 characters.
2. Verify `MARKETPLACE_OPS_ALERT_RECIPIENTS` and the actual production mail transport.
3. Confirm the queue worker consumes `FAMILY_ONBOARDING_QUEUE_CONNECTION` (default `database`) on the default queue. The job timeout is 60 seconds; queue retry-after must exceed that. Restart workers after deploying job changes.
4. Keep the Laravel scheduler running. `family-onboarding:dispatch-emails` is scheduled every minute with overlap protection. It can also be run manually.
5. On staging, complete normal and invited registrations, verify an existing account, inspect the submitted admin record, and verify a real mail delivery using staging recipients. Automated tests use SQLite and fake mail/transports; no production email was sent during development.
6. Enable `FAMILY_ONBOARDING_ENROLLMENT_ENABLED=true` and keep `FAMILY_ONBOARDING_ENFORCEMENT_ENABLED=true`; rebuild cached configuration as usual.

Rollback: disable enrollment to stop enrolling new signups. Disable enforcement to lift redirects for unfinished accounts. Keep schema, records, workers and recovery in place. Re-enabling does not enroll accounts created while disabled or reset completed accounts.

Configuration:

```dotenv
FAMILY_ONBOARDING_ENROLLMENT_ENABLED=false
FAMILY_ONBOARDING_ENFORCEMENT_ENABLED=true
FAMILY_ONBOARDING_TIMEZONE=America/New_York
FAMILY_ONBOARDING_QUEUE_CONNECTION=database
```

The timezone represents the current operating area. Expanding service into other timezones requires adding location-based timezone resolution before offering visits there.

## Local review

Run `./docs/product/family-onboarding-2026-09-15/local-preview.ps1` from PowerShell. This starts the real app at `http://127.0.0.1:8033` with a separate SQLite database, synthetic users, a separate session cookie and non-delivering mail capture. It does not use the application's usual MySQL database. The setup refuses any other database target.

| Login | Purpose |
| --- | --- |
| onboarding.preview@example.test | New family with unfinished onboarding |
| existing.preview@example.test | Existing family, no onboarding enrollment |
| onboarding.admin@example.test | Admin review and follow-up |

Local-only password for these fixtures: `LoLoPreview!2026`. You can also register a new synthetic family at `/register`. Do not use these fixture accounts or password outside this isolated preview.

The original static design remains available at `/previews/family-onboarding/`; it does not save information.

The isolated preview uses the same seven-option care-task catalog as the main database seeder.

## Validation artifacts

- `tests/Feature/Family/FamilyOnboardingTest.php`: focused authorization, enrollment, persistence, transaction, date/DST, request-handoff, delivery and admin tests.
- `phpunit-onboarding.xml`: 32 focused tests and 181 assertions passed.
- `phpunit-regression.xml`: 126 existing registration, authentication, verification, invitation/account, homepage, lead, request, recipient-profile and admin/mail tests; 910 assertions passed.
- `integrated-qa.mjs`, `admin-qa.mjs` and `integrated-validation.json`: browser checks against the isolated app; screenshots in `screenshots/integrated-*.png`.
- `npm run build`: passed. Existing large-chunk and runtime-font-resolution notices remain; the font files are present and used by the preview.
- Simple-profile handoff correction: 84 tests / 639 assertions passed across `FamilyOnboardingTest`, `CareRecipientProfileTest`, and `CareRequestFlowTest`. Covers the full task catalog, prefilled answers, refresh/skip behavior, optional answers, sharing acknowledgment, reuse of the existing draft, and concurrent edits.
- `profile-handoff-qa.mjs`: browser verification of all seven help choices, both prefilled questions, saved edits after refresh, and mobile layout.

No production migration, rollout flag, real welcome visit, real SMS, or external admin email was performed as part of implementation.
