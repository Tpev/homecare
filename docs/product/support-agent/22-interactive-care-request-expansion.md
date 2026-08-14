# Interactive Support and Care-Request Expansion

Status: Active product contract; implementation not yet authorized

Last updated: August 14, 2026

Owner: Product

## Purpose

The intelligent support experience is expanding beyond answers and navigation. Its intended Family outcome is a role-aware conversational interface that can understand a care need, help the user choose the correct request path, reuse authorized account information, prepare a structured request, show a deterministic recap, and publish the confirmed request through normal LoLo domain services.

This document records the accepted product decisions from the August 14 discussion. It is normative for future capability specifications and evaluations. It does not enable production AI, grant a user access, publish KB content, or authorize implementation changes to pricing, payments, Stripe, or caregiver payouts.

## Product boundary

The assistant remains one controlled product layer, not a general autonomous website operator.

- The model may understand ordinary English, identify missing information, recommend a request path, and explain its recommendation.
- The user explicitly chooses the request path before request drafting begins.
- The server owns identity, authorization, current account state, field validation, pricing results, confirmation references, idempotency, publication, and receipts.
- The assistant may use only approved role-scoped tools and semantic destinations.
- Family/care-receiver and Caregiver experiences remain separate. A Family user cannot receive Caregiver-only data or operations, and a Caregiver cannot receive Family-only data or operations.
- Human transfer remains available throughout. Once transferred, automation stops under `DEC-008`.

The long-term aspiration is that users can complete most ordinary LoLo tasks through the conversation. Each read or write capability must still be separately specified, authorized, evaluated, feature-controlled, and released. This aspiration does not grant blanket tool access.

## Supported care pathways

| User need | Assistant behavior | Result |
| --- | --- | --- |
| One-time care | Recommend when appropriate, obtain the user's explicit choice, collect the minimum fields, show the recap, and publish only after the action-specific confirmation | Open marketplace request |
| Regular/recurring care | Recommend when appropriate, obtain the user's explicit choice, collect the recurring schedule and minimum fields, show the recap, and publish only after the action-specific confirmation | Open marketplace request |
| 24/7 coverage | Explain that LoLo needs a person to arrange this and transfer the conversation to human support | No AI-created request |
| Immediate danger or emergency | Show the approved emergency limitation/instruction before transfer | No AI-created request |

Mentioning 24/7 coverage is not by itself treated as an emergency. Emergency handling is triggered only by immediate-danger language or another approved safety condition.

## Request-type recommendation

The assistant may ask concise questions and recommend one-time care, regular/recurring care, or human-assisted 24/7 coverage. It must:

1. explain the recommendation in plain English;
2. present the relevant choices;
3. require the user to choose before filling a request draft;
4. avoid silently selecting a type from ambiguous language; and
5. preserve the user's ability to talk to a person.

The recommendation is not a clinical recommendation and must never classify medical care as an ordinary LoLo request.

## Conversational request contract

There is one progressive conversational flow. Users are not shown the current internal **Fast Track** versus **Complete Setup** modes.

The assistant captures every usable detail already supplied, asks only for missing material information, and does not repeat a question whose answer is already known. It may parse several details from one message. Profile-derived or inferred material values are visible in the final recap and remain modifiable.

### Minimum common information

- Who needs care: the requester or an authorized saved care-receiver profile
- At least one approved non-medical care task
- Service address
- Request type explicitly selected by the user

### One-time schedule

- Future service date
- Start time
- Duration, from which the server derives the end time

### Regular/recurring schedule

- One or more weekdays
- Start time and duration for each selected day; different days may have different schedules
- First service date
- Ongoing or a stated end date

### Deterministic defaults and generated fields

- Use the Family Account's saved preferred caregiver-response window when available; otherwise default to 12 hours.
- Show that response window in the final recap with a way to modify it. Do not make the user answer it as a routine conversational question.
- Use the current fast-track domain mode internally.
- Generate a concise title and scope from confirmed request information.

### Optional information

Ask for recipient details, mobility context, task notes, home-access instructions, additional care notes, or a third-party contact only when the user's need makes the information relevant or the user volunteers it. Do not ask filler questions to improve an internal score.

## Authorized Family context

For the authenticated Family account only, approved tools may retrieve and reuse:

- the user's own Family Account and authorized care-receiver profiles;
- saved service addresses and contact information;
- existing and previous care requests;
- upcoming and completed visits;
- care preferences and instructions; and
- relevant non-secret account information.

The assistant may reuse a previous request only when the user explicitly asks for that behavior, such as **same as last time**. It must confirm important reused values before publication. Payment credentials, authentication secrets, another family's records, Caregiver-only private data, and unrelated support-ticket content are excluded.

Every tool must enforce Family-account authorization server-side. Model-supplied record or account identifiers confer no access.

## Recap, confirmation, and publication

When all required information is valid, the server produces a deterministic recap. It must clearly show:

- one-time or regular/recurring request type;
- who needs care;
- tasks and relevant notes;
- explicit schedule, duration, and timezone;
- full service address;
- the requested caregiver-response window;
- the authoritative customer price and applicable total calculation when pricing is enabled; and
- what happens next: the request will go live and eligible caregivers can see it, but no caregiver is hired yet.

The two primary controls are:

- **Modify something**
- **Confirm and create request**

Confirmation publishes the request directly to the marketplace. It does not enter human review. Conversational assent such as **yes**, **okay**, or **looks good** is not the initial commit mechanism.

The server must bind confirmation to the authenticated actor, Family account, draft version, material-field hash, expiration, capability version, and idempotency key. It revalidates everything at commit time. A failed or uncertain commit never produces a false success message; the draft remains available for correction or reconciliation.

### Recap modification contract

Selecting **Modify something** keeps the request private and opens clear section-level controls:

- **Who needs care**
- **Help needed**
- **Schedule**
- **Address**
- **Additional instructions**
- **Caregiver response time**

Each section has a visible **Change** control. The user may alternatively describe a change in ordinary English. The assistant updates only the intended fields, asks for any newly required information, and then presents the complete fresh recap rather than only the changed fragment.

Every material change increments the draft version and immediately invalidates the earlier confirmation reference. Changing request type also clears or remaps incompatible schedule fields rather than silently retaining them. Publication requires a newly issued confirmation bound to the updated recap.

### Confirmation validity and easy renewal

A server-issued request-publication confirmation remains valid for 30 minutes after the recap is generated. It becomes invalid earlier if a material draft field changes, the conversation transfers to human support, the user logs out, Family authorization changes, the exact-user pilot grant is revoked or expires, or the capability/tool is disabled.

Expiration never discards the seven-day saved draft and never forces the user to answer the intake questions again. Replace the expired action with one clear primary control:

> **Review and confirm again**

One activation reloads the current saved draft, re-resolves authoritative profile/task data, revalidates authorization and every field, and displays a fresh complete recap with a new 30-minute confirmation. Do not make the user find the old conversation turn or manually reload the page. If authoritative data changed, identify the affected section in plain English and take the user directly to that correction; preserve every still-valid field.

## Draft persistence and resumption

The structured conversational draft is private and reversible:

- Autosave each valid normalized field as the conversation progresses.
- Keep the draft private; saving it must never publish it, notify a Caregiver, or make it visible in the marketplace.
- Allow the authorized user to resume it for seven calendar days after its last valid update.
- Automatically delete the draft content after that seven-day inactivity window, subject only to an approved legal/security hold.
- Provide a clear **Discard this draft** control that deletes the draft immediately and invalidates any related recap or confirmation.
- If the user starts what appears to be a different request while a draft exists, ask whether to resume the saved request or discard it and start again. Do not silently merge unrelated requests.
- Store draft version and field provenance so concurrent sessions and later corrections cannot overwrite newer answers silently.

The canonical support transcript follows its separately approved retention policy. The seven-day draft is a structured working record and must not be kept longer merely because some of its values also appear in the conversation.

## Pricing and payment product truth

The accepted business truth for future assistant behavior is:

- All care on the platform costs the customer **$30 per hour**.
- The $30 hourly customer price is complete. Do not add a platform fee, tax, holiday fee, mileage fee, or other surcharge in the assistant's quote.
- The assistant may calculate estimated totals deterministically from $30 multiplied by the confirmed duration.
- LoLo's 10% commission is retained from within the $30 customer price.
- The actual Stripe processing fee is passed down to and deducted from the Caregiver payout.
- Conceptually, for one hour: customer price $30; LoLo commission $3; Caregiver payout is $27 less the actual Stripe processing fee.

Payment authorization timing remains the same as the ordinary application behavior: publishing an open request does not authorize payment. Authorization occurs when a Caregiver is hired and the booking is created. Capture occurs later through the existing completed/approved-hours workflow.

### Mandatory implementation hold

Read-only inspection found that current payment implementation and configuration can add a 10% platform fee above the $30 rate and use a separate authorization buffer. That behavior conflicts with the accepted product truth above. The user explicitly directed that pricing, payment, Stripe, payout, buffer, and account-override code must not be changed as part of this specification work.

Therefore:

- no pricing or payment code changes are in scope now;
- the assistant must not activate customer price promises or totals until the authoritative pricing/payment service is reconciled in a separately approved project; and
- current code is evidence of present behavior, not authority to contradict this accepted product decision.

## Publication notifications and operational effects

An assistant-created request follows the same externally visible publication path as the ordinary request form:

- create an open request that eligible Caregivers can discover;
- send the existing new-care-request alert to LoLo operations;
- record the existing `care_request_published` funnel event; and
- take the Family user to the authoritative request page after the success receipt.

Do not add a mass Caregiver notification or give users or Caregivers a separate **AI request** treatment. Add only restricted internal provenance that records `origin: ai_support` and links the support conversation, action evidence, confirmation, idempotency reference, and authoritative receipt. Internal provenance must not expose conversation content in marketplace views.

Failure of the operations email after the request commits does not make request creation fail or permit a duplicate retry. Report creation truthfully, record the notification failure, and use the normal retry/alert path.

## Release path without shadow mode

Product has explicitly rejected the production-conversation shadow phase. Phase 2 shadowing is skipped, not deferred.

The replacement evidence path is:

1. deterministic unit, integration, authorization, confirmation, and idempotency tests;
2. frozen offline conversation and safety evaluations;
3. staff-operated test accounts in a production-like environment;
4. older-adult mobile and accessibility usability checks;
5. a tiny exact-user pilot enabled through the existing admin grant control;
6. live monitoring, full interaction visibility in Admin, immediate human transfer, and kill switches; and
7. gradual expansion only after the named-pilot gates pass.

Skipping shadow mode must not weaken deny-by-default access, production-data retention decisions, role isolation, confirmation controls, human takeover, or rollback readiness.

## Current ordinary-form evidence

The current request wizard supports the accepted minimum contract:

- `request_type` accepts one-time or recurring;
- at least one approved `CareTask` is required;
- address line 1, city, state, and ZIP are required;
- one-time care requires date, time, duration, and a future normalized range;
- recurring care requires weekdays, a per-day schedule, a start date, and ongoing/end-date choice;
- `preferred_response_hours` is required from 1 through 72 and currently defaults to 12;
- optional recipient, mobility, notes, access, and third-party fields already exist; and
- publication creates an open request.

This evidence is used only to align the new contract with the maintained domain workflow. No legacy AI copilot behavior is reused.

## Required capability split

Engineering must not implement this as one unconstrained tool. Create separate capability specifications and release controls for:

1. care-path recommendation and explicit selection;
2. authorized Family context retrieval;
3. shared one-time/recurring request draft preparation with request-type-specific controls;
4. deterministic validation, recap, modification, and confirmation renewal;
5. confirmed idempotent publication and authoritative receipt with separate one-time/recurring commit controls; and
6. 24/7 coverage transfer.

The Caregiver interactive task roadmap remains in scope but is separate from this Family request-creation package.

## Product decision status

No product interview remains open for the declared initial build. `DEC-057` closes 24/7 context and the human promise; `DEC-063` closes pilot size/order; `DEC-064` through `DEC-066` close abort, quality, cost, and rollback gates.

Pricing-service reconciliation remains a separate future project. It does not block building the assistant with pricing disabled, but it blocks activating customer pricing answers and totals. The complete implementation authority is [the approved build contract](23-interactive-assistant-approved-build-contract.md).
