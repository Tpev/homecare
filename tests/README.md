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

- Tests use `RefreshDatabase`, so each test runs with a clean database state.
- Admin-only tests assume admin email middleware allows `test@test.com`.
- If tests fail after schema updates, run:

```bash
php artisan migrate:fresh --seed
```
