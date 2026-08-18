# Payments, Submitted Hours, and Recovery — Batch 4

Status: Implemented in source; production deployment and KB publication intentionally deferred for the joint Batch 3/4 review

Date: August 18, 2026

Production impact now: None. No deployment, production command, availability change, pilot-grant change, payment mutation, visit mutation, or user-session change was performed.

## Product decision

For the two-user pilot, AI Support uses this approved pricing truth:

- the Family pays **$30 per hour**;
- the caregiver earns **$27 per hour**; and
- LoLo receives **$3 per hour** as the platform portion.

These three values are the governed support truth even while other application pricing code awaits separate reconciliation. Batch 4 does not modify Stripe, booking prices, authorization, capture, refund, payout, timesheet, or correction domain behavior.

## Delivered scope

### Governed knowledge first

The new `payment-time-kb-v1` package contains 18 stable entries and 90 linked evaluation cases. It explicitly covers all 32 `FAM-PAY` intents, `FAM-VISIT-018` through `FAM-VISIT-029`, and `FAM-VISIT-034`.

The package covers:

- the exact `$30 / $27 / $3` pricing truth and explicit-duration calculations;
- request publication versus later authorization and capture;
- authorization holds versus final captured charges;
- secure saved-card changes and card-data boundaries;
- pending payment and card/bank authentication;
- normalized payment failure reasons and retry guidance;
- correction and completed-extra-visit payment attention;
- authorized, captured, refunded, and net-paid amounts;
- refunds, disputes, unfamiliar charges, receipts, invoices, and tax-document boundaries;
- discounts, billing ownership, and private caregiver-payout boundaries;
- submitted-hours review, recorded time, expected-time difference, tasks, and notes;
- correction comparisons, approval, changes requested, payment continuation, escalation, and dispute status.

The package supports Family retrieval throughout. Its pricing entry also supports Caregiver retrieval.

### Safe live-state reader

`FamilyPaymentTimeStateReader` re-authorizes every query through the signed-in Family Account. It returns only normalized support facts.

For payment attention it may return:

- the authorized care record and exact semantic destination;
- Family-visible authorized, captured, refunded, or additional-pending amounts;
- one safe reason category and simple recovery instruction; and
- a state hash for later verification.

The supported reason categories are:

- secure authentication required;
- insufficient funds;
- expired card;
- incorrect card details;
- card declined;
- payment method missing;
- authorization expired;
- additional authorization required;
- provider temporarily unavailable; or
- reason unavailable.

Raw `last_error`, Stripe/provider IDs, client secrets, card data, codes, and unmatched provider text never enter the support response. If no safe category matches, the truthful answer is that no safe specific reason is available.

For submitted hours it may return:

- caregiver and authorized care subject;
- recorded start, end, expected duration, worked duration, and difference;
- task labels, completion state, and Family-visible notes;
- whether the Family already confirmed the hours;
- latest correction status, proposed duration, difference, and Family charge; and
- the exact resource-authorized timesheet or payment-attention target.

### Older-adult response and navigation

Known payment failures now produce one short explanation, one recovery instruction, and one exact button. A correction needing payment goes to the payment control; an ordinary hours review goes to submitted hours. Regular-care records use the authorized regular-care attention target.

The assistant still does not collect card data, emulate authentication, silently retry a charge, approve hours, or declare success from page arrival. Existing secure UI controls remain authoritative.

### Deterministic pricing

After `KB-B4-PRICE-001` is published, Family and Caregiver pricing questions bypass the model. Exact calculations are allowed only when the user explicitly states a duration between one minute and 168 hours.

Examples:

- 2.5 hours: Family `$75.00`, caregiver `$67.50`, LoLo `$7.50`;
- 2 hours: Family `$60.00`, caregiver `$54.00`, LoLo `$6.00`.

The fast path is publication-gated. Deploying code without publishing the new pricing entry does not activate the new price answer.

## Publication operation

The package has a dedicated idempotent command:

```bash
php artisan ai-support:import-payment-time-kb
```

That command is plan-only. The later production publication command is:

```bash
php artisan ai-support:import-payment-time-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Batch 4 payment and submitted-hours knowledge for the two-user pilot." \
  --confirm=PUBLISH-PAYMENT-TIME-KB
```

The publication command can be run by either full Administrator alone. It publishes only the exact 18-entry manifest. Existing-content differences are refused. It does not change Availability, grants, payment records, visits, timesheets, or application sessions.

Do not run this production command until the user chooses the joint Batch 3/4 deployment window.

## Evaluation and proof

The source build includes:

- 18 exact knowledge definitions;
- 90 linked knowledge evaluation cases;
- structural proof that every approved payment and submitted-hours intent is mapped;
- draft-import, idempotency, exact-publication, and unchanged-pilot/control tests;
- exact `$30 / $27 / $3` reconciliation and calculation tests;
- Family and Caregiver runtime pricing tests with zero provider calls;
- safe normalized failure-reason tests that prove raw provider text is not shown;
- submitted-hours and schedule-difference tests; and
- existing state-matrix and Batch 3 operating-layer regressions.

The generated Family catalog now has 324 records and 197 explicitly KB-mapped intents. Batch 3’s historical Wave 1 count remains 190; the additional seven unique mappings come from Batch 4.

Final source verification on August 18, 2026:

- complete AI Support suite: **156 tests / 2,916 assertions**;
- isolated Family Batch 1–4 mass harness: **64 tests / 2,107 assertions**;
- mass routing: **45 / 45 intents**, **137 / 137 phrases**, and **10 / 10 collisions**;
- wider payment, Stripe, care-history, time-correction, timesheet, completed-extra-visit, and regular-care regression: **100 tests / 623 assertions**; and
- formatting and generated-catalog integrity: passed.

## Deliberately unchanged

- Production remains on the existing deployed commit until the joint review.
- The exact two-user pilot remains the only enabled audience.
- **Live for everyone** remains off.
- No database migration is required for Batch 4.
- No Stripe or application payment calculation was changed.
- No existing payment, visit, correction, refund, payout, or timesheet was written.
- The old readiness/preflight evidence system is not required for this release under `DEC-072`.

## Joint Batch 3/4 review

When an appropriate maintenance window is available:

1. review the combined Batch 3 and Batch 4 code and documentation;
2. run the full isolated AI Support and wider targeted regression suites;
3. commit and push the combined Batch 4 source to `master` without deploying during active shifts;
4. run the normal `deploy.sh` once;
5. publish the exact Batch 4 KB package with the command above;
6. test both pilot accounts for price, failed-payment reason, submitted-hours recap, exact navigation, secure recovery, and human transfer;
7. confirm Admin transcripts and KB entries; and
8. leave **Live for everyone** off.

After that joint review, the next implementation batch is Batch 5: care profiles and request lifecycle.
