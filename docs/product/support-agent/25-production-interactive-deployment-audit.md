# Production Interactive Assistant Deployment Audit

Status: Authenticated production deployment, Draft import, Settings cleanup, and selective publication verified; limited release remains blocked

Audit date: August 14, 2026

Owner: Product and engineering

Related implementation: `61fca0341a467e2cb2ea65f559bd81c7c268658f`

Related release evidence: `e8694a8ee71e5de7382c159058efca709d60f264`

## Executive result

The interactive assistant code is present in production and is failing closed as designed. No live user can receive an AI answer or AI action in the observed state: both deployment guards are off, user-visible AI is off, all AI roles and capabilities are off, human-only is on, and there are no exact-user grants. Subsequent governed KB publication did not change those independent eligibility gates.

The authenticated Admin surfaces render correctly, the existing human-support fallback remains available, and the support dialog fits a 390-pixel mobile viewport without horizontal overflow. The browser inspection created or changed no message, request, grant, control version, KB record, or support assignment. The separately executed production import intentionally created the 12 governed Draft records described below.

The expanded interactive KB import was subsequently completed and verified in production. All 24 governed entries were then reviewed; 23 approved non-pricing entries were published and `KB-CARE-006` remained a validated Draft. Every entry is Version 1, reports validation passed, contains authoritative-source evidence, and has exactly five linked evaluation IDs. The content-free activity trail records the import plus exactly 23 successful publication events with no matching publication failure.

Limited release is not ready yet. The Settings inconsistency corrected in `4ac0f07` is deployed and production-verified: the operator UI no longer renders or accepts `shadow_enabled`, while the server retains the historical key and permanent `DEC-047` enablement denial. The governed non-pricing publication is recorded in the [production KB publication and Settings verification](26-production-kb-publication-and-settings-verification.md).

## Audit scope and method

The initial deployment audit used an authenticated full-Administrator session for the control plane and then the dedicated production editorial Family test account for the customer view. The Family account received no pilot grant. That initial audit was read-only except for the existing Admin **Login as** session change. The later authorized KB lifecycle mutations are separately scoped and evidenced in the [publication verification](26-production-kb-publication-and-settings-verification.md).

The deployed pages prove that the interactive Admin and runtime UI introduced by the implementation are present. The application does not expose an exact deployed Git revision in the inspected pages, so this browser audit does not independently attest the production server's Git `HEAD`.

Screenshots were captured for immediate visual review but are intentionally excluded from the repository so production browser artifacts do not become durable product documentation.

## Production state observed

| Control or inventory | Observed state | Result |
| --- | --- | --- |
| Runtime deployment guard | Off | Pass; fail-closed |
| Provider deployment guard | Off | Pass; fail-closed |
| Master and user-visible controls | Off | Pass |
| Family and Caregiver role controls | Off | Pass |
| Answer, navigation, context, intake, draft, recap, publication, and 24/7 controls | Off | Pass |
| Human-only control | On | Pass |
| Exact-user grants | 0 active | Pass |
| Governed KB | 24 working, 1 Draft, 23 published, 0 paused, 0 overdue | Pass; selective non-pricing publication verified |
| Customer support presentation | Human support only | Pass |

## Surface audit

| Surface | Evidence | Result |
| --- | --- | --- |
| Admin overview | Failing-closed state, both deployment guards off, zero grants, 23 published / 1 held Draft, human support preserved, no-shadow policy shown | Pass |
| Pilot list | Empty exact-user grant list; search and status filters render | Pass |
| User pilot card | Dedicated Family test account reports **Not enabled** and **Runtime Deployment Guard Off**; exact-user bundle, date, reason, acknowledgement, and higher-control warning render | Pass; no grant created |
| KB index | Search, lifecycle/role filters, create-draft entry point, 23 Published entries, and one held Draft render | Pass |
| KB editor | Content, roles, applicability, permitted/prohibited facts, navigation targets, sources, evaluation IDs, validation, review/publish workflow, history, and guarded deletion render | Pass; initial read-only audit plus separately evidenced governed publication |
| Activity | Content-free control-plane events render with actor, policy/outcome, and timestamp evidence; no transcript body is exposed | Pass |
| Settings | Deployment warnings and deny-by-default controls render; Shadow is absent; operational reason, impact acknowledgement, and typed `APPLY` confirmation are required | Pass; finding `PROD-UI-AIS-002` closed in production |
| Family desktop support | Launcher and dialog say **LoLo Support** and explicitly promise a human team-member reply; no AI label or action appears | Pass |
| Family mobile support | At 390 by 844 pixels, the dialog fits the viewport, document width equals viewport width, and visible navigation/minimize/send controls are 44 by 44 pixels | Pass |

## Browser health

After a fresh authenticated Admin reload, the inspected flow produced zero current application console errors. A Meta Pixel availability warning and unused preloaded-CSS warnings were present and did not affect the AI Support flow.

The reused browser session initially retained two earlier Livewire `503` responses and a dialog-state error from the deployment/login interval. They did not recur after reauthentication and reload. Treat them as a transient observation unless reproduced outside a deployment or session transition.

## Findings

### `PROD-KB-INT-001` - interactive KB package imported and verified

Status: Closed August 14, 2026.

The production command created all 12 missing `KB-CARE-*` records. The authenticated follow-up audit verified:

- 24 total working entries, all Draft, with zero published, paused, or overdue entries;
- all stable IDs from `KB-CARE-001` through `KB-CARE-012` present as Version 1;
- validation passed and authoritative sources present for every imported entry;
- exactly five linked evaluation IDs per entry, 60 total;
- 12 successful `draft_created` audit events and 12 successful `validation_passed` events at the import timestamp;
- zero failed import events; and
- zero exact-user grants, both deployment guards off, and human-only on after import.

The command sequence used a dry run first and applied only with an authorized Administrator email:

```bash
php artisan ai-support:import-interactive-kb
php artisan ai-support:import-interactive-kb --apply --actor-email=<admin-email>
```

The apply operation created validated Drafts only and did not enable AI. Keep `KB-CARE-006` held and unpublished.

### `PROD-UI-AIS-002` - remove shadow control from operator settings

Status: Closed in production August 14, 2026.

The prior Settings selector included `shadow_enabled` even though `DEC-047` permanently excludes production-conversation shadowing. The Admin component now excludes the key from both its rendered current-state/change-control surfaces and its accepted mutation validation. A forged Livewire change is rejected before any control version can be stored. The service still recognizes the key for historical compatibility and independently rejects enablement under `DEC-047`.

Production verification found no Shadow row and no Shadow selector option, 17 remaining operator controls, both deployment guards off, and human-only as the sole On control.

Verification passed:

- focused Settings regression: 2 tests, 11 assertions;
- complete AI Support suite: 67 tests, 583 assertions;
- full Laravel suite: 682 tests, 5,051 assertions; and
- targeted Pint plus `git diff --check`.

### Live AI execution deliberately not exercised

No production model call, automated conversation, draft, recap, Care Request publication, or human-transfer transition was attempted. Exercising those paths would require deployment guards, controls, and an exact-user grant, which remain intentionally absent. Their current evidence remains the isolated deterministic, browser, accessibility, and model-evaluation suite recorded in the implementation evidence. A production-like staff rehearsal is still required before a named-user pilot.

## Release position

This audit authorizes no AI activation. It confirms that the deployed state is safe while the release gates remain open.

The next controlled batch is:

1. Close provider privacy/retention and monitoring ownership evidence.
2. Run the production-like staff-account safety, handoff, cost-stop, and rollback rehearsal.
3. Complete the five-person older-adult usability gate.
4. Name the first two Family users and obtain an explicit limited-release decision before creating 14-day exact-user grants.
