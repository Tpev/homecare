# Recruitment polish — method and coverage

This September 9 package is a new review of the local application. The September 7 baseline and September 8 redesign screenshots remain unchanged. The application continues to use its existing isolated September 8 SQLite database at `http://127.0.0.1:8028`.

## Scope

The matrix covers the path from creating a care request through finding caregivers, reviewing applicants, reviewing a hire and seeing the booked visit. Both one-time and recurring care are shown at desktop 1440×1000, mobile 390×844 and narrow mobile 320×844.

For each care type, the matrix includes:

- Initial creation and a completed review draft.
- Public and invitation-only request summaries, with full care information in an expanded details capture.
- Caregiver recommendations, name search, no results, previously sent invitations and saved caregivers.
- Invitation terms/message review before sending.
- No applicants, one applicant, four applicants, shortlisted applicants, past applicants and expanded applicant details.
- Hire terms review before confirmation and an existing scheduled visit.

Recurring-only captures show the proposed weekly schedule before hiring, the arrangement after hiring and its individual dated visits. These are separate product objects with separate routes and states.

## Evidence

Each captured state has a viewport PNG, a full-page PNG, plain visible text and a structured inventory of headings, controls, current field values, options, links, open disclosures, images, selected navigation and candidate rows. Details that are initially collapsed are captured in the explicit expanded states. Browser-only draft text is identified by the state name and is never represented as a saved request or sent invitation.

Checks cover HTTP/application errors, horizontal overflow, actual loading of the local Inter and Source Serif 4 fonts, primary action contrast in normal/hover/focus states, a visible focus indicator, actual applicant counts and whether the first candidate is visible in the initial viewport. The report also records the current frontend manifest hash so stale screenshots can be identified.

Contrast checks are scoped to the captured primary controls and do not claim a complete WCAG audit. The human visual review samples desktop/mobile layouts and key decision screens in addition to automated checks.

## Existing fixture semantics

The read-only pre-capture snapshot is [fixtures-before.json](fixtures-before.json). Open one-time care is dated September 10. Scheduled one-time and recurring examples have a September 9 10:00–12:00 Raleigh visit and remain stored as `scheduled`, with no start recorded. The screenshots show the real stored lifecycle; no clock or booking state is fabricated to make a scenario appear successful.

The no-applicant fixtures contain zero applications; the single-applicant fixtures contain one shortlisted application; the many-applicant fixtures contain four applications. The invited fixtures contain two existing invitations. An applicant’s shortlist status and the family’s saved caregiver list are distinct concepts and are checked as distinct screens.

## No final marketplace actions

Capture uses normal application navigation, filters, native disclosures, form draft entry and confirmation previews. It never publishes a request, sends an invitation/message, hires a caregiver, changes a booking, submits a review or charges a payment. A browser request guard rejects known final marketplace methods if a capture accidentally attempts one.

The [data comparison](data-preservation.json) compares every row and field in 26 care, agreement, invitation, application, payment, review, profile and favorite tables before and after capture. Login/session records, read receipts and activity telemetry are excluded. The original historical screenshot packages are not regenerated.

One existing repository migration was pending in this older local runtime. After an exact SHA256-verified SQLite backup and integrity check, the existing September 9 pricing-agreement migration added an empty table and nullable booking/payment reference fields. The comparison preserves the complete original column set and reports new columns separately. [Migration and backup evidence](runtime-migration.json).

During an early expanded-inventory pass, a harness focus error triggered 55 attempted invitation submissions. The network guard aborted every request before it reached the server; the 26-table comparison confirms no invitation or care data changed. The routine was corrected to click only disclosures inside the active dialog, and affected captures were repeated. The [guard incident record](harness-guard-incident.json) is retained; the final corrected runs have zero blocked or submitted marketplace actions.

The final CSS build changes only the recruitment stage keyboard focus indicator relative to the normal-state matrix build. Original screenshot hashes remain intact, with the narrowly scoped equivalence and separate final keyboard screenshots recorded in [asset provenance](asset-provenance.json). This avoids relabeling old screenshots as if they were captured on a different build.

## Reproduce

Run from `C:\Users\Epicpp\homecare` with the local application already running:

```powershell
& 'C:\Program Files\nodejs\node.exe' --preserve-symlinks --preserve-symlinks-main docs/product/find-care-recruitment-polish-2026-09-09/qa/capture.mjs
& 'C:\Program Files\nodejs\node.exe' --preserve-symlinks --preserve-symlinks-main docs/product/find-care-recruitment-polish-2026-09-09/qa/snapshot.mjs after
& 'C:\Program Files\nodejs\node.exe' --preserve-symlinks --preserve-symlinks-main docs/product/find-care-recruitment-polish-2026-09-09/qa/build-report.mjs
& 'C:\Program Files\nodejs\node.exe' --preserve-symlinks --preserve-symlinks-main docs/product/find-care-recruitment-polish-2026-09-09/qa/serve-gallery.mjs
```

The gallery is served at `http://127.0.0.1:8032/find-care-recruitment-polish-2026-09-09/`. To recapture a selected state without changing the others, set `POLISH_CAPTURE_IDS` to a comma-separated list of matrix IDs. If app assets change, recapture the complete matrix so every screenshot has the final build hash.
