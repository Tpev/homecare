# Approved extra hours on a held prepaid visit

Local implementation for booking 227, payment 109, approved time correction 16 and its support ticket 66. Implementing and testing this code does **not** approve deployment, a live charge, payout release, notifications, or ticket resolution.

The production evidence supplied on September 24 records the successful original recovery (receipt 21), an active hold, $372 captured, zero refunds and no recorded transfer. Actual tracking was September 23 19:00:54 through September 24 07:25:33 Eastern (744 whole minutes). Correction 16 approves 19:00 through 07:25 with no break (745 minutes), approved by family member 378. The displayed family total is $384.92: an additional $12.92. Production preview must verify these amounts again.

## Review and apply

After separately approved deployment, run this read-only preview:

```bash
php artisan homecare:adjust-prepaid-visit 227 --ticket=66 --time-correction=16 --admin=1
```

The preview checks the actual completed visit, unchanged original payment ledger and recovery fingerprint, unpaid payout record, exact support ticket, latest immutable family-approved correction, current family-account access, overlaps and price. It displays explicit Eastern dates, the existing capture, target total, exact additional cents, caregiver gross, and a state fingerprint. Final caregiver net depends on Stripe's actual additional processing fee, so preview does not represent it as final. There are no Stripe calls or writes, local writes, messages or ticket changes during preview.

Review the preview and independently verify the original charge, refunds, disputes and absence of transfers in Stripe. Only after explicit approval of the exact charge, substitute the fingerprint and a new UUID below. Do not execute this template as written:

```bash
php artisan homecare:adjust-prepaid-visit 227 --ticket=66 --time-correction=16 --admin=1 \
  --apply --provider-verified --expected-additional-cents=1292 \
  --expected-state=REVIEWED_PREVIEW_HASH --request-id=STABLE_UUID \
  --reason="Ticket 66: family approved correction 16 for 745 minutes. Retain USD 372 and collect only USD 12.92; keep caregiver transfers held."
```

The service commits a durable reservation before contacting Stripe, locks the visit and payment during application, and preserves the original capture, processing fee, earning and authorization-placeholder entries. It records the exact approved timestamps and the real approving family member, preserving tracking/location evidence in the original fields and immutable before/after receipts. It records the additional charge once, recalculates earnings using finalized Stripe fees, updates the unpaid payout record and marks correction 16 applied when reconciliation is complete. The receipt's immutable caregiver delta is the preview amount before the additional charge fee; `provider_payload.earnings` records the actual finalized net and delta. The support ticket remains open, no notifications or messages are sent, and the payment hold stays active throughout.

The same UUID only returns its existing receipt; it never dispatches another charge, including after Stripe's idempotency retention window. A different UUID cannot bypass a reservation. A crash or response loss leaves the reservation blocking further charge attempts and payout release. An unknown outcome with no stored PaymentIntent ID requires separate provider investigation using the receipt UUID in Stripe metadata; this command deliberately cannot create another intent to resolve that uncertainty.

## Known charge needing reconciliation

If the additional charge is known but its processing fee is pending, or a known PaymentIntent requires family authentication, the receipt remains unfinished and the hold stays active. The command returns a failure exit status with the receipt UUID; that does not by itself mean the card was not charged. Do not issue a new UUID or retry an ordinary correction. Do not mark the ticket resolved or tell the caregiver they have been paid.

After reviewing the known intent in Stripe (and separately arranging any required card authentication), explicitly reconcile the **same** receipt:

```bash
php artisan homecare:adjust-prepaid-visit 227 --ticket=66 --time-correction=16 --admin=1 \
  --apply --reconcile --provider-verified --request-id=ORIGINAL_STABLE_UUID
```

Reconciliation only retrieves the stored PaymentIntent; it never creates or confirms a charge. It validates the exact amount, provider identity and unchanged local checkpoint before finishing ledger records. Unexpected changes require separate review. Webhook delivery cannot misclassify this reserved adjustment as the original charge or release the hold; the approved reconciliation step reads its provider outcome. Refund and dispute evidence continues to be recorded and invalidates the checkpoint.

## Separate payout release

After successful adjustment, independently review Stripe and caregiver account readiness. The original account-readiness error can be stale while the hold is active. Preview the release on the correction's ticket:

```bash
php artisan homecare:recover-prepaid-visit 227 --ticket=66 --admin=1 --release
```

The release validator accepts exactly the original charge plus this successful audited adjustment, matching the corrected visit, actual family-member approval, total captured, final fees and earnings. It refuses drift, other financial operations, unfinished corrections or paid payout records. It shows the final caregiver net. The original financial fingerprint remains in the hold history; a separate fingerprint records the completed adjustment.

Applying release requires its own explicit approval, fresh fingerprint, new UUID and provider verification through the existing recovery command. Release itself performs no Stripe write, but makes normal transfer jobs eligible to move money. Ticket resolution and messages are separate authorized actions after verifying the result.

## Validation boundaries

Tests use SQLite in memory and mocked/bypass Stripe. They cover the production timestamps, shared family approval, capture/fee evidence preservation, exact additional cents, duplicate and uncertain attempts, known-intent reconciliation, hold protection, webhook behavior and separately approved release. SQLite cannot validate MySQL lock contention or production Stripe account readiness. Deploy all service and command changes together and drain/restart long-lived workers before live application.

Local validation on September 24: **150 tests passed, 799 assertions** across `PrepaidVisitAdjustmentTest`, `PrepaidVisitRecoveryTest`, `BookingCorrectionServiceTest`, `CareBookingTimeCorrectionTest` and `StripeMarketplacePaymentTest`. Tests ran against a clean archive of committed code with only this patch and an in-memory SQLite configuration. Laravel Pint and the tracked diff whitespace check passed. The tested implementation/test files were hash-checked against the workspace. No production command or Stripe live request was executed.
