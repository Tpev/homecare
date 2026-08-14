# Interactive Assistant Implementation and Release Evidence

Status: Implementation and fail-closed production deployment verified; limited-release approval pending

Evidence date: August 14, 2026

Owner: Product and engineering

Implementation commit: `61fca0341a467e2cb2ea65f559bd81c7c268658f`

Normative scope: `DEC-047` through `DEC-066`, [interactive expansion](22-interactive-care-request-expansion.md), and [approved build contract](23-interactive-assistant-approved-build-contract.md)

## Outcome

The approved initial interactive assistant is implemented without enabling production AI. The implementation supports role-scoped answers and semantic navigation, Family care-path selection, authorized Family context, encrypted one-time and recurring drafts, deterministic recap, 30-minute confirmation, idempotent live request publication, authoritative receipt, and direct human transfer. Caregiver scope remains answers and navigation only. A 24/7, medical, emergency, explicit-human, knowledge-gap, cost-limit, or repeated-provider-failure condition cannot create a request and transfers to human support.

This is build evidence, not limited-release approval. Repository defaults, example environment values, and database controls remain fail-closed. This record alone authorizes no production user, grant, provider call, or control activation. Subsequent governed publication of 23 reviewed non-pricing entries is recorded separately and did not activate customer AI.

An authenticated production audit on August 14, 2026 verified that the interactive surfaces are deployed and failing closed. The Admin control plane renders, there are zero exact-user grants, and the dedicated Family test account sees human support only. The expanded package was imported, then 23 reviewed non-pricing entries were Published while held `KB-CARE-006` remained Draft and all AI gates stayed off. See the [production deployment audit](25-production-interactive-deployment-audit.md) and [publication verification](26-production-kb-publication-and-settings-verification.md).

No Stripe, customer charging, fee, authorization-buffer, Caregiver payout, Family pricing override, or payment-service file was changed. Pricing answers remain held under `DEC-049` even though the approved business truth is $30 per hour.

## Implemented product surface

### Conversation and roles

- The existing support ticket and message stream remains canonical for users and administrators.
- A new automated conversation is possible only when both deployment guards, all required database controls, the exact role, the exact capability, and an unexpired exact-user grant allow it.
- Automated Family conversations use the `opener_only` visibility boundary. Another active member of the same Family Account cannot view the transcript, draft, recap, action, or receipt.
- Caregiver grants include only `support_answers_v1` and `semantic_navigation_v1`; Family context and every request-write capability remain unavailable.
- Every automated conversation exposes **Talk to a person**. Transfer is atomic and final until an administrator deliberately returns the exact still-eligible user to automation.
- Shadow processing remains impossible under `DEC-047`.

### Family request interaction

- The model may recommend `one_time`, `recurring`, `human_24_7`, or `clarify`; the user must choose one-time or regular care explicitly before a draft starts.
- The assistant reads only the current authorized Family Account, ready recipient profiles, household proposal fields, approved care-task taxonomy, and a prior request only after explicit reuse language.
- Draft payloads and interactive action payloads use Laravel encrypted casts, are bound to actor, Family Account, and conversation, use optimistic versions, and expire after seven days of inactivity.
- Material values are never published directly from model output. Server code normalizes and validates dates, times, Eastern Time, one-to-twelve-hour duration, 30-minute increments, one recurring slot per selected day, future dates, state abbreviation, ZIP, task IDs, profile versions, and account ownership.
- The recap is deterministic and displays type, recipient, care tasks, schedule and Eastern Time, adjustment, address, instructions, response window, and the live-versus-hired/payment disclosure.
- Any material change or ownership/control change invalidates the old confirmation. Logout preserves the draft but requires a fresh recap. An expired confirmation offers one-step review and renewal.
- Publication reauthorizes and locks the ticket, preview, actor, account, draft version, material hash, capability, tool, and commit flag. It creates one ordinary open Care Request, no booking, no hire, and no payment authorization.
- One-time and recurring publication have independent commit and tool controls. Repeated confirmation returns the same authoritative request.

### Human transfer and safety

- Emergency language first shows the 911 instruction and then transfers.
- Medical/clinical requests show the non-medical boundary and transfer.
- 24/7 or continuous day-and-night care transfers immediately with no queue, business-hours, or response-time claim.
- Pending previews/actions are invalidated on transfer, revocation, logout, relevant control shutdown, or draft change.
- Both administrators receive an in-app handoff alert; the canonical conversation plus a restricted deterministic internal summary prevents unnecessary repetition.
- Fabricated-success, pricing-hold, or support-time language is suppressed and automatically stops the answer capability. A second provider/contract failure within an hour also stops the capability.

### Admin, KB, evidence, and retention

- Existing Admin pages show deployment guards, database controls, exact-user grants, governed KB lifecycle, compact activity, canonical transcript ownership, drafts, recap counts, receipts, model/configuration, latency, token/cost, KB version IDs, handoff, and confirmed-action evidence.
- The expanded manifest contains 12 English request-support entries and 60 entry-linked evaluations. Import is dry-run by default and creates validated Drafts only; it never publishes knowledge or changes controls/grants.
- The pricing entry is assigned only to the held pricing capability and cannot enter ordinary support-answer retrieval.
- Retrieval now excludes zero-score entries instead of offering unrelated published content to the model.
- Expired draft and recap content is deleted while lifecycle tombstones and content-free evidence remain. Existing hold-aware retention behavior is preserved.

## Runtime and provider contract

The production candidate is `gpt-5.6-luna` with low reasoning, one bounded response per user message, strict JSON Schema, parallel tool calls disabled, a 900-token output ceiling, at most one retry, and `store: false`. Authorization, safety prechecks, retrieval applicability, validation, recap, confirmation, publication, receipt, cost stopping, and navigation authorization remain server-controlled.

The request contract follows the official [OpenAI function-calling guidance](https://developers.openai.com/api/docs/guides/function-calling) for strict schemas and the official [GPT-5.6 Luna model record](https://developers.openai.com/api/docs/models/gpt-5.6-luna). Pricing used for evidence on August 14, 2026 was $1.00 per million uncached input tokens, $0.10 per million cached input tokens, and $6.00 per million output tokens.

Conversation controls target less than $0.02, emit compact alert evidence at $0.03, and stop further model calls with human transfer at $0.05. Cost controls cannot weaken a safety or authorization gate.

## Verification evidence

### Deterministic application tests

| Evidence | Result |
| --- | --- |
| Final full Laravel suite after operator-UI cleanup | 682 passed, 5,051 assertions, 0 failed |
| AI Support suite after operator-UI cleanup | 67 passed, 583 assertions, 0 failed |
| Focused Admin Settings cleanup regression | 2 passed, 11 assertions, 0 failed |
| Final transfer/draft/runtime safety slice | 22 passed, 159 assertions, 0 failed |
| Interactive request runtime slice after task-note correction | One-time/recurring, privacy, takeover, idempotency, expiry, logout, invalid dates/slots/ZIP all pass |
| Feature-scoped Pint | 46 feature files pass; later touched files pass targeted Pint |
| Production Vite build | Pass; 110 modules transformed |
| Migration | Fresh isolated SQLite migration through `2026_08_14_100500` passes; MySQL `--pretend` emits valid explicit short constraint names |
| Diff hygiene | `git diff --check` passes |

The repository-wide Pint command still reports pre-existing style debt in unrelated legacy files. Those files were not rewritten as part of this feature. Feature-scoped formatting is clean.

### Browser and accessibility evidence

The Playwright harness used a fresh isolated SQLite database and dedicated AI pilot accounts. It did not use production or the developer's normal database and did not call the model.

| Scenario | Result |
| --- | --- |
| Exact Family pilot opens private AI conversation and reviews deterministic recap | Pass |
| Recap displays Eastern Time, disclosure, no held price, and approximately 44px controls | Pass |
| 200% text produces no document-level horizontal overflow | Pass |
| Confirm publishes one live Care Request and returns authoritative receipt/route | Pass |
| Same-account non-granted Family member sees no AI label, transcript, recap, or recipient | Pass |
| Admin overview, 12 governed KB Drafts, conversation, published draft state, and receipt evidence | Pass |
| Existing human support claim/reply/unread flow and desktop keyboard draft | Pass |
| Required phone widths, Android Chromium, iPhone WebKit, rotation, offline retry, long history, and 200% text | Pass |

The affected regression matrix passed 14 of 15 on its first combined run. The sole failure was an existing desktop-Chromium focus timing assertion; the identical case passed in both mobile projects and passed immediately when rerun alone. No product code was changed for that non-reproducible timing result.

### Live model quality evidence

The frozen `interactive-evals-v1` corpus contains 56 cases across path choice, extraction, navigation, handoff, and role/scope boundaries. Reports retain case IDs, metrics, error codes, and response hashes only; they do not retain prompts or answer text.

| Prompt | Result | Extraction | Hard failures | Cost | P50 / P95 |
| --- | --- | ---: | ---: | ---: | ---: |
| v1 | 53/56 | 96.30% | 2 | $0.142617 | 2,728 / 4,884 ms |
| v2 | 55/56 | 100% | 1 | $0.151808 | 2,863 / 4,727 ms |
| v3 final | **56/56** | **100%** | **0** | **$0.094446** | **2,820 / 4,769 ms** |

The three original v1 failures passed 15/15 repeated v2 runs. The remaining v2 phrase, “one afternoon,” passed 5/5 repeated v3 runs before the final full v3 gate. Final report SHA-256: `6D563ECA9F4C661D638FAEB81869E9476C28CC7096046E36D6B1D384BF2C7989`.

The final full gate meets the declared zero-hard-failure, at-least-98%-extraction, and five-second P95 model targets. It does not replace representative older-adult usability evidence or production provider/privacy evidence.

## Audit findings closed during implementation

1. MySQL identifier length is controlled with explicit short foreign-key/index names.
2. Task notes are joined to Care Tasks by authoritative task ID, not sorted array position.
3. The Family Account verifier recognizes the audited `opener_only` boundary.
4. Admin KB/pilot search fields no longer update the server on every keystroke; they update on blur.
5. Retrieval drops unrelated zero-score KB entries.
6. Invalid/rolled calendar dates, duplicate recurring-day slots, and malformed ZIP values cannot produce a ready draft.
7. Draft mutation, recap/message/action creation, and human transfer now use a consistent ticket-lock boundary; stale automated ticket objects cannot create work after human ownership wins.
8. Windows compiled-view failures from a parallel formatter/test experiment disappeared in the required serial full-suite rerun; they were test-environment file locks, not application failures.
9. Admin Settings no longer renders or accepts the obsolete `shadow_enabled` control. The service retains the historical key and permanent `DEC-047` denial; focused, AI Support, and full-suite regressions pass in `4ac0f07`.

## Production deployment and next operational step

The existing `deploy.sh` was used without changing its workflow. The resulting authenticated audit verified deny-by-default production behavior. It pulls `master`, installs dependencies, builds assets, runs additive migrations, refreshes Laravel caches, restarts workers/PHP-FPM, and performs health checks.

The audited production state keeps these values false:

```dotenv
AI_SUPPORT_RUNTIME_AVAILABLE=false
AI_SUPPORT_PROVIDER_ENABLED=false
AI_SUPPORT_RETENTION_EXECUTION_ENABLED=false
AI_SUPPORT_OFFLINE_EVALUATION_ENABLED=false
```

The expanded KB manifest was previewed and imported as validated production Drafts using an authorized administrator:

```bash
php artisan ai-support:import-interactive-kb
php artisan ai-support:import-interactive-kb --apply --actor-email=<admin-email>
```

The authenticated follow-up audit verified all 12 stable IDs, Version 1, validation passed, authoritative sources, 60 linked evaluations, 12 successful creations, and 12 successful validations. A later governed Admin review Published 11 non-pricing interactive entries plus all 12 initial entries, with exactly 23 successful publication events and zero failures. Keep `KB-CARE-006` unavailable while the pricing implementation hold remains.

Do not enable deployment guards or create an exact-user grant until the remaining limited-release gates below are explicitly approved. When approval exists, enable only the Family one-time capability slice first; recurring commit and Caregiver controls remain independent.

## Remaining limited-release gates

- Production provider no-training, retention, region/destination, deletion, and contractual evidence
- Cache/index/replica/export/backup inventory and restore/re-deletion evidence under `DEC-058`
- Human takeover, both-admin alert, 24/7, emergency, and rollback rehearsal in a production-like staff account
- Monitoring/alert ownership for latency, cost, provider failures, automatic stop, and operations notification failures
- Representative five-person older-adult study with the comprehension and 90% unassisted-completion gates
- Exact names of the first two Family pilot users, 14-day grant dates, review owner, and explicit limited-release approval
- Continued closeout of the independent legacy derivative/backup extinction tail

Pricing/payment reconciliation remains a separate project and is not a condition for testing the non-pricing one-time pilot, provided all pricing answers remain held.

## Rollback

For an incident, enable `human_only` or disable the affected capability/role/master control. This immediately invalidates unconsumed previews/actions and moves applicable automated conversations to human support. Leave valid published Care Requests and authoritative receipts intact. Preserve unexpired drafts unless the incident requires privacy deletion. If database controls cannot be trusted, set either deployment guard false and refresh cached configuration. Do not roll back by deleting ordinary Care Requests or payment records.
