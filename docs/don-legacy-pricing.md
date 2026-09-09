# Don Johnson's caregiver agreement

Don pays **$15.75 per worked hour total**, his agreed caregiver receives **$15.00 per worked hour**, and LoLo bears Stripe processing costs. There is no additional family processing fee. For booking #159's 133 minutes this means **$34.91 charged to Don**, **$33.25 paid to the caregiver**, and **$1.66 retained before Stripe processing costs**.

## Local change and production activation

The code change alone does not activate the agreement on production. An authorized operator must deploy the reviewed change, run its additive migration, then register the exact family/caregiver pair using the command below. This task has no permission to connect to production.

The agreement is stored by family account ID and caregiver user ID, not by a mutable email address. Its source booking ID is also its inclusive lower boundary: `--booking=159` repairs #159 and eligible future scheduled bookings with IDs at least 159. Lower booking IDs are excluded even when scheduled in the future. New one-time, recurring, extra, and continuous-coverage bookings copy the agreed family rate, caregiver rate, and processing-fee policy into their own pricing snapshot. Existing snapshots retain their rates if the agreement or standard rates later change. Another caregiver does not inherit this agreement.

The existing email-only legacy override is insufficient: it has no caregiver pay or processing-cost policy. It remains for old billing compatibility; registered pair agreements govern new booking snapshots.

Customers without a registered pair agreement retain their existing behavior, including recurring-plan rates and family-level legacy rate overrides. Regression tests exercise another family's custom rate, another plan's saved rate, the same caregiver serving a different family, and Don booking a different caregiver.

After registration, an unpaid booking for this pair at or above the source booking ID that still lacks its agreement snapshot is blocked from authorization or capture until repaired. This also protects a visit missed by the repair or created concurrently with activation. Bookings below the cutoff retain their existing pricing and payment behavior. Existing agreement snapshots continue to use their saved rates if the standard pricing version or rollout flag changes.

## Operator steps

1. Deploy through the normal reviewed release procedure and apply the additive migration `2026_09_09_000001_create_care_pricing_agreements`. Restart application workers through that procedure.
2. Run this read-only preview on the application host:

   ```bash
   php artisan homecare:repair-don-pricing --booking=159
   ```

   Verify that the named family is Don and that the caregiver is the person who agreed to receive $15/hour. The command verifies Don's existing account email and prints the caregiver's user ID, source visit, future scheduled visits at or above the cutoff, corrected totals, and any in-scope records requiring separate review. With `--booking=159`, confirm that no booking below #159 appears. It makes no Stripe calls.
3. Register the agreement and repair eligible booking/payment snapshots, substituting the verified IDs:

   ```bash
   php artisan homecare:repair-don-pricing --booking=159 --apply --admin=ADMIN_USER_ID --caregiver=VERIFIED_CAREGIVER_USER_ID
   ```

   This requires an existing administrator and the exact caregiver from the preview. It is idempotent and records the original pricing, replacement pricing, cutoff, reason, and administrator in booking audit events. Repeat runs must use the same source booking ID to preserve the agreement's cutoff. It preserves visit times, original authorization IDs and amounts, financial operations, and ticket state. It does not capture, refund, transfer, notify anyone, or mark family approval. Captured, refunded, legacy, uncertain, or otherwise ineligible records are reported and skipped; a nonzero exit indicates that separate review remains. The agreement still protects future new bookings when an old visit is skipped.
4. Reopen support ticket #52 and verify **133 minutes, $15.75/hour, $34.91 family charge, $33.25 caregiver payout**. Keep the verified visit times. Confirm actual payment status before applying the existing correction flow. If nothing has been captured, the target is a $34.91 capture, not a $33.81 refund. If money has since settled, reconcile it through the existing audited correction process with the right rates before notifying or resolving the ticket.
5. Audit any in-scope historical booking IDs printed by the command for incorrect charges. Bookings below #159 are outside this repair. The repair deliberately does not rewrite settled history. Verify a newly generated future visit has family rate 1575 cents, processing fee rate 0, caregiver rate 1500 cents, and policy `platform_pays_processing`.

## Verification

Run the focused agreement tests and payment/recurring regression suites against the isolated test database and Stripe bypass configured in `phpunit.xml`:

```bash
php vendor/bin/phpunit tests/Feature/Payments/DonLegacyPricingTest.php tests/Feature/Payments/StripeMarketplacePaymentTest.php tests/Feature/RegularCare/RegularCarePlanFlowTest.php tests/Feature/RegularCare/CompletedExtraVisitTest.php
```

Tests cover exact charges and transfers with nonzero processing fees, previews without writes, existing future visits, booking-ID cutoff isolation (including earlier IDs scheduled in the future), repeated repair, settled-payment exclusions, caregiver identity checks, pair isolation, stable snapshots, recurring generation, and completed extra visits.
