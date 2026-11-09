# Test Suite Guide

This project includes a regression-focused test suite for caregiver onboarding, moderation, and discovery.

## Quick Start

Run all tests:

```bash
php artisan test
```

Run only caregiver regressions:

```bash
php artisan test tests/Feature/Caregiver/CaregiverRegressionTest.php
```

Run caregiver auth flow tests:

```bash
php artisan test tests/Feature/Auth/CaregiverOnboardingTest.php
```

## What The Caregiver Regression Tests Cover

File: `tests/Feature/Caregiver/CaregiverRegressionTest.php`

1. Onboarding submission:
   - Submitting onboarding moves status to `under_review`.
   - Creates profile version snapshot.
   - Creates moderation log action `submitted`.

2. Availability validation:
   - Overlapping time ranges are rejected.

3. Admin moderation actions:
   - Approve moves status to `active` and logs `approved`.
   - Reject moves status to `draft`, saves rejection reason, logs `rejected`.
   - Suspend/unsuspend toggles `suspended` <-> `active` and logs both actions.

4. Profile editing:
   - Caregiver edits do not deactivate profile.
   - Version snapshot is created with reason `profile_edit`.

5. Search visibility:
   - Search only displays caregivers with `status=active`.

6. Moderation logs UI:
   - Admin route renders successfully.
   - Recent actions are visible in rendered output.

## Notes

- CRM phone matching is covered by `tests/Feature/LeadPhoneDeduplicationTest.php`.
  The November 9, 2026 migration backfills phone keys and merges existing duplicates
  within each lead type. It keeps the oldest lead during backfill, moves activities
  and SMS history, repoints provider calls, and saves original records in the merge
  activity. Email delivery reservations and external import identities are retained.
  New submissions reuse the existing record without resetting its stage or owner.
  Editing a phone to match another record keeps the edited record's ID.
- Deploy the CRM phone changes together with `php artisan migrate --force`, while
  CRM writes and queue workers are paused. The migration stops before schema changes
  if matching phones belong to different linked family accounts; its error names
  the lead IDs that need review. Blank, incomplete, and extension-bearing phones are
  excluded from automatic merging. Family and referral lead types remain separate.
  Rolling back the schema does not split merged histories; original snapshots remain
  in `lead_activities` for review or recovery.

- Tests use `RefreshDatabase`, so each test runs with a clean database state.
- Admin-only tests assign the explicit `admin` role; fixture email addresses do not grant permissions.
- If tests fail after schema updates, run:

```bash
php artisan migrate:fresh --seed
```
