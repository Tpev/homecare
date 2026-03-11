# Playwright E2E Suite

This folder contains end-to-end browser tests for the HomeCare marketplace.

## Scenarios Covered

- Auth smoke and role redirect behavior
- Caregiver onboarding submit flow
- Family request creation (manual form)
- Invitations:
  - family invites from caregiver profile
  - caregiver accepts invitation
- Identity verification (Didit bypass mode for E2E)
- Core cycle:
  - caregiver applies
  - family hires
  - both sides chat
  - caregiver check-in/check-out
  - family timesheet confirmation + review
- Support + admin:
  - caregiver opens support ticket on a booked request
  - admin resolves ticket
- Admin moderation:
  - approve under-review caregiver
  - moderation logs visible

## Deterministic Fixtures

Before Playwright starts the app server, it runs:

1. `php artisan migrate:fresh --force`
2. `php artisan homecare:e2e-seed`

Seeded credentials:

- Family: `family.e2e@example.com` / `password`
- Admin: `test@test.com` / `password`
- Caregiver (ready): `caregiver.ready.e2e@example.com` / `password`
- Caregiver (new): `caregiver.new.e2e@example.com` / `password`
- Caregiver (under review): `caregiver.review.e2e@example.com` / `password`

## Didit in E2E

Playwright runs with `DIDIT_BYPASS=true`, so identity verification is handled internally and never calls the external Didit API.

## Run

```bash
npm run test:e2e
```

Headed mode:

```bash
npm run test:e2e:headed
```

Interactive UI:

```bash
npm run test:e2e:ui
```
