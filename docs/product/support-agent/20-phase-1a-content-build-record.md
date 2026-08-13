# Phase 1A Governed Content Build Record

Status: Production deployment, Draft import, and two Draft-only editorial corrections verified; nothing published

Recorded: August 14, 2026

Owner: Product and engineering

Implementation commit: `688de776dc0fa9a18ebe9c8108ac298eec73db70`

Authority: `DEC-016`, `DEC-022`, `DEC-023`, `DEC-025`, and `DEC-032` through `DEC-046`

## Outcome

The approved English-only initial knowledge package is reproducible, schema-validated, executable offline, deployed, and imported into production as governed Drafts.

This implementation does **not** publish knowledge, call a model, enable an AI control, create a pilot grant, expose navigation, or change the existing human-only customer experience.

## Delivered artifacts

| Artifact | Version or command | Result |
| --- | --- | --- |
| Initial governed knowledge manifest | `initial-kb-v1` | Exactly 12 approved stable IDs with sources, roles, semantic targets, boundaries, review dates, and 60 linked evaluation IDs |
| Entry-level evaluation catalog | `initial-kb-evals-v1` | Exactly 60 executable synthetic fixtures linked one-to-one with the approved entry definitions |
| Critical regression catalog | `initial-kb-evals-v1` | 10 additional synthetic cross-entry regressions covering emergency and human-transfer precedence, role ambiguity, removed/signed-out/Admin contexts, prompt injection, secret handling, missing targets, and in-flight handoff |
| Deterministic validator | `php artisan ai-support:validate-initial-content` | Validates manifest, fixtures, roles, targets, actions, safety outcomes, and handoff suppression with zero model calls |
| Draft-only importer | `php artisan ai-support:import-initial-kb` | Dry-run by default; explicit Admin actor and `--apply` required for writes |
| Admin overview | `/admin/ai-support` | Reports actual working, Draft, published, paused, and overdue KB counts |

## Artifact integrity

| Artifact | SHA-256 |
| --- | --- |
| `resources/ai-support/knowledge-base/v1.php` | `DC12F6A5517A58D4CB85747E6E30DB791B50352F4996DF341F3590D55EFD59B2` |
| `resources/ai-support/evaluations/v1.php` | `0AD0C792359AF4D448863F5470F56CB5FCF2F2E4F17DCA4533A0AED761427A5A` |

Any content change produces a new checksum and must be reviewed against the accepted definitions and evaluation expectations before import or model evaluation.

## Import safety behavior

The importer uses the existing governed KB workflow and Admin audit service.

- A missing approved stable ID is created as version 1 in `Draft` state.
- An identical existing Draft is a no-op.
- A different existing Draft, non-Draft working version, missing working version, or tombstoned stable ID blocks the whole operation without overwrite.
- Every new version is validated inside the import transaction.
- Published-entry count must remain identical before and after the operation or the transaction rolls back.
- The importer never invokes approve, publish, resume, grant, control, model/provider, support-ticket, or domain-write services.
- Deploying the code does not automatically import content; there is no content migration or deployment hook.

## Verification evidence

### Deterministic content result

| Measure | Result |
| --- | ---: |
| Governed entries | 12 |
| Entry-level cases | 60 |
| Critical cross-entry regressions | 10 |
| Total cases | 70 |
| Critical cases | 52 |
| Validation hard failures | 0 |
| Model calls | 0 |
| User-visible behavior changes | 0 |

### Automated regression result

Command: `php artisan test tests/Feature/AiSupport`

Current result after Phase 1B adapter integration: **45 tests passed, 404 assertions, 0 failures**.

The suite proves:

- Exact inventory and evaluation linkage.
- Dry-run performs no database writes.
- Apply creates validated Drafts only and is idempotent.
- Conflicts and tombstones fail closed without mutation.
- Imported Drafts remain excluded from retrieval.
- Published count, controls, grants, and runtime state remain unchanged.
- Non-Admin import attempts are refused.
- Admin overview counts use actual KB state.
- Existing legacy-retirement, pilot-isolation, retention, handoff, confirmation, and runtime-safety controls still pass.

### Production Admin audit

Performed August 14, 2026 in an authenticated full-Administrator browser session. The audit was read-only: no KB field, lifecycle state, control, grant, support record, or domain record was changed.

| Area | Observed production evidence | Result |
| --- | --- | --- |
| Overview | Runtime guard off; customer AI failing closed; master off; user-visible off; human-only on | Pass |
| Pilot isolation | 0 active, 0 scheduled, and 0 expiring exact-user grants; pilot list empty | Pass |
| KB aggregate state | 12 working, 12 Draft, 0 published, 0 paused, 0 overdue | Pass |
| Stable inventory | All 12 approved `KB-SUP-*`, `KB-FAM-*`, and `KB-CGV-*` IDs present as Version 1 | Pass |
| Entry validation | Every one of the 12 entry editors reports `Validation passed` | Pass |
| Evaluation linkage | Every entry contains five linked evaluation IDs; 60 total | Pass |
| Source linkage | Every entry contains authoritative sources; 33 source records total | Pass |
| Role and target isolation | Shared entries map to Family and Caregiver plus `support.center`; each Family/Caregiver entry maps only to its expected role and registered semantic target | Pass |
| Audit evidence | 12 successful Draft-creation events plus 12 successful validation events; 0 failed events | Pass |
| Settings | No stored control versions; safe defaults remain master off, user-visible off, shadow off, human-only on, both roles off, and support capability off | Pass |
| Unauthenticated isolation | Overview, pilots, KB, activity, and settings each returned `302` to `/login` without a session | Pass |
| Public invisibility | Public home returned `200`; rendered source contained no `AI Support`, `AI assistant`, or `automated support` marker | Pass |
| Current browser health | No console error reproduced on the final current-page check; all audited pages loaded successfully | Pass |

The browser session history contained two earlier Livewire `503` resource messages and one dialog-state error. These did not recur during the completed page audit, and the final current-page console check was clean. Treat recurrence outside deployment/maintenance windows as an operational follow-up.

The browser-visible Phase 1A UI and imported records prove that the implementation is deployed. The application does not expose the server Git SHA in the Admin UI, so this browser audit does not independently attest the exact server checkout hash.

### Draft-only editorial findings — closed

The safety, authorization, lifecycle, and source structure passed. The two grammatical findings were corrected in both the repository manifest and the governed production Drafts on August 14, 2026:

1. `KB-SUP-002` fact: change `in LoLo current United States scope` to `within LoLo's current United States scope`.
2. `KB-FAM-004` negative retrieval example: change `Show me another family account members.` to `Show me the members of another Family Account.`

Both affected production editors were saved and rerun through validation; each reported `Validation passed`. The production overview remained 12 working, 12 Draft, 0 published, 0 grants, and customer AI failing closed. Repository validation and the focused content suite also passed. These corrections never affected live users because every entry remained Draft and retrieval-ineligible.

Before measured execution, fixture validation also found and corrected a model-contract inconsistency: 24 existing cases required human-only transfer but did not expose `SUP-HANDOFF-001` in their synthetic available-tool list. The case IDs, messages, expected outcomes, critical classification, and total 70-case inventory did not change. The executable fixture now exposes handoff wherever transfer is required, and the catalog validator permanently rejects this impossible configuration.

## Controlled deployment and import sequence

This sequence completed successfully on August 14, 2026 and remains the recovery/redeployment reference.

After the implementation commit is deployed through the normal `deploy.sh` process:

1. Run `php artisan ai-support:validate-initial-content`.
2. Run `php artisan ai-support:import-initial-kb` and inspect the dry-run plan.
3. Resolve any reported conflict through the normal Admin lifecycle; do not bypass or overwrite it.
4. Only when the plan reports 12 creates or approved identical no-ops and zero conflicts, run `php artisan ai-support:import-initial-kb --apply --actor-email="AUTHORIZED_ADMIN_EMAIL"`.
5. Run the dry-run command again and require 12 identical no-ops and zero creates/conflicts.
6. Inspect the Admin overview and KB list. Require 12 working, 12 Draft, 0 published, accurate paused/overdue counts, and no active pilot grant or enabled AI control.

The production apply step is an explicit operator action. It is not authorized merely by deploying this commit.

## Remaining gate

Phase 1A deployment, production Draft import, and editorial cleanup are complete and verified. The next product milestone is `DEC-012`: evaluate least-cost runtime candidates on the identical synthetic corpus. The adapter exists, but provider execution is waiting on usable API credit as recorded in [the Phase 1B adapter and execution record](21-phase-1b-offline-model-evaluation.md). This work remains offline and cannot authorize production shadowing or a user-visible pilot. `DEC-014` still blocks production-data shadowing, and `DEC-015` still blocks a truthful user-visible human-response promise.
