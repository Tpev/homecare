# Don Johnson's account-wide pricing

Updated October 25, 2026 for support ticket #88 and booking #322.

Don pays **$15.75 per worked hour total with every caregiver**. Each caregiver receives **$15.00 per worked hour**. LoLo pays Stripe processing costs; neither hourly amount has an extra processing deduction or surcharge. Amounts are prorated to approved minutes and rounded to cents per visit.

## Verified history and current incident

- Don Johnson is user #275, family account #7. Original agreement #1 was scoped to Madison Fragnito (#34), with source booking #159.
- Ticket #52 and booking #159 confirm the split: 133 minutes cost Don $34.91 and pay the caregiver $33.25.
- Booking #322 / request #323 is Caroline Petrini-Poli (#40), October 27, 12:20–14:20 Eastern. It was still scheduled and unstarted when investigated on October 25.
- The request application showed $15.75 from an email override, but the booking fell outside the Madison-only agreement and saved standard rates: $30 care + $1 processing, caregiver $27. The application amount was not an authoritative billing snapshot.
- Stripe PaymentIntent `pi_3UTPP26ewFB76RHF1raHaCt0` was `requires_capture`, with $74.40 authorized and $0 received. This is a hold, not a captured charge. The old admin screen incorrectly read legacy amount fields and showed $0 authorized.
- Correct two-hour amounts for #322 are **$31.50 family total** and **$30 caregiver earnings**. With the existing 20% buffer, a newly created two-hour authorization is $37.80.

## Implementation and activation

The additive migration `2026_10_25_000001_add_family_wide_care_pricing_agreements` adds an account-wide scope. Existing pair agreements remain unchanged until explicitly extended. The original caregiver and source booking remain as historical provenance. Account-wide agreements take priority over pair agreements and apply to every caregiver, including new one-time, recurring, extra, and Continuous Coverage visits.

The code and migration alone do not extend Don's live agreement. After the reviewed release is deployed and migrations run, an administrator can use **Apply account pricing and repair uncharged visits** on [ticket #88](https://carelolo.com/admin/support/tickets/88). This uses the same audited, idempotent repair as the command below. It does not send a support reply or resolve the ticket.

Alternatively, an authorized operator can preview and apply:

```bash
php artisan homecare:repair-don-family-pricing --booking=322
php artisan homecare:repair-don-family-pricing --booking=322 --apply --family=7 --admin=VERIFIED_ADMIN_USER_ID
```

Verify Don, account #7, booking #322 and its caregiver in the preview. The apply command requires an administrator and the reviewed family account. It extends the existing agreement and repairs eligible unpaid booking/payment snapshots across the family, including earlier booking IDs. It corrects each linked application's caregiver rate to $15 and records previous values, administrator, agreement and existing authorization in audit events. A repeated run does not duplicate repairs or audit events.

Captured, transferred, refunded, cancelled, uncertain, legacy-payment or pending-correction records are not rewritten. Skipped candidates are reported for separate review; the command returns nonzero if any remain. Settled historical payments are preserved. A missed or stale booking is blocked from authorization and capture until its agreed pricing is repaired.

Existing authorization IDs and actual held amounts remain unchanged. The $74.40 hold on #322 must stay accurately represented until capture, cancellation or expiration. The normal capture flow bills only approved minutes at $15.75/hour and pays $15/hour to the caregiver. Do not use “complete and bill” to repair this future visit, and do not edit Stripe independently of the application ledger.

The repair changes no visit times, completion status, approvals, ticket state, notifications, refunds or transfers. Prepaid care hours continue to exclude special pricing agreements through their existing eligibility rule.

## Display checks after activation

Verify all four saved pricing values on #322: family 1575 cents/hour, family processing 0, caregiver 1500 cents/hour, policy `platform_pays_processing`. Its booking and payment must reference the account-wide agreement. Confirm the visit remains scheduled and the captured amount remains zero.

The family request form, caregiver profile, booking details, approval, book-again and active plan estimates use the agreement or saved booking snapshot. Caregiver invitations, applications, job listings, work inbox, visit tracker, recap and earnings use caregiver compensation separately. Processing text must state that LoLo covers it. Admin request details show both rates and real authorization/capture amounts. Historical settled visits retain their saved terms.

## Release boundary and validation

Committing and pushing this change does not deploy it or apply the live #322 repair. Use the normal authorized release process and the explicit agreement activation above. No assistant SSH or production-server access was used.

The workspace also contains earlier, unrelated changes. Do not deploy the whole working tree without its normal review. The prepaid source manifest must match the reviewed shared-file changes, and deployment must retain existing screening/prepaid compatibility guards.

Focused verification:

```bash
php vendor/bin/phpunit tests/Feature/Payments/DonFamilyPricingTest.php tests/Feature/Payments/DonLegacyPricingTest.php tests/Feature/Payments/StripeMarketplacePaymentTest.php tests/Feature/RegularCare/RegularCarePlanFlowTest.php tests/Feature/RegularCare/CompletedExtraVisitTest.php tests/Feature/Caregiver/CaregiverWorkInboxTest.php tests/Feature/Caregiver/CareRequestInvitationTest.php
```

Validation of the working checkout on October 25: 142 regression tests / 1,446 assertions passed across pricing, caregiver, admin, recruitment, coverage and care-hours suites; the final pricing/admin rerun passed 12 tests / 131 assertions. PHP syntax checks passed for 14 pricing-related files. The pricing-only commit was then tested on a clean copy of master, with 31 tests / 272 assertions passing across DonFamilyPricingTest, CaregiverWorkInboxTest and CareRequestInvitationTest.

Tests use the isolated SQLite test database and Stripe bypass, covering rates, minute rounding, processor costs, preserved holds and settled history, idempotency, wrong-account/admin checks, stale-pricing guards, account isolation, invitations, recurring generation, coverage estimates and family/caregiver/admin views.

## Historical pair-only tooling

`homecare:repair-don-pricing --booking=159` remains the historical pair-only repair. It uses #159 as an inclusive booking-ID cutoff and cannot extend the policy to other caregivers. Use the new account-wide command for ticket #88. `homecare:audit-don-visits --booking=159` remains a read-only all-caregiver visit/payment report; it does not change rates, cancel duplicates or reconcile processor state.
