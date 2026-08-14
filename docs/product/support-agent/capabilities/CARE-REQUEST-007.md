# Capability Specification: `CARE-REQUEST-007` — Publish a Confirmed One-Time or Recurring Request

Status: Implemented and evaluated; release disabled

Version: 1.0

Owner: Family care product

Required release reviewers: Product, engineering, security/privacy, support operations, design/accessibility

Last reviewed: August 14, 2026

Implementation evidence: [Interactive assistant implementation and release evidence](../24-interactive-assistant-implementation-and-release-evidence.md)

## 1. User outcome

After pressing **Confirm and create request** on a current recap, an authorized Family user can publish exactly that one-time or recurring request and receive an authoritative receipt.

The request goes directly live without human review. Publication does not hire a Caregiver, create a booking, or authorize payment.

## 2. Risk and controls

- Risk class: D — Execute
- Separate controls for capability, tool, one-time commit, recurring commit, role, exact user, model/runtime, and human ownership
- Mandatory action-specific server-bound confirmation
- Transactional domain write and idempotent reconciliation
- Global and per-capability kill switches

One-time publication enters pilot first. Recurring commit remains off until one-time evidence passes under `DEC-063`.

## 3. Commit input

Conceptual tool: `publish_confirmed_care_request`

Inputs:

- Opaque confirmation reference
- UUID idempotency key
- Exact action identifier

Do not accept client/model-supplied Family ownership, recipient/account ownership, material request fields, status, price, payment, notification result, or receipt values at commit. Resolve the normalized payload from the locked server preview.

## 4. Commit transaction

The service:

1. Resolves and locks the preview/draft and conversation.
2. Reauthorizes actor, Family membership, exact pilot, automated ownership, capability, request-type commit flag, and tool version.
3. Verifies reference hash, 30-minute expiry, draft version, material hash, and record versions.
4. Revalidates every current domain field.
5. Creates the open request and child task/recipient/contact records transactionally through approved maintained domain services.
6. Marks confirmation consumed and stores compact confirmed-action evidence.
7. Emits durable domain/audit events.
8. Returns an authoritative receipt.

The implementation must not reuse the retired legacy AI copilot path.

## 5. Publication effects

Match ordinary publication:

- Request is open and discoverable by eligible Caregivers.
- Existing operations new-request alert is sent after commit.
- Existing `care_request_published` funnel event is recorded.
- Family receives the authoritative request route.
- No mass Caregiver notification or user-visible **AI request** label is added.

Record restricted internal `origin: ai_support` provenance linked to conversation, action evidence, confirmation/idempotency, and receipt.

## 6. Receipt

Return and render only server-proven values:

- Outcome code
- Safe request reference
- Created timestamp
- Actor/action/idempotency references
- Recipient and schedule summary
- Safe **View request** destination

User copy:

> Your care request is live.

> Eligible caregivers can now see it. No caregiver has been hired yet, and no payment has been authorized yet.

## 7. Payment and pricing boundary

Publishing creates no PaymentIntent, payment record, booking, authorization, or capture. Authorization remains at Caregiver hire/booking creation; capture remains in the completed/approved-hours workflow.

Do not change or call pricing/payment/payout code in this capability. Customer price display remains held until the separate reconciliation project completes.

## 8. Failure and reconciliation

| Situation | Required behavior |
| --- | --- |
| Missing/expired/changed confirmation | No write; one-step fresh recap |
| Authorization or control denied | No write or leaked record; offer human/manual support |
| Domain validation failure | Return to exact draft section |
| Timeout/connection loss | Query by idempotency key before retry or outcome message |
| Duplicate click/two tabs/repeated request | Return original receipt; never duplicate |
| Operations email failure after commit | Report request live; retry/alert email separately |
| Audit/domain event cannot be made durable | Fail/reconcile under approved transactional event design; never silently lose evidence |
| Human transfer wins race | Invalidate confirmation; no commit |

Never queue an unconfirmed future write.

## 9. Events and metrics

Record action confirmed, tool proposed/completed/failed, domain creation, notification result, reconciliation, duplicate prevented, and request route opened. Metrics include confirm-to-receipt, success, validation, duplicate/reconciliation, notification failure, recap equality, cost, and user comprehension.

## 10. Evaluation and release

Zero-tolerance cases include unconfirmed action, forged reference, changed draft, cross-account substitution, removed member, revoked grant, disabled one-time/recurring flag, double click, two tabs, timeout after commit, event/notification failure, human-transfer race, model false-success attempt, and medical scope after recap.

Gates:

- 100% authorization, confirmation, idempotency, reconciliation, receipt, and transfer-race cases
- 100% equality between confirmed normalized recap and published record
- Zero duplicate requests and fabricated success
- Kill-switch rehearsal passes
- Every exact-user pilot request reviewed
- Recurring commit release only after the one-time gate passes
