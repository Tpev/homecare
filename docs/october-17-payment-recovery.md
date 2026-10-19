# October 17 visit and payment recovery

This recovery addresses one visit by Margaleine Tondo for the Villano family. The October 9 booking was canceled, but Margaleine recorded her October 17 care against that old booking. The October 17 booking has the valid authorization. These records represent one worked visit, not two billable visits.

The operator has confirmed that October 9 was canceled and the recorded **697 minutes (11 hours 37 minutes)** belong to October 17. The command requires that assignment confirmation explicitly and preserves its audit evidence. Preparing this implementation does not commit, push, deploy, change production records, or move money.

## Exact records and effect

| Item | Source record | Correct October 17 record |
| --- | --- | --- |
| Booking | 244 | 311 |
| Request | 238, scheduled October 9 | 317, scheduled October 17 |
| Payment | 126 | 175 |
| Stripe PaymentIntent | `pi_3UKiSN6ewFB76RHF0KKlFOqg` | `pi_3URf2A6ewFB76RHF0YL59lIw` |
| Original payment state | Expired, canceled; no capture | Existing USD 427.80 authorization |

The reviewed recorded times, in `America/New_York`, are:

- Check-in: **October 17, 2026 at 19:30:56**.
- Checkout and timesheet submission: **October 18, 2026 at 07:08:49**.
- Stored worked time: **697 minutes**, copied exactly without rounding or recalculation.

Apply copies this recorded visit evidence onto booking 311, accepts the source booking's pending cancellation requests **6 and 7**, and makes booking 244 permanently nonbillable through a recovery marker. Booking 244's original recorded timestamps and check-in/out events **825 and 826** remain intact as historical evidence, with an audit cross-reference to the recovered visit. Its retained hours must never be treated as a second amount due.

The target's missing caregiver-terms acceptance remains **NULL**. Recovery does not invent acceptance or create a new approval by Mary or Kyle. The administrator records the reassignment using the same family, caregiver, and identical saved pricing as the source agreement accepted in September. Timesheet approval uses the existing **24-hour automatic approval policy**, measured from the original October 18 submission; it is not recorded as explicit family approval.

| Financial item | Amount |
| --- | --- |
| Family care | USD 348.50 |
| Family processing fee | USD 11.62 |
| Capture from the existing authorization | **USD 360.12 / 36012 cents** |
| Caregiver gross | USD 313.65 / 31365 cents |

No new authorization or PaymentIntent is created. On the reviewed USD 427.80 authorization, the uncaptured remainder is USD 67.68. A release of that remainder is not a refund of the captured care payment. Caregiver net is computed using actual Stripe fees; the USD 360.12 family charge is not caregiver earnings.

## Read-only preview

Once the reviewed implementation is available in the application, run this at the operating-system shell from its directory, outside Tinker:

```bash
php artisan homecare:recover-oct17-payment
```

The default preview reads the database only. It does not contact Stripe, reassign the visit, accept cancellations, approve hours, or move money. Save the complete output and its `expected_state` fingerprint. Confirm the two record identities, exact stored times and minutes, unchanged pricing, cancellation evidence, and expected USD 360.12 capture. Investigate any changed state before applying; do not replace a stale fingerprint merely to bypass a guard.

## Fresh provider review

Before apply, inspect both payment histories in Stripe and any separate or manually created payment associated with this care:

- Payment 126's original intent is canceled and has received zero funds.
- Payment 175's existing intent belongs to the reviewed Villano customer, is live USD, and is still capturable for USD 360.12. There is no prior capture that would be duplicated.
- No other manual charge, refund, dispute, transfer, or completed recovery changes the conclusion that this single visit remains unpaid.

Record the object IDs and review time with the incident. `--provider-verified` attests that this investigation has been performed; it does not replace it. `--assignment-confirmed` attests the already reviewed October 9 cancellation and assignment of the 697 minutes to October 17. The service also checks its guarded database and provider conditions before capture.

If the existing authorization has expired or its state differs, stop and review the result. This command does not create a replacement authorization as a fallback.

## Prepared apply command

Apply can change visit records and capture money. Use administrator **1** only while that administrator remains the authorized operator. Replace `PREVIEW_HASH` below with the reviewed preview's `expected_state`. The fresh request UUID prepared for this incident is **`92793988-b03e-45c6-be1f-c5d927e56358`**; save it and the exact reason with the incident before the first invocation.

```bash
php artisan homecare:recover-oct17-payment --apply --admin=1 --expected-state=PREVIEW_HASH --request-id=92793988-b03e-45c6-be1f-c5d927e56358 --provider-verified --assignment-confirmed --reason="Confirmed October 9 was canceled and the original 697-minute timesheet belongs to October 17. Reassign booking 244 evidence to booking 311, keep source nonbillable, and capture USD 360.12 from the existing authorization under the original timesheet's 24-hour approval policy."
```

If recovery has already been started with different reviewed parameters, resume that existing request instead of using this prepared example. Do not change UUIDs or reasons to bypass an existing receipt.

Before capture or webhook settlement, the payment snapshot records the actual 697 minutes and the exact family and caregiver amounts above. Settlement uses the normal V2 charge, fee, and caregiver-transfer ledger. It may transfer caregiver net to the connected Stripe account; that is separate from a bank payout.

## Verify and resume safely

Save the full result and verify:

- Booking 311 contains the original October 17 check-in, October 18 checkout/submission, and 697 worked minutes.
- Booking 244 retains its historical timestamps and events but is permanently excluded from billing. Cancellation requests 6 and 7 are accepted, and both records contain the recovery audit linkage.
- Target caregiver-terms acceptance remains NULL, and the audit describes administrator reassignment and policy-based timesheet approval without attributing a new approval to the family or caregiver.
- Exactly USD 360.12 is captured on `pi_3URf2A6ewFB76RHF0YL59lIw`; no replacement intent or source-booking charge was created.
- Any caregiver transfer matches the normal ledger's net after actual Stripe fees. Verify the later bank payout separately.

A timeout, exception, or lost success message does not prove capture failed. Read the preview and inspect the **same existing PaymentIntent** before continuing. Retry only with the **same UUID and original reviewed options**. Do not create a new authorization, charge manually in Stripe, remove the source's nonbillable marker, or clear recovery metadata.

If capture succeeded but local bookkeeping, fee finalization, or caregiver transfer is incomplete, reconcile that existing captured payment. Do not charge the family again to repair a later settlement step.
