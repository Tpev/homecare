# Limited-Release Readiness Contract

Status: Historical; operating gates superseded by `DEC-072`

> Retained for traceability. Current operation is defined in [Simplified AI Support availability](37-simplified-availability.md).

Approved: August 14, 2026

Decision owner: Product

Applies to: Provider/privacy hardening, operational readiness, staff rehearsal, older-adult usability, and the later first two-user Family pilot

## 1. Outcome and release boundary

This phase must make the approved interactive assistant ready for an explicit limited-release decision without enabling a live user prematurely. Development, deployment, evidence collection, and the initial rehearsal leave both production deployment guards off, every customer AI control off, human-only on, and exact-user grants absent.

Deploying this phase with the existing `deploy.sh` does not activate AI. A later named-user release requires a separate Product decision after every gate in this contract passes.

The following remain prohibited:

- production-conversation shadowing or invisible model processing;
- percentage, role-wide, account-wide, wildcard, or general cohorts;
- background model analysis, automatic summaries, or polling;
- an activation shortcut that bypasses independent deployment, control, human-only, capability, role, and exact-user gates;
- pricing, Stripe, fee, payout, authorization-buffer, or payment-flow changes.

## 2. Provider and model contract

For the initial bounded pilot, `DEC-068` accepts the existing server-side OpenAI API credential instead of requiring a separately administered dedicated-project credential. The credential must still never enter browser state, database content, application logs, Admin evidence, or client bundles. A dedicated restricted project credential remains recommended before expansion.

The serving candidate remains `gpt-5.6-luna` with low reasoning while it passes the frozen gates. Use the Responses API with:

- `store: false`;
- one model request per user message by default;
- strict structured output;
- parallel tool calls disabled;
- maximum 900 output tokens;
- at most one bounded retry;
- no autonomous loops;
- no provider conversations, files, vector stores, web search, background mode, MCP, Code Interpreter, hosted tools, or provider-side conversation memory.

Send a stable privacy-preserving `safety_identifier` derived by HMAC from the local user ID with a dedicated secret. Never send the raw user ID, name, email, or another direct identifier for this purpose.

The model receives only the newest user message, the bounded recent public conversation, applicable published KB excerpts, minimum authorized context, and the active-draft values required for that turn. It must not receive Admin notes, unrelated profile data, payment information, credentials, complete account history, or another user's content.

The application remains authoritative for eligibility, safety interception, retrieval applicability, navigation authorization, validation, recap, confirmation, publication, idempotency, receipt, cost stop, and handoff.

Official references checked August 14, 2026:

- [GPT-5.6 Luna model record](https://developers.openai.com/api/docs/models/gpt-5.6-luna)
- [OpenAI model guidance](https://developers.openai.com/api/docs/guides/latest-model)
- [OpenAI platform data controls](https://developers.openai.com/api/docs/guides/your-data#default-usage-policies-by-endpoint)

## 3. Provider privacy and retention

Record evidence that API data is not used for training unless LoLo opts in, that every Responses request sets `store: false`, and that default abuse-monitoring retention is no more than the approved 30-day maximum. LoLo must not opt in to model-improvement data sharing for this project.

Request Zero Data Retention for the dedicated project. ZDR is desirable but does not block synthetic staff rehearsal or the initial non-medical two-user pilot while the documented provider behavior remains within `DEC-058`. Any future medical-record, regulated-PHI, upload, or clinical workflow requires a separate legal/provider decision and is not authorized here.

Do not purchase regional data residency for this initial phase. Record the actual provider destination and contractual position. Reconsider residency only if law, contract, or an explicit Product decision requires it.

Emergency and explicit medical-procedure detection must run before the provider call where deterministic detection applies. Do not persist complete assembled prompts or undelivered candidate answers. Canonical support content and minimized evidence remain governed by `DEC-058` and the retention specification.

## 4. Versioned cost and performance controls

The official Luna record checked August 14, 2026 lists $0.20 per million uncached input tokens, $0.02 per million cached input tokens, and $1.20 per million output tokens. Replace the former dated $1.00/$0.10/$6.00 runtime estimate with independently versioned input, cached-input, and output rates plus source and effective date. A future rate change must be governed and auditable; it does not itself change the serving model.

Retain the conservative conversation rules from `DEC-065`:

- target under $0.02 per completed model-assisted conversation;
- warning at $0.03;
- at $0.05, stop model work, preserve the safe draft, and transfer to human support;
- staff-rehearsal hard stop at $2 per day;
- two-user-pilot hard stop at $5 per day;
- optional OpenAI project billing alert at $25 per month; it is recommended but not a release gate for the initial pilot under `DEC-068`;
- no more than 50 model-assisted turns per pilot user per day, followed by human support;
- five-second P95 conversational target and eight-second P95 tool-action target;
- warn when a target fails across at least 20 measured turns.

Cost never weakens authorization, correctness, confirmation, privacy, or human transfer.

## 5. Admin release-readiness workspace

Add an Admin release-readiness surface that reports, without activating anything:

- current deployment and stored safety controls;
- exact eligible-user count;
- provider configuration, data-use, retention, destination, and deletion evidence;
- KB health and the `KB-CARE-006` pricing hold;
- deterministic, model, browser, accessibility, and rehearsal results;
- operations-alert delivery result;
- older-adult study status;
- monitoring ownership and unresolved automatic stops;
- named-user pilot fields only when a later release decision is being prepared;
- a computed `BLOCKED` or `READY FOR EXPLICIT APPROVAL` result.

Either existing full administrator may record and complete evidence alone. No second-person approval is required. Every mutation records actor, time, prior/effective state, reason, and result. The readiness surface must not expose a shortcut that creates a grant, changes an AI control, or enables a deployment guard.

Admin conversation evidence continues to show the complete labeled canonical conversation plus safe model, KB, tool, draft, confirmation, receipt, cost, latency, retry, reconciliation, and transfer evidence. It must not expose chain-of-thought, complete assembled prompts, credentials, payment data, or unnecessary sensitive payloads.

## 6. Monitoring, alerts, and automatic stops

Both full administrators receive existing in-app and operations-email alerts. Either may claim the incident or transferred conversation. The Admin application shows a persistent critical state when an automatic stop or operations-notification failure is unresolved. No user-facing message may promise a queue position, queue status, business hours, wait time, or response SLO.

Immediately disable the affected capability for:

- any non-granted visibility or invocation;
- cross-user, cross-account, or cross-role disclosure;
- automated reply after human takeover;
- unconfirmed or duplicate publication;
- recap-to-domain-record mismatch;
- fabricated success;
- emergency, medical-boundary, or 24/7 handling failure;
- two consecutive provider failures beyond the per-turn retry ceiling;
- material privacy leakage.

A provider timeout or invalid structured response returns a safe message or transfer and never a guessed result. Notification-delivery failure must be independently visible because email cannot be its own only failure channel.

## 7. Isolated production-like rehearsal

Provide a repeatable one-command rehearsal using the exact release commit, a temporary isolated database, synthetic Family and Caregiver accounts, the governed non-pricing KB, and live Luna calls with synthetic text. Capture marketplace/Caregiver notifications and prohibit production records, live users, real Care Requests, Stripe operations, and external customer side effects.

The rehearsal must cover:

- Family grounded answers and semantic navigation;
- one-time, regular, and 24/7 care-path selection;
- complete one-time intake, saved draft, recap, modification, confirmation, and publication;
- expired 30-minute confirmation with one-step recap reload;
- duplicate confirmation and uncertain-publication reconciliation;
- explicit human request and takeover;
- emergency, medical-boundary, 24/7, knowledge-gap, pricing-hold, provider-failure, and cost-stop transfer;
- grant expiry, revocation, logout, and non-granted invisibility;
- cross-account and Family/Caregiver isolation;
- rollback with human support preserved.

Send one clearly labeled content-free production operations-alert test to both administrators. Destroy the temporary rehearsal database after content-free evidence, hashes, timings, costs, and identifiers are recorded.

## 8. Older-adult usability gate

Test five non-team English-speaking older adults with synthetic accounts and data:

- every participant is at least 65;
- at least two are 75 or older;
- at least two report low digital confidence;
- at least three primarily use mobile;
- at least one uses enlarged text or another accessibility setting.

Each participant attempts six tasks without coaching: ask a support question, navigate to a safe page, describe a need and choose a care type, complete a one-time request, modify the recap, and transfer to a person.

Pass only when:

- at least 27 of 30 task attempts complete without assistance;
- every participant understands the recap;
- every participant understands that a live request does not mean a Caregiver is hired;
- every participant understands that publication does not authorize payment;
- every participant can reach a person;
- no participant loses a safe draft;
- 200% zoom, screen-reader labels, keyboard/focus order, contrast, and touch targets pass.

After a material interaction or comprehension change, retest the affected task with enough new participants to retain five valid post-change observations.

## 9. Prepared first pilot scope

This contract prepares but does not approve activation for exactly two named Family/care-receiver users with 14-day grants.

The prepared first-pilot slice is:

- non-pricing governed answers;
- safe semantic navigation;
- care-type recommendation;
- authorized Family context;
- complete one-time draft, recap, confirmation, and ordinary live publication;
- direct human transfer.

Regular/recurring care may be recognized and explained, but automated recurring publication stays off until the one-time gate passes. Use human support or the existing authorized manual path. A 24/7 need transfers promptly. Caregiver AI remains off during the first Family pilot. The pricing entry remains held and a pricing question transfers to a person. This phase does not change payment behavior.

Every pilot interaction and created request must later be reviewed and marked reviewed in Admin within 24 hours. Either administrator may own review. No expansion from two to at most five users occurs until the 14-day evidence is reviewed and every critical issue is closed.

### `DEC-070` initial-pilot amendment

Product accepted Option B on August 15, 2026. For the exact initial pilot only, the following six evidence items may use the distinct `Deferred before expansion` state: provider sharing/retention, provider destination/contract reference, downstream extinction/restore, staffed rollback observation, five-person older-adult study, and real screen-reader verification. A deferral is not a Pass and remains blocking in expansion scope.

The amendment is valid only while all of these conditions hold:

- the enforced policy version is `dec-070-initial-family-v1`;
- the only approved users are Family IDs `19` and `282`;
- the only bundle is `family_support_v1`;
- at most two non-revoked grants exist;
- every grant expires within 14 days and no later than August 29, 2026;
- Caregiver AI, recurring commit/tool controls, pricing, payment behavior, and every other user remain off;
- initial-pilot preflight is green with all six items visibly Deferred, while expansion preflight remains Blocked;
- a separate exact-deployed-commit release decision is persisted before any grant or exposure-opening stored control;
- both deployment guards remain off and zero grants exist while preflight and the release decision are recorded.

The six deferred obligations must Pass before adding a third user or expanding role, capability, publication type, price/payment behavior, dates, or user scope.

## 10. Later activation and rollback order

Only after explicit limited-release approval:

1. Keep human-only on.
2. Record the exact two expiring grants while both deployment guards remain off.
3. Enable only the required Family and one-time stored controls while human-only remains on.
4. Enable the runtime/provider deployment guards through the normal deployment workflow.
5. Prove every other user remains ineligible and sees existing human support.
6. Turn human-only off last at the scheduled pilot start.

For rollback:

1. Turn human-only on.
2. Disable the affected capability.
3. Revoke exact-user grants.
4. Disable master/user visibility if necessary.
5. Set either deployment guard false and redeploy if stored controls cannot be trusted.

Rollback preserves valid Care Requests and receipts, invalidates pending confirmations, preserves safe unexpired drafts unless privacy requires deletion, and leaves human support usable.

## 11. Required implementation evidence

The implementation batch must deliver:

- release-readiness Admin UI;
- provider/privacy evidence registry;
- versioned provider cost configuration;
- privacy-preserving provider safety identifiers;
- monitoring and automatic-stop behavior;
- content-free operations-alert test;
- read-only release preflight;
- isolated live-provider rehearsal tooling;
- older-adult moderator script and scoring record;
- operations and rollback runbook;
- focused, AI Support, browser/accessibility where applicable, and full regression evidence;
- updated decision, risk, readiness, deployment, and release records.

Passing this implementation phase changes readiness evidence, not production authority.
