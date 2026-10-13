# AI Support rework — 11 September 2026

Implemented locally against the care redesign. This change has not been committed, pushed, or deployed. The production database, accounts, settings, conversations, and published knowledge were not changed.

## What changed

| Audit finding | Result | Main regression coverage |
| --- | --- | --- |
| Financial confirmations did not use the exact booking quote | Hire, recurring offer, and hours confirmations use the existing pricing and payment services. They show dates, duration, currency, care amount, applicable fees, total, and the estimated authorization hold where relevant. Confirmation checks the quote again; a changed quote requires a fresh recap. | `FinancialConfirmationSafetyTest`, existing payment and legacy-pricing tests |
| A review could approve hours and capture payment | The bot requires hours approval first. A caregiver review no longer approves hours or captures payment. Existing booking/payment services retain their behavior. | Review-before-approval refusal; approved review has no payment side effect |
| Explicit restrictions were misunderstood | Restrictions and informational questions run before action matching, draft extraction, and unfinished journeys. “Don't hire anyone. Just show me applicants.” reads applicants without a hire confirmation. Wait/decline-to-proceed instructions revoke old confirmation cards. | `ActionInstructionSafetyTest`, including an active recurring draft and affirmative commands |
| Guidance and KB were behind the redesign | The managed package contains 193 entries and covers all 171 distinct KB references across the 324-intent catalog. Current navigation and recruitment copy replace stale instructions. General pricing reads platform settings; a particular booking requires its own quote. The prompt version is `interactive-support-v10`. | Knowledge content, catalog alignment, pricing, and package publication tests |
| Some targets were invisible | The four old target IDs remain compatible but point to visible controls or sections. Guidance opens disclosures, checks geometry, focuses the destination, and only then reports arrival. Closing inline chat restores a visible page control. | Guided assistance tests plus local desktop/mobile browser checks |
| Recurring visits lost their date | Visit and timesheet guidance retain the generated request/visit identity. Overview and Schedule links open the selected visit. Plan links remain available for changes to the repeating schedule. Legacy records without a visit request use a valid plan fallback. | Generated-visit, missing-request fallback, current/long-running/null-end/stale visit tests |
| Payment and handoff recovery was fragile | Expected payment, validation, and permission failures return safe messages and authorized visit/billing links. Prepared forms are single-use and reauthorized on consumption; human handoff, admin reply, logout, and control/access changes invalidate stale preparations. | Financial recovery, preparation lifecycle, cross-account and idempotency tests |
| Operational reporting overstated what happened or what was proven | Incident messages describe the actual persisted containment state. Admins see failed calls separately from successful-call latency, sample limitations, scheduler/retention heartbeats, and source/configuration/published-KB fingerprints. Historical evaluations do not establish current quality. | Operational truth, evidence Overview, and retention tests |

## Preservation and compatibility

- No database schema migration or destructive care-data operation was added.
- Existing agreements, booking pricing snapshots, platform settings, payment services, and business idempotency remain authoritative. For example, the code's default two-hour quote can be $62 while a legacy snapshot can be $35; the bot must show the applicable quote rather than impose a new rate.
- Old navigation target IDs and plan routes remain supported. Generated visit records keep their own dates, hours, and payment context.
- Older financial cards with missing or changed quote facts require renewal rather than silently confirming a different total.
- Published KB history and sources remain available through the existing version workflow. Custom/unmanaged entries are not automatically deleted. Conflicting administrator work is surfaced rather than silently overwritten.
- Operational retention uses existing retention dates and holds, protects open incidents and active release decisions, and records execution separately from a dry run.

## Validation

**438 tests passed, with 7,948 assertions**, covering AI Support, existing Support, Payments, Booking, and the Family Schedule calendar. The final combined run completed in 2 minutes 43 seconds. Vite production build, PHP formatting, and `git diff --check` passed. Vite still reports the existing large image-conversion bundle warning.

Tests use isolated SQLite databases, synthetic accounts, fake provider responses, and Stripe bypass. No real provider request, caregiver message, or payment was sent. The [validation record](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/validation.json) binds the passing run to source/configuration/knowledge fingerprints; the [full test log](C:/Users/Epicpp/homecare/.tmp/support-rework-2026-09-11/final-regression.log) and [JUnit report](C:/Users/Epicpp/homecare/.tmp/support-rework-2026-09-11/final-regression.xml) provide the raw results.

Browser checks on the isolated app verified:

- The original “Don't hire anyone” message returns the applicant summary without a hire confirmation. Sending it after a hire recap also removes the previous confirmation and active hiring goal, with a clear “Review applicants” link.
- A synthetic three-hour hire recap showed $90 care, $3 processing, a $93 total, and a $111.60 authorization hold including the configured buffer, alongside the exact date and duration. The hire was not confirmed.
- Schedule guidance focuses a visible 1176 × 205 section on desktop. On mobile, the old arrangements/regular-care/history targets occupy real visible sections rather than one-pixel markers.
- The List/Calendar toggle preserves useful focus after a Livewire update; exact dated visit links remain present.
- Closing inline support returns focus to the visible “Chat with support” button on both desktop and 390 × 844 mobile.
- The family and admin views have no horizontal page overflow at the tested mobile size.
- Admin Overview renders the current v10 prompt, 193 published KB versions, honest missing-monitor/evidence states, and distinct successful/failed provider metrics.
- Initial local publication succeeded for all 193 entries. Repeating the final package publication made **zero changes**, reporting **193 exact published no-ops**.

The local browser QA installation is [http://127.0.0.1:8040](http://127.0.0.1:8040). Its database is `.tmp/support-rework-2026-09-11/qa.sqlite`, separate from the existing local app on port 8028.

Synthetic QA accounts use the password `password`:

- Family with seeded requests and visits: `family.e2e@example.com`
- Admin: `test@test.com`
- Caregiver: `caregiver.ready.e2e@example.com`

A current deterministic regression result is not a fresh live-model benchmark. The admin evidence view makes that distinction explicit; no new live-model quality percentage is claimed.

## Publishing the knowledge with a future deployment

Deploying source files alone does not publish the database-backed KB. The existing command now reconciles the full managed package, independent of legacy importer ordering:

```sh
php artisan ai-support:realign-family-kb
```

That command only displays the plan, pending publication, conflicts, and unmanaged entries. To publish the reviewed package, an authorized administrator runs:

```sh
php artisan ai-support:realign-family-kb --publish --actor-email="YOUR_AUTHORIZED_ADMIN_EMAIL" --reason="Update AI Support for the care redesign and booking-specific confirmations."
```

Use the actual authorized admin email; a family login cannot publish the KB. The command does not enable AI, change pilot grants, or modify care/payment records. It refuses conflicting protected work and applies the managed change transactionally. Repeat publication of matching published content is a no-op.

See [OPERATIONS.md](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/OPERATIONS.md) for the evidence report contract, monitor semantics, and retention details.

The [knowledge alignment notes](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/KNOWLEDGE-ALIGNMENT.md) and [26-entry change manifest](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/changed-knowledge-entries.json) describe the content changes and draft-preservation rules.
