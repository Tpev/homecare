# October 3 payment recovery

This command is limited to Margaleine Tondo's October 3, 2026 visit for the Villano family: booking **239**, request **227**, payment **120**, caregiver user **349**, and family owner user **335**. It recovers payment for the existing recorded visit; it does not correct hours or resolve the separate September prepaid hold or October 17 booking conflict.

Preparing or deploying this command does not charge the family. The default command is a database-only preview. Only an operator's explicit `--apply` invocation can authorize and capture payment.

## Evidence and expected amount

The reviewed visit has **721 worked minutes (12 hours 1 minute)**. Preserve its stored check-in, checkout, timesheet-submission timestamps, and worked minutes. Do not use the admin time-correction interface to make this payment recovery.

| Item | Reviewed value |
| --- | --- |
| Family care amount | USD 360.50 |
| Family processing fee | USD 12.02 |
| Total family charge | **USD 372.52 / 37252 cents** |
| Caregiver gross | USD 324.45 / 32445 cents |
| Original PaymentIntent | `pi_3UK1bF6ewFB76RHF0L3C0G6p` |
| Original authorization | USD 446.40; expired and canceled |
| Audited Stripe customer | `cus_UzbLWUlGKpPlYP` |

The caregiver's final net depends on the actual Stripe processing fee. The total family charge is not her earnings. Capturing through the existing settlement flow may also transfer her net to her connected Stripe account automatically; a successful transfer does not by itself confirm arrival in her bank account.

The expired authorization cannot be captured by retrying it. Before creating a replacement, review the current database and Stripe history for any payment that succeeded after the original audit.

## Deploy and preview

Deploy only the reviewed files for this recovery through the normal application deployment process. The working checkout contains unrelated changes; do not include them in a blanket commit, copy, or deployment. Run the recovery tests before deployment. Do not run the apply command as a deployment hook.

The required application changes are:

- `app/Console/Commands/RecoverOct3Payment.php`
- `app/Services/Payments/Oct3PaymentRecoveryService.php`
- The recovery changes in `app/Services/Payments/BookingPaymentV2Service.php`
- The Stripe inspection payload fields in `app/Services/Payments/StripeClient.php`

Review the exact changes in shared files against the production base rather than copying unrelated working-tree changes along with them. The new regression tests are `tests/Feature/Payments/Oct3PaymentRecoveryTest.php` and `tests/Feature/Payments/RecoveredAuthorizationCaptureTest.php`. This recovery introduces no database migration.

The focused validation command passed **51 tests / 250 assertions** during preparation:

```bash
php artisan test --compact tests/Feature/Payments/Oct3PaymentRecoveryTest.php tests/Feature/Payments/RecoveredAuthorizationCaptureTest.php tests/Feature/Booking/AutoApproveTimesheetsCommandTest.php
```

From the deployed application's directory, at the operating-system shell rather than inside Tinker, run:

```bash
php artisan homecare:recover-oct3-payment
```

This does not call Stripe or change records. Review the complete output, including:

- The booking, payment, caregiver, and family identities above.
- The existing visit and timesheet evidence, with no unresolved dispute, correction, cancellation, or payment hold.
- `worked_minutes: 721`, `target_charge_cents: 37252`, and `caregiver_gross_cents: 32445`.
- The available administrator candidates and the `expected_state` SHA-256 fingerprint.
- Any existing recovery attempt or financial activity requiring investigation.

Keep the preview with the incident record. A changed fingerprint requires a fresh review of what changed, not merely copying a new hash to bypass the guard. Stop if any of the expected facts differ.

## Review approval and Stripe before applying

Choose an approval basis explicitly:

- **`auto-approval`:** use only when the existing accepted terms and 24-hour approval policy apply to this caregiver-submitted timesheet and the review finds no dispute or unresolved correction. This does not assert that Mary or Kyle explicitly approved the hours. The command checks the required policy conditions.
- **`family`:** use when actual family approval has been obtained and recorded in the incident evidence. Supply the eligible approving user's ID with `--approved-by`. Do not invent an approver merely to satisfy the option.

Inspect Stripe for the original PaymentIntent and every other authorization, charge, refund, dispute, and caregiver transfer linked to this booking or recovery. Confirm that no successful payment is being duplicated and that the saved customer and payment method belong to this family. Record the inspected object IDs and the time of the review.

`--provider-verified` is the operator's attestation that this review was completed. It does not perform or replace that investigation. If the outcome of an earlier attempt is unknown, follow the retry section before attempting any new authorization.

During apply, the service also checks the original intent is canceled with zero received and checks both the original and replacement intents against the audited Stripe customer, USD currency, and live mode. A mismatch stops recovery. These provider checks happen only during apply; the default preview remains database-only and makes no Stripe request.

## Apply the reviewed recovery

Generate **one UUID** for this recovery and save it with the reviewed preview and operator's reason. Replace all placeholders below. `ADMIN_ID` must be a valid administrator from the preview; `PREVIEW_HASH` is its reviewed `expected_state`; `STABLE_UUID` is the saved UUID. Do not generate a new UUID for a retry.

For the existing 24-hour auto-approval policy:

```bash
php artisan homecare:recover-oct3-payment --apply --admin=ADMIN_ID --expected-state=PREVIEW_HASH --request-id=STABLE_UUID --approval-basis=auto-approval --provider-verified --reason="October 3 booking 239: recover the expired authorization for the unchanged caregiver-submitted 721 minutes under the existing 24-hour auto-approval policy; reviewed Stripe history shows no prior capture."
```

If the basis is actual family approval, use `--approval-basis=family --approved-by=FAMILY_APPROVER_ID` instead and write a reason identifying the approval evidence. Do not run both variants.

The apply operation can move money. It is limited to the reviewed USD 372.52 family charge and retains the original visit times. The recovery audit records the approval basis after successful recovery; auto-approval must not be described as an explicit approval by Mary.

## Verify the result

Save the command's full output. Check the database preview and Stripe again:

- The replacement payment belongs to booking 239, and the total captured for this recovery is USD 372.52 exactly once.
- The original expired authorization remains part of the history; its USD 446.40 hold is not an additional captured charge.
- The original visit times and 721 worked minutes remain unchanged.
- The approval basis and recovery audit match the reviewed operation.
- Any caregiver transfer matches the computed net after actual Stripe fees. If capture succeeded but transfer or fee finalization remains pending, investigate that settlement stage without creating another family payment.

Use the connected account's payout record to verify bank payment separately. Do not report the family charge, caregiver transfer, and bank payout as interchangeable states.

## Interrupted or uncertain results

A timeout, exception, lost terminal, or missing success message does not prove that Stripe did nothing. Preserve the output, UUID, reviewed arguments, and any returned Stripe IDs.

1. Run the database-only preview and inspect Stripe before deciding what remains incomplete.
2. Reuse the **original request UUID and reviewed arguments** when resuming the same operation. Never use a new UUID to bypass an unresolved attempt or a guard failure.
3. When a replacement PaymentIntent ID is known, recovery retrieves that existing intent instead of creating another. Inspect and continue its existing state; do not start an independent charge in the Dashboard.
4. If authorization creation has an unknown outcome and **no replacement PaymentIntent ID is known**, an attempt older than **23 hours** must stop for manual Stripe investigation. Do not assume the original creation request is still protected by provider idempotency retention, and do not substitute a new UUID.
5. A payment requiring customer action, a decline, a state mismatch, or a manual-review stop needs the indicated investigation or family action. Do not alter the stored hours, clear audit metadata, or force statuses to make the command proceed.

If a charge is found in Stripe but is missing locally, reconcile that existing charge before any further payment attempt. Successful capture followed by incomplete local bookkeeping or caregiver transfer is not a reason to charge the family again.
