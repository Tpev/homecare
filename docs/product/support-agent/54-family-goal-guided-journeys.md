# Family Goal-Guided Journeys — Batch 10

Status: Approved implementation contract; Family only; Caregiver AI deferred; production availability remains Pilot only

Approved: August 20, 2026

Owner: Product and Engineering

## 1. Outcome

LoLo Support must move from answering isolated intents to owning an authenticated Family user's ordinary goal from the first vague request through one truthful ending:

1. the result is completed and verified;
2. the user completes a required secure application step while the assistant navigates, highlights, resumes, and verifies; or
3. the same conversation transfers to a person with the useful authorized context preserved.

The primary Batch 10 journey is care selection and request creation. A Family user may say only **“My mother needs some help”**. The assistant determines what is known, asks the minimum simple questions, recommends one-time or regular care, lets the user choose, completes the request draft with them, resolves ordinary prerequisites, shows a recap, and publishes only after explicit confirmation.

The assistant owns the continuity of the goal. It does not receive unrestricted control of the database, browser, DOM, routes, or application.

## 2. Scope and rollout boundary

This specification applies only to authenticated English-language Family users.

- Caregiver AI support is explicitly deferred and is not part of Batch 10.
- Batches 6 through 9 are the source-complete prerequisite. Batch 10 source implementation may proceed now, but Batches 6–9 must be deployed, published, activated, and browser-audited for the same two Family pilot users before Batch 10 is activated in production.
- Batch 10 uses the same two-user Pilot boundary. Implementation, migration, KB publication, or deployment must not enable **Live for everyone**.
- Existing human support remains available from every journey.
- Existing ordinary forms remain usable without the assistant.
- No runtime model change is authorized by this specification.
- No Stripe pricing, fee, authorization, capture, payout, refund, or application payment-policy change is authorized.

## 3. Starting position

### Production-verified baseline

Batches 1 through 5 are deployed and production-verified for the exact two-user pilot:

| Measure | Live baseline |
| --- | ---: |
| Original Family intents inventoried | 324 / 324 |
| Explicit KB mappings | 230 / 324 |
| Approved Explain stages | 182 / 324 |
| Execute plus Verify | 23 / 324 |
| Assisted but not completed | 120 / 324 |
| Human terminal path | 61 / 324 |
| No operational path | 120 / 324 |

### Current source baseline after Batch 9

| Measure | Source baseline |
| --- | ---: |
| Original Family intents inventoried | 324 / 324 |
| Explicit KB mappings | 324 / 324 |
| Approved Explain stages | 318 / 324 |
| Execute plus Verify | 74 / 324 |
| Assisted but not completed | 152 / 324 |
| Human terminal path | 87 / 324 |
| No operational path | 11 / 324 |

The Batch 9 source validates 324 catalog records, 1,296 registered phrases, and all explicit KB mappings. The complete AI Support suite passes 219 tests with 5,708 assertions; the isolated Family Batch 1–9 harness passes 127 tests with 4,899 assertions. Those results prove deterministic contracts, not production browser behavior for Batches 6 through 9.

### Foundations to extend, not replace

The repository already contains:

- `FamilyIntentResolver` and the 324-intent `FamilyIntentCatalog`;
- `FamilyIntentJourneyService` and deterministic domain handlers;
- purpose-built Family readers and the Family overview;
- semantic destinations, guided tasks, arrival/focus/highlight behavior, and completion verifiers;
- reversible preparations and recap-confirmed, idempotent actions;
- care-path recommendation and explicit selection;
- one-time and recurring request drafts, recap, publication, and receipts;
- care-profile, applicant/hiring, visit/hours, regular-care, account/access, notification, and history operating layers;
- same-conversation human takeover; and
- mobile chat behavior, persistent conversation state, and Admin transcript/action visibility.

Batch 10 must compose these foundations into journeys. It must not create a second AI runtime or a generic model-controlled action system.

## 4. Product principles

1. **Start from the user's goal.** The user does not need to know a page name, feature name, request type, or internal status.
2. **One simple next step.** Ask one material question at a time and present the most useful next action prominently.
3. **Use known information.** Read authorized current state and reuse confirmed information rather than asking again.
4. **Explain why.** Recommendations and blockers use short plain-English reasons.
5. **Keep the goal alive across pages.** Navigation is a continuation of the conversation, not the end of assistance.
6. **Verify before saying done.** A click, page arrival, model sentence, or user saying “done” is not completion evidence.
7. **Let the user change course.** Every recommendation, prepared value, and recap remains reviewable and modifiable.
8. **Transfer without repetition.** Human ownership preserves the conversation and useful authorized journey state.
9. **Fail truthfully.** When current state cannot be read or verified, say so and offer the safe page or a person.
10. **Keep the model cheap.** Use the model for genuinely ambiguous language; use deterministic state, templates, tools, and verification for the rest.

## 5. Persistent Family journey contract

There may be one active goal journey per automated support conversation. A journey records or derives:

- a stable journey type and version;
- the authenticated actor and Family Account binding;
- the user's plain-language goal;
- relevant authorized resource references;
- completed, current, and remaining steps;
- normalized confirmed facts and their provenance;
- the current requested action or question;
- linked preparation, guided-task, recap, confirmation, receipt, and handoff references;
- last authoritative state/version and verification result; and
- expiry, completion, cancellation, or transfer state.

Required states are conceptually:

```text
discovering -> awaiting_choice -> collecting -> ready
            -> awaiting_secure_ui -> awaiting_confirmation
            -> executing -> verifying -> completed

Any active state -> blocked | cancelled | expired | transferred
```

The implementation may reuse the existing conversation actions, preparations, drafts, and guided tasks instead of adding a new table. Add persistent schema only if the existing records cannot safely restore the goal and current step after refresh/navigation.

### Goal continuity

- Refresh and ordinary in-app navigation retain the active journey.
- Logout invalidates pending confirmation but may allow the safe journey to resume after reauthentication and reauthorization.
- Human takeover stops automation immediately.
- If a user introduces a clearly different goal, do not merge unrelated state. Offer **Continue what we were doing** or **Start the new task**.
- If the relevant record changes elsewhere, rebuild the step or recap from fresh state.
- Expired 30-minute confirmations keep the safe draft and produce an easy **Review again** action.
- The user can always cancel the journey without changing the underlying domain record.

## 6. Care-type decision journey

### Deterministic routing matrix

| Need expressed by the user | Assistant path |
| --- | --- |
| One specific visit, occasion, or future date | Recommend **One-time care** |
| Repeating weekly days or an ongoing weekly schedule | Recommend **Regular care** |
| Several irregular dates without a weekly pattern | Explain that these are separate one-time visits; help with the first and preserve the remaining dates |
| Continuous day-and-night or explicit 24/7 coverage | Explain briefly and transfer to a person |
| Immediate danger | Tell the user to call 911 first, then transfer |
| Medical/clinical procedure or advice | Explain the non-medical boundary and transfer |
| Not enough information | Ask whether this is one specific visit or repeats every week |

Words such as **often**, **sometimes**, **for a while**, **overnight**, or **morning help** do not by themselves establish a complete request type or schedule. Overnight care may be one-time only when it fits the currently supported request duration; continuous coverage transfers.

### Required interaction

For a clear supported need, show a short recommendation with its reason and explicit controls:

> Based on what you told me, regular care looks like the best fit because you need help every Monday and Wednesday.

- **Continue with regular care**
- **Choose one-time care instead**
- **I'm still not sure**
- **Talk to a person**

For ambiguity, ask only:

> Is this help for one specific date, or will it repeat every week?

The user must explicitly select one-time or regular care before the draft becomes publishable. Natural language such as **“Actually, it is just this Sunday”** changes the selection, clears incompatible schedule fields, and preserves compatible confirmed information.

### Care-choice completion

Care-choice itself is complete when the server records the explicit selection and the assistant starts the matching draft or transfers the 24/7/exception path. It does not require a domain write merely to improve the intent-level completion count.

## 7. End-to-end care-request journey

After one-time or regular care is selected, the assistant performs this ordered loop:

1. Read the authorized Family Account, care-receiver profiles, saved address, relevant request history, and supported prerequisites.
2. Identify what the user already supplied and what authoritative information can safely be reused.
3. If a care-receiver profile is missing or incomplete, offer to complete the supported fields in chat or open the exact profile control.
4. Ask only for missing material request information.
5. Validate each normalized value using existing server rules.
6. If secure payment setup is required, open and highlight the existing secure payment method control; never collect card information in chat.
7. Resume the request automatically after the secure step and re-read authoritative payment status.
8. Present a deterministic recap with **Modify something** and **Confirm and create request**.
9. On confirmation, reauthorize, reject stale state, execute idempotently through the existing publisher, and verify the resulting request.
10. Tell the user the request is live, show the request type and schedule, and explain that no caregiver is hired merely because it was published.

The journey must support both complete all-at-once messages and slow one-answer-at-a-time conversations. It must preserve the existing 30-minute recap behavior and easy recap regeneration.

## 8. General app-guidance loop

Every supported Family journey uses the same pattern:

```text
Understand goal -> Read authorized state -> Explain simply -> Offer one next action
-> Navigate to registered target -> Focus/highlight -> Observe application result
-> Verify authoritative state -> Continue the goal or finish
```

### Navigation and highlighting

- Use only registered, role-authorized semantic destinations and targets.
- Action buttons must name the user outcome, for example **Update payment method**, not **Go here**.
- On arrival, scroll the target into view, move accessible focus appropriately, and show a visible instruction near the target.
- A missing, disabled, or stale target returns a deterministic recovery step rather than a dead end.
- The chat remains available and anchored to the latest message while the user works.
- The active journey strip shows the current goal and a short progress statement, not internal IDs or states.

### Resuming after a UI step

The browser may report arrival and registered application events, but the server re-reads authoritative state before continuing. If verified, the assistant says what changed and advances. If unchanged, it explains the next attempt. If unavailable or conflicting, it offers recovery or a person.

## 9. Initial journey catalog

Batch 10 must compose the existing Batches 1–9 capabilities into these user-facing journeys:

| Journey | Successful ending |
| --- | --- |
| Choose care and create a request | One-time/regular request verified live, or appropriate human transfer |
| Complete a care-receiver profile | Supported profile state verified ready or intentionally saved incomplete |
| Add or change a payment method | Secure UI completed and authoritative usable-method state verified |
| Understand and recover a failed payment | Safe reason explained when available; recovery verified or transferred |
| Review applicants and hire | Selected invitation/decision/message/hire action verified |
| Manage a visit and submitted hours | Requested supported visit/hours outcome verified or transferred |
| Manage regular care | Supported offer/schedule/extra/skip/pause/resume/end action verified |
| Find history, receipts, or rebook | Correct record opened and supported rebook outcome verified when requested |
| Manage messages and notifications | Exact conversation/notification action verified |
| Get human help | Same conversation transferred with useful authorized context |

The journey layer must call only already registered readers, destinations, preparations, tools, and verifiers. A separate implementation may add a new domain tool only after its exact action, authorization, confirmation, idempotency, and verification contract is added to the intent catalog and tests.

## 10. Older-adult interaction requirements

- Use short sentences and ordinary words.
- Ask one question at a time unless the user already supplied multiple answers.
- Prefer two or three clear choices over open-ended instructions.
- Keep the primary next action visually obvious and at least 44 by 44 CSS pixels.
- Show a compact progress statement such as **Next: choose the days**.
- Never make the user remember information from an earlier screen.
- Keep typed messages clearing immediately on send; Enter submits and Shift+Enter creates a new line.
- Keep the conversation anchored to the latest message like a messaging application.
- Preserve focus and draft text across polling/rerenders.
- Do not repeatedly tell the user where a page is when the assistant can provide a safe navigation button.
- Offer **Talk to a person** without forcing the user to fail first.

## 11. Knowledge behavior

Do not create another broad KB package merely to increase counts. Reuse the existing 324 explicit mappings and current published truths.

Add or revise knowledge only when the journey exposes a specific missing or contradictory product truth. The six exceptional payment rows may retain preparation/human handling rather than a fabricated explanation. Promo codes, billing ownership, refunds, disputes, tax documents, and other undefined product behavior remain unavailable or human-owned until Product defines them.

Personalized state always comes from authorized readers or receipts, never from KB retrieval or model memory.

## 12. Authorization, privacy, confirmation, and safety

- Derive actor and Family Account server-side and reauthorize every read and write.
- Model- or client-supplied record IDs never grant access.
- Return normalized minimum state, never raw models, SQL, provider payloads, credentials, tokens, payment-card data, CVC, or another household's information.
- Consequential writes use a material deterministic recap, explicit action-specific confirmation, a 30-minute expiry, fresh-state checks, idempotency, the existing domain service, and a receipt.
- A generic **yes**, a click on navigation, or a previous confirmation cannot authorize a different action.
- Emergency and medical handling run before ordinary journey routing.
- All 24/7 care coordination and exceptional disputes remain human-owned.
- The assistant must never promise caregiver availability, acceptance, queue position, response time, or resolution.
- Pilot eligibility and capability controls are enforced server-side before model use, state reads, or actions.

## 13. Failure and recovery

Every journey must handle:

- missing, deleted, completed, withdrawn, or stale resources;
- membership or ownership changes;
- another tab or member changing the record;
- expired confirmations;
- secure-UI cancellation or failure;
- unavailable readers or verifiers;
- target missing or disabled;
- provider timeout before any domain write;
- domain timeout with reconciliation by idempotency key;
- refresh, navigation, logout, and return; and
- user correction, cancellation, new goal, or human request.

Never discard a safe draft merely because a confirmation expired. Never retry a consequential write until the application reconciles whether the first attempt succeeded.

## 14. Evaluation and acceptance

### Care-choice corpus

Add ordinary, imperfect, ambiguous, and changing-mind cases including:

- “I need someone this Sunday.”
- “My mother needs help Monday and Wednesday every week.”
- “I don't know what type of care I need.”
- “I need someone tonight and maybe next week too.”
- “She needs somebody there all day and night.”
- “Actually this is only for Saturday.”
- several irregular future dates;
- overnight but not continuous care;
- immediate danger and medical procedures; and
- typos, incomplete answers, repeated answers, and contradictory answers.

### Required deterministic tests

- correct care-path recommendation and explicit-choice enforcement;
- journey restoration after refresh and registered navigation;
- one active goal without silent cross-goal merging;
- exact-account and wrong-account state matrices;
- semantic destination and target authorization;
- secure payment navigation without chat receiving card data;
- recap modification, expiry, regeneration, stale-state denial, idempotency, and receipt;
- authoritative verification after UI and chat actions;
- emergency/medical priority and 24/7 transfer;
- non-pilot denial and **Live for everyone** remaining off;
- no provider call for deterministic continuation/polling; and
- no synthetic request, invitation, booking, payment, or message left live after tests.

### Browser acceptance

Exercise desktop and mobile with the exact pilot boundary:

1. vague need -> recommendation -> explicit selection -> completed one-time request;
2. recurring need -> different weekday schedules -> completed regular request;
3. incomplete profile detour -> return to request without repetition;
4. payment-method detour -> verified return to the active goal;
5. changing the selected care type and rebuilding the recap;
6. refresh and navigation while retaining the journey;
7. failed or cancelled secure step with recovery;
8. 24/7 transfer and emergency-first transfer;
9. keyboard, focus, reflow, zoom, and screen-reader semantics; and
10. cleanup of every synthetic domain record created by the audit.

### Success measures

- 100% critical safety, authorization, confirmation, and cross-account cases pass.
- 100% supported scripted journeys end in verified completion, verified guided completion, or correct transfer.
- At least 95% of non-critical scripted journey variants complete without a repeated question or wrong destination.
- Zero unverified completion claims.
- Zero synthetic live records left after automated or browser evaluation.
- Average model cost remains below $0.01 per completed assisted journey where deterministic continuation is possible.

The desired portfolio direction is to reduce the 11 no-operational-path rows to zero and move appropriate high-value assisted rows toward verified completion. Do not relabel informational or human-owned intents merely to improve a percentage. Journey completion is the primary product measure; intent-stage counts remain the authority measure.

## 15. Implementation sequence

Implement as one cohesive source batch in this order:

1. Reconcile and preserve the Batch 6–9 source baseline.
2. Add the minimal reusable active-journey contract on top of current records and services.
3. Make care-path selection the first full journey and connect it to existing request drafting/publication.
4. Generalize resume, navigation, highlight, verification, recovery, and completion presentation.
5. Register the initial journey catalog using existing Batches 1–9 capabilities.
6. Add deterministic journey and care-choice evaluations plus browser coverage.
7. Update capability stages only where repeatable evidence proves a stronger terminal outcome.
8. Update the coverage registry, master plan, Admin outcome labels, and this implementation record.

After tests pass, deployment remains a normal `deploy.sh` operation. Publish any exact new KB entries, activate only the existing two Family pilot users, and perform the authenticated browser acceptance above. Do not select **Live for everyone**.

## 16. Definition of done

Batch 10 is source-complete when:

- the care-choice journey works from vague need through explicit one-time/regular selection;
- the selected path continues through the existing verified request lifecycle;
- active goals survive supported navigation and refresh;
- every initial journey uses a registered, authorized terminal path;
- UI detours resume instead of ending assistance;
- all personalized claims and completion statements have reader/receipt evidence;
- the focused and complete AI Support suites pass;
- the mass registry remains 324 / 324 with no mapping regression;
- desktop/mobile/accessibility journey tests pass; and
- documentation reports source, deployed, published, activated, and production-audited state separately.
