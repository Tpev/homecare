# Two-User Family Pilot Operations Runbook

Status: Working operating procedure derived from accepted `DEC-070` and `DEC-071`; grants no new release authority

Effective: August 17-29, 2026

Operating owners: Either full Administrator `1` or `18`; both receive operational alerts

## Fixed operating boundary

The live pilot remains limited to:

- production Family users `19` and `282` only;
- bundle `family_support_v1`;
- non-pricing support answers, registered navigation, authorized Family context, care intake, private draft, recap, confirmed one-time publication, and 24/7 human transfer;
- one-time commit and one-time publication tool only;
- current exact-commit release decision on `5c20f4167f1199ca5b4248a7b3516dfa3998b91b` through August 29;
- the existing canonical human-support conversation, with either Administrator able to take over;
- no Caregiver AI, recurring publication, pricing/payment behavior, third user, or general release.

Do not interpret the live readiness page's initial-pilot `BLOCKED` state as a new incident. That preflight requires guards off, Human Only on, and zero grants and is intentionally false after ordered activation. The authoritative live checks are the effective exact-commit decision, exact cohort, open controls, eligibility results, incidents/warnings, per-conversation evidence, and health monitor.

## Start-of-day check

One Administrator performs and records these content-free checks:

1. Open `/admin/ai-support`:
   - customer state is `Pilot controls open`;
   - active exact-user grants equal `2`;
   - no scheduled or additional grant exists;
   - governed KB has no paused or overdue published entry.
2. Open `/admin/ai-support/readiness`:
   - explicit release decision remains `APPROVED · EFFECTIVE` on `5c20f416...`;
   - open incidents and open warnings equal zero;
   - the six expansion obligations remain Deferred, not falsely Passed.
3. Open users `19`, `282`, one non-pilot Family user, and one Caregiver spot check:
   - `19` and `282` are Eligible;
   - the non-pilot Family user is denied for no exact grant;
   - the Caregiver is denied because the role is not released.
4. Run:

```bash
php artisan ai-support:monitor-health
```

5. Confirm:
   - failed operations notifications: `0`;
   - daily cost stop: `Not reached`;
   - no unexplained tool sample;
   - latency and cost changes are understood from reviewed conversations.

Laravel defines the same monitor on a five-minute schedule in `routes/console.php`. Production scheduler wiring was verified on August 17, 2026: the cron service was active and root invoked `cd /var/www/homecare && php artisan schedule:run` every minute. The matching content-free evidence is recorded as `Passed` under **Cost and performance monitoring** in Admin Release readiness. Recheck the runner after any cron, deployment-user, path, PHP, or scheduler configuration change. A manual run is still useful at launch, after a drill, after an incident, and at end of day because it produces operator-visible evidence.

## Review every pilot conversation

Review each new or changed pilot ticket in `/admin/support/tickets` before considering the interaction checked.

For every automated turn, verify:

- the opener is exact user `19` or `282`;
- the UI clearly labels the assistant and still offers **Talk to a person**;
- responder mode and delivery event agree;
- model-configuration, latency, input/output tokens, and cost are present;
- result capability is inside the Family pilot bundle;
- only relevant published KB-version IDs appear;
- the answer contains no invented success, queue status, response-time promise, clinical instruction, or unsupported price;
- drafts, recaps, confirmations, receipts, and related-care links exist only when the user actually entered that flow;
- no tool event exists for an answer-only turn;
- no later automated answer occurs after human-only ownership.

Keep review evidence content-free. Record safe ticket/reference IDs, versions, metrics, result codes, dates, and pass/fail outcomes. Do not copy transcripts, prompts, care details, credentials, payment information, or unnecessary personal data into readiness evidence or documentation.

## Additional review for a published one-time request

If the assistant reaches publication, verify all of the following before marking the interaction reviewed:

1. the complete recap visibly matched the intended draft;
2. the user explicitly confirmed the current recap within 30 minutes;
3. no material edit survived without a fresh recap and confirmation;
4. the authoritative confirmed-action receipt exists once;
5. exactly one ordinary Care Request was created;
6. the request fields equal the confirmed recap;
7. the request is live but no Caregiver is represented as hired;
8. no unsupported payment statement was made;
9. notification failure, if any, is handled separately and did not duplicate the request;
10. the Family route opens the authoritative request page.

Any uncertainty about confirmation, equality, duplicate prevention, authorization, or the publication result is an immediate stop condition.

## Human takeover

Use human ownership when the user asks for a person, the assistant reaches an emergency/medical/24/7 boundary, the assistant is uncertain after bounded clarification, or an operational stop triggers.

- Emergency: show the 911 instruction before transfer and do not call the provider for the emergency reply.
- 24/7 coverage: transfer promptly with no queue position, wait estimate, business-hours statement, or response-time promise.
- Human reply: an Administrator public reply changes the conversation to human-only and suppresses later automation.
- Return: use **Return deliberately** only after the issue is understood, no incident or unsafe state remains, the exact user is still eligible, and the reason is content-free.
- Never make the user repeat information already available in the canonical conversation.

The production ticket `#28` proves the basic public-Admin-reply takeover and deliberate-return path. It does not replace the full synthetic 14-observation safety rehearsal.

## Cost and performance limits

| Limit | Required response |
| --- | --- |
| Conversation target | Observe five-second P95; warning only after at least 20 trailing-hour samples |
| Tool target | Observe eight-second P95; warning only after at least 20 trailing-hour samples |
| Conversation warning | `$0.03` |
| Conversation stop | `$0.05`; stop further model loops and transfer safely |
| Pilot daily stop | `$5`; system stops `capability.support_answers_v1` |
| Per-user daily turn limit | `50` |

Do not dismiss a slow individual turn merely because the 20-sample warning has not opened. Review repeated latency drift before it reaches the automatic threshold.

## Immediate stop and rollback

Trigger rollback for non-granted exposure, cross-user/account data, unauthorized or duplicate publication, recap-to-record mismatch, missing confirmation, fabricated success, automated reply after takeover, emergency failure, material privacy leakage, or repeated provider/tool instability.

Ordered rollback:

1. turn `human_only` on;
2. disable the affected capability or commit/tool control;
3. revoke the affected exact-user grant or both grants when scope is uncertain;
4. disable `user_visible_enabled` and `master_enabled` when containment requires it;
5. if stored controls cannot be trusted, set either deployment guard false and run the normal `deploy.sh` workflow;
6. verify the existing human chat still opens and accepts messages;
7. confirm pending recaps/actions are invalid and no new automated write can occur;
8. preserve valid ordinary Care Requests, receipts, and safe drafts unless privacy requires deletion;
9. inspect both Administrator notification channels and claim the incident;
10. record content-free incident and rollback evidence.

Resolving an incident never re-enables a capability. Recovery requires a separate deliberate control decision and fresh verification.

## End-of-day check

1. Review every ticket changed during the day; unreviewed count must be zero.
2. Run `php artisan ai-support:monitor-health`.
3. Confirm no open incident, warning, or failed AI/handoff notification exists.
4. Reconcile daily model cost with per-ticket event evidence.
5. Confirm exactly two grants and no capability/role drift.
6. Record only content-free metrics and references.

## Scheduled pilot end

No later than August 29:

1. turn Human Only on before ending the pilot;
2. verify both pilot conversations remain usable by human support;
3. let the exact grants expire or revoke any still-active grant deliberately;
4. verify both users are no longer AI-eligible;
5. keep Caregiver and recurring controls off;
6. run the health monitor and review all final interactions;
7. record the end decision and retained obligations;
8. do not expand or reactivate without a new explicit release decision.

## Expansion gate

Before a third user or any capability expansion, complete and pass:

1. provider no-training and effective-retention account evidence;
2. provider destination/contract reference;
3. downstream extinction inventory and isolated restore/re-deletion rehearsal;
4. the complete synthetic 14-observation human-takeover/rollback record;
5. the five-person non-team older-adult study with correction/retest where needed;
6. a real screen-reader session.

The current pilot, a clean day, or a successful ticket does not convert any of these obligations to Passed.

## Repository handling

This runbook is prepared locally with the activation record. Do not deploy a documentation commit while the release decision is bound to production commit `5c20f416...`; a later deployment must either retain that exact commit or be followed by a fresh exact-commit preflight and release decision before user-visible automation resumes.
