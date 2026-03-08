# HomeCare Roadmap

Last updated: March 8, 2026

## Product Goal
Build a trusted two-sided marketplace for non-medical home care:
- Families post care requests and hire independent caregivers.
- Caregivers apply, chat, and get hired directly.
- HomeCare provides trust/safety, workflow, and platform operations.

## What We Have So Far

### Core marketplace foundation
- Role-based auth (`family`, `caregiver`) and gated routes.
- Caregiver registration + onboarding wizard.
- Caregiver moderation lifecycle:
  - `draft -> under_review -> active -> suspended`
  - rejection reason + resubmission flow
  - moderation logs + profile version snapshots
- Caregiver search/browse with filters and sort.

### Family request lifecycle
- Family request creation wizard with:
  - address
  - date/time window
  - tasks + task notes
  - recipient details
  - optional third-party contact
- Family request dashboard and request detail management.
- Caregiver applications to open requests.
- Family actions on applicants:
  - shortlist
  - reject
  - hire
- Hiring updates request state and applicant statuses.

### Messaging (integrated)
- Conversation and message data model tied to care request/application.
- Real-time style inbox/chat via auto-refresh polling (2s).
- Unread indicator in navbar.
- Access control:
  - only request family + applicant caregiver can view
  - send enabled when application is `shortlisted` or `hired`
- Chat entry points integrated from:
  - family applicant management
  - caregiver request/application flow
  - global `/messages` inbox

### DX / QA
- Demo seed data for family, caregiver, request, application, and conversation.
- Regression tests covering caregiver flow, family flow, and messaging flow.

## What We Still Need For a Strong V1 Launch

## P0 (must-have before public launch)
1. Payments + payout flow
- Stripe Connect setup for caregiver payouts.
- Platform fee collection and ledger trail.
- Booking/session payment status (`authorized`, `captured`, `refunded`, etc.).

2. Booking execution layer
- Turn request/hire into real care sessions (with start/end, status, cancellation).
- Attendance/check-in model and post-session completion flow.

3. Reviews and rating system
- Family <-> caregiver reviews after completed care.
- Anti-abuse rules (one review per completed booking, moderation controls).

4. Notifications
- Email notifications for:
  - application received
  - shortlisted
  - hired/rejected
  - new message
- In-app notification center (basic).

5. Trust and safety baseline
- Identity verification placeholders replaced with real provider integration.
- Background-check integration workflow.
- Basic incident/reporting path in admin.

6. Admin operations hardening
- Dispute queue.
- User/account actions audit trail.
- Request/application moderation tools.

## P1 (high-value shortly after launch)
1. Better matching/ranking
- Relevance ranking for caregivers and requests.
- Distance + availability + skill weighting.

2. Family and caregiver profile depth
- Better profile completeness guidance.
- Service preferences and care plan templates.

3. Messaging improvements
- Attachments (documents/photos).
- Typing indicator and delivery/read state UX.
- Optional websocket transport (Reverb/Pusher) for instant push updates.

4. Analytics + funnel visibility
- Request-to-application and application-to-hire conversion.
- Time-to-first-applicant and time-to-hire.

## P2 (scale and polish)
1. Mobile-first UX pass and dedicated responsive refinements.
2. Insurance/compliance enhancements by market.
3. Referral and growth loops.

## Design / UX Work Still Needed
- Define a stronger visual system aligned with “HomeCare + Uber-like efficiency”:
  - clearer hierarchy for key actions
  - cleaner status visibility
  - faster chat/request interactions
- Consolidate spacing, typography, and color tokens across auth, dashboards, requests, and chat.
- Add empty/error/loading states with consistent component patterns.

## Suggested Launch Sequence
1. Complete P0 payments + booking + reviews + notifications.
2. Run internal pilot in Raleigh with seeded/admin-supervised accounts.
3. Collect operational data for 2-4 weeks and close critical issues.
4. Launch public beta with strict moderation and support playbooks.

## Current Key Routes
- Family requests: `/family/requests`
- Caregiver request board: `/care-requests`
- Messaging inbox: `/messages`
- Caregiver profile edit: `/caregiver/profile/edit`
- Caregiver public search: `/caregivers/search`

## Validation Commands
```bash
php artisan migrate
php artisan db:seed
php artisan test tests/Feature/Messaging/CareRequestMessagingTest.php
php artisan test tests/Feature/Family/CareRequestFlowTest.php
php artisan test tests/Feature/Caregiver/CaregiverRegressionTest.php
```
