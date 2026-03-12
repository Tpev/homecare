# Caregiver UI Design Guidelines (V1)

This document defines the design system for caregiver-facing screens.
Use it as the default for new pages and UI refactors.

## 1) Core Principles

- Mobile-first by default.
- Action-first: the next important action must be visible without scrolling when possible.
- Calm and trustworthy: reduce noise, avoid multiple competing accents.
- Consistent hierarchy: one primary header, one primary status signal, one primary CTA.
- Progressive disclosure: advanced details should live in collapses/tabs, not the first view.

## 2) Visual Language

### 2.1 Surfaces

- `light shell`: app background (`hc-page` / light gradient backdrop).
- `primary command surface`: dark card for real-time workflows (shift control, earnings hero).
- `neutral content surface`: white cards for forms, lists, and reviews.

### 2.2 Color Semantics

- `primary`: navy/slate (`bg-slate-900`, `bg-slate-950`) for focus/navigation.
- `success`: emerald for positive progress and completion.
- `warning`: amber for pause/wait/review-required states.
- `danger`: rose/red for dispute/cancellation/critical actions.
- `info`: cyan/sky for informative notices.

Never use more than one status color in the same row unless required by state comparison.

### 2.3 Typography

- Page title: `text-2xl font-display font-semibold`.
- Section title: `text-lg font-display font-semibold`.
- Meta/labels: `text-xs uppercase tracking-[0.12em]`.
- Body copy: `text-sm`.

## 3) Layout Rules

## 3.1 Top Bar Pattern

- Non-shift tabs: title + compact meta + action buttons.
- Shift tab: compact action row only (`Back to requests`, `Open chat`) + segmented tabs.
- Avoid duplicate context between top bar and command center.

### 3.2 Segmented Navigation

- Page-level tabs: one segmented control, `grid cols-2` on mobile, `cols-4` on desktop.
- In-card tabs (Live/Tasks/Details): always inside the dark command card.
- Active tab style should contrast strongly with inactive tabs.

### 3.3 Density

- Mobile spacing: `gap-2` to `gap-3`.
- Avoid more than 4 dense metric cards in one visible block on phone.
- If more data is required, move to details tab/collapse.

## 4) Component Standards

### 4.1 Status

- Show status once per context block.
- Prefer a single badge or short status line, not multiple stacked pills.
- Use uppercase labels only for short status words.

### 4.2 Buttons

- Primary action: full-width on mobile in critical workflows (start/pause/resume/end).
- Secondary actions: grouped below or in details tab.
- Destructive actions must be visually distinct and never adjacent to primary green CTA.

### 4.3 Cards

- Dark command cards for live operational flows (shifts/earnings).
- White cards for post-action workflows (reviews, support forms).
- Use consistent radius: `rounded-2xl` or `rounded-3xl`.

## 5) Shift Screen Canonical Structure

1. Top compact bar (actions + page tabs).
2. Dark shift command center:
   - Header: title + one status signal.
   - Internal tabs: Live / Tasks / Details.
   - Live tab: schedule, live counters, state notice, primary controls.
   - Tasks tab: checklist.
   - Details tab: agreement, timestamps, audit/timeline, support links.
3. Review card (only when applicable).
4. Support tools in separate Support tab.

## 6) Copy Guidelines

- Use short sentence fragments for labels.
- Keep notices action-oriented:
  - Good: `Shift paused. Resume when back, or end directly.`
  - Avoid: long explanatory paragraphs in live panels.
- Prefer human language over internal terms (`Shift started` vs `booking_in_progress`).

## 7) States and Feedback

- Loading actions must show inline text (`Capturing GPS...`).
- Success should be specific (`Shift closed and reviewed.`).
- Empty states must include a next step CTA.
- Never leave empty UI chrome (for example, empty card footers).

## 8) Accessibility Baseline

- Minimum touch target: 44px height for interactive controls on mobile.
- Ensure color contrast for text on dark surfaces.
- Keep button labels explicit (`Start shift`, `End shift`).
- Provide `aria-label` for icon-only or rating interactions.

## 9) QA Checklist Before Merge

- No duplicated context blocks on the same screen.
- One clearly visible primary action in live workflows.
- Tabs readable on 390px width.
- Status colors map correctly to business state.
- No empty slot/footers causing dead space.
- Works with long names/titles without layout break.

## 10) Scope

Applies to all caregiver-facing pages:

- Dashboard
- Shift management
- Earnings
- Invitations
- Care request application flow
- Profile quality/completeness workflow

