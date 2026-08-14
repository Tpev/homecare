# Capability Specification: `CARE-REQUEST-003` — Publish a Confirmed One-Time Care Request

Status: Superseded by `CARE-REQUEST-007`; retained for historical traceability only

Version: 0.1

Owner: Family care product

Required approvers: Product, engineering, security/privacy, support operations, design/accessibility

Last reviewed: August 13, 2026

Do not implement this document as current authority. The approved one-time and recurring publication contract is [`CARE-REQUEST-007`](CARE-REQUEST-007.md), governed by `DEC-048` through `DEC-066`.

## 1. User outcome

After reviewing a valid one-time care-request draft, an authorized family user can explicitly publish it and receive an authoritative request reference.

User-facing promise:

> LoLo will publish exactly the request shown in the preview after you press **Create this care request**.

Publication does not guarantee a caregiver response, hire, booking, or payment outcome.

## 2. Scope

### Supported users

- Active family owner
- Active family member authorized for shared operational care actions

### Preconditions

- `CARE-REQUEST-001` draft is valid and current.
- User has reviewed the deterministic preview.
- Draft represents one-time non-medical care.
- Current family/account authorization is valid.
- The conversation has not transferred to human support.
- The capability and commit tool are enabled for the user cohort.

### Unsupported actions

- Recurring-care publication
- Direct caregiver invitation
- Hiring or payment authorization
- Cancellation or changes to an existing request
- Medical care request

## 3. Risk and autonomy

- Risk class: D — Execute
- Side effect: Creates an open marketplace care request and may trigger normal LoLo notifications/operations events.
- Confirmation: Action-specific server-bound confirmation is mandatory.
- Reversibility: A request may have a later cancellation workflow, but publication is treated as material because it becomes visible and operational.

## 4. Deterministic preview

The server builds the preview from the normalized draft and current authoritative profile/task data. Display:

- **Who needs care**
- **Help needed**
- **Date** using an unambiguous full date
- **Start, end, duration, and timezone**
- **Care address**
- **Information that will be shared with potential caregivers**
- **What happens next**: the request is published; eligible caregivers may respond; no caregiver is yet hired
- Any known price or payment consequence only if supplied by the authoritative pricing/payment service; otherwise do not estimate

Primary action:

> Create this care request

Secondary actions:

- **Change something**
- **Talk to a person**
- **Use the regular form** when applicable

Do not use **Continue**, **Yes**, or a conversational “okay” as the initial commit control.

## 5. Confirmation contract

The preview service returns an opaque confirmation reference bound to:

- Authenticated actor
- Family account
- Capability and tool version
- Draft ID and version
- Hash of every material normalized field
- Current relevant record versions
- Request type
- Expiration
- Idempotency key

Confirmation is invalid when:

- Any material field changes
- The actor, family membership, or authorization changes
- The draft expires or is abandoned
- The capability/tool is disabled
- Transfer to human support begins before commit
- The confirmation was already consumed

The server must not accept a preview hash created by the model or client.

## 6. Commit tool contract

Conceptual tool: `publish_one_time_care_request`

Inputs:

- Confirmation reference
- Idempotency key or request reference issued by the server

Inputs must not include client/model-supplied family ownership fields.

The service:

1. Resolves the actor and active family context.
2. Locks or otherwise protects the draft/confirmation from concurrent use.
3. Revalidates authorization, capability, draft version, and domain fields.
4. Creates the care request and child records transactionally through the approved domain service.
5. Marks the confirmation consumed.
6. Emits durable action and domain events.
7. Returns an authoritative receipt.

Receipt:

- Success/failure status
- Care-request ID and safe reference
- Created timestamp
- Actor reference
- Idempotency reference
- Safe next-route target
- Normal notification/event status as needed

## 7. Success response

Build the response from the receipt, not model memory:

> Your care request was created successfully.

Primary action: **View request**

Optional explanation:

> Caregivers can now review the request. No caregiver has been hired yet.

Never claim that a caregiver was booked or that payment was completed.

## 8. Failure and reconciliation

| Situation | Required behavior |
| --- | --- |
| Preview expired/changed | Show fresh preview and require confirmation again |
| Authorization denied | No write; do not reveal unrelated records; offer human support |
| Domain validation fails | Return to draft with the exact correctable field |
| Commit timeout | Query by idempotency key before retrying or claiming failure |
| Duplicate click/retry | Return the original successful receipt; create no duplicate |
| Notification fails after record creation | Report request creation truthfully; alert/retry notification separately |
| Event/audit cannot be durably recorded | Fail or reconcile according to approved event-integrity design; never silently lose evidence |
| Transfer to human begins before commit | Invalidate pending confirmation and hand off the preview/draft |

## 9. Safety and escalation

- Re-run restricted-domain and non-medical validation before commit.
- If new user text changes the care scope after preview, invalidate confirmation.
- Do not publish as a workaround for an existing visit no-show, dispute, or urgent active-care problem.
- Escalate repeated failure, disagreement over the preview, authorization uncertainty, or user request.

## 10. Events and metrics

Required events:

- `action_previewed`
- `action_confirmed`
- `tool_proposed`
- `tool_completed` or `tool_failed`
- Care-request domain creation event
- Notification result
- User opened receipt destination

Metrics:

- Preview-to-confirm rate
- Preview correction rate
- Confirmation invalidation reasons
- Publish success and validation failure
- Duplicate prevention/reconciliation
- Time from confirmation to receipt
- User comprehension that request is published but not hired
- Cost per safely published request

## 11. Evaluation requirements

Zero-tolerance cases:

- Commit attempted without confirmation
- Client/model fabricates or changes confirmation reference
- Draft changes after preview
- Removed member confirms from stale page
- Cross-account draft/confirmation ID substitution
- Double click, two tabs, repeated HTTP request
- Timeout after record creation and retry
- Transfer to human support between preview and commit
- Tool returns failure and model tries to claim success
- Notification fails after successful creation
- Event/audit failure
- Medical scope appears after preview

Gates:

- 100% authorization, confirmation, idempotency, receipt, and transfer-to-human cases pass.
- Zero fabricated-success statements.
- User research shows no critical confusion between “request published,” “caregiver hired,” and “visit booked.”
- All `CARE-REQUEST-001` dependencies pass.

## 12. Rollout and rollback

- Depends on released `SUP-HANDOFF-001`, `CARE-REQUEST-001`, and approved preview/event contracts.
- Historical initial plan called for shadowing proposed commits; `DEC-047` later rejected that path. Current authority is `CARE-REQUEST-007` and `DEC-063`.
- Then internal accounts and an opt-in limited cohort.
- Separate feature switches for preview and commit.
- Disabling commit leaves draft review, manual form, and human chat available.
- Rollback invalidates pending confirmations and preserves completed requests and receipts.

## 13. Open decisions

- Final domain service used instead of directly reusing the current `PublishCareRequestService`
- Confirmation lifetime and exact preview versioning
- Notification and operations behavior for agent-created requests
- Event durability design when domain write succeeds but event recording fails
