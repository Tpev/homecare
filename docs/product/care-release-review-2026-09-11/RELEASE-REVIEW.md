# Care UI release review — September 11, 2026

**Assessment: local compatibility review passed after fixes. The reviewed changes are packaged for master; deployment is performed separately by the site owner.**

The reviewed workspace is based on commit `d966d945e3683fbed02ce07ec8b56232f722296d`. That baseline predates this release: it lacks 26 required files and has earlier content in 26 others. The release commit must pass the verifier against the recorded manifest.

Agreed rollout: commit and push the reviewed changes to master, the site owner runs the updated `deploy.sh`, and then provides access to a production family test account for authenticated checks. The owner chose this direct rollout instead of a separate staging setup.

## Issues found and fixed

| Issue | Correction | Verification |
| --- | --- | --- |
| A recurring hire estimate could use an already-started short slot instead of the first future visit that would be booked. | The confirmation uses the existing occurrence service to preview the actual first future date, duration and platform quote. It does not create a plan or booking. | Regression reproduced the old 1-hour/3-hour mismatch, then verified the preview matches the eventual booking. Historical pricing and hiring cases pass. |
| Legacy filled requests without a booking, and unpublished drafts, could be described as closed. | Their saved state is described accurately. Filled requests retain pending visit setup; drafts remain unpublished. | Draft, filled, cancelled and expired cases pass without changing action eligibility. |
| Coverage bookings could be labelled as regular recurring visits. | Restored the “Continuous care” distinction on recurring-care visit cards. | All four visit types retain their exact generated-visit links. |
| The existing recurring timeline could hide a live or paused visit after the scheduled check-in grace period. | Applied scheduled grace only to scheduled visits. Current live/paused visits use the Schedule page’s existing recent-end/start rules; stale visits stay excluded. | Reproduced before the fix, then checked active/paused/missing-end/stale cases and unchanged booking rows. |
| Preparing an inactive deployment could clear compiled templates used by the active release when storage is shared. | Each release now has its own `bootstrap/cache/views`, persisted through Laravel’s configuration cache. Shared sessions and uploads remain durable. | Mocked initial deploy, subsequent deploy, manual rollback and automatic failed-health rollback all pass; previous compiled views survive. |
| New styles, templates and fonts could be omitted from a tracked-files-only release; local test backups could be added accidentally. | Added a 52-file release manifest and commit verifier. Git now ignores `.tmp`, `.codex-temp` and `.playwright-mcp`. | Working-tree verification passes. The baseline commit correctly fails. Cleanup scripts and the SQLite backup are confirmed ignored. |

Three test cases still expected earlier UI wording or exact count markup. Their assertions now reflect the intended labels and accessible count text; ownership, state, pricing, route and data-preservation assertions remain in place.

## Compatibility evidence

**433 distinct automated tests passed** across the final scoped runs:

| Area | Passed tests |
| --- | ---: |
| Full Family suite after all fixes | 143 |
| Payments, authentication, family-account access/sharing, notifications and caregiver invitations | 135 |
| Recurring care and booking, including four added compatibility cases | 113 |
| Marketplace/private requests | 13 |
| Unit checks and family support/navigation guidance | 29 |
| **Total** | **433** |

Tests used isolated SQLite memory databases, private compiled-view/cache directories, array mail and Stripe bypass/mocks. The final Family run passed 1,336 assertions; support/unit checks passed 322 assertions. Earlier failing reproduction runs are retained in local logs for traceability.

The cached-application rehearsal used a **disposable copy of the September 10 synthetic backup**, containing the older test requests that had been removed from Maria’s active local account:

- 43 requests and 10 recurring plans across legacy URL tabs and lifecycle states.
- **420 authenticated routes returned HTTP 200** under cached configuration, routes and Blade views.
- **535 rows across 24 care/business tables remained byte-for-byte equivalent at the field level**, including requests, applicants, invitations, bookings, payments, reviews and plan records.
- 12 desktop/mobile screen checks passed without JavaScript errors or horizontal overflow.
- Applicant screens remained free of comparison prices; local fonts returned HTTP 200.

The frontend production build, PHP formatting checks and whitespace checks passed. Existing Vite notices about font URLs resolved at runtime and large unrelated chunks remain non-blocking; both font files were verified through HTTP.

There are **no database migrations, model changes, route changes or dependency upgrades in this care UI release**. Existing pricing, payment and booking services continue to own business decisions. The changes do not reset accounts, rewrite old requests or regenerate visits.

Evidence:

- [Cached old-data rehearsal](snapshot-validation.json)
- [Deployment smoke test](../../../tests/Deployment/atomic-deploy-smoke.sh)

Detailed logs remain in the local `.tmp/preprod-*` folders, and desktop/mobile screenshots remain in this review's local `screenshots` folder. These local artifacts and database fixtures are excluded from the release. Earlier Family wording failures are superseded by the final full Family pass.

## Release requirements

1. Commit the reviewed files in [runtime-files.txt](runtime-files.txt), the relevant regression tests and deployment documentation. Include all four new care CSS files, seventeen new Blade partials, both fonts and their licenses. Exclude local databases, backups, cleanup scripts, browser captures and local server/environment helpers from the runtime release.
2. Verify the intended release commit against the recorded Git blob hashes:

   ```text
   node docs/product/care-release-review-2026-09-11/verify-release.mjs <release-commit>
   ```

   This must pass. The verifier normalizes Git line-ending filters so Windows/Linux differences do not create false mismatches. Do not regenerate the manifest simply to bypass a mismatch; review and validate any subsequent change first.
3. The site owner deploys the verified commit using the updated atomic deployment process, keeping the previous release available for rollback.
4. Once the owner provides the production family test account, check older one-time requests, legacy recurring plans and exact visits, pending hours/payment, history and existing private invitation links. The `/up` health check alone cannot verify these authenticated screens. Actual production records and live Stripe authentication were not exercised by the synthetic local rehearsal.

The [deployment guide](../../deployment/zero-downtime-deployments.md) documents activation and rollback. No production credentials, data, services or external messages were used by this review.
