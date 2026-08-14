# Interactive Assistant Approved Build Contract

Status: Approved product contract; ready for capability specification and implementation

Approved: August 14, 2026

Owner: Product

Implementation state: Not built; production and user-visible controls remain off

## Purpose

This document is the consolidated implementation and release authority for the next intelligent-support build. Product approved the complete package on August 14, 2026. It complements the [interactive care-request expansion](22-interactive-care-request-expansion.md), closes the remaining product interviews for this build, and gives engineering and GPT-5.6 Sol one bounded set of defaults.

Approval of this contract does not activate production AI, publish KB content, create a pilot grant, modify payment behavior, or authorize any user outside an exact named pilot. Implementation must still produce the deterministic, evaluation, usability, operational, privacy, and pilot evidence below.

## 1. Human transfer and 24/7 coverage

### User experience

For a 24/7 coverage need, transfer promptly without requiring the user to complete a long intake. The final automated public message is:

> I’ve transferred this conversation to LoLo Support. They’ll reply here as soon as they can.

Do not show queue position, queue status, business hours, waiting estimates, or a guaranteed response time. Both authorized administrators receive an operational alert with a direct conversation link; either may claim the conversation. Automation stops atomically under `DEC-008`.

### Human handoff context

Include only information already supplied or retrieved for the active authorized purpose:

- User identity and role
- Family Account reference
- Care recipient, when known
- Confirmed location
- Desired start date
- Care needs already described
- Continuous or overnight requirement
- Emergency-screening result
- Unanswered questions
- Full canonical conversation and compact event history

Invalidate pending confirmations before transfer. The summary is a convenience layer; the transcript and authoritative records remain evidence.

After the pricing activation hold in `DEC-049` is separately released, the assistant may state the approved $30 hourly price. If asked, it may perform transparent deterministic arithmetic such as $720 for 24 hours, while explaining that a person must coordinate coverage and availability. Until reconciliation, it does not quote the price against contradictory production behavior. A 24/7 need alone is not an emergency; immediate-danger language triggers emergency instructions first.

## 2. Production-data retention and extinction

The following maximums close `DEC-014`:

| Data class | Approved handling |
| --- | --- |
| Suppressed or undelivered model output | Keep in memory when possible; delete immediately after validation, suppression, failure, or reconciliation; absolute maximum one hour |
| Restricted redacted diagnostics | Seven calendar days; no full transcript, complete prompt, secrets, credentials, payment data, or unrestricted tool payloads |
| Linkable raw product analytics | 30 calendar days |
| De-identified aggregate metrics | 24 calendar months |
| Search indexes and caches | Invalidate immediately; maximum extinction 24 hours |
| Live/read replicas | Maximum extinction 24 hours after canonical deletion |
| Manual exports | Seven days by default; maximum 30 days only with a documented incident, legal, security, or contractual purpose and explicit expiry |
| Model/provider handling | No training or model improvement; shortest supported retention and never more than 30 days; production use is blocked until configuration and contract evidence exist |
| Backups and snapshots | Maximum 35 days; restored systems must reapply all deletion manifests before becoming accessible |

Narrow legal/security holds remain the only exception and retain their existing authorization, scope, review, release, and evidence rules. If an actual destination cannot meet the applicable maximum, reduce the data, replace/reconfigure the destination, or block production use.

## 3. Date, time, duration, and request-type rules

- Use `America/New_York` as the default and show **Eastern Time** in the recap.
- If the user appears to mean another timezone, ask for clarification.
- Resolve relative dates to an explicit full date and repeat it.
- Never guess a vague period such as **morning**; obtain a concrete start time.
- Supported request durations are one through twelve hours in 30-minute increments.
- Recurring weekdays may use different start times and durations.
- If a recurring start date does not fall on a selected weekday, explain the adjustment and use the first matching date.

Recommendation rules:

| Need | Recommendation |
| --- | --- |
| One specific visit | One-time care |
| Repeated weekly care | Regular/recurring care |
| Continuous day-and-night coverage | Human-assisted 24/7 coverage |
| Immediate danger | Emergency instruction, then human transfer |
| Medical or clinical task | Non-medical limitation and human transfer |

The user explicitly selects the path before drafting. When a material value is ambiguous, ask one short question. Never silently choose a recipient, task, address, schedule, duration, timezone, or request type. After two misunderstandings of the same material field, offer transfer.

## 4. Draft ownership, context reuse, and concurrency

- A draft is bound to one authenticated user, Family Account, and canonical support conversation.
- Different authorized Family members may maintain separate drafts.
- Optimistic versioning prevents two tabs or devices from silently overwriting newer content.
- A stale session must reload and reconcile before saving or confirming.
- Logout invalidates the active confirmation but preserves the seven-day private draft.
- Loss of Family access makes the draft inaccessible immediately and prevents resume, preview, or publication.
- A different apparent request produces **Resume saved request** and **Discard and start over** choices; unrelated requests are never silently merged.

Saved recipients and addresses appear as simple selectable cards. If only one likely value exists, the assistant may propose it but must keep it visible and modifiable. Do not ask the user to retype known information. A previous request is loaded only after an explicit request such as **same as last time**. Every reused material value appears in the final recap.

Dynamic account facts come from purpose-limited authorized tools, never KB memory. Every read is server-authorized and audited.

## 5. Receipt and failure contract

### Successful receipt

Build the success view from the authoritative domain receipt. Show:

- **Your care request is live**
- Safe request reference
- Recipient
- Schedule and timezone
- **View request**
- **Eligible caregivers can now see it**
- **No caregiver has been hired yet**
- **No payment has been authorized yet**

### Failure behavior

| Failure | Required behavior |
| --- | --- |
| Model/provider failure | Preserve the draft and offer human support |
| Authorized read-tool failure | Retry once, then transfer or provide the normal manual path |
| Domain validation failure | Open only the affected recap section and preserve valid fields |
| Publication timeout/unknown result | Reconcile by idempotency key before retrying or reporting failure |
| Operations notification fails after commit | Report request creation truthfully; retry/alert notification separately |
| Confirmation absent/expired/invalid | No write; provide the one-step fresh recap path |
| Capability or pilot disabled | No model/tool action; preserve safe draft according to retention rules and route to human/manual support |

Never queue an unconfirmed publication for later and never claim success without the authoritative receipt.

## 6. Administrator evidence

Authorized administrators can inspect:

- Complete canonical user, assistant, and human messages with explicit responder labels
- Current automated or human ownership
- KB entries and exact versions used
- Safe summaries of tool reads and writes
- Draft state, version, and expiry
- Recap and confirmation state
- Publication receipt and domain link
- Model/configuration, latency, tokens, and cost
- Handoff reason
- Errors, retry, and reconciliation results

Do not expose private chain-of-thought, complete assembled prompts, credentials, payment data, or unnecessary sensitive tool payloads. The initial pilot has no transcript-export feature. Administrator reads and takeover actions are permission-controlled and audited.

## 7. Governed KB expansion

Add one independently governed English entry for each topic:

1. Choosing one-time, recurring, or 24/7 care
2. One-time request requirements
3. Recurring request requirements
4. Human assistance for 24/7 coverage
5. Non-medical and emergency boundaries
6. $30 hourly pricing
7. Request published versus Caregiver hired
8. Payment authorization timing
9. Saved-information reuse and privacy
10. Seven-day drafts and confirmation renewal
11. Modifying and publishing a request
12. What happens after publication

Either authorized KB operator may complete the lifecycle alone under `DEC-022`. Dynamic account state, availability, request existence, publication success, hire, booking, and payment state cannot come from a KB answer; they require current authorized domain context or receipt.

The $30 pricing entry may be authored and evaluated now but remains unreleased or inapplicable to production answers until the `DEC-049` pricing-service reconciliation hold is released.

## 8. Initial release scope by role

### Family

The first interactive Family package may include, behind separate controls:

- Governed answers
- Human transfer
- Semantic navigation
- Authorized Family context reads
- Request-type guidance and explicit selection
- One-time and recurring private drafts
- Deterministic recap and modification
- Confirmed one-time and recurring publication
- 24/7 transfer
- Authoritative receipt and navigation

### Caregiver

Build the shared role-aware runtime, but initially release only:

- Governed answers
- Dashboard orientation/navigation
- Work Inbox orientation/navigation
- Visit orientation/navigation
- Account/profile orientation/navigation
- Human transfer

Caregiver applications, work acceptance, shift operations, time submission, profile mutations, and payout operations require later capability specifications and confirmations.

### Initially excluded for both roles

- Attachments or medical documents
- Voice interaction
- Caregiver hiring
- Payment-method changes
- Cancellations and disputes
- Timesheets and visit approval
- Sending marketplace messages
- Profile or access-permission changes

## 9. Build and exact-user pilot sequence

Production-conversation shadowing remains skipped under `DEC-047`.

1. Build and test the common runtime, canonical transcript integration, governed retrieval, and human handoff.
2. Add semantic navigation.
3. Add Family context reads and care-path recommendation.
4. Build both one-time and recurring draft structures.
5. Test and pilot one-time drafting/publication first.
6. Enable recurring publication only after the one-time gate passes.
7. Enable Caregiver answers/navigation under separate role and capability controls.
8. Start with staff-operated test accounts.
9. Enable exactly two named Family pilot users.
10. Expand to no more than five named Family pilot users after evidence review.
11. Use the existing 14-day expiring grants and review every pilot interaction.
12. Do not enable a percentage cohort or general availability without another explicit product release decision.

One build may prepare shared one-time and recurring foundations, but the production commit flags and release evidence remain separate.

## 10. Accuracy and evaluation gates

The product cannot promise that probabilistic language interpretation will never misunderstand a sentence. It can guarantee that uncertain or invalid interpretation cannot bypass deterministic validation, recap, confirmation, authorization, and receipt controls.

Required release gates:

- 100% authorization and cross-account isolation cases
- 100% confirmation enforcement cases
- 100% duplicate-prevention and reconciliation cases
- 100% equality between confirmed normalized recap and published domain record
- Zero fabricated-success statements
- 100% emergency, medical-boundary, 24/7, and human-takeover cases
- 100% invisibility and zero model/tool execution for non-granted users
- At least 98% first-pass request-type and material-field extraction on the frozen representative corpus
- Every unresolved ambiguity becomes a question rather than a guessed publishable value
- Review every pilot conversation and created request
- No cohort expansion with an unresolved critical failure

## 11. Older-adult usability and accessibility gate

Test with at least five representative older adults across mobile and desktop:

- At least 90% complete the supported task without staff assistance.
- Every participant can explain the final recap.
- Every participant understands that a live request does not mean a Caregiver is hired.
- Every participant understands that publication does not authorize payment.
- Controls work at 200% zoom.
- Screen-reader labels, keyboard order, focus, contrast, and touch targets pass.
- Questions remain short and singular.
- No participant loses a draft because of navigation, refresh, confirmation expiry, or timeout.

## 12. Cost and performance contract

- Use the accepted Luna-low baseline while it continues to pass the frozen gates.
- Use deterministic code for authorization, validation, date normalization, pricing arithmetic, recaps, confirmations, and receipts.
- Retrieve only relevant KB versions and necessary role-scoped account fields.
- Default to one model round trip per user message and avoid autonomous model loops.
- Target less than $0.02 per completed model-assisted conversation.
- Alert at $0.03 per conversation.
- At $0.05, stop further model loops, preserve the draft, and transfer safely.
- Target five-second P95 conversational response and eight-second P95 tool action.
- Never weaken correctness, confirmation, privacy, or human transfer to meet a cost target.

## 13. Automatic stop and rollback

Immediately disable the affected capability for:

- Any AI visibility or invocation by a non-granted user
- Unauthorized access or cross-role/cross-account disclosure
- Unconfirmed or duplicate publication
- Published record differing from the recap
- Fabricated success
- Automated reply after human takeover
- Emergency-handling failure
- Repeated provider/tool instability beyond the declared retry limit
- Material privacy leakage

Rollback invalidates pending confirmations, stops automated writes, preserves valid domain records and receipts, preserves safe unexpired drafts, and leaves human support usable.

## 14. Pricing implementation hold

The $30-per-hour business truth remains approved. The assistant may not activate pricing answers or totals in production until the separately scoped payment/pricing reconciliation makes the authoritative service match that truth. This build does not modify Stripe, customer charging, platform fees, authorization buffers, Caregiver payouts, or Family-specific overrides.

## 15. Product readiness conclusion

No further product interview is required before engineering begins the declared initial build. Engineering may decide schema details, service composition, transaction/outbox mechanics, job structure, retry implementation, and component organization within this contract.

Release remains blocked until implementation and evidence exist. Any change to user-visible scope, role authority, pricing truth, retention, human ownership, confirmation, pilot size, release gates, or hard-failure thresholds returns to Product and the decision log.
