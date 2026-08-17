# App-Aware Guided Assistance

Status: Batches 1 and 2 implemented; registry-driven deterministic test round passes; browser/accessibility expansion remains

Established: August 17, 2026

Owner: Product

Scope: Signed-in Family users first; reuse the same platform foundation for Caregivers with separate role-specific readers, targets, and actions

## Product outcome

LoLo Support is not merely a chatbot beside the application. It is an app-aware assistant that can:

1. understand what the user is trying to accomplish;
2. read the authorized, current LoLo state relevant to that user and goal;
3. explain that state and the next step in simple English;
4. offer a clear action button;
5. navigate to the exact authorized page or record;
6. focus and visibly highlight the exact control the user needs;
7. prefill permitted non-secret information when useful;
8. observe the resulting application event;
9. verify the result against authoritative server state; and
10. tell the user that it worked, explain a recoverable failure, or transfer to a human.

The canonical interaction loop is:

```text
Understand -> Read -> Explain -> Offer button -> Navigate -> Focus/highlight
           -> User or AI-assisted action -> Verify authoritative result -> Continue
```

Personalized claims such as “your payment method is expired,” “you have two applicants,” or “your change was saved” must come from an authorized application reader or a successful domain receipt. The model may phrase the fact simply, but it may not invent, infer, or remember it as current truth.

No generative AI system can honestly guarantee that every free-form answer is 100 percent accurate. LoLo can make account-status and action-result statements deterministic: if current authoritative data is unavailable or ambiguous, the assistant says it cannot verify the state and offers the safe page or a person.

## Representative experience: change a credit card

The intended end-to-end experience is:

1. The Family user says, “I need to change my credit card.”
2. An authorized billing-status reader checks whether the user may manage the Family payment method and returns only safe facts such as `present`, `brand`, `last4`, `expiry_month`, `expiry_year`, and normalized attention state. It never returns a card number, CVC, token, or provider secret.
3. The assistant gives a short explanation and renders an **Update payment method** button.
4. Clicking the button opens the authorized billing page and starts a guided task.
5. The page scrolls the secure payment-method section into view, moves accessible focus to its heading or primary control, and highlights it with both a visible outline and text such as “Use this button to update your payment method.”
6. The user enters card details only in the existing secure Stripe UI. Chat never collects, prefills, records, or receives those details.
7. The normal billing service completes or rejects the change and emits a stable application result.
8. The guided-task verifier reads the authoritative billing state again. A browser click or client event alone is not proof of success.
9. On verified success, the chat records and presents a deterministic message such as “Your payment method ending in 4242 is now on file.” On failure, it explains the safe provider/application reason that the product exposes and keeps the user on the recovery step. If the result cannot be verified, it says so instead of claiming success.

Navigation and highlighting do not themselves modify billing. Changing the payment method remains inside the existing secure, structured application flow.

## Two kinds of status read

### Targeted status

Most turns need one narrow read, for example:

- the current payment-method and billing-attention state;
- one care request, its status, applicant count, and next available action;
- the next visit and whether hours need Family attention;
- one timesheet/payment state;
- one care receiver profile and which required information is missing;
- one regular-care or Continuous Coverage plan;
- unread messages or notifications relevant to the current task.

Targeted readers are preferred because they expose less data, cost less to assemble, and produce simpler answers.

### Family overview

The user may also ask “Is everything okay?” or “What needs my attention?” A Family overview reader returns a normalized summary across supported domains. It does not dump database records into the model. It returns compact facts and attention items such as:

- payment method missing, expiring, expired, or usable;
- care profile missing required information;
- draft or open request and its current next step;
- applicant or hiring attention;
- next visit, changed visit, or visit issue;
- submitted hours waiting for review;
- failed or incomplete payment requiring action;
- unread care-related message or notification;
- regular-care or Continuous Coverage item requiring attention.

Every item carries a stable type, authorized resource reference when applicable, current state/version, severity, a plain-language label, and one registered next target. Unsupported domains are reported as `not_checked` or `unavailable`, never silently treated as healthy.

“Check everything” therefore means “check every Family domain for which LoLo has an approved status reader,” not unrestricted database inspection.

## Authorized state-reader contract

The model never receives SQL access, an ORM, arbitrary model names, or a generic database-query tool. The application exposes purpose-built readers such as:

- `family_overview_v1`
- `family_payment_status_v1`
- `family_request_status_v1`
- `family_visit_status_v1`
- `family_timesheet_status_v1`
- `family_care_profile_status_v1`
- applicant, regular-care attention, messages, and history readers;
- later, broader regular-care, Continuous Coverage, and notification readers.

Every reader must:

- derive the authenticated user and Family Account on the server;
- run the same policies used by the normal page;
- accept only registered resource identifiers and bounded options;
- select and normalize only the fields needed by its contract;
- distinguish absent, unavailable, denied, stale, and error states;
- return stable state and next-action reason codes, not only prose;
- include a freshness time and resource version when one exists;
- avoid secrets, unnecessary care notes, and cross-account data;
- be safe to call repeatedly; and
- be tested against wrong-user, wrong-account, removed-member, stale-record, and deleted-record cases.

The orchestrator selects a reader based on the user's intent and current page. The reader result, not the model, determines whether a personalized fact may be stated.

## Guided navigation contract

The existing semantic navigation registry expands from route-only destinations into guided targets. Each target declares:

- stable target ID, for example `family.billing.payment_method`;
- route name and allowlisted route parameters;
- allowed role and any owner/member restriction;
- resource authorization resolver when the destination is record-specific;
- stable client target ID, never a free-form CSS selector or coordinate;
- visible instruction and button label;
- expected page-arrival signal;
- optional safe prefill contract;
- expected completion event and server verifier;
- fallback parent target; and
- behavior when the element is hidden, disabled, already complete, or removed by a product update.

The chat action contains only a registered target and authorized parameters. The server resolves the destination URL. The model cannot invent a route, element, selector, or record ID.

## Semantic UI targets and highlighting

Guideable application controls receive stable semantic attributes owned by the product UI, for example:

```html
<section data-ai-target="family.billing.payment-method">...</section>
<button data-ai-target="family.billing.update-payment-method">...</button>
```

After a normal or Livewire navigation, a small client guide coordinator:

1. receives the active server-issued guided task;
2. confirms that the current page matches the registered target;
3. waits for the declared Livewire/component-ready signal;
4. locates the exact semantic target;
5. scrolls it into view without disorienting motion;
6. moves keyboard/screen-reader focus to an appropriate heading or control;
7. applies a high-contrast highlight that is not color-only;
8. displays one short instruction beside or above the target; and
9. reports `arrived`, `target_missing`, `target_disabled`, or `already_complete`.

There is no arbitrary DOM access, visual-coordinate clicking, or autonomous browser control. If the registered target is missing, the coordinator removes the highlight, records the failure, keeps the user on the safe parent page, and offers human help. It never guesses which similar-looking button to use.

The highlight is temporary and dismissible. The textual instruction remains available in the guide strip and chat. Reduced-motion preferences, keyboard navigation, screen readers, 200 percent zoom, reflow, and mobile overlays are first-class acceptance cases.

## Guided-task session

Navigation creates a short-lived guided-task record bound to the authenticated user and canonical support conversation. It contains:

- task and step IDs;
- user, role, and Family Account binding;
- target and authorized resource reference;
- permitted prefill/draft reference;
- expected completion event and verifier;
- state: `offered`, `navigating`, `arrived`, `in_progress`, `completed`, `failed`, `cancelled`, or `expired`;
- timestamps and expiry; and
- safe result or failure code.

The support widget remains available throughout LoLo pages. During navigation it may minimize into a persistent **LoLo is guiding you** strip so it does not cover the control. The strip offers **Show instructions**, **Stop guiding me**, and **Talk to a person**. If the existing workflow redirects to an approved external secure page such as Stripe Checkout, the server preserves the guided task and resumes it only after the user returns to LoLo. When completion is verified, the chat presents the success result and the next useful step.

Only one foreground guided task is active per user. Starting a new one cancels or explicitly parks the previous task; it does not leave stale highlights on another page.

## Prefill contract

Prefill means preparing reversible form state, not silently completing an action.

- The assistant may prefill only fields allowlisted by that form's versioned contract.
- The user must be able to see and edit every prefilled value.
- The normal form validation, authorization, and save action remain authoritative.
- Prefill values travel through a server-side draft or signed opaque reference, not sensitive query strings.
- A field is never overwritten when the user has already changed it unless the user explicitly asks.
- Existing saved data is not treated as user confirmation for a new material action.
- Passwords, card data, CVC, bank details, verification codes, reset tokens, identity documents, and provider secrets are never chat or prefill fields.
- Prefilling can be automatic after an explicit “open and prefill” button because it is reversible; submission or another consequential write follows its own confirmation rule.

## Completion and continuation

A page click, DOM mutation, or client event means only that something was attempted. The assistant says an action is complete only after one of these authoritative proofs:

- the normal domain service returns a successful receipt bound to the guided task; or
- a post-action reader verifies the expected new state and resource version.

The completion path should usually be deterministic and should not require another model call:

1. receive a domain/UI event with the guided-task ID;
2. run the declared server verifier;
3. mark the task completed, failed, or still pending;
4. append a templated chat result with safe authoritative values;
5. remove the highlight and guide strip; and
6. offer the next registered action when useful.

This keeps latency and model cost low and prevents the model from improvising success language. A model call is reserved for interpreting new user language or explaining a complex verified result.

If a user finishes outside the guided UI, the next turn or page refresh may still verify the result. If no reliable completion event exists, the guide may offer **Check again**; it must not repeatedly poll or claim success from elapsed time.

## UX rules for older users

- Ask one short question at a time.
- Prefer one primary action button and one secondary alternative.
- Label buttons with the outcome: **Update payment method**, **Review submitted hours**, or **Open Maria's care profile**.
- State where the user is going before navigation.
- After arrival, use one instruction tied to one highlighted control.
- Keep chat history, typed drafts, and the guided task across allowed navigation and refresh.
- Never require the user to repeat information the authorized application already knows, unless freshness or confirmation requires it.
- Never say “done,” “saved,” “paid,” “approved,” or “sent” without authoritative verification.
- If the expected control is unavailable, explain the actual state in plain language and offer the next valid target.
- Keep **Talk to a person** available at every step and transfer the same context without asking the user to repeat it.

## Cost model

The guided architecture is deliberately inexpensive:

- use one model call to understand a new natural-language goal;
- use deterministic application readers for status;
- reuse normalized overview data within the same short-lived turn when still current;
- navigate, highlight, prefill, and verify without a model call;
- use templated arrival, success, and common failure messages;
- call the model again only for a genuinely new or ambiguous user request;
- never run an autonomous planning loop or continuous browser observer.

Database reads, Livewire events, and deterministic templates should handle most steps after intent recognition.

## Failure behavior

| Failure | User experience |
| --- | --- |
| Reader denied | Reveal no record existence; give the generic allowed next step or transfer |
| Reader unavailable or stale | Say the status cannot be verified now; offer the safe page or human support |
| Destination no longer valid | Stay on/open the fallback parent page; do not guess another record |
| Target element missing | Remove guidance; explain that the exact control could not be shown; offer human help |
| Target disabled or action already complete | Read authoritative state and explain why; offer the actual next step |
| Prefill no longer matches current record | Preserve user input, refresh the draft, and ask the minimum correction |
| Action validation fails | Keep the user on the field/control and explain the correctable issue |
| Completion cannot be verified | Say “I couldn't verify that yet”; offer **Check again** or human support |
| User leaves the workflow | Keep the task resumable until expiry and remove any page highlight |
| Human takes over | Stop automated guidance and replies immediately; preserve the target, draft, and verified state for the human |

## Next implementation sequence

### Batch 1 - guided-assistance foundation and payment-method vertical slice

Build the reusable guided-task record, expanded semantic target registry, client guide coordinator, deterministic arrival/completion messages, and server verifier. Prove the complete loop on the payment-method workflow:

- answer how to add or change a card;
- read safe current payment-method/attention state;
- render **Add payment method** or **Update payment method**;
- navigate to the billing page;
- focus and highlight the secure payment-method control;
- preserve the guided task across the Stripe Checkout redirect and resume it after the user returns to LoLo;
- verify the new safe card state after the existing billing flow finishes; and
- tell the user the verified outcome.

This batch does not change payment calculation, authorization, fee, or capture behavior.

### Batch 2 - Family read and guide coverage

Add the Family overview plus targeted readers and exact guide targets for:

1. existing care requests and applicant attention;
2. next/current visits and visit issues;
3. submitted hours, approval, payment attention, and timesheet recovery;
4. care receiver profiles and missing information; and
5. the relevant exact request, visit, timesheet, profile, message, and history pages.

At the end of this batch, the assistant can truthfully answer “What needs my attention?” and guide the user to each supported next step.

### Batch 3 - safe prefill

Add versioned prefill contracts for non-secret, reversible Family forms, beginning with care profiles, request reuse/editing where the product supports it, message drafts, and support intake. The user reviews and saves through the normal UI.

### Batch 4 - confirmed actions

Promote high-value actions from guided manual completion to recap-and-confirm execution one at a time. Start with low-ambiguity actions backed by existing domain services. Keep payment credentials, passwords, medical decisions, exceptional refunds/disputes, account closure, ownership transfer, and serious safety/privacy cases in their structured or human workflows.

### Batch 5 - Caregiver adaptation

Reuse the platform components with separate Caregiver readers and semantic targets for onboarding/profile status, Work Inbox, applications/invitations, upcoming visits, hours needing update, earnings/payout setup, messages, and support. Family data and capabilities are never exposed in the Caregiver bundle.

## Batch 1 acceptance criteria

Batch 1 is complete when:

- an authorized Family user can ask to add or change a payment method and receive the correct button for current state;
- an unauthorized member receives no owner-only fact or destination;
- clicking the action opens the correct page without exposing secrets in the URL;
- the exact secure control is focused, visibly highlighted, and described accessibly;
- the guide works after Livewire navigation, refresh, mobile layout, keyboard use, and reduced-motion mode;
- missing/disabled targets fail safely without highlighting a substitute;
- no card number, CVC, provider token, or credential enters chat, model input, guide storage, logs, or URLs;
- a successful existing billing update is verified server-side before the assistant says it worked;
- a failed, abandoned, or unverifiable update is never reported as successful;
- the support conversation, typed draft, and human-transfer option remain available; and
- navigation, arrival, completion, verification, cancellation, and failure cases have focused automated tests.

## Batch 1 implementation record

Implementation status: Complete in source on August 17, 2026. Production availability is unchanged by this batch and remains controlled through the existing Pilot/Everyone/Emergency stop setting.

Delivered:

- a reusable, short-lived, exact-user guided-task record with encrypted payloads, lifecycle state, expiry, cancellation, and deterministic result codes;
- a narrow owner-authorized Family payment-method reader that returns only readiness, attention, brand, last four digits, and expiry;
- deterministic add/change/status intent handling that bypasses the model and therefore adds no model cost for this workflow;
- an owner-only semantic billing target whose client contract contains a stable target ID rather than a selector or coordinate;
- **Add payment method** or **Update payment method** chat actions selected from fresh server state;
- navigation to Billing & Payments, accessible focus and non-color-only highlight of the existing secure control, plus **Show me**, **Stop**, and **Talk to a person** controls;
- task and typed-chat-draft continuity across page navigation, Livewire rendering, refresh, and the existing Stripe Checkout round trip;
- authoritative post-return verification before a deterministic success message, with truthful recovery for cancellation, target failure, checkout failure, and unavailable verification;
- immediate guided-task cancellation on human takeover, pilot revocation, relevant control stops, and logout; and
- mobile reflow, 200 percent text, keyboard focus, reduced motion, missing-target, cross-user, expiry, cancellation, failure, handoff, payment-regression, and end-to-end browser coverage.

Unchanged boundaries:

- card number, CVC, payment-method token, Stripe customer ID, credentials, and unrestricted records never enter model input, guide payloads, chat, URLs, or compact interaction events;
- Family members do not receive owner-only card facts or the billing-management destination;
- the assistant does not click the secure control, fill payment credentials, or mutate the payment method;
- existing Stripe, payment authorization, capture, fee, payout, and request-publication behavior is unchanged; and
- this does not enable AI for any additional user or switch availability to Everyone.

Batch 2 now adds narrow Family reads and exact guide targets for requests/applicants, visits and caregiver change requests, submitted hours/payment attention, care-receiver profiles, messages, and history, culminating in a truthful “What needs my attention?” overview. The registry-driven deterministic test round is complete. A real-browser/accessibility expansion remains before Batch 3 safe prefill.

### Acceptance audit

| Batch 1 requirement | Implementation evidence | Automated proof |
| --- | --- | --- |
| Authorized owner receives the correct Add/Update action | `FamilyPaymentMethodStatusReader` plus deterministic `offerPaymentMethod` mode selection | `GuidedPaymentMethodTest`: missing card, existing card, and current-card question cases |
| Member receives no owner-only fact or destination | Owner check precedes billing read, task creation, target exposure, and prompt target listing | `GuidedPaymentMethodTest`: Family member boundary and target-registry assertions |
| Action opens only the registered page and exposes no secret in the URL | Server resolves `family.billing.payment_method` to `family.billing.show`; client receives only the semantic target ID | `GuidedPaymentMethodTest`: exact redirect and encrypted/minimized stored state assertions |
| Exact secure control is focused, highlighted, and described accessibly | `data-ai-target`, bounded exact-ID lookup, focus, outline/shadow, live announcement, and guide-strip instruction | Playwright guided payment-method scenario |
| Livewire navigation, refresh, mobile, keyboard, 200 percent text, and reduced motion work | Server task survives navigation; client reacquires the semantic target after render/refresh; responsive/reduced-motion CSS and JS are explicit | Playwright guided payment-method scenario verifies refresh, 390px layout, 200 percent text, focus, minimum controls, draft retention, and reduced motion |
| Missing or disabled target fails without substitution | Exact target matching has no selector/coordinate fallback; server records a failed task and truthful recovery message | `GuidedPaymentMethodTest`: separate missing-target and disabled-target cases |
| Secrets never enter AI or guidance state | Reader allowlists brand/last4/expiry only; model is bypassed; task payload is encrypted and contains mode/instruction only; compact events contain IDs/result codes | `GuidedPaymentMethodTest`: no provider request, no hidden provider/customer identifiers, and no safe-card value in raw task attributes |
| Success is verified server-side | Existing Stripe sync completes first; verifier re-reads current billing state; only then is deterministic success recorded | `GuidedPaymentMethodTest`: full bypass Checkout start/return/verification case; Playwright verifies returned card and chat result |
| Failed, abandoned, or unverifiable outcome never says success | Recovery returns task to the exact billing step and uses explicit non-success copy | `GuidedPaymentMethodTest`: checkout cancellation, unavailable verification, target failure, and expiry cases |
| Conversation, draft, and human transfer remain available | Existing persistent widget remains; guide adds Stop and Talk to a person; handoff cancels task without billing mutation | Playwright verifies draft continuity and guide controls; `GuidedPaymentMethodTest` verifies handoff cancellation |
| Lifecycle events and regressions are covered | Offered, started, arrived, action-started, completed, cancelled, recovery, and target-failed outcomes use compact interaction events | Focused guided-task tests, full PHP application suite, production asset build, and four-scenario AI Support browser pack |

## Batch 2 acceptance criteria

Batch 2 is complete in source when:

- an eligible Family owner or member can ask what needs attention and receive a current, deterministic summary across the supported Family domains;
- saved payment-method attention is owner-only, while care-payment actions remain available according to the normal Family-account permissions;
- request status and caregiver-response claims come from the current authorized request/application records;
- next/current visit status and pending caregiver change requests come from current authorized bookings;
- submitted hours, time corrections, care-payment recovery, and completed-extra-visit attention use the same unresolved-action source as the Family dashboard;
- care-receiver profile responses distinguish missing, draft, and ready state and identify the first required missing step;
- unread/recent conversations and completed-care history are read only within the active Family Account;
- every button resolves a registered target and an authorized resource on the server, with no model-provided route, record ID, selector, or coordinate;
- the destination exposes the exact stable semantic target and the existing guide coordinator focuses and highlights it accessibly;
- wrong-account, expired, missing, and disabled targets fail closed and never reveal whether another Family's record exists;
- these reads, responses, navigation actions, and highlights do not mutate requests, bookings, hours, profiles, messages, or payments; and
- the new deterministic intents do not consume a model call or expand production availability beyond the existing pilot users.

## Batch 2 implementation record

Implementation status: Complete in source on August 17, 2026. Production availability is unchanged. The registry-driven deterministic mass-test round is complete; real-browser, responsive, accessibility, stale-resource, and pilot inspection remain the next QA slice.

Delivered:

- a deterministic Family intent router for **What needs my attention?**, requests/applicants, current or next visits, caregiver visit-change requests, submitted hours/timesheets, care-payment failures, care-receiver profiles, messages, and history;
- a normalized Family assistance service that derives the authenticated Family Account server-side and produces simple English directly from current application state without sending those account facts to the model;
- reuse of `FamilyActionInboxBuilder` as the canonical unresolved-action source for payment attention, time corrections, completed extra visits, live visits, unapproved timesheets, applicants, and now caregiver-requested visit changes;
- owner-only saved-payment-method attention in the overview using the Batch 1 safe card reader, including missing, expiring, expired, ready, and unavailable states without exposing card or provider secrets;
- request lifecycle and applicant reads, current/next booking reads, submitted-duration and approval-state reads, draft/ready profile reads with the first missing required step, user-specific unread conversation reads, and completed-history availability reads;
- up to six outcome-labelled guide buttons on one assistant message, with one foreground task and clean invalidation of unused sibling buttons when the user chooses a step;
- additive guided-task resource references and resource resolvers for care requests, care plans, care-receiver profiles, and conversations, all re-authorized against the active Family Account when offered, started, and rendered;
- exact registered destinations and stable semantic UI targets for Care, request overview, caregiver replies, visit status, visit-change decisions, timesheets, care-payment recovery, regular-care attention, care profiles and their missing step, message inbox/thread, and Care history;
- deterministic, content-free `family_account_status_read` and guided-task lifecycle events; and
- focused automated coverage for intent boundaries, no-model execution, authoritative overview composition, cross-account denial, resource-bound URLs, read-only behavior, visit-change attention, exact profile steps, semantic page targets, and the complete Batch 1 payment regression suite.

Unchanged boundaries:

- Batch 2 reads and guides; it does not approve hours, accept/reject a visit change, send messages, edit profiles, retry payments, or perform another domain mutation for the user.
- A highlighted button remains part of the normal application workflow. Except for the separately verified Batch 1 payment-method flow, Batch 2 does not claim that a later domain action succeeded merely because the user reached or clicked the control.
- The model receives no ORM, SQL, generic database tool, raw record dump, payment secret, care note dump, arbitrary URL, or browser selector.
- Continuous Coverage management, notification-state reads, full historical amount aggregation, named-record natural-language resolution, form prefill, and confirmed domain execution remain later coverage.
- Family members still receive no owner-only saved-card fact or payment-method-management destination.
- No AI availability control, pilot grant, care/payment rule, or existing domain behavior changes in this batch.

### Batch 2 focused acceptance audit

| Batch 2 requirement | Implementation evidence | Focused automated proof |
| --- | --- | --- |
| Truthful “What needs my attention?” | Supported-domain overview combines safe payment-method state, canonical unresolved care actions, draft profiles, and unread conversations | `FamilyGuidedAssistanceTest`: four-domain attention composition and no-provider assertion |
| Requests and applicants | Family-scoped lifecycle read plus applied/shortlisted counts and exact Applicants target | Intent, overview, route-binding, and rendered target assertions |
| Visits and visit issues | Current/next booking selection plus pending caregiver change requests from the canonical booking | Scheduled/live selection coverage and dedicated caregiver-change test |
| Submitted hours and payment recovery | Canonical action inbox plus latest submitted-timesheet fallback, normalized duration/status copy, and exact Timesheet/Payment targets | Submitted-hours resource route, unchanged booking state, and payment regression assertions |
| Care-receiver profiles | Active-account list distinguishes none/draft/ready and maps the first missing required field to steps 1, 4, or 5 | Missing-profile and draft-profile overview assertions; dynamic profile resource authorization |
| Messages and history | User-specific read timestamps identify unread threads; completed booking count/latest record powers history copy | Empty and unread cases plus rendered Inbox/History target assertions |
| Server-authorized resources | Registry resolves only account-owned request, plan, profile, or conversation IDs and rechecks on navigation/render | Explicit other-Family denial and exact resource URL assertions |
| Exact accessible target | Stable `data-ai-target` markers cover every Batch 2 destination and reuse the bounded guide coordinator | Rendered semantic-target assertions across static and record pages |
| Read-only and inexpensive | Deterministic path bypasses provider and creates only support messages, guide tasks/actions, and compact events | No HTTP request, no model event, and unchanged request/booking/profile assertions |

Implementation verification completed on August 17, 2026:

- `php artisan ai-support:test-family-intents` passed all 40 declared registry intents, 120 representative phrasings, 10 near-neighbor collision cases, and 29 isolated application tests with 355 assertions;
- the mass test exercised all 13 Batch 1 payment-method tests, all seven Batch 2 focused tests, five positive/changing-state matrix tests, and four runner/corpus tests;
- the runner used in-memory SQLite, bypassed deployed configuration caches, made zero provider calls, wrote no production database record, and left availability and exact pilot grants unchanged;
- the complete `tests/Feature/AiSupport` regression passed 133 tests with 1,174 assertions;
- Pint, PHP syntax checks, Blade compilation, route caching, and the production Vite build passed; and
- the deployment-facing availability setting and exact two-user pilot grants were not changed.

The [Family Batch 1-2 evaluation harness](40-family-batch-1-2-evaluation-harness.md) is now the repeatable regression command for these 40 rows. The next QA slice extends it across real browser navigation for every target, mobile/reflow, keyboard and screen-reader behavior, stale/deleted resources, member/owner variants, and representative pilot inspection.

## Relationship to the coverage registry

The [Family intent and AI action coverage registry](38-family-intent-action-coverage-registry.md) remains the exhaustive demand backlog. This document defines the reusable experience required to move rows from generic explanation or route navigation into app-aware **Read**, **Guide**, **Prefill**, and eventually **Confirm & execute** coverage.

Guiding the user through the normal UI is not the same as the AI performing the domain action. Registry rows should say **Guide** until the assistant itself invokes an authorized write tool and receives an authoritative receipt.
