# Safety, Privacy, and Human Escalation

Status: Proposed

Last reviewed: August 13, 2026

Owner: Security/privacy with product and support operations

Required approvers: Security/privacy, product, engineering, support operations

## Safety objective

The agent must make routine help easier without weakening LoLo's existing authorization, consent, care, payment, or audit controls. Uncertainty, denial, or handoff is safer than an unsupported answer or action.

## Zero-tolerance invariants

Within LoLo's supported system boundary:

- A user cannot view or affect another family account through the agent.
- A removed, inactive, or otherwise ineligible member receives no continuing access.
- A model cannot bypass a domain policy or server validator.
- A Class D action cannot commit without valid action-specific confirmation.
- A retry cannot create a duplicate material action.
- A success message cannot appear without an authoritative success receipt.
- A conversation transferred to human support cannot receive further public agent replies after its one-time deterministic transfer confirmation.
- Sensitive data is not placed in notification previews, analytics, or unnecessary model context.

Any violation or credible near miss pauses the affected capability.

## Authorization

Every read and write re-resolves the authenticated user, active family membership, relevant resource, capability state, and policy. Route middleware and UI visibility are helpful but insufficient.

Never accept a user ID, family account ID, ownership field, or authorization result from model output or client state. Tool services derive them from the authenticated server context.

For shared-care actions, store the actual acting user as required by [the family account access specification](../family-account-access-spec.md).

## Confirmation safety

For Class D actions, the server produces a preview containing every material consequence. The preview is bound to:

- Actor
- Family account
- Capability and tool
- Normalized arguments
- Relevant record versions
- Estimated or known financial consequence when applicable
- Expiration
- Idempotency key

The commit accepts only a matching valid confirmation. A changed draft or state invalidates it.

Confirmation copy must name the action: **Create this care request**, not **Continue** or **Yes**.

Preview validity is capability-specific and separate from retention. Under `DEC-028`, unconfirmed preview content is deleted when cancelled, replaced, invalidated, or expired and never remains longer than 24 hours after creation. After an authoritative commit, only compact confirmation and receipt evidence remains for 24 calendar months; the authoritative domain record follows its own schedule.

## Restricted initial domains

The following remain Class E until independently specified and approved:

- Medical diagnosis, clinical advice, medication decisions, or medical procedures
- Immediate danger, abuse, neglect, self-harm, or crisis assessment
- Payment-method changes or collection of card details
- Billing disputes, refunds, chargebacks, and payment corrections
- Timesheet approval or other actions that trigger payment capture
- Hiring and payment authorization
- Cancellation of booked care or a regular-care plan
- Family invitation, removal, ownership transfer, or account closure
- Identity verification decisions
- Legal interpretation

The agent may provide an approved limitation, open the normal structured surface, or escalate. It may not improvise a resolution.

## Emergency and medical behavior

LoLo support is not an emergency service. The existing emergency footer remains visible. The intelligent agent also needs deterministic escalation behavior for defined critical language.

When a message indicates immediate danger:

- State that LoLo is not an emergency service.
- Direct the user to call 911 in the United States or the approved applicable local emergency instruction when LoLo supports another jurisdiction.
- Do not delay that instruction with additional questions.
- Create a high-priority human-support escalation according to the approved operational playbook.
- Do not claim to have contacted emergency services.

When a user requests medical or clinical care:

- Explain that LoLo supports non-medical care.
- Do not diagnose or recommend a procedure.
- Offer the approved human-support or appropriate structured next step.

Keyword rules may provide a deterministic backstop but are not sufficient by themselves. The critical evaluation corpus must include indirect, misspelled, and contextual expressions.

## Data minimization

Send only data needed for the current capability. Examples of data that should not enter model context unless separately justified:

- Full payment credentials
- Passwords, invitation tokens, or authentication secrets
- Government identifiers
- Unrelated private care notes
- Unrelated support conversations
- Raw page DOM or hidden form fields
- Sensitive URL query values
- Full family-account history when only one request is relevant

Before implementation, every context field must appear in a data inventory with purpose, source, retention, and redaction rules.

## Transcript and event privacy

Under `DEC-024`, LoLo keeps customer text only while it has a documented operational, contractual, or legal purpose. Compact audit metadata, action receipts, pilot-grant history, and KB version evidence may be retained longer when their distinct purpose requires it. Expired data is deleted automatically rather than retained indefinitely for possible future use.

Under `DEC-026`, the unified human-and-AI canonical support transcript remains while the conversation is open and for 12 calendar months after its most recent final resolution. Reopening resets the clock. At expiry, conversation-bearing content and identifiable derivatives are deleted automatically unless a narrow authorized hold applies. Linked authoritative domain records are unaffected.

Under `DEC-025`, LoLo does not persist a second complete assembled model request containing copied conversation history, retrieved KB bodies, instructions, and page context. Analytics, events, summaries, and debugging stores also cannot become parallel transcripts. Structured agent events may have different operational value, but each data class requires an exact retention period, deletion trigger, and approved exception before production. See [Data retention, deletion, and legal holds](13-data-retention-and-deletion.md).

Do not store private chain-of-thought. Store:

- Observable user and assistant messages
- Capability and policy reason codes
- KB IDs and versions
- Tool names, safe arguments or argument hashes, and results
- Preview and confirmation references
- Model/prompt/schema versions
- Token, latency, and cost fields
- Escalation and human ownership events

Redact or omit sensitive fields from analytics and broad administrative lists. Access to detailed transcripts should be role-controlled and audited.

The admin experience must show the expected deletion date for retained agent records and any active legal or security hold. A hold is narrow, documented, time-bounded or periodically reviewed, and never a silent reason for indefinite retention.

## Prompt injection and untrusted content

Treat all user text, retrieved support content, and record text as untrusted data. It cannot redefine policies, tool permissions, system instructions, or approval boundaries.

Controls include:

- Retrieve only approved KB entries for product truth.
- Keep policy checks outside the model.
- Expose only capability-specific tools.
- Validate tool arguments against server schemas and authorization.
- Never execute instructions embedded in user-provided notes or retrieved record content.
- Add adversarial evaluations such as “ignore your rules,” false administrator claims, and instructions hidden in care notes.

## Escalation triggers

Escalate when:

- The user selects **Talk to a person**.
- No applicable approved KB entry or released capability exists.
- Authorization or identity context cannot be established safely.
- A critical or restricted domain is detected.
- The user corrects the same material misunderstanding twice.
- A navigation or tool action fails repeatedly.
- Model output fails schema or safety validation and deterministic fallback cannot complete safely.
- The user disputes a previous action or claims harm.
- A human support rule explicitly requires manual handling.

Sentiment may help prioritize review but must not be the only escalation signal.

## Handoff experience

### User sees

> I’ve transferred this conversation to LoLo Support. They’ll reply here as soon as they can.

Do not show queue position, queue status, business hours, guaranteed response time, or automated wait-time updates. Do not say “someone is joining now” unless a person is actually present. Both authorized administrators receive an alert and either may claim; the public response does not depend on which person is staffed.

### Support sees

- User identity, role, and authorized family context
- Origin and current semantic page
- User's stated goal
- Facts collected and which were user-confirmed
- Capability and risk class
- KB entries used
- Navigation and tools attempted
- Exact tool errors or policy denials
- Pending draft, without an active commit confirmation
- Escalation reason and priority
- Complete transcript and event timeline

For a 24/7 coverage transfer, include available confirmed recipient, location, desired start, care needs, continuous/overnight requirement, emergency-screening result, and unanswered questions without delaying transfer for a long intake. After the `DEC-049` pricing activation hold is separately released, the assistant may state $30 per hour and perform requested deterministic duration arithmetic; coverage coordination and availability remain human responsibilities. See `DEC-057`.

The generated summary is convenience, not evidence. Support can inspect the original events.

## Agent/human ownership states

| State | Public responder |
| --- | --- |
| Automated | Agent may answer within released capabilities |
| Transferred to human; internally unassigned | Human support only; no automated queue/status replies |
| Human assigned | Human only |
| Human waiting on user | Human only unless deliberately returned |
| Returned to automation | Agent resumes after admin action and user-visible notice |
| Closed | Read-only or new conversation according to support rules |

Transfer must atomically change the responder mode and suppress any in-flight model reply. Internal staff claiming must also be atomic so two administrators cannot own the conversation simultaneously.

## Abuse and reliability controls

- Per-user and per-conversation rate limits
- Maximum message and attachment sizes
- Tool-call and retry ceilings
- Duplicate-message and duplicate-action protection
- Circuit breakers for model/provider failure
- Global and per-capability kill switches
- Safe deterministic messages during provider outage
- Alerts for denial spikes, schema failures, repeated tool errors, and unusual costs

## Safety review requirements

Classes D and E require:

- Threat modeling
- Authorization and tampering tests
- Confirmation review
- Adversarial model evaluations
- Human-handoff rehearsal
- Privacy-field review
- Kill-switch exercise
- Named incident owner

Security/privacy may require these controls for lower classes when the data or audience warrants it.
