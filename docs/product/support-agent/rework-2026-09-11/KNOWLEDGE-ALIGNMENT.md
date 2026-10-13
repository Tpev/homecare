# Knowledge and navigation alignment

This package updates local source and provides an explicit database publication path. It does not deploy code, enable AI, change pilot access, or alter care, payments, conversations, or account records. Production knowledge was not published during this work.

## Source inventory

`family-experience-alignment-v2` resolves all **193 managed stable IDs** across the seven existing source packages. It refreshes missing entries without relying on the order of legacy import commands. The current intent catalog has 324 intents and 171 distinct mapped knowledge IDs. `KB-FAM-004` intentionally resolves to the newer Family Operations definition instead of the older Initial definition.

The [changed-entry manifest](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/changed-knowledge-entries.json) lists **26 affected entries**, including entries whose shared source evidence changed. The remaining managed entries are reconciled to their existing canonical definitions. No managed stable ID was retired.

| Source package | Changed stable IDs |
| --- | --- |
| Initial | `KB-FAM-001`, `KB-FAM-002` |
| Interactive | `KB-CARE-006`, `KB-CARE-007` |
| Family Operations | `KB-FOP-PAY-011` |
| Payment and Time | `KB-B4-PRICE-001`, `KB-B4-PAY-011` |
| Profile and Request | `KB-B5-REQUEST-001`, `002`, `005`, `006`, `008`, `009`, `010` |
| Marketplace and Care | `KB-B67-MATCH-001`, `002`, `003`, `004`, `005`, `007`; `KB-B67-VISIT-009`, `014`; `KB-B67-REGULAR-001`, `002`, `007` |
| Family Administration | `KB-B89-HISTORY-003` |

The prompt and configuration identify this release as `interactive-support-v10`. The source registry and generated intent catalog are updated together; historical audit/evaluation documents remain historical.

## Current product guidance

The assistant names Overview, Care request, Schedule, and History. Schedule supports List and Calendar. Recruitment guidance uses Care request → Find caregivers → Review applicants → Get started. Search, Invited, and Saved profiles have separate request-bound targets. Saved profiles and shortlisted applications are distinct. Public request publication opens Find caregivers and does not imply a hire.

Legacy target IDs remain valid. `family.request.overview` still means Full care details. `family.care_actions` still opens the existing Needs your attention page. New targets are `family.request.summary`, `.discovery`, `.invitations`, `.saved`, and `.start`; they retain role and account authorization.

Visit and hours guidance uses the exact generated request when present. A recurring plan remains a separate destination. Current/paused visits use the existing schedule recency rule, including valid long visits and recent null-end records; old unfinished visits remain visible as issues without being called the next current visit. Overview and Schedule use their existing exact-visit action URL.

The four formerly hidden markers now sit on visible, named controls/sections. Guidance opens enclosing disclosures, checks usable geometry, moves keyboard focus, and reports arrival only after focus succeeds. Mobile chat closing retains stable inline opener identity through Livewire history restoration.

## Pricing information

Knowledge and model instructions contain no fixed universal dollar amounts. General deterministic pricing reads the existing `MarketplacePricing` defaults and names care, Family processing fee, and Family total separately. Specific/personal/historical care questions defer to the exact agreement, booking snapshot, and ledger. Changed-rate tests prove that general answers follow configuration without changing a booking. Financial confirmation recaps use the separate authoritative quote implementation documented in RESULTS.md.

## Governed publication

Run commands from the intended release with the intended database explicitly configured. A source deployment alone does not update published knowledge.

```sh
# Read-only preview in the configured environment.
php artisan ai-support:realign-family-kb

# Create validated drafts/revisions; preserve currently published pointers.
php artisan ai-support:realign-family-kb --apply --actor-email="YOUR_AUTHORIZED_ADMIN_EMAIL"

# After reviewing the exact plan, publish through submit/review/approve/publish.
php artisan ai-support:realign-family-kb --publish --actor-email="YOUR_AUTHORIZED_ADMIN_EMAIL" --reason="Align support guidance with the current care experience."

# Verify the resulting package has only exact published no-ops.
php artisan ai-support:realign-family-kb
```

For the synthetic local QA database only, first load `.tmp/support-rework-2026-09-11/qa-environment.ps1` in the same PowerShell session; it explicitly selects the private database used on port 8040. Then use its synthetic authorized admin `test@test.com`. Do not use that environment or account as a production instruction. The root task exercised publication in this private database, with 193 managed entries published.

The plan distinguishes missing entries, new revisions, untouched managed draft refreshes, exact published no-ops, and exact content awaiting publication. Paused/withdrawn/unavailable entries and inconsistent pointers are conflicts. An unrecognized or manually modified draft is also a conflict, even when editable. An administrator must resolve conflicting work through the knowledge workflow; the command has no force-overwrite switch.

New package drafts carry an audit fingerprint and edit version. Only an unchanged draft positively identified by that provenance can refresh automatically. Refresh creates a new version and retains the prior draft and its sources. Published versions remain unchanged until explicit publication. Entry, working/published version, and source locks precede re-planning; publication rechecks the current working pointer and content fingerprint. A changed pointer after a previous preview cannot silently replace manual work.

Published IDs outside the managed package are listed for a separate editorial review and preserved. The command does not automatically archive or withdraw custom knowledge. If one contains obsolete advice, an administrator should revise it or explicitly withdraw it while retaining its history before claiming the whole installation is aligned.

## Verification

The focused regression suite uses SQLite `:memory:`, private compiled views/cache, and fake providers. It covers all 193 definitions, the 26-entry manifest, missing and published entries, idempotent publication, pending publication, unknown/edited draft refusal, managed draft/source preservation, paused/withdrawn conflict reporting, and a working-pointer change between preview and execution. It also covers role/account-bound targets, visible semantic markers, generated visit/timesheet links, current/stale/long/null-end visits, dynamic pricing, and no repricing of personal or historical care.

Final focused and consolidated counts and local browser results are recorded in [RESULTS.md](C:/Users/Epicpp/homecare/docs/product/support-agent/rework-2026-09-11/RESULTS.md). Lock behavior is implemented with database row locks; SQLite tests validate re-planning and refusal logic, not a parallel production-database load test.
