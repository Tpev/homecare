# Caregiver Journey V2 Spec

## 1. Objective

Design a caregiver journey that is fast, clear, and conversion-focused from first offer to paid shift.

Primary business outcomes:

- Increase `offer -> response` rate
- Increase `response -> hire` rate
- Increase `hire -> shift completed` rate
- Reduce time to first completed shift

## 2. Scope

In scope:

- Caregiver logged-in journey
- Offer discovery and response flow
- Hired shift preparation and execution
- Post-shift closure and earnings handoff

Out of scope:

- Payments rail changes
- KYC provider changes
- Family-side redesign (except needed integration points)

## 3. User Story

As a caregiver with an account, I want to quickly see the best offers, respond with confidence, and move through shift completion and earnings with minimal friction.

## 4. Journey Map (Target)

1. Login and land on dashboard.
2. See one prioritized work inbox with clear next actions.
3. Open offer, understand fit in under 10 seconds.
4. Respond in one tap (accept invite / apply / open chat).
5. If hired, get pre-shift readiness prompts.
6. Run shift from mobile command center (start, pause, resume, end).
7. Submit review and see payout progression clearly.

## 5. Core UX Changes

## 5.1 Unified Work Inbox

Replace split mental model (`Invitations` + `Browse Requests`) with one consolidated inbox view.

Inbox sections:

- Needs response now
- Best matches for you
- Applied / waiting
- Hired / upcoming shifts

Each item shows:

- Title, location, schedule type
- One status chip
- One primary CTA
- Optional secondary CTA

## 5.2 Next Action Engine

Every item must expose exactly one primary action based on state.

State-to-CTA mapping:

| State | Primary CTA | Secondary CTA | Destination |
|---|---|---|---|
| Invited (pending) | Accept invite | Decline | Invite row actions |
| Open match (not applied) | Apply now | View details | Request detail |
| Applied | Open application | Withdraw (optional later) | Application tab |
| Shortlisted | Open chat | Update application | Messages / request |
| Hired (no booking accepted) | Accept agreement | Open chat | Shift details |
| Scheduled | Start shift | Open details | Shift live tab |
| In progress | Pause shift | End shift | Shift live tab |
| Paused | Resume shift | End shift | Shift live tab |
| Completed (awaiting family) | View recap | Contact support | Shift live tab |
| Reviewed | Done | View earnings | Earnings |

## 5.3 Offer Card Contract

All offer cards should follow one visual contract:

- Header: title + one status chip
- Subline: city/state + schedule snippet
- Fit row: trust badges + key fit reason
- Footer: primary CTA + optional secondary link

No duplicated metadata blocks across screens.

## 5.4 Pre-Shift Readiness

Before start window, show a compact checklist:

- Agreement accepted
- Shift window starts in X min
- GPS/location permission available

If any requirement is missing, primary CTA becomes the blocker-resolving action.

## 5.5 Post-Shift Closure

After end shift:

- Show recap immediately
- Show status track: `Timesheet submitted -> Family confirmation -> Added to payout`
- Show one primary CTA: `Leave review` (if missing) else `View earnings`

## 6. Screen Specs

## 6.1 Dashboard (Caregiver Home)

Goal:

- Show what to do now in under 5 seconds.

Blocks:

- Hero with 2 KPIs max
- Work Inbox preview (top 5 items)
- Live/upcoming shift quick card
- Setup cards only if incomplete

Primary CTA priority:

1. Continue live shift
2. Respond to pending invite
3. Apply to best match

## 6.2 Work Inbox Screen

Goal:

- One place for all opportunities and active work.

Controls:

- Filter chips: `All`, `Needs response`, `Invited`, `Applied`, `Hired`, `Completed`
- Sort: `Priority`, `Newest`, `Start soon`, `Best fit`

Rows:

- Must always show one primary CTA
- Must support direct invite acceptance without navigation

## 6.3 Request Detail / Application Screen

Goal:

- Evaluate and respond quickly.

Rules:

- Keep `Overview`, `Application`, `Shift`, `Support` tabs
- Minimize repeated headers/statuses
- Keep copy concise and action-oriented

## 6.4 Shift Command Center

Goal:

- Mobile-first operational reliability.

Rules:

- Live tab defaults during active states
- Full-width primary controls
- State-driven controls only (no invalid button combinations)
- Timeline and advanced details moved to Details tab

## 6.5 Earnings Screen

Goal:

- Close trust loop after work.

Must show:

- Available balance
- Next payout estimate/date
- Shift earnings history with payout statuses

## 7. Notifications (Modular)

Channel strategy:

- `in_app` required
- `email` enabled now
- `sms` and `push` as pluggable channels

Caregiver-critical events:

- New matching request
- New invite
- Invite accepted/declined updates (counterparty visibility)
- Hired
- Shift starting soon
- Shift started/completed

Notification payload standard:

- `event_key`
- `title`
- `body`
- `url`
- `subject_id/type`
- `dedupe_key`

## 8. Analytics and Funnel Tracking

Track these events and timestamps:

- `caregiver_dashboard_viewed`
- `work_inbox_viewed`
- `offer_opened`
- `invite_accepted`
- `invite_declined`
- `application_submitted`
- `chat_opened`
- `hired_received`
- `shift_started`
- `shift_paused`
- `shift_resumed`
- `shift_completed`
- `review_submitted`

Core KPI definitions:

- Time to first response
- Time to first hire
- Time to first completed shift
- Offer response rate
- Invite acceptance rate
- Hire conversion rate
- Shift completion rate

## 9. Implementation Plan (Sprints)

## Sprint 1: Inbox + Next Action Foundation

- Build unified work inbox page
- Add state-to-CTA resolver
- Add inline accept/decline in inbox rows
- Add dashboard inbox preview
- Add analytics events for inbox and response

Acceptance:

- Caregiver can respond to an invite without leaving inbox
- Every row has exactly one primary CTA

## Sprint 2: Shift Lifecycle Polish

- Add pre-shift readiness block
- Finalize shift status-driven CTA logic
- Add post-shift status track and clearer recap
- Ensure review card lifecycle is clean (no empty footer states)

Acceptance:

- No invalid controls shown in shift states
- Post-shift path to review/earnings is one-tap

## Sprint 3: Optimization + Experiments

- Add prioritized ranking for inbox cards
- Add response urgency labels
- Add conversion experiments on offer card copy and CTA order
- Add funnel dashboard slices by source (`invite` vs `open market`)

Acceptance:

- We can compare conversion by source and by CTA variant

## 10. QA Scenarios

Critical manual test paths:

1. Pending invite -> accept -> chat opens.
2. Open request -> apply -> appears in applied state.
3. Hired -> accept agreement -> start shift with GPS.
4. In progress -> pause -> resume -> end shift.
5. Completed -> review -> earnings screen progression.
6. Notification deep links always open correct destination.

## 11. Design and Copy Constraints

Reference:

- `docs/caregiver-design-guidelines.md`

Hard rules:

- Mobile-first layout
- One primary action per block
- No repeated status badges in adjacent blocks
- No empty visual containers
- Keep language direct and plain
