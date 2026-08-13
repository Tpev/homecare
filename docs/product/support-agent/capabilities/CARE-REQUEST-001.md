# Capability Specification: `CARE-REQUEST-001` — Prepare a One-Time Care-Request Draft

Status: Draft

Version: 0.1

Owner: Family care product

Required approvers: Product, engineering, design/accessibility, security/privacy, support operations

Last reviewed: August 13, 2026

## 1. User outcome

A signed-in family user can describe a one-time non-medical care need in ordinary language and receive a complete, editable, validated draft without navigating a long form alone.

User-facing promise:

> I can help prepare your care request one step at a time. You will review everything before it is published.

This capability does not publish, hire a caregiver, authorize payment, or promise caregiver availability.

## 2. Scope

### Supported users

- Active family account owner
- Active family account member authorized for operational shared-care actions

### Supported request

- One-time non-medical care request
- One recipient
- One service address
- Start and end time that pass current request validation
- Approved LoLo care-task taxonomy

### Unsupported neighboring intents

- Recurring/weekly care: explain that this draft flow currently handles one-time care and offer the approved regular-care/manual path.
- Book the same caregiver again: route to `CARE-REBOOK-001` when released or the existing **Book again** flow.
- Medical or clinical procedures: show the non-medical limitation and escalate.
- Immediate danger: emergency instruction and priority human escalation.
- Hiring, payment, cancellation, or visit changes: route to the applicable capability/manual flow.

## 3. Risk and autonomy

- Risk class: C — Prepare
- Side effect: Creates or updates a private reversible draft only.
- Confirmation: No Class D commit confirmation. The user must review all material fields before publication under `CARE-REQUEST-003`.
- Notifications: Saving the draft must not notify caregivers or represent the request as published.

## 4. Required information

The conversational minimum is:

1. Care recipient
2. Non-medical care tasks
3. Start date
4. Start time
5. Duration or end time
6. Service address

Additional notes are collected only when relevant or required by the current care-request domain contract. The agent should not make an older user answer filler questions merely to improve an internal “quality score.”

The legacy AI copilot's broader required-field behavior is not inherited. Product must define the new minimum fields against the current ordinary care-request domain workflow before approval.

## 5. Trigger language

Supported examples:

- “I need someone tomorrow morning to help my mother shower and make breakfast.”
- “Can you help me post care for Dad on Friday from 9 to noon?”
- “I need a caregiver for myself for a few hours next Tuesday.”

Near-neighbors:

- “I need Caroline again next Friday.” -> Book-again flow, not a new open-market draft.
- “I need someone every Monday.” -> Regular-care/manual flow.
- “Can someone give my mother an injection?” -> Non-medical limitation and escalation.

## 6. Authorized context

| Field | Purpose | Server source | Model context |
| --- | --- | --- | --- |
| Active family membership | Authorization | `FamilyAccountContext` | Role/state only |
| Saved recipients | Offer user-recognizable choices | Authorized care-recipient/profile service | Minimal display labels and IDs hidden from model where possible |
| Saved service addresses | Avoid retyping | Authorized family/profile data | Only candidate address selected or shown to user |
| Approved care tasks | Map ordinary language | `CareTask` catalog | Names and stable taxonomy IDs |
| User timezone | Resolve dates/times | Account/application context | Yes |
| Current draft/version | Continue task safely | Agent draft store | Relevant fields |

Do not expose unrelated recipient notes, full family history, payment data, or another member's private support tickets.

## 7. Conversation behavior

- Acknowledge the user goal and capture all details already provided.
- Ask one material question at a time.
- Prefer visible choices for known recipients, addresses, and common tasks.
- Resolve relative dates to an explicit date and repeat it to the user.
- If the time is vague, ask a concrete clarification such as **Would 9:00 AM work?** rather than guessing.
- If a task maps ambiguously, show the mapped task in the draft and ask the user to confirm or choose.
- Let the user change any field with ordinary language.
- Offer **Use the regular form** and **Talk to a person** throughout.

The agent may generate a concise title from confirmed task/recipient context, but the title is not a substitute for the material fields.

## 8. Draft contract

The draft stores normalized fields plus provenance:

- Value
- Source: user message, authorized profile, deterministic default, or agent extraction
- Confirmation state for material inferred/profile values
- Updated timestamp and draft version

At minimum, the review view presents:

- Recipient
- Tasks and any task notes
- Explicit date
- Start and end time plus duration
- Full service address
- Additional access/safety notes that will be shared through the care workflow

Do not show an arbitrary percentage “quality score” to the user unless research proves it helps comprehension. Use plain status such as **Still need the time** or **Ready to review**.

## 9. Validation

Deterministic server validation controls:

- Active family-account access
- Recipient/profile accessibility
- Task IDs exist and are allowed
- Date/time parse in the user's timezone
- End is after start and duration satisfies current domain rules
- Address fields and supported geography
- Text length and unsafe/control characters
- One-time request type

Invalid fields remain in the draft only as uncommitted user text where useful; they never become publishable values.

## 10. Safety and escalation

Escalate or route safely when:

- Medical/clinical task or immediate danger is indicated.
- User cannot identify the recipient or authorized address.
- User reports a current caregiver no-show, dispute, or urgent existing visit; do not create a new request as a substitute.
- Authorization changes during the conversation.
- The same material field is misunderstood twice.
- Draft storage or validation repeatedly fails.
- User asks for a person.

## 11. Results

### Success

State:

> Your draft is ready to review. Nothing has been published yet.

Primary action: **Review request**

Secondary actions: **Change something**, **Use the regular form**, **Talk to a person**

### Failure

Preserve safe draft data, identify the one correctable issue, or transfer with the draft summary. Never say the request exists in the marketplace.

## 12. Events and metrics

Required events:

- Capability routed
- Draft started/updated
- Field provenance/confirmation changed
- Validation failed/passed
- Draft ready for review
- Manual fallback
- Escalation

Metrics:

- Draft completion rate
- Turns/time to ready
- User correction rate by field
- Abandonment and human-handoff rate
- Validation failures
- Older-adult unassisted completion and comprehension
- Tokens, latency, and cost per ready draft

## 13. Evaluation requirements

Include:

- All details in one message
- One detail at a time
- Multiple saved recipients/addresses
- “Tomorrow morning” near midnight and daylight-saving boundaries
- User changes date, task, recipient, and address
- Misspelled care tasks
- Relative duration
- Recurring-care near-neighbor
- Book-again near-neighbor
- Medical task and current-emergency language
- Removed family member or cross-account recipient ID
- Model timeout/invalid output and rule fallback
- Refresh/navigation and human takeover mid-draft
- 200% text, screen reader, compact phone, mobile keyboard

Gates follow [evaluation and testing](../06-evaluation-and-testing.md), including 100% authorization isolation and at least 90% declared task completion/usability targets before broader release.

## 14. Rollout and rollback

- Dependencies: `SUP-HANDOFF-001`, context/event contracts, approved task/recipient/address sources.
- Release after grounded answers and semantic navigation are stable.
- Begin in shadow mode using real eligible request-creation conversations.
- Capability flag disables intelligent drafting while preserving the manual form and human chat.
- Rollback preserves safe drafts when permitted and removes any pending publication confirmation.

## 15. Open decisions

- Exact minimum domain-required fields versus current copilot completeness fields
- Whether profile values need individual confirmation or only final review
- Initial language scope
