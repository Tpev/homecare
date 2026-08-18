# Family Chat Operator Master Coverage and Delivery Plan

Status: Active master plan; Batches 1 through 4 implemented in source; joint Batch 3/4 production review is next

Audited: August 18, 2026

Owner: Product and Engineering

Primary scope: Authenticated Family users, with Caregiver adaptation after the Family operating layer is proven

## Product outcome

LoLo Support should become the simplest way to use the application. A Family user should not need to know which page, menu, tab, or control exists. The user should be able to describe a goal in ordinary English, including incomplete or imperfect wording, and have the assistant own the journey through one of three truthful outcomes:

1. the requested result is authoritatively completed;
2. the user is moved through a required secure or structured application step and the result is verified; or
3. the conversation is transferred to a person with the relevant context preserved.

The assistant owns the journey even when it must not own every input or decision. Passwords, card numbers, CVCs, identity documents, verification codes, medical judgment, exceptional refunds, serious disputes, account ownership transfers, account closure, and 24/7 coordination remain in secure structured or human workflows. The chat can still explain the situation, prepare safe context, open the correct workflow, and continue after it.

The required user experience is:

> Tell LoLo what you need. LoLo checks the right account information, explains it simply, takes you to the exact next step, prepares what it safely can, verifies what happened, and gets a person when judgment is required.

## Audit basis

This plan reconciles the source repository rather than treating previous summaries as implementation proof. The audit covered:

- the 324-row [Family intent and action coverage registry](38-family-intent-action-coverage-registry.md);
- the [app-aware guided-assistance contract](39-app-aware-guided-assistance.md);
- the governed knowledge packages and their evaluation catalogs;
- runtime intent routing, authorized Family context, guided tasks, semantic navigation, request drafting, confirmation, publication, and human handoff;
- Admin knowledge creation, editing, validation, review, publication, pause, versioning, withdrawal, and deletion;
- the Batch 1–3 executable catalog, mass-evaluation corpus, preparation contracts, and complete AI Support feature suite; and
- the mobile and task-first corrections recorded in documents 42 and 43.

The following verification passed on August 18, 2026:

| Verification | Result |
| --- | ---: |
| Complete AI Support feature suite | 156 tests / 2,916 assertions passed |
| Deep runtime intent inventory after Batch 4 | 45 / 45 intents |
| Deep runtime representative phrases | 137 |
| Near-neighbor routing collisions | 10 / 10 protected |
| Provider calls made by the Batch 1-2 plan | 0 |
| Initial KB evaluations | 60 |
| Interactive KB evaluations | 60 |
| Family Operations KB Wave 1 | 50 new entries + 1 corrective revision |
| Family Operations intent mapping | 217 links / 190 unique Family intents |
| Family Operations KB evaluations | 255 |
| Payment/time Batch 4 | 18 entries / 90 evaluations |
| Interactive request/provider cases | 56 |
| Executable Family intent catalog | 324 / 324 |
| Explicit KB mappings after Batch 4 | 197 / 197 |
| Catalog phrase definitions | 1,296 |
| Batch 3 isolated operating-layer assertions | 1,491 |
| Batch 1-4 mass harness | 64 tests / 2,107 assertions |
| Wider payment/time/regular-care regression | 100 tests / 623 assertions |

## Current capability inventory

### Coverage portfolio

The registry contains 324 unique Family intents.

| Current action level | Intents | Meaning |
| --- | ---: | --- |
| Complete | 6 | The assistant has a complete deterministic action path; all six are in the care-request draft/confirmation lifecycle |
| Assisted | 100 | Draft, read, navigate, highlight, or partial guidance exists, but the assistant does not complete the full domain outcome |
| No AI action | 176 | The assistant cannot currently perform or guide the declared action reliably |
| Human transfer | 42 | Human handling is the current terminal behavior |

Information coverage is also incomplete: 86 intents have a governed or otherwise approved explanation, 62 have partial explanation, and 176 have no approved answer.

All 324 intents now have an executable disposition, but the 40-intent Batch 1–2 deep runtime slice remains the verified domain read/guide subset. A valid backlog or human disposition is not a claim that the assistant executes that intent.

### Coverage by Family domain

`C/A/N/H` means complete, assisted, no AI action, and human transfer. `Y/P/N` means yes, partial, and no approved explanation.

| Domain | Intents | Explain Y/P/N | Do C/A/N/H |
| --- | ---: | ---: | ---: |
| Orientation and care-path selection | 17 | 15 / 2 / 0 | 0 / 8 / 3 / 6 |
| Login, identity, account, and security | 20 | 4 / 1 / 15 | 0 / 5 / 13 / 2 |
| Family access and ownership | 20 | 5 / 11 / 4 | 0 / 11 / 6 / 3 |
| Care-receiver profiles | 26 | 5 / 3 / 18 | 0 / 7 / 18 / 1 |
| Care-request lifecycle | 45 | 34 / 8 / 3 | 6 / 30 / 8 / 1 |
| Applicants, messaging, and hiring | 25 | 1 / 2 / 22 | 0 / 2 / 22 / 1 |
| Billing and payment recovery | 32 | 8 / 8 / 16 | 0 / 14 / 16 / 2 |
| Visits, submitted hours, and problems | 35 | 2 / 9 / 24 | 0 / 9 / 23 / 3 |
| Regular care and extra visits | 26 | 3 / 6 / 17 | 0 / 7 / 17 / 2 |
| Continuous Coverage / 24/7 management | 26 | 1 / 1 / 24 | 0 / 0 / 22 / 4 |
| Messages and notifications | 17 | 1 / 2 / 14 | 0 / 3 / 13 / 1 |
| Care history, receipts, and rebooking | 15 | 2 / 3 / 10 | 0 / 3 / 10 / 2 |
| Support, complaints, privacy, and exceptions | 20 | 5 / 6 / 9 | 0 / 1 / 5 / 14 |

### Implemented runtime building blocks

The current implementation already has the correct foundations to expand rather than replace:

- a persistent authenticated support conversation with human takeover and return to automation;
- exact-user Pilot availability and one separate Everyone switch;
- Family/Caregiver role and Family-account authorization checks;
- governed KB storage with immutable published versions and Admin lifecycle controls;
- lexical governed-KB retrieval with role, membership, capability, and route applicability filtering;
- deterministic emergency, medical, 24/7, human-request, payment-method, and nine broad Family state handlers;
- current Family overview across supported requests, visits, submitted hours, profile gaps, unread messages, regular care, and payment attention;
- 27 registered navigation targets, of which 24 are Family-authorized;
- 19 exact highlightable semantic targets and 12 resource-bound targets;
- persistent guided tasks with arrival, missing-target, disabled-target, cancellation, expiry, and human-handoff behavior;
- safe saved-card reading and end-to-end payment-method completion verification through the existing secure Stripe flow;
- authorized Family context for care-request drafting, including ready care profiles, household data, task choices, and optional previous-request reuse;
- one-time and recurring request drafting, one-question-at-a-time completion, recap, 30-minute renewal, explicit confirmation, idempotent publication, and receipt;
- two registered write tools: one-time request publication and recurring request publication;
- a mobile full-screen conversation, latest-message behavior, focus preservation, and a persistent human-help action; and
- compact event, cost, action, and handoff evidence visible through existing support and Admin surfaces.

### Current runtime limitations after Batch 4

The operating layer is implemented, but domain breadth is still incomplete:

- all 324 intents are cataloged, while only the high-confidence handlers and explicitly prepared paths have deep runtime authority;
- known intents use explicit KB mappings, but many rows still lack governed knowledge or authorized live-state readers;
- the model may answer, choose a care path, patch a care-request draft, propose an allowlisted navigation target, or transfer, but it cannot invoke general Family tools;
- only care-request publication has a registered confirmed domain write;
- Batch 2 guidance does not approve hours, accept or reject a visit change, send a message, edit a profile, retry a care payment, or mutate another domain record;
- authoritative completion verification is complete for care-request publication and the saved-payment-method journey, not for every highlighted control;
- the five preparation contracts fill existing forms but deliberately do not save, send, approve, dispute, publish, or submit;
- payment and submitted-hours reads now have a normalized authorized reader, but secure payment/authentication and hours approval remain in the existing UI;
- support for hiring, visit changes, regular care mutations, Family administration, Continuous Coverage, and exceptional outcomes still needs domain-specific readers, targets, verifiers, and narrow tools; and
- source now contains the approved `$30 Family / $27 caregiver / $3 LoLo` truth, but it is not active in production until the joint deployment and exact Batch 4 KB publication.

### Master execution board

| Workstream | Current position | Target | Delivery state |
| --- | --- | --- | --- |
| Intent portfolio | 324 / 324 executable dispositions; 45 deep runtime rows | Every implemented row retains evidence and rollout state | Batch 4 source complete; expand by domain |
| Governed knowledge | Family Operations Wave 1 in production; Batch 4 pack ready in source; 197 explicit catalog mappings | Stable domain packs mapped explicitly to every Explain-capable intent | Batch 4 source complete; production publication deferred |
| Intent resolution | Layered safety, active task, deterministic prep/handlers, catalog classification, bounded fallback | Add high-confidence domain handlers only with authority/evidence | Batch 3 complete |
| Authorized reads | Overview plus normalized payment-failure, payment-amount, submitted-hours, correction, requests, visits, profiles, messages, history, and regular-care state | Narrow normalized readers for every state-dependent supported intent | Batch 4 expanded; continues Batches 5-9 |
| Navigation and guidance | 24 Family destinations; 19 exact highlights | Every guided intent has a resource-authorized target, one-step instruction, and recovery behavior | Foundation complete; coverage expands by domain |
| Preparation/prefill | Five reversible Batch 3 contracts plus care-request chat draft | Add domain contracts only for supported existing forms | Batch 3 complete; expand by domain |
| Confirmed execution | Two request-publication tools | Narrow confirmed tools for appropriate existing domain services | Expands Batches 4-8 |
| Authoritative verification | Generic registry; request receipt and saved payment method proven; unavailable verifier is explicit | Every complete journey has a domain receipt or fresh state verifier | Interface complete; expands by domain |
| Human support | Atomic same-conversation transfer, Admin alert, context summary | Human terminal path for every judgment/exception intent with no repetition | Implemented; refine with domain context |
| Older-adult UI | State-aware start, mobile chat, universal contextual follow-ups, action progress, recovery, and guide highlight | Complete more domain journeys without adding cognitive load | Batch 3 complete; usability expands by domain |
| Evaluation | 324 catalog rows, 197 mappings, 1,296 phrases, 18 Batch 4 entries, 90 Batch 4 KB cases, 45 deep runtime intents, 137 routes, 10 collisions | Per-intent multi-turn, state, browser, and usability coverage | Batch 4 source complete; joint review pending |
| Admin visibility | KB lifecycle, transcripts, Availability, searchable 324-intent catalog, and compact outcome summary | Add useful domain funnel detail without transcript duplication | Batch 3 complete; refine by domain |

## Knowledge-base audit

### Current governed inventory

Family Operations KB Wave 1 has been published in production and its 190 covered intent rows remain mapped. Batch 4 adds 18 source-defined payment/time entries, 90 evaluations, and seven additional unique mappings for a total of 197. The Batch 4 package and pricing truth are not yet deployed or published in production. See [document 47](47-payment-time-recovery-batch-4.md).

| Package | Entries | Current scope |
| --- | ---: | --- |
| Shared support and safety | 3 | Human transfer, emergency/non-medical boundary, English-only support |
| Family orientation | 5 | Dashboard, requests, new request, Family access, Account Settings |
| Caregiver orientation | 4 | Dashboard/onboarding, Work Inbox, visits, Account Settings |
| Interactive Family care requests | 12 | Care-path choice, required request information, 24/7 transfer, safety, draft/confirmation/publication behavior |
| Family Operations Wave 1 | 50 new + 1 revision | Payments, requests/applicants, visits/hours, profiles/access, messages/notifications, regular care, and history |
| Payment/time Batch 4 | 18 new | `$30 / $27 / $3` pricing, payment lifecycle/failures/recovery/history/refunds/disputes, and submitted-hours/correction state |

`KB-B4-PRICE-001` is the current approved support definition: `$30/hour` paid by the Family, `$27/hour` earned by the caregiver, and `$3/hour` received by LoLo. The runtime supports deterministic, publication-gated quotation and explicit-duration calculations. Batch 4 deliberately does not alter current payment code; that implementation reconciliation remains a separate product task.

### Knowledge gaps

The KB lifecycle and Admin UI are implemented; the missing work is breadth and mapping. There is little or no governed day-to-day content for:

- login recovery, verification failures, account security, and personal-account consequences;
- Family membership, invitations, permissions, removal, leaving, and ownership edge cases;
- care-profile fields, sharing visibility, readiness, archiving, and current-care effects;
- live request changes, withdrawal, duplication, expiry, and restoration behavior;
- applicant comparison, invitations, rejection, messaging, hiring prerequisites, and failures;
- payment behaviors outside the Batch 4 lifecycle/recovery definitions and exceptional financial decisions;
- visit changes, cancellation, no-show, check-in/out, and visit behaviors outside submitted-hours/correction coverage;
- regular-care offers, counters, extra visits, skipping, schedule changes, pause, resume, and ending;
- messages, notification state, notification preferences, and communication recovery;
- care-history totals, records, receipts, downloads, and rebooking; and
- complaints, privacy requests, safety reports, unavailable product behavior, and human-only exceptional handling.

New knowledge must be authored as stable product facts or task playbooks, not one long article and not one entry per wording variant. One entry may support several intent IDs. Every covered intent maps explicitly to one or more KB stable IDs in the executable intent catalog.

Family Operations Wave 1 and Batch 4 together map 197 unique Family intents. Remaining gaps are login/security, care-profile and request mutations, hiring, visit and regular-care actions, Continuous Coverage, complaints/privacy, exceptional handling, and deeper domain edge cases. Final breadth remains determined by distinct truths, permissions, failure behavior, and review cadence rather than a quota.

## Definition of an AI-owned journey

An intent is not “covered” merely because the assistant recognizes it or opens a page. Every intent receives an explicit disposition and one or more supported capability stages:

| Stage | Proof required |
| --- | --- |
| Understand | Representative natural wording, typos, and follow-ups resolve to the intended intent without harmful collisions |
| Explain | Approved KB truth applies to the exact role and state; unsupported facts are not inferred |
| Read | A narrow authorized reader returns normalized current state without exposing raw records or provider errors |
| Navigate | The server issues an authorized semantic destination, never a model-provided URL or selector |
| Guide | The page verifies the destination, scrolls to and accessibly highlights the exact control, and explains one step |
| Prepare | Allowlisted non-secret values are prefilled or drafted and remain visible, editable, and reversible |
| Execute | A narrow existing domain service performs the explicitly confirmed action with current authorization and idempotency where required |
| Verify | A domain receipt or fresh authoritative read proves the result before the assistant says it is complete |
| Recover | Stale, disabled, missing, conflicting, denied, and failed states produce the next truthful action without repetition |
| Human | Transfer is atomic, automation stops, and the human receives the relevant authorized context without asking the user to repeat it |

An AI-owned journey reaches **Verify**, a verified secure-UI result, or **Human**. A journey that stops at a generic answer or an unverified highlighted button is assisted but not complete.

## Older-adult experience contract

Every new journey must follow these rules:

- Use ordinary words and short sentences; never expose internal status names or IDs.
- Ask one question at a time.
- Prefer large choice buttons over requiring structured typing.
- Show one primary action and at most one secondary alternative.
- Label an action by its result: **Update payment method**, **Review Maria's hours**, or **Open James's profile**.
- State where the user is going before navigation; never surprise the user with a page jump.
- Keep the conversation, typed draft, active task, and completion state across allowed navigation and refresh.
- On arrival, scroll to, focus, and highlight one exact control without clicking a consequential action automatically.
- Never ask for information the authorized application already knows unless the user must verify freshness or consent.
- After a repeated or rephrased request, do not send substantially the same instructions again. Offer the action, ask the one missing question, diagnose the blocked state, or offer a person.
- Understand short contextual replies such as “yes,” “that one,” “I am the owner,” “take me there,” “I did it,” “it is not there,” and “go back” from the active task.
- Never say “done,” “saved,” “paid,” “approved,” “sent,” or equivalent without authoritative proof.
- Always provide **Talk to a person** and preserve context through the transfer.

## Target operating architecture

### 1. Executable Family intent catalog

Move the 324-row portfolio into a machine-readable catalog while keeping the Markdown registry as the human-readable view. Every record declares:

- intent ID, domain, priority, and supported role/account states;
- normal, older-adult, typo, and contextual-follow-up phrases;
- current and target capability stages;
- KB stable IDs;
- state reader and normalized result schema;
- navigation and semantic target IDs;
- optional prefill contract;
- optional tool and confirmation policy;
- completion verifier;
- human-only and never-in-chat rules;
- evaluation IDs; and
- release state: backlog, building, pilot, or released.

The application and tests should reject an implemented intent that lacks this traceability. Unknown or product-gap intents retain a truthful disposition instead of being silently treated as supported.

### 2. Hybrid intent resolution

Use a low-cost layered router:

1. deterministic safety and explicit high-confidence task handlers;
2. active-task contextual follow-up resolution;
3. bounded model classification against only the applicable Family intent subset when wording remains ambiguous; and
4. one clarifying question or human transfer when confidence is insufficient.

Known intent IDs select mapped KB, readers, and tools directly. Lexical KB search remains a supplementary discovery mechanism, not the only way product truth is selected.

### 3. State-aware assistant home

When the chat opens, provide a small personalized start card using existing authorized readers:

- **See what needs my attention**;
- **Create a care request**;
- **Check my next visit**;
- **Payment help**;
- **Something else**; and
- **Talk to a person**.

If current state requires action, the first choice may name it, such as **Review Maria's submitted hours**. Show no more than three personalized primary suggestions before the general choices.

### 4. Universal guided-task coordinator

Generalize the current payment and Batch 2 guided-task implementation so every journey can retain:

- intent and goal;
- selected authorized resource;
- current step and expected page target;
- one primary and one secondary action;
- optional prefill reference;
- expected completion signal and verifier;
- last failure/recovery result;
- expiry and resume behavior; and
- human-transfer summary.

The active task, not a loose review of the transcript, resolves short follow-ups and prevents repetition loops.

### 5. Versioned prefill contracts

Each supported form declares exact fields the assistant may prepare. All values are visible and editable. Prefill is reversible and does not count as submission or confirmation. Passwords, card data, CVCs, bank credentials, verification codes, tokens, identity documents, and provider secrets are never prefill fields.

### 6. Narrow confirmed tools and verifiers

Tools wrap existing application domain services. They do not reproduce business logic in the assistant. Each tool declares authorization, inputs, recap material, confirmation level, idempotency, expected receipt, failure codes, and rollback or recovery behavior.

The model never constructs or directly invokes an unrestricted database mutation. It selects an allowed intent; the server constructs the exact tool proposal and validates it again at confirmation.

## Delivery roadmap

### Completed foundation — Batches 0 through 3

- governed KB lifecycle and Admin control plane;
- Pilot/Everyone/Emergency-stop availability;
- Family request intake, drafting, recap, confirmed publication, and 24/7 transfer;
- saved-payment-method read, secure guidance, and verified completion;
- Family overview and read/guide coverage for requests, applicants, visits, submitted hours, payment attention, profiles, messages, history, and regular care;
- mobile chat polish and task-first payment correction; and
- the 40-intent deep runtime harness plus runtime safety regression;
- the executable 324-intent catalog and 190 explicit Wave 1 mappings;
- state-aware Family start choices and universal active-task continuation/recovery;
- generic authoritative verifier and content-free intent telemetry contracts;
- searchable Admin intent coverage and outcome reporting; and
- five reversible preparation contracts for profile, request reuse, caregiver message, submitted-hours correction/dispute, and support intake.

### Batch 3A — Family operating layer

Implementation status: Complete in source on August 18, 2026. Production availability is unchanged.

Build the reusable behavior required by every later vertical:

1. executable 324-intent catalog with the 40 implemented rows mapped to current evidence;
2. state-aware assistant start card and personalized next actions;
3. universal active-task follow-up handling;
4. repetition/confusion detection and recovery;
5. reusable one-step action cards, progress, resume, stop, check-again, and human controls;
6. generic completion-verifier interface and truthful unverified-result behavior;
7. intent-level events for recognized, action-offered, opened, arrived, prepared, confirmed, completed, abandoned, failed, and transferred; and
8. an Admin coverage view or generated report showing capability stage and recent unmatched/recovery outcomes by intent.

Acceptance requires the existing 40 intents and payment journey to use the common task behavior without regression. A user who says “yes,” “take me there,” “I did it,” or “I cannot find it” after any supported guide receives context-aware continuation rather than a repeated generic answer.

### Batch 3B — Safe preparation

Implementation status: Complete in source on August 18, 2026. Final save/send/submission remains in the existing UI.

Add reversible prefill and draft contracts for:

1. care-receiver profile creation and updates;
2. request duplication/reuse and supported edit fields;
3. caregiver-message drafts;
4. submitted-hours correction or dispute preparation; and
5. structured support intake and human-transfer context.

The normal application owns save/send/submission unless a later confirmed tool explicitly promotes the action. Live request editing remains limited to behavior the product currently defines; unresolved versioning and notification consequences are not guessed.

### Batch 4 — Payments, submitted hours, and recovery

Make the assistant able to explain and recover the highest-anxiety money and time situations:

- authorization hold versus capture;
- request publication versus later payment authorization;
- normalized payment-failure and action-required states;
- submitted-hours totals and differences;
- correction and extra-visit payment attention;
- payment history, receipts, refunds visible to the Family, and unfamiliar-charge triage;
- exact secure retry/authentication controls; and
- authoritative verification after recovery.

Implemented in source on August 18, 2026. This batch includes the 18-entry payment/time KB pack, 90 linked KB evaluations, the `$30 / $27 / $3` product truth, deterministic publication-gated price calculations, normalized failure and submitted-hours readers, exact resource-authorized recovery targets, and existing reversible dispute/correction preparation. Secure payment/authentication and consequential hours/payment actions remain in the normal UI. Production deployment and publication are deferred to the joint Batch 3/4 review.

### Batch 5 — Care profiles and request lifecycle

Complete the main preparation journey:

- create, finish, edit, review visibility, choose default, archive, and restore care profiles;
- select one-time, recurring, or human-led 24/7 care;
- create, reuse, duplicate, withdraw, and—after semantics are defined—edit a live request;
- explain request status and why a request is blocked or expired; and
- verify every save, publication, withdrawal, and restoration result.

### Batch 6 — Applicants, messaging, and hiring

Add authorized applicant comparison and next actions:

- summarize and compare only Family-visible caregiver facts;
- open the exact applicant or conversation;
- prepare and send caregiver invitations and messages;
- reject or mark an applicant as unsuitable;
- explain prerequisites or failures; and
- hire through a strong recap and explicit confirmation using the existing authoritative booking/payment service.

The assistant never promises availability or makes a subjective safety/clinical decision for the Family.

### Batch 7 — Visits and regular care

Cover the ongoing-care lifecycle:

- next/current visit details and change requests;
- cancellation, late-cancellation explanation, no-show reporting, and visit problems;
- submitted hours, corrections, approval, payment, and review;
- rebooking the same caregiver;
- regular-care offers, counters, schedule changes, extra visits, skip, pause, resume, and end; and
- receipts and verified after-action state.

Financial or schedule-changing actions require a material recap. Serious safety, record disputes, or exceptional payment cases transfer with prepared context.

### Batch 8 — Family administration, communication, and records

Cover the remaining ordinary account work:

- Family invitations, resend/cancel, member removal, leaving, and permissions;
- personal profile, verification, password-navigation, and account-security recovery;
- notifications, preferences, and read state;
- care history, totals, records, printing/download where the product supports it; and
- privacy/support request preparation.

Signed-out login and password-recovery help needs an intentionally public or pre-authenticated support surface. The authenticated chat cannot by itself solve being unable to sign in.

### Batch 9 — Continuous Coverage and exceptional outcomes

The chat remains the front door for 24/7 and exceptional cases even when a person owns execution:

- recognize 24/7 needs without treating them as emergencies;
- collect only immediately useful confirmed context without delaying transfer;
- preserve the full conversation and structured summary;
- show no queue position, wait time, or availability promise;
- let the user continue in the same conversation; and
- distinguish a successful context-preserving transfer from an unresolved care outcome.

Continuous Coverage management remains out of general AI execution until the currently flagged product workflow is deliberately released and given separate capability contracts.

### Batch 10 — Caregiver adaptation

After the Family operating layer is stable, reuse the platform with a completely separate Caregiver catalog, KB applicability, readers, targets, prefill contracts, tools, and evaluations. Initial Caregiver priorities are onboarding/profile readiness, Work Inbox, invitations/applications, upcoming visits, submitted hours, earnings/payout setup, messages, and human support. Family-only data and actions never become Caregiver capabilities by reuse or prompt instruction.

## Knowledge delivery plan

Build and publish knowledge alongside the corresponding capability batch:

| KB pack | Primary registry domains | Planned batch |
| --- | --- | ---: |
| Account and authentication | Account/security | 8 |
| Family access and permissions | Family access/ownership | 8 |
| Care profiles and visibility | Care-receiver profiles | 3B and 5 |
| Live request lifecycle | Care requests | 3B and 5 |
| Applicants, invitations, messages, and hiring | Matching/hiring | 6 |
| Billing, authorization, capture, recovery, and receipts | Payments | 4 |
| Visits, submitted hours, corrections, and problems | Visits/timesheets | 4 and 7 |
| Regular care and extra visits | Regular care | 7 |
| Notifications, history, and records | Communications/history | 8 |
| Complaints, privacy, security, and exceptional support | General support | 8 and 9 |
| 24/7 intake and human coordination | Continuous Coverage | 9 |

For each pack:

1. resolve contradictory or undefined product behavior first;
2. author normal behavior, permissions, state meanings, recovery, and escalation;
3. link every entry to the intent IDs it supports in the executable catalog;
4. add match, close-neighbor, wrong-role/account, stale-state, and handoff cases;
5. validate and publish through the existing one-operator Admin lifecycle; and
6. measure retrieval misses and repeated user questions after pilot use.

## Product decisions that block truthful coverage

The repository already identifies 22 intents whose product behavior is absent, partial, or deliberately held. The important unresolved groups are:

- phone number and multi-factor authentication availability;
- personal data export, Family Account closure, ownership transfer, and member-level restrictions;
- permanent care-profile deletion;
- live request editing/versioning, request-type conversion, and reopening withdrawn requests;
- caregiver blocking;
- card removal, invoices/tax documents, promos, billing ownership, and unfamiliar-card authorization;
- pricing, fees, and payment timing reconciliation;
- editing/removing a submitted review;
- replacing a regular caregiver;
- AI reminders; and
- history download/print behavior.

Until Product defines an item, its catalog disposition is **Explain unavailable behavior**, **Guide to the currently valid alternative**, or **Human**. The model must not fill the gap with a plausible answer.

## Evaluation and test program

### Fast tests on every implementation change

- executable-catalog schema and traceability;
- intent routing for every changed row;
- active-task follow-ups and nearby collisions;
- KB applicability and prohibited inference;
- reader authorization, state normalization, and cross-account denial;
- registered target and semantic marker;
- no-domain-mutation proof for read/guide/prefill paths;
- confirmed-tool validation, stale state, idempotency, and receipt;
- completion-verifier success, failure, and unavailable state;
- human takeover race and automation suppression; and
- mobile component and focus regressions.

### Per-intent corpus

Every implemented intent has at least:

- three ordinary phrasings;
- one imperfect, vague, or typo-heavy phrasing for priority intents;
- one contextual follow-up where applicable;
- one close-neighbor collision;
- one wrong-role/account or denied-state case;
- one stale, unavailable, or failure-state case; and
- one human-transfer case when applicable.

Priority journeys should have broader older-adult phrasing rather than weakening expected outcomes to make the router pass.

### Multi-turn outcome scenarios

Test complete journeys, not only classification:

1. user describes the goal;
2. assistant selects or clarifies the intent;
3. authorized state is read;
4. the correct action is offered;
5. navigation reaches the exact page/control;
6. safe values are prepared;
7. confirmation is requested when required;
8. the normal application service succeeds or returns a defined failure;
9. the assistant verifies the result; and
10. the next valid step or human transfer is offered.

Every scenario also receives refresh/resume, back navigation, missing target, double-click, stale tab, expired task, human takeover, and mobile variants in proportion to risk.

### Browser and usability testing

- narrow mobile viewport and desktop;
- keyboard-only operation;
- 200 percent text and reflow;
- reduced motion;
- accessible names, focus, announcements, and highlights;
- real screen-reader use on the highest-value journeys;
- slow network, background refresh, and navigation return;
- actual pilot accounts using ordinary production-like state; and
- short sessions with older adults who have not been taught the app.

The key usability question is: **Could the user finish without knowing where the feature lives?**

## Rollout model

Keep the simplified operating model:

1. build and test locally/CI;
2. deploy through `deploy.sh`;
3. leave availability on **Pilot**;
4. exercise the changed journeys with the two existing Family pilot users;
5. review conversations, repeated-answer loops, abandoned tasks, wrong destinations, and human transfers;
6. correct failures and add their exact wording as regression cases; and
7. use the existing **Everyone** switch only when Product deliberately chooses general availability.

No new branch, PR workflow, shadow mode, evidence ceremony, or multi-person publication rule is introduced by this plan. Safety comes from narrow deterministic contracts, tests, pilot observation, authoritative verification, and the existing emergency stop.

## Success and cost measures

Track outcomes per intent and journey rather than total message volume:

- intent recognized or clarified;
- correct state read;
- action offered and selected;
- target arrival and highlight;
- prefill accepted or edited;
- confirmed action completed;
- authoritative verification succeeded;
- user abandoned or repeated the request;
- recovery succeeded;
- human transfer reason; and
- model calls, tokens, latency, and cost per completed journey.

Non-negotiable measures are:

- zero cross-account or wrong-role disclosure;
- zero domain write without the declared authorization and confirmation;
- zero unverified completion claim;
- 100 percent human-transfer availability; and
- 100 percent critical emergency/medical boundary cases passing.

Product targets for supported scripted journeys are at least 95 percent end-to-end completion, with every critical safety and authorization case passing. The two-user pilot is too small for a meaningful percentage gate; every repeated loop, wrong destination, false statement, or blocked core journey becomes a correction and regression case.

Keep serving cost low by using the model once for a genuinely new or ambiguous goal, then using deterministic readers, intent mappings, templates, navigation, prefill, tools, and verifiers. Do not call the model to poll a page or rephrase a known task state. Target an average model cost below $0.01 per completed assisted journey and report any deterministic journey that unexpectedly calls the provider.

## Immediate implementation recommendation

Batch 4 is implemented in source. The immediate next step is the **joint Batch 3/4 review and controlled deployment** described in [document 47](47-payment-time-recovery-batch-4.md): run the combined regression, deploy once through `deploy.sh`, publish the exact Batch 4 KB package, test both pilot accounts, and keep **Live for everyone** off.

After that joint review, the next build is **Batch 5 — Care profiles and request lifecycle**. The Batch 3 implementation record is [document 46](46-family-operating-layer-batch-3.md).

## Maintenance rule

This document is the delivery authority; the 324-row registry remains the intent inventory. For every capability change:

1. update the executable intent record;
2. update the human-readable registry row;
3. add or revise linked KB truth;
4. implement only the declared reader, target, prefill, tool, verifier, or human path;
5. add outcome-level tests;
6. record actual release state; and
7. update the coverage counts.

Do not mark an intent complete because a model produced a good answer once. Mark it complete only when its declared terminal result is repeatable, authorized, and verified.
