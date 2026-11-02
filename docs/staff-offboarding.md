# Staff offboarding

## John Annuar Morano — November 2, 2026

Live identity verified in the administrator UI: user **407**, **John Annuar Morano**, **johnannuarmorano@gmail.com**, role **SDR**. The user confirmed **john@carelolo.com** is also his address and requested that all assignments return to the unassigned queues.

Completed in production through the UI: removed `john@carelolo.com` from new-lead alerts. Saved recipients are `hello@carelolo.com` and `peverelli.t@gmail.com`. Escalation recipients are blank and therefore inherit this list. Saved settings were reopened and verified. No notification or test message was sent.

Still pending in production: account revocation, incoming-SMS email suppression, session/token revocation, permanent identity blocks, and assignment release. The existing production UI protects staff from deletion and offers no offboarding action. The implementation in this change is local until deployed and explicitly applied.

Live audit found 162 family leads and 714 referral leads assigned to John, at least one caregiver CRM record, and 659 historical family calling activities. His profile showed no phone and no AI pilot grant. Counts may change before application; the command reports current database counts. The active administrator was verified as user **1**, **Thibaud Peverelli - Admin**.

## Production application

The deployment owner deploys the reviewed complete change using the existing deployment procedure, including its migration and worker restart. The assistant has not accessed the production server through SSH. The current checkout also contains the previously prepared prepaid family-wallet release; do not treat deploying the whole checkout as an isolated staff-offboarding release. The deployment owner must select the reviewed release and preserve the existing screening/prepaid compatibility requirements.

First run the read-only preview from the deployed application directory:

```bash
php artisan staff:offboard 407 --email=johnannuarmorano@gmail.com --alias=john@carelolo.com --by=1
```

Then apply the user's requested removal and assignment release:

```bash
php artisan staff:offboard 407 --email=johnannuarmorano@gmail.com --alias=john@carelolo.com --by=1 --reason="Staff departure confirmed by administrator; revoke access and return all assignments to the unassigned queues." --apply
```

Repeat the preview afterwards. It must report `already_revoked: true`, zero sessions, zero active API/OAuth credentials, zero owned lead/caregiver/support records and zero pending notifications. Both email addresses must appear in `blocked_emails`. Check the UI's Offboarded filter and ensure John is absent from active staff/owner choices. Do not use a real SMS, recovery email or customer notification as a test.

## Coverage

- Password login, existing database sessions, remember-me cookies and the password-reset broker are denied. Middleware also rejects stale authenticated user models. Existing passwords and remember tokens are invalidated.
- The account becomes `offboarded`, loses content permissions and is excluded from the active user directory. Both confirmed email addresses are blocked against registration or reassignment to another account. The ordinary SDR creation command cannot reactivate the account.
- Content API tokens, hosted MCP access/refresh tokens and unused authorization codes are revoked. A disabled account cannot qualify as a content-team actor.
- Incoming-SMS alerts and family acquisition recipient lists exclude blocked addresses. All outgoing Laravel mail filters blocked To/CC/BCC/Reply-To addresses immediately before transport, including older queued messages. Other recipients remain eligible.
- Pending notification deliveries are suppressed and all user notification preferences disabled. User-targeted notification dispatch checks current account state. Recorded phone/SMS numbers are blocked in the Twilio send path before any provider request; conflicting shared phone ownership aborts the operation for review.
- Family/referral leads, caregiver CRM ownership and support ticket claims return to unassigned queues. Follow-up dates, customer data and historical activity remain. New assignment-release audit entries identify the administrator and prior owner; caregiver record versions advance.
- Offboarding is transactional and repeatable. It requires an exact user ID/email pair and an active administrator. Aliases belonging to other accounts and administrator/self targets are rejected.

The historical user row and minimal identity blocks remain to preserve business attribution and enforce the ban. This is permanent removal of operational access, not erasure of call history, customer records or backups. Do not restore or roll back to code that lacks the revocation gates; use a compatible forward repair. The migration deliberately refuses a rollback that would discard the security records.

The app can block only known identities. It cannot identify a person signing up with an unrelated new identity, recall messages already accepted by a provider, erase prior exports, or revoke access to independently administered services. The owner of the `john@carelolo.com` mailbox/forwarding rule and any separately granted Slack, Google, Meta, Twilio or other business access must terminate those permissions through their respective administration tools. Their existence or termination was not verified in this audit.

## Local validation

176 tests passed with 1,362 assertions: the new 11-test staff-offboarding suite plus existing authentication, user deletion, SDR CRM access, CRM SMS/retry, family acquisition, content API/OAuth, email preference and administrator notification suites. PHP formatting and `git diff --check` passed. These are local tests using the test database and fake/array transports; they do not prove production revocation or external mailbox shutdown.
