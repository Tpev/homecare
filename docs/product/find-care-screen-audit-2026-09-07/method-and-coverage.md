# Find-care audit: method, coverage and reproducibility

This audit records the existing family experience so a later UI/UX redesign can start from a complete, reviewable baseline. It does not implement the redesign.

The [master tracker](master-tracker.md), [visual report](index.html) and [capture manifest](captures.json) describe the evidence that actually exists. The [one-time source inventory](one-time-source-inventory.md) and [recurring source inventory](recurring-source-inventory.md) describe current source behavior, including conditional branches that may not appear in the captured fixture set. A documented source branch is not automatically a completed screenshot.

## What is being tracked

The tracker uses the seven requested stages:

| Stage | Meaning of the captured state |
|---|---|
| Create | Request form; care pattern, recipient, help, schedule, address, privacy and optional detail branches. |
| Waiting / inviting | Posted request, invitation controls and statuses, applicants, conversations, caregiver selection and related pending offers. |
| Hired / before shift | Dated booked visit before check-in; recurring agreement and its next/later visits; payment attention; recurring management panels. |
| During shift | Actual in-progress visit state, check-in/task/time/support information and associated tabs. A paused visit is a separate conditional state. |
| After shift | Care has ended and its recorded outcome is visible. This stage is kept separate from the family's decision to approve hours and payment. |
| Timesheet / review | Submitted hours, approval/payment action, correction review and caregiver rating/comment states. |
| Closed | Final visit record and feedback, cancelled/no-show/withdrawn branches where captured, history, and ended recurring arrangement. |

Each state is classified as `one_time`, `recurring` or `shared`. The shared care navigation appears as Overview, Arrangements, Schedule and History. Every selected request tab is a separate screen record. Opening a disclosure, an invitation drawer or a recurring-management panel creates a separate capture where that branch is included.

Recurring care has two lifecycles: the ongoing agreement and each dated visit. Completing or reviewing one visit does not end the recurring agreement. Generated recurring visit requests can have `request_type=one_time` in the database while remaining members of a recurring plan; capture classification follows plan membership. The source inventory records any resulting visible-label discrepancy.

## How the screenshots were made

- The actual local application was rendered after building its current Vite assets. Existing app Blade/Livewire/source behavior was retained; no product-source redesign was applied for the audit.
- The capture scripts use a separate SQLite database at `.tmp/find-care-screen-audit/find-care-audit.sqlite`, a dedicated local session cookie and a local server at `http://127.0.0.1:8027`.
- Fixture requests, caregivers, recipient details, plans, bookings and lifecycle values are synthetic audit data. The scripts do not use production-family records for these screenshots.
- The environment uses mail, payment/verification and support-provider isolation settings defined in `capture/environment.mjs`. Fixture creation is not live caregiver hiring, invitation delivery or payment processing.
- Browser: Edge Chromium, headless. Desktop: 1440 × 1000 CSS pixels. Mobile: 390 × 844 CSS pixels, touch and mobile emulation. Device pixel ratio: 1. Locale: en-US. Motion is reduced for capture consistency.
- The capture session restricts remote content. Google Fonts are allowed; remote map and payment widgets are blocked. Their current visible fallback states are preserved. A capture is not a verification of hosted Stripe, map-provider or external-service behavior.
- Tabs, panels and native disclosures are opened through the actual UI. The capture does not replace the product view with a mockup or rewrite the DOM to simulate a new design.

## What each screen record contains

`captures.json` stores a screen ID, variation, stage, title, route, selected tab, disclosure/panel state, status, and evidence paths for desktop and mobile. Each viewport record contains:

| Evidence | Purpose |
|---|---|
| `viewport` PNG | The first screenful from the top, including any long profile/header before the workflow. |
| `focus` PNG, when present | A viewport screenshot scrolled to the page heading, or the specific review/profile/plan form for a form-focused row. The gallery prefers this image while retaining the original first screenful. |
| `full` PNG | The full rendered page, including information below the fold in the selected tab/panel. |
| `text` file | Full visible body text, preserving headings, global navigation, helper text, notices, labels and populated data. |
| `inventory` JSON | Visible headings, controls with current values/state/options, links/destinations, native disclosures/open states, image sources/loading state and document dimensions. |
| `metadata` | Viewport width/height, document height, horizontal overflow and actual final URL. |

`screens/{ID}.md` puts both viewports' screenshots and complete information inventories in one reviewable file. It preserves input values and dropdown options even when those values do not appear in body text. The report generator labels a state Captured only when both viewports' required screenshots, visible-text file and structured inventory are present; a missing supplied focus PNG also creates an evidence warning.

Full-page captures show the selected tab and currently open details. They do not expose inactive tabs or collapsed content. Dropdown option values are enumerated in the inventory; a screenshot of the closed dropdown does not display every option at once. Browser-native confirmation dialogs and hosted widgets require their own explicit evidence if they matter to a later decision.

## Coverage boundaries and known gaps

The seven core stages are the organizing requirement for both one-time and recurring care. The master tracker gives the actual captured/total count by stage and variation; `report-summary.json` provides the same checkable counts and missing-evidence warnings. A row with status Failed or Incomplete evidence remains unfinished.

The following boundaries remain explicit even when every planned core capture is complete:

1. **Simple/quick care-profile creation uses a separate fresh account.** The main family has a saved profile, so Nora Brooks is used for the two `OT-create-quick-profile` / `RC-create-quick-profile` captures. Both device sizes show the questions, filled draft values and sharing acknowledgment. No profile or care request is saved by these captures.
2. **Real hosted Stripe/payment-authentication UI is excluded.** Payment-state screens can be captured using isolated data and current fallback behavior; hosted authentication, real card challenges and live payment completion are not claimed.
3. **Connected maps and live geolocation are excluded from this session.** Visible location labels and fallback controls are recorded; third-party map imagery and browser/device permission flows are not verified.
4. **Conditional workflow variations remain a source checklist until pictured.** Examples include saved-information/self-care/quick-profile choices, private request, no matching caregiver, further result pages, pending/countered/declined/expired plan, paused visit versus paused agreement, time corrections, manually reported extra visits, disputes/no-shows, payment/refund outcomes and empty/filter result branches. Some may already have matching rows; consult the capture manifest instead of treating this list as a blanket absence claim.
5. **A closed recurring agreement may keep its next confirmed visit.** “Ended arrangement” and “all future visits cancelled” need different fixture evidence. The source inventory also records screens whose current wording or linked dates may not accurately reflect that distinction.
6. **External delivery is not part of this UI audit.** A recorded invitation/chat/payment action or state is evidence of the rendered interface, not proof that a real caregiver received a message or that money moved.

The source inventories preserve current discrepancies for the redesign discussion. They are findings to inspect, not corrected behavior. Desktop/mobile visibility differences are retained in their separate information files rather than combined into one imagined screen.

## Reproducing or extending the audit

Run from `C:/Users/Epicpp/homecare` using the existing installed PHP, Node and Playwright dependencies. The audit scripts are under `docs/product/find-care-screen-audit-2026-09-07/capture`.

1. Build the existing frontend with the project's normal Vite build command.
2. Run `setup.mjs` to migrate the isolated audit SQLite database. `environment.mjs` supplies audit-specific environment variables; this command does not migrate the configured production database.
3. On first setup, run `seed.mjs`, `seed-extra.mjs`, then `seed-empty-family.mjs` to create the synthetic lifecycle, conditional-state and fresh-family fixtures and write the local fixture manifest. These mutate only the isolated audit database. Reuse the existing manifest/database for recapture; the primary seeder is not intended to be rerun over existing fixture records.
4. Start `serve.mjs` for the dedicated local audit server. If launching it as a background process on Windows, use a hidden window.
5. Run `capture.mjs`. The optional `AUDIT_CAPTURE_GROUP` values select `core`, `expanded`, `plans`, `create`, `additional`, `profiles` or `all`. The script resumes `captures.json` and skips records captured with its current capture version. Plan a separate baseline folder and deliberate fresh capture when source or fixtures change. The screenshot process needs network permission for the application's Google Fonts; other remote requests are blocked by the capture harness. `AUDIT_CAPTURE_MODE=reconcile` refreshes evidence and only retakes existing screenshots when visible text changes, a prepared form is involved, or the row is on the verification target list.
6. Run `build-report.mjs` after all intended captures are finished. This generates `master-tracker.md`, `index.html`, `screens/{ID}.md` and `report-summary.json` from the manifest and evidence on disk. It performs no application/database/network actions. Run `validate.mjs` for file, PNG dimension, link and seven-stage coverage checks, and `validate-captures.mjs` for the prepared-form and closed-disclosure visibility checks. Final results are in `validation.json` and `validation-notes.md`; `provenance.json` records the source revision and hashes.

On this Windows workstation the Node executable is `C:/Program Files/nodejs/node.exe`. Restricted-shell execution uses `--preserve-symlinks --preserve-symlinks-main`; for example:

```powershell
& 'C:/Program Files/nodejs/node.exe' --preserve-symlinks --preserve-symlinks-main 'docs/product/find-care-screen-audit-2026-09-07/capture/build-report.mjs'
```

The capture manifest is written as each screen finishes. A report generated during capture is a partial snapshot; regenerate it at the end before using its totals as the final baseline. The generation timestamp is included in the tracker, gallery and summary.

## Tracking the later redesign

UX review, findings, redesign decision, owner and priority are deliberately blank in the generated screen reports. Add an optional `review-notes.json` at the audit root, keyed by screen ID, to retain decisions across report regeneration:

```json
{
  "RC-plan-active": {
    "review": "",
    "findings": "",
    "redesign": "",
    "owner": "",
    "priority": ""
  }
}
```

The screenshot baseline and its information inventory remain evidence of the current product. Record proposed changes in the review fields, then create separate after-change evidence when redesign work is implemented.
