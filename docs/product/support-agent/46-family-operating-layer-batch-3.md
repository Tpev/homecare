# Family Operating Layer — Batch 3A and 3B Implementation Record

Status: Implemented in source; production deployment pending

Implemented: August 18, 2026

Owner: Product

Production availability: Unchanged. Pilot remains limited to the exact two Family users; **Everyone** remains off.

## Outcome

Batch 3 converts the Family assistant from a set of individual support paths into a reusable operating layer. The assistant now has an executable disposition for every one of the 324 documented Family intents, explicit governed-knowledge mappings for the 190 Wave 1 intents, state-aware entry choices, universal task continuation, authoritative completion-verifier contracts, privacy-safe intent telemetry, and five reversible preparation contracts.

This batch does not make all 324 intents executable. It makes their support state explicit and testable. An intent may be explained, read, guided, prepared, executed, verified, transferred, or retained as backlog without the runtime silently claiming broader authority.

## Delivered architecture

### Executable Family intent catalog

`resources/ai-support/intents/family-v1.php` is generated from the human-readable 324-row registry by `tools/generate_family_intent_catalog.php`. The application validates the source hash and fails closed if the generated catalog is stale.

Every record declares:

- stable intent ID, domain, priority, Family role, and active-membership applicability;
- ordinary, imperfect, and contextual wording;
- current and target capability stages;
- explicit KB stable IDs;
- reader, navigation, guided-task, preparation, tool, verifier, and human-transfer contract references;
- unsupported and never-in-chat behavior;
- evaluation IDs; and
- rollout state.

The generated catalog contains exactly 324 unique records, 190 explicit Wave 1 KB mappings, and 1,296 phrase definitions. The runtime refuses malformed counts, missing contract keys, invalid IDs, or a stale registry hash.

### Layered intent resolution

Family intent resolution now uses this order:

1. deterministic safety and human-transfer guards;
2. active-task contextual replies;
3. five deterministic preparation families;
4. the existing 40 high-confidence Family handlers;
5. bounded catalog lexical classification;
6. two-choice clarification only when lexical evidence is strong; and
7. the existing bounded provider path when the request remains unmatched or needs request-intake extraction.

Short context-free phrases do not force a weak catalog match. Known intent IDs retrieve only their explicitly mapped governed KB entries. General lexical KB retrieval remains a fallback for unmatched support questions.

### State-aware Family assistant home

An eligible Family user opening a new support conversation sees no more than three personalized suggestions derived from authorized current state, followed by six stable general choices:

- See what needs my attention
- Create a care request
- Check my next visit
- Payment help
- Something else
- Talk to a person

The service uses only the active Family Account and existing normalized readers. It does not expose the start experience to an ineligible user and does not change availability.

### Universal active task behavior

Guided tasks now retain the stable intent, goal, current step, semantic destination, expected control, verifier, optional preparation, recovery count, resume behavior, and human summary.

Across supported tasks, short replies such as **yes**, **take me there**, **show me**, **I did it**, **check again**, **I cannot find it**, **it did not work**, **stop**, and **cancel** continue the active task without a provider call. Repeated failure changes the recovery wording and keeps human transfer available instead of repeating the same instruction.

Opening and arriving remain distinct from completion. The assistant says an action is complete only after its registered verifier returns authoritative proof.

### Generic completion verifiers

The verifier registry includes:

- `family_payment_method_v1` — fresh saved-payment-method state;
- `care_request_receipt_v1` — an authorized live care-request receipt;
- `care_request_draft_state_v1` — registered but unavailable until a specific safe proof contract is added;
- `authoritative_family_state_v1` — registered but unavailable without an intent-specific reader; and
- `unavailable_v1` — explicit truthful non-verification.

A user statement, page arrival, or highlighted control never counts as proof by itself.

## Five reversible preparation contracts

The `ai_support_preparations` store uses exact-user ownership, encrypted payloads, allowlisted fields, a version, state, expiry, and visible summaries. Preparation is not a domain mutation.

| Contract | Chat can prepare | Existing UI owns the final action |
| --- | --- | --- |
| `care_profile_v1` | Non-secret care-profile fields | Profile editor save |
| `care_request_reuse_v1` | Reusable supported request fields | Request wizard review/publication |
| `caregiver_message_v1` | Recipient context and message body | Messaging send |
| `submitted_hours_correction_v1` | Correction/dispute facts and explanation | Existing hours/support workflow |
| `support_intake_v1` | Category and issue summary | Support submission or human continuation |

Preparations can be opened, resumed, or discarded. Forms show that prepared values have not been saved, sent, approved, disputed, or submitted. The service re-authorizes referenced records and rejects password, secret, token, key, card, CVC, or verification-code fields. It has no generic ORM, SQL, provider, or browser-write capability.

## Intent telemetry and Admin coverage

Content-free events now support the task funnel:

- recognized and clarified;
- action offered;
- opened and arrived;
- prepared and preparation opened;
- confirmed and completed;
- abandoned, looped/recovered, verification failed, or task failed; and
- transferred.

Allowed metadata is limited to compact IDs and state codes. It does not contain transcript text, field values, care notes, card facts, or provider errors.

The Admin AI Support overview can search the 324-intent catalog and shows total, KB-mapped, pilot, preparation, and backlog counts plus current stages and KB mappings. Its 30-day outcome summary shows unmatched, loop, failure, recovery, and transfer counts without copying conversations.

## Retention and failure behavior

Expired preparation and action payloads join the existing AI Support retention command. Human takeover, automation loss, wrong-account access, expiration, cancellation, or an invalid resource fails closed. Preparations never make an existing product action irreversible and never bypass the normal authorization or confirmation point.

## Verification completed in source

The Batch 3 feature suite passed:

- 10 tests;
- 1,491 assertions;
- all 324 catalog records;
- all 190 explicit Wave 1 mappings;
- all five preparation contracts;
- cross-account and secret-field denial;
- active-task continuation, repetition recovery, stop, and unavailable-verifier behavior;
- state-aware home limits and choices; and
- Admin coverage/outcome rendering.

The full Family mass command passed:

- 324 / 324 executable catalog records;
- 190 / 190 explicit KB mappings;
- 1,296 catalog phrase definitions;
- 40 / 40 implemented runtime intents;
- 122 / 122 implemented routing phrases;
- 10 / 10 collision cases;
- 42 isolated application tests with 1,867 assertions; and
- zero provider calls and no production database use.

The existing interactive runtime plus Batch 3 suite passed 25 tests with 1,583 assertions after tightening weak close-neighbor clarification. The final complete AI Support regression passed **149 tests with 2,768 assertions**.

## Boundaries preserved

- Production availability and pilot grants are not changed by source deployment or migration.
- The exact two Family pilot users remain the only pilot audience until an Administrator deliberately changes Availability.
- **Everyone** remains off.
- Pricing remains held; the assistant must not quote or calculate the unresolved price truth.
- Existing Stripe, payment authorization, capture, fee, payout, and request-publication behavior is unchanged.
- No generic database, ORM, SQL, browser-control, arbitrary URL, or unrestricted write tool exists.
- Card numbers, CVCs, passwords, tokens, verification codes, credentials, and provider secrets never enter chat preparation.
- Human takeover remains terminal for automation.
- Batch 4 confirmed payment/time actions are not part of this batch.

## Deployment

Deploy through the repository's normal script only:

```bash
./deploy.sh
```

The migration adds only `ai_support_preparations`; it does not delete or rewrite existing product records. After deployment, verify:

```bash
git rev-parse HEAD
php artisan migrate:status
php artisan ai-support:test-family-intents --plan
php artisan ai-support:monitor-health
```

In Admin → AI Support, verify that Availability still shows the two-user Pilot and that **Everyone** is off. Then inspect the Intent coverage section and open support as each pilot user for a short guided and preparation smoke test.

## Next phase

Batch 3 is the platform layer. The next implementation is Batch 4 from the master plan: payment-state explanation/recovery and submitted-hours correction/dispute journeys using narrow readers, existing domain workflows, explicit confirmation only where authorized, and authoritative receipts. Do not broaden production availability as part of that implementation.
