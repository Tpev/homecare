# Application test evidence — September 8 implementation

The executed selections cover **224 distinct tests / 1,687 assertions**, all passing in their final recorded runs. This is a combined result from selected suites, not a run of the entire application test suite. The table counts each test file once, using its latest relevant result; repeated development runs and intermediate failures are excluded.

## Disjoint results

| Test selection | Passed tests | Assertions | Evidence and provenance |
| --- | ---: | ---: | --- |
| Existing request, privacy, booking operations/corrections, payments, messaging and caregiver invitation compatibility — eight files listed below | 112 | 698 | [Original shell log](compatibility-tests.log), 71.04 seconds |
| `Family/CaregiverInvitationExperienceTest.php` | 18 | 159 | [Original shell log](family-invitation-tests.log), 11.04 seconds |
| `Family/FamilyCareExperienceTest.php` | 10 | 93 | [Original shell log](family-care-experience-tests.log), 9.26 seconds |
| `AiSupport/FamilyGuidedAssistanceTest.php` and `AiSupport/FamilyGuidedAssistanceStateMatrixTest.php` | 19 | 195 | [Original shell log](guided-assistance-compatibility-tests.log), 28.09 seconds |
| `RegularCare/RegularCarePlanFlowTest.php` | 28 | 227 | [Agent tool-output transcription](recurring-and-correction-test-evidence.md), 41.18 seconds |
| `Booking/CareBookingTimeCorrectionTest.php` | 21 | 143 | [Agent tool-output transcription and derivation](recurring-and-correction-test-evidence.md); executed with an earlier seven-test recurring home suite |
| `RegularCare/RecurringCareHomeTest.php` | 9 | 126 | [Agent tool-output transcription](recurring-and-correction-test-evidence.md), latest home-only run, 23.47 seconds, chunk `1c136c` |
| `Family/CareWorkspaceNavigationTest.php` | 7 | 46 | [Original shell log](workspace-navigation-tests.log), latest run after applicant ordering fix, 27.18 seconds |
| **Total** | **224** | **1,687** | **16 distinct test files** |

The first row is the exact combined command:

```text
php artisan test --compact tests/Feature/Family/CareRequestFlowTest.php tests/Feature/Marketplace/PrivateCareRequestTest.php tests/Feature/Booking/BookingTrustOpsTest.php tests/Feature/Booking/BookingCorrectionServiceTest.php tests/Feature/Booking/AutoApproveTimesheetsCommandTest.php tests/Feature/Payments/StripeMarketplacePaymentTest.php tests/Feature/Messaging/CareRequestMessagingTest.php tests/Feature/Caregiver/CareRequestInvitationTest.php
```

`BookingCorrectionServiceTest.php` and `CareBookingTimeCorrectionTest.php` are different files and are each counted once. The plan agent executed time correction together with the earlier recurring home suite: **28 tests / 248 assertions**. At that time recurring home contributed **7 / 105**; correction's **21 / 143** attribution is the difference, not a separately printed run total. The latest home-only **9 / 126** replaces both that older contribution and the later **8 / 116** result. The new test checks that in-progress and paused visits appear before the queue, required decisions precede optional older reviews, and persisted state is unchanged. The combined total therefore does not count earlier home tests twice.

The four existing-flow/guided-assistance rows total **159 / 1,145**. The three recurring/correction rows add **58 / 496**. The latest workspace contract run adds **7 / 46**. Creation-only probes, earlier workspace runs, the initial compatibility failure run and filtered test reruns are not added again.

### Workspace result provenance

The latest `php artisan test --compact tests/Feature/Family/CareWorkspaceNavigationTest.php` run followed the applicant ordering fix. Its [saved shell log](workspace-navigation-tests.log) records:

```text
Tests:    7 passed (46 assertions)
Duration: 27.18s
```

This saved result supersedes both the earlier seven-test / 46-assertion tool-output transcription from exec session `34533` and the preceding six-test / 42-assertion result. Those older runs are not added to the total. No test was rerun solely to produce this evidence note.

## What this verifies

The existing-flow selection exercises care creation, recipient/contact persistence, saved profiles, one-time and recurring timing, private eligibility, hiring and failure recovery, booking state repair, messaging, submitted hours, reviews, payment boundaries and booking corrections. The migrated failed-hire and timesheet tests now reach their existing database/payment assertions through the new decision review controls. Family invitation tests cover the request-specific invitation experience. Family experience tests retain card identity, filtering and navigation coverage.

Guided-assistance tests check authorized resource routes, attention states and registered targets across current family screens. The recurring selections cover plans, scheduling, exact visit links and correction behavior. Workspace contracts cover the redesigned navigation and decision previews. Refer to the individual test files and [detailed compatibility report](compatibility-summary.md) for the precise assertions; these results do not imply that every possible business state or browser action is covered.

## Preservation of the user's existing tests

The original `tests/Feature/Family/CareRequestFlowTest.php` was saved before the six necessary UI expectation migrations at `.tmp/find-care-screen-audit-2026-09-08/CareRequestFlowTest.before-ui-update.php`.

Its SHA256 is `5113e279952e8cf153e86e667ce05d68cffd6f5f0da71580629c499dc906585b`. [The byte-preservation proof](test-migration-preservation.txt) confirms every byte outside the six migrated method blocks matches that saved original, including the user's Carbon/DataProvider imports and same-day scheduling test/provider additions. Existing database/profile assertions within those methods remain present. [The migration table](compatibility-summary.md#intentional-migration-of-existing-ui-assertions) describes the updated navigation, copy and confirmation steps.

The original compatibility run's six failures were retired copy/navigation expectations; they stopped several tests before their downstream assertions. The passing rerun, rather than the initial failure classification alone, supplies the current compatibility evidence. [Initial run log](compatibility-before-test-migration.log).

## Limits and separate evidence

- These are selected application tests, not the entire repository suite. No tests were rerun solely to assemble this report.
- PHPUnit is configured for the testing environment with an in-memory SQLite database, array mail/cache/session drivers and Stripe bypass. Tests can mock provider boundaries. Passing tests do not establish a real card authorization, capture, email delivery or other external provider transaction.
- Blade/Livewire tests verify rendered output and server-side behavior. They do not by themselves establish browser focus order, responsive appearance, contrast compliance or real-time widget interaction. Those are separate [workspace browser checks](../workspace-ui-validation.json), role smoke checks and the final capture report.
- The source screenshots use an isolated synthetic data copy. They are independent examples for each state, not a chronological record of one real customer's care.
- Screenshot completion, field-by-field fixture comparison and untouched-baseline checks have their own artifacts. This test total must not be used as a substitute for those results.
- Original shell logs are retained for the four compatibility selections and latest workspace run. Some agent-run test results were available only in tool output and are explicitly labeled transcriptions; the correction per-file count is derived as described above.
