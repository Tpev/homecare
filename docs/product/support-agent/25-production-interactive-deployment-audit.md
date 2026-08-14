# Production Interactive Assistant Deployment Audit

Status: Authenticated production deployment and Draft-import audit complete; limited release remains blocked

Audit date: August 14, 2026

Owner: Product and engineering

Related implementation: `61fca0341a467e2cb2ea65f559bd81c7c268658f`

Related release evidence: `e8694a8ee71e5de7382c159058efca709d60f264`

## Executive result

The interactive assistant code is present in production and is failing closed as designed. No live user can receive an AI answer or AI action in the observed state: both deployment guards are off, user-visible AI is off, all AI roles and capabilities are off, human-only is on, there are no exact-user grants, and no KB entry is published.

The authenticated Admin surfaces render correctly, the existing human-support fallback remains available, and the support dialog fits a 390-pixel mobile viewport without horizontal overflow. The browser inspection created or changed no message, request, grant, control version, KB record, or support assignment. The separately executed production import intentionally created the 12 governed Draft records described below.

The expanded interactive KB import was subsequently completed and verified in production. The database now contains all 24 governed entries as Drafts, including `KB-CARE-001` through `KB-CARE-012`; nothing is published. Every imported entry is Version 1, reports validation passed, contains authoritative-source evidence, and has exactly five linked evaluation IDs. The content-free activity trail records 12 successful creations and 12 successful validation runs with no failure.

Limited release is not ready yet. A non-safety-blocking finding remains: Settings still displays the permanently denied `shadow_enabled` control even though the overview and server policy correctly state that shadow mode is outside the release. The imported non-pricing entries also require deliberate review and selective publication before a controlled staff rehearsal.

## Audit scope and method

The audit used an authenticated full-Administrator session for the control plane and then the dedicated production editorial Family test account for the customer view. The Family account received no pilot grant. The audit was read-only except for the existing Admin **Login as** session change; no application-domain data was mutated.

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
| Governed KB | 24 working, 24 Draft, 0 published, 0 paused, 0 overdue | Pass; interactive import verified |
| Customer support presentation | Human support only | Pass |

## Surface audit

| Surface | Evidence | Result |
| --- | --- | --- |
| Admin overview | Failing-closed state, both deployment guards off, zero grants, Draft-only KB counts, human support preserved, no-shadow policy shown | Pass |
| Pilot list | Empty exact-user grant list; search and status filters render | Pass |
| User pilot card | Dedicated Family test account reports **Not enabled** and **Runtime Deployment Guard Off**; exact-user bundle, date, reason, acknowledgement, and higher-control warning render | Pass; no grant created |
| KB index | Search, lifecycle/role filters, create-draft entry point, and 24 Draft records render | Pass |
| KB editor | Content, roles, applicability, permitted/prohibited facts, navigation targets, sources, evaluation IDs, validation, review/publish workflow, history, and guarded deletion render | Pass; no mutation |
| Activity | Content-free control-plane events render with actor, policy/outcome, and timestamp evidence; no transcript body is exposed | Pass |
| Settings | Deployment warnings and deny-by-default controls render; operational reason, impact acknowledgement, and typed `APPLY` confirmation are required | Pass with finding `PROD-UI-AIS-002` |
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

### `PROD-UI-AIS-002` - shadow control remains visible

Severity: Operator-consistency cleanup; not a current safety failure.

The Settings selector includes `shadow_enabled` even though `DEC-047` permanently excludes production-conversation shadowing. Server policy correctly rejects any attempt to enable it, and the overview says **No shadow mode**. Remove the control from the Admin selector so the operator interface matches the approved product model and cannot invite a meaningless failed operation.

### Live AI execution deliberately not exercised

No production model call, automated conversation, draft, recap, publication, or human-transfer transition was attempted. Exercising those paths would require deployment guards, controls, published KB content, and an exact-user grant, which were intentionally absent. Their current evidence remains the isolated deterministic, browser, accessibility, and model-evaluation suite recorded in the implementation evidence. A production-like staff rehearsal is still required before a named-user pilot.

## Release position

This audit authorizes no AI activation. It confirms that the deployed state is safe while the release gates remain open.

The next controlled batch is:

1. Remove the visible shadow control and redeploy that small operator-UI cleanup while preserving the server denial.
2. Review and selectively publish only the non-pricing KB entries needed for the first one-time Family rehearsal; keep every runtime, role, capability, and grant control off.
3. Close provider privacy/retention and monitoring ownership evidence.
4. Run the production-like staff-account safety, handoff, cost-stop, and rollback rehearsal.
5. Complete the five-person older-adult usability gate.
6. Name the first two Family users and obtain an explicit limited-release decision before creating 14-day exact-user grants.
