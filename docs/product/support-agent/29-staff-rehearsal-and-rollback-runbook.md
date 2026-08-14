# Staff Rehearsal and Rollback Runbook

Status: Approved runbook; execution evidence open

Last updated: August 14, 2026

Owner: Either full administrator or engineering operator

## Safety boundary

The complete rehearsal runs only from a development or dedicated rehearsal checkout. It is prohibited in `production`, refuses a customer-runtime guard that is on, requires no uncommitted tracked changes so the report names the exact release candidate, uses the fixed isolated Playwright SQLite target, seeds synthetic accounts, captures marketplace side effects, and deletes the temporary database after the run.

Do not run the browser publication scenario against the production database. A synthetic Care Request must never become visible to a real Caregiver.

## Prerequisites

1. Use the exact commit proposed for deployment.
2. Install PHP, Composer, Node, and Playwright dependencies.
3. Set a dedicated OpenAI project key for the synthetic live-model gate.
4. Set `AI_SUPPORT_SAFETY_IDENTIFIER_SECRET` to a separate random value of at least 32 characters.
5. Keep `AI_SUPPORT_RUNTIME_AVAILABLE=false`.
6. Do not put real names, addresses, health details, requests, transcripts, or production-derived fixtures in the rehearsal.

## Plan-only inspection

```bash
php artisan ai-support:rehearse-release
```

This performs no process, provider call, database mutation, or report write.

## Complete one-command rehearsal

```bash
php artisan ai-support:rehearse-release \
  --execute \
  --live-provider \
  --output=ai-support/rehearsal-<date>-<commit>.json
```

The command:

1. records the current commit;
2. builds the production assets;
3. runs the isolated AI Support browser specification against a fresh synthetic SQLite database;
4. runs the frozen interactive corpus through Luna low;
5. enforces the $2 synthetic cost ceiling;
6. retains only metrics, versions, hashes, cost, latency, and safe identifiers;
7. deletes `database/playwright.sqlite` in a final cleanup step;
8. fails unless browser, provider, and database-destruction gates all pass.

Do not mark `staff_rehearsal` Passed from a partial report.

## Required scenario matrix

The supporting deterministic and browser evidence must cover:

| Area | Required result |
| --- | --- |
| Exact Family user | AI label, person button, private conversation, and approved capabilities only |
| Same-account non-granted member | Existing human support; no AI content, draft, recap, or model call |
| Caregiver | Answers/navigation boundary only; no Family draft or publication |
| Care-path selection | One-time, regular, ambiguity, and 24/7 route correctly |
| One-time request | Draft, save/resume, recap, modify, confirm, ordinary publication, authoritative receipt |
| Confirmation expiry | No write; current draft reloads into a fresh recap in one step |
| Duplicate confirmation | Exactly one Care Request and one confirmed-action record |
| Human takeover | No later automated reply or publication; pending actions invalidated |
| Emergency | 911 instruction precedes transfer; no provider call |
| Medical boundary | Non-medical boundary and transfer; no clinical action |
| 24/7 | Prompt human transfer with no queue or response-time promise |
| Pricing hold | No $30 answer or calculation; human transfer |
| Provider failure | One bounded retry maximum; safe fallback; repeated failure stops the capability |
| Cost/turn stops | Safe draft preserved and conversation becomes human-owned |
| Rollback | Human-only preserved; confirmations invalid; valid records and receipts remain |

## Operations and rollback rehearsal

Record `rollback_rehearsal` only after observing the following in a synthetic environment:

1. An automated conversation has an active recap and confirmation.
2. Human takeover changes ownership before another automated reply.
3. The recap and preview become invalid.
4. A forged or stale confirm cannot publish.
5. An automatic capability stop creates one unresolved Admin incident.
6. Both administrators receive the content-free stop/handoff alerts.
7. Resolving the incident does not re-enable the capability.
8. Human support remains usable throughout.

## Later pilot activation order

This runbook does not authorize activation. After a separate explicit release approval:

1. Keep human-only on.
2. Deploy runtime/provider guards through the normal deployment workflow.
3. Enable only the required Family and one-time stored controls.
4. Create exactly two future-dated 14-day grants.
5. Prove every other user remains ineligible.
6. Turn human-only off at the scheduled start.

## Emergency rollback order

1. Turn human-only on.
2. Disable the affected capability.
3. Revoke exact-user grants.
4. Disable master/user visibility when needed.
5. Set either deployment guard false and run the normal deployment when stored controls cannot be trusted.

Never delete an ordinary Care Request or payment record as rollback. Preserve valid receipts and safe drafts unless privacy requires deletion.
