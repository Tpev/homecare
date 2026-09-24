# Prepaid visit recovery

Local implementation for the confirmed accidental completion of request 211 / booking 227 / payment 109 / support ticket 65. Deployment and production application require separate approval. Preparing this code does not put the live payment on hold.

## Intended result

Reopen the same future one-time visit, retain its captured payment, preserve the original tracking and financial evidence, and hold all money movement until an administrator reviews the actual completed visit. Request, caregiver, family, schedule, pricing, and accepted terms remain unchanged. No Stripe write, public reply, internal message, or ticket resolution is performed by the recovery command.

The hold is stored under `care_booking_payments.metadata.prepaid_visit_recovery`. No migration is required. It must only be written by `PrepaidVisitRecoveryService` after the payment guards have been deployed.

## Guarded flow

1. Preview is read-only and returns the exact booking/request/people/payment IDs, Eastern schedule, retained captured amount, and a SHA-256 state fingerprint.
2. Applying requires an administrator, exact ticket, UUID operation key, that fingerprint, an evidence-based reason, confirmation that no care occurred, and confirmation that Stripe was independently checked for capture/refunds/disputes/transfers. Records are reloaded and locked in booking/payment order; drift aborts the operation.
3. The existing tracking is snapshotted in an immutable correction receipt, then reset. Original booking events and care notes remain. The ticket is linked to the exact booking but stays open. The payment and operation ledger retain their amounts and references.
4. The held visit cannot start before its scheduled start. Caregiver check-in and completion work normally after that time. Family confirmation records approval of the actual hours without another charge. Automatic timesheet approval skips the held visit.
5. Manual transfer retries, hourly transfer retries, 15-minute reconciliation, and payment webhook finalization cannot transfer the held money. Ordinary corrections, refunds, and additional charges are blocked for this held payment.
6. A separate `--release` preview requires a real completed visit after reset, consistent elapsed/worked/break time, explicit family approval, unchanged financial evidence, and an actual bill AND caregiver net amount that exactly match the payment. If hours change the bill, the hold stays in place for a separately approved adjustment. The separate [held adjustment workflow](prepaid-visit-adjustment.md) handles a latest family-approved positive adjustment without releasing transfers. A later release validates that adjustment receipt, both charges and the actual approving family member.
7. Applying a reviewed release does not itself call Stripe, but makes normal transfer jobs eligible to pay the caregiver. Treat release approval as approval of that payment consequence. It never creates a new family charge.

The command only supports the current one-time payment ledger, one original successful charge, finalized fees, and no refund, transfer attempt, dispute, overage, active/failed correction, paid payout, or prior recovery. Uncertain states stop for review. A reset is refused once the scheduled visit has started or if the booking is reviewed, cancelled, disputed, replaced, or marked no-show.

An initial manual authorization can remain `pending` with amount zero because `recordAuthorization()` uses `firstOrCreate()` before and after client confirmation. Recovery recognizes this historical placeholder only when it has no processing/failure timestamp or error, belongs to the same booking/reference/currency, and its nonempty PaymentIntent ID matches both the payment and the successful primary charge's parent ID and metadata. The charge must be the sole charge and match the captured amount and primary Stripe charge ID. Other pending or failed operations still block recovery, as do all transfer/refund/dispute operations regardless of status.

The preview lists accepted placeholder operation IDs in `authorization_placeholders_covered_by_capture`. Their rows remain unchanged and remain included in the state fingerprint, financial hold fingerprint, and correction snapshots. The exception applies to both reset and subsequent release; it does not repair the general authorization writer, change financial amounts, call Stripe, or replace the required independent Stripe verification. Blocked operations now report their exact ID, type, and status.

## Deployment and production preflight — NOT EXECUTED

- Obtain approval to deploy the reviewed files and approval to apply the exact production preview. Existing unrelated working-tree changes are not part of this repair.
- Install all payment guards and the command together. Restart/drain long-lived queue and scheduler workers so no old worker can bypass the new guards. Allow existing financial requests to finish before applying; row locks coordinate processes running the updated code, not older deployed code or external Stripe Dashboard actions.
- Verify production request 211 still maps to booking 227, caregiver 349, family 335, and payment 109; ticket 65 must belong to caregiver 349 and point to request 211.
- Recheck the original Stripe charge and payment ledger: captured USD 372.00, refunded USD 0.00, no transfer or dispute. Stripe's pending balance availability date is not a payout hold. `--provider-verified` is an explicit operator attestation, not an automated Stripe reconciliation.
- Resolve the actual approving admin user ID from production. Do not infer it from a name or use a test user ID.
- Confirm the displayed schedule is September 23, 2026 19:00 to September 24, 2026 07:00 **America/New_York (EDT)**. Do not convert it to fixed UTC-5; New York observes daylight time on these dates.

Read-only preview (replace the admin placeholder):

```text
php artisan homecare:recover-prepaid-visit 227 --ticket=65 --admin=ADMIN_ID
```

Only after explicit approval of that preview, supply its exact fingerprint and a fresh UUID:

```text
php artisan homecare:recover-prepaid-visit 227 --ticket=65 --admin=ADMIN_ID --apply --confirm-no-care --provider-verified --expected-state=PREVIEW_HASH --request-id=UUID --reason="Ticket 65: caregiver confirmed no work occurred for the September 23 overnight visit. Preserve the captured USD 372 payment and hold it pending actual care and family approval."
```

If the result is uncertain, rerun with the SAME UUID. Successful receipts are returned without resetting the visit again. Never generate a new operation key to work around an uncertain result or failed eligibility check.

Post-apply verification: same scheduled visit and identifiers; tracking/approval cleared; captured amount and Stripe references unchanged; one successful `reopen_prepaid` correction; hold active; zero new charge/refund/transfer operations; ticket still open and no messages sent. Check the caregiver can see the scheduled visit and the admin ticket shows the hold banner.

## After actual care — separate future approval

The family approves actual hours through the normal visit screen. The hold persists. Preview release using:

```text
php artisan homecare:recover-prepaid-visit 227 --ticket=65 --admin=ADMIN_ID --release
```

Review the new evidence and preview. Only after separate approval and another Stripe check, apply with `--release --apply --provider-verified`, the new fingerprint, a new UUID, and the actual approval reason. The original reset's UUID must not be reused for release.

Do not remove the hold manually, mark the original payment uncaptured, delete its ledger, or refund/rebook just to bypass an eligibility failure. If the actual bill differs or the family requests a refund, leave the hold and prepare a separate financial correction with an explicit approved amount.

## Testing boundaries

Feature tests use an in-memory SQLite database and the existing Stripe bypass or a mock that rejects every provider call. They cover the real caregiver/family component flow and unchanged-payment release, automatic jobs, stale model instances, preview drift, wrong actors/tickets, missing confirmations, unsafe ledger states, and idempotent replay. Regression tests cover existing booking corrections and payment flows.

Local validation completed: **92 tests passed, 483 assertions** across `PrepaidVisitRecoveryTest`, `BookingCorrectionServiceTest`, and `StripeMarketplacePaymentTest`. This includes the stale authorization reset/release path, preserved ledger snapshots, changed-preview rejection, and mismatched or unsafe financial evidence. Laravel Pint passed for the changed PHP files, and the tracked patch passed `git diff --check`.

SQLite tests verify behavior but do not simulate MySQL row-lock contention. Production worker draining, provider verification, and a freshly approved preview remain deployment prerequisites. No production verification or money movement is part of local tests.
