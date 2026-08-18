# Family Batch 1-2 Evaluation Harness

Status: Implemented and passing in source

Established: August 17, 2026

Owner: Product and Engineering

Scope: The exact 40 Family intent rows currently covered by guided-assistance Batches 1 and 2

## Outcome

LoLo now has one repeatable command that tests the complete declared Batch 1/2 Family intent slice:

```bash
php artisan ai-support:test-family-intents
```

The command does not enable AI Support, change Pilot/Everyone availability, alter a pilot grant, call OpenAI, call Stripe, or use the production database. It starts a separate Laravel test process with configuration caches bypassed and an in-memory SQLite database. The only optional persistent output is a content-minimized JSON report when `--output` is supplied.

The full command requires Composer development dependencies, including PHPUnit, and is therefore intended for the development workspace or CI before deployment. The production `deploy.sh` intentionally installs Composer with `--no-dev`; on that server, use `--plan` only to validate the deployed corpus/router inventory, then use the real-browser QA slice for deployed behavior. Do not install development dependencies on production just to run this suite.

The first complete run on August 17, 2026 passed:

| Layer | Result |
| --- | ---: |
| Registry intents | 40 / 40 |
| Representative user phrasings | 120 / 120 |
| Nearby out-of-scope collision cases | 10 / 10 |
| Application tests | 29 / 29 |
| Application assertions | 355 |
| Complete AI Support feature regression | 133 tests / 1,174 assertions |
| Provider calls | 0 |
| Production database writes | 0 |

The same corpus/router inventory was then deployed through the normal production workflow at commit `0655b5b54e12ff8abffb6d00dcb81b723bd4a504`. Production `--plan` passed all 40 registry intents, 120 routing phrases, and 10 collision cases using zero provider calls and no database write.

The first corrective run on August 18, 2026 expanded the same 40-row corpus to 121 phrases. It passed 121 of 121 phrases, 10 of 10 collision cases, 40 of 40 registry intents, and 30 application tests with 367 assertions. It continued to use isolated in-memory SQLite, made zero provider calls, and made zero production writes. Those cases lock natural “status of my care request” wording and dedicated regular-care status/plan routing.

The task-navigation correction later that day expanded the corpus to 122 phrases with the exact pilot wording **“Hi, I want to use another credit card.”** The full runner passed 122 of 122 phrases, 10 of 10 collision cases, all 40 intents, and 32 application tests with 376 assertions. The deterministic payment-specific regression also proves that the exact phrase and its “I’m the account owner” follow-up create the authorized **Update payment method** action with zero provider calls.

## Production browser QA record

Authenticated production QA began August 17, 2026 with exact Family pilot user `19`. Before sending any Batch 1/2 intent, the audit confirmed:

- Availability remained **Pilot only**;
- exactly Family users `19` and `282` had active pilot access;
- **Live for everyone** remained off; and
- no care, payment, profile, message, request, timesheet, or availability record was changed.

The first live journey found a blocking support-widget regression. After the existing chat had been resolved, **Start a new conversation** rendered the empty composer, but the background refresh reclaimed the resolved conversation before the first new message could be sent. The old ticket returned and Send became unavailable. Creating an ordinary Support Center ticket was verified not to be an AI-assistant path and was not accepted as a workaround.

The correction adds an explicit server-owned “starting new conversation” state. Polling may still discover an active conversation normally, but after the user deliberately resets a resolved or closed chat it cannot reclaim the old ticket. The state clears only after the first new chat ticket is created. Regression proof now covers:

- resolved and closed conversations;
- a background refresh after reset;
- first-message creation as a distinct new chat ticket; and
- a real Playwright wait beyond the polling interval followed by an enabled Send action.

The focused PHP regression passes 14 tests and 96 assertions, the exact Chromium resolved-chat browser test passes, and the production Vite build passes. The blocked attempt itself is not counted as a passed intent journey.

The deployed correction was then verified in production: after **Start a new conversation**, the composer remained usable beyond the polling interval, retained its draft, and sent the Family overview question. The deterministic answer read current account state and returned three guide buttons without changing care data.

That first new-ticket send exposed a second client defect. Livewire changed the dynamic root Alpine initializer when the new ticket ID appeared, leaving the visible composer bound to an old state object. Four null pending-message expressions also reached the console. The answer rendered, but later visible typing could leave Send disabled until a page reload. The grouped source correction now:

- keeps the root `x-data` expression stable while reading initial server values from data attributes;
- preserves Livewire updates to the guided-task payload without recreating the Alpine component;
- makes every pending-message expression null-safe;
- re-synchronizes guidance after a completed guided task; and
- proves two consecutive messages after a resolved-chat reset, beyond the polling interval, with no page error and no reload.

After a safe reload, a payment-method question received the expected unavailable-state answer and Billing guide. A later natural affirmative, “yes do it,” was not connected to that existing button and safely transferred the conversation to human support. This is a real conversational-usability gap, not a safety failure: human ownership correctly stopped further automation. Affirmative follow-up handling must be added without auto-confirming a care, payment, timesheet, or other write. Further live intent testing requires the current ticket to be deliberately returned to automation by an Administrator or a fresh eligible pilot chat.

The corpus and runner follow the evaluation pattern in the official [OpenAI evaluation guidance](https://developers.openai.com/api/docs/guides/evals): freeze representative tasks, declare the expected outcome, run the same suite after changes, and track success separately from cost and latency. Batch 1/2 account reads and intent routing are deterministic, so this slice intentionally does not spend model tokens.

## What “passed” means

An intent is reported as passed only when both layers pass:

1. **Intent routing** — all three frozen natural-language phrasings reach the expected Batch 1/2 handler, and nearby unsupported intents do not collide with the new handlers.
2. **Shared application regression** — the real Laravel services pass the Batch 1 payment workflow, Batch 2 state/read/guide workflow, resource authorization, semantic destination, safe failure, and no-domain-mutation tests in an isolated database.

This prevents a shallow result where the assistant recognizes a sentence but the actual account-state or guide flow is broken.

## Frozen corpus

The machine-readable corpus is `resources/ai-support/evaluations/family-guided-v1.php`. `FamilyIntentEvaluationCatalog` validates it before every run.

The catalog must contain each declared registry row exactly once:

- Batch 1: six saved-payment-method intents;
- Batch 2: one Family overview intent;
- Batch 2: three care-profile intents;
- Batch 2: two request intents and two applicant/message matching intents;
- Batch 2: eight care-payment/history intents;
- Batch 2: nine visit, hours, correction, and payment-continuation intents;
- Batch 2: five regular-care intents;
- Batch 2: two communication intents; and
- Batch 2: two Care-history intents.

Every row declares:

- registry intent ID;
- Batch number;
- domain;
- expected deterministic handler;
- at least three representative English phrasings; and
- the focused runtime test that supplies its application evidence.

The catalog validator fails if an ID is missing, duplicated, malformed, has fewer than three phrasings, names an unknown handler, or lacks runtime evidence.

## Test layers

### 1. Language routing and collision protection

`FamilyIntentCoverageTest` checks all 122 phrasings and the 10 near-neighbor cases. The collision set protects existing or later flows such as request creation, passwords, refunds, Family invitations, account deletion, caregiver browsing, medical help, human transfer, general product information, and notification settings.

The mass corpus found real natural-language gaps during its first implementation. The deterministic router was expanded to understand plural care profiles, ordinary applicant wording, natural request-status phrasing, pending charge wording, failed time-correction payments, scheduled-care wording, visit-change decisions, corrected hours, completed extra visits, and regular-care history. The expected cases were not weakened to hide those misses.

### 2. Batch 1 payment-method regression

`GuidedPaymentMethodTest` verifies:

- missing, existing, and queried saved-card state;
- active-member shared billing authorization with only safe card facts and the registered secure destination;
- exact registered billing navigation and accessible semantic target;
- expired task denial;
- server-authorized arrival, missing-target, and disabled-target results;
- existing secure Checkout continuation;
- authoritative completion verification before success;
- cancellation and unverifiable-result recovery; and
- immediate cancellation when a human takes over.

### 3. Batch 2 focused regression

`FamilyGuidedAssistanceTest` verifies:

- deterministic intent routing that does not capture request creation;
- a multi-domain “What needs my attention?” result from authorized state;
- submitted-hours resource routing and cross-account denial;
- pending caregiver visit-change guidance;
- empty profile, messages, history, and visit results;
- regular-care plan targeting instead of leaking its system request; and
- rendered semantic target markers on Batch 2 pages.

### 4. Batch 2 state matrix

`FamilyGuidedAssistanceStateMatrixTest` adds positive and changing-state coverage for:

- request lifecycle and caregiver applicants;
- scheduled, live, and completed/submitted-hours booking states;
- failed care payment and exact recovery target without provider-error disclosure;
- ready care profile, unread exact conversation, and completed Care history;
- time-correction payment action;
- next regular-care visit; and
- completed extra-visit payment attention.

These tests assert that domain records remain unchanged. Expected AI-support records—messages, guided tasks/actions, and compact events—are allowed because they are the support feature's output. The tests also assert that no model request or `model_turn_completed` event occurs.

## Commands

Validate and inventory the corpus without creating a test database or report:

```bash
php artisan ai-support:test-family-intents --plan
```

Run the full evaluation:

```bash
php artisan ai-support:test-family-intents
```

Run this full form in development or CI, not on the normal no-dev production install.

Run it and save a content-minimized report on Laravel's local storage disk (`storage/app/private` in the current application):

```bash
php artisan ai-support:test-family-intents \
  --output=ai-support/evaluations/family-batch-1-2-latest.json
```

Inspect a selection:

```bash
php artisan ai-support:test-family-intents --plan --batch=1
php artisan ai-support:test-family-intents --plan --domain=payments
php artisan ai-support:test-family-intents --plan --intent=FAM-PAY-003
```

`--batch`, `--domain`, and `--intent` may be repeated. Filters limit the intent-routing inventory and per-intent report. A non-plan execution still runs the complete shared application regression because one state reader, target registry, or guide lifecycle change can affect several domains.

## JSON report

The optional `family-intent-evaluation-report-v1` JSON contains no production user message or account data. It records:

- corpus version and frozen date;
- isolated execution properties and duration;
- routing, collision, and application-suite totals;
- overall pass/fail;
- one result per selected registry intent; and
- the focused runtime-evidence test associated with each intent.

If the shared runtime regression fails, no selected intent is reported as passed. The console preserves PHPUnit's precise failing test and assertion for diagnosis.

## What this harness does not claim

- It does not test all 324 Family registry rows. It tests the exact 40 rows declared as Batch 1/2 Read/Guide coverage.
- It does not prove that Batch 2 performs the user's domain action. Batch 2 opens and highlights the normal application control; the user still performs the action. Batch 1 separately verifies the payment-method result after the normal secure flow.
- It does not replace the existing 56-case provider evaluation for request intake and drafting.
- It does not make a stochastic model-quality claim because the Batch 1/2 fast paths under test do not call the model.
- It does not replace Playwright browser checks, real mobile/browser inspection, or a screen-reader session. The production pilot has now covered representative overview, request, visit, hours, profile, messages, history, payment-attention, refresh, mobile reflow, keyboard focus, and guide recovery journeys. A real screen-reader session, the second pilot member variant, and isolated stale/deleted-resource browser simulations remain.
- It does not inspect or mutate production pilot data and does not switch availability to Everyone.

## Maintenance rule

Whenever a Batch 1/2 phrase, handler, state reader, authorization rule, navigation target, semantic UI marker, verifier, or registry status changes:

1. update the frozen case or add a new registry case;
2. add at least three representative phrasings and one nearby collision when relevant;
3. name focused runtime evidence;
4. run the complete command;
5. fix the implementation or consciously update the declared product behavior; and
6. update the coverage registry and this record if the 40-intent scope changes.

New Batch 3+ capabilities should join this runner only after their own expected authority is explicit. A prefill, confirmed write, or verified completion must never inherit a passing Read/Guide result as proof of its stronger behavior.
