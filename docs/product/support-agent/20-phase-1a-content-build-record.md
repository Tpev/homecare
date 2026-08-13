# Phase 1A Governed Content Build Record

Status: Production deployment and Draft import verified; two Draft-only editorial corrections pending; nothing published

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
| `resources/ai-support/knowledge-base/v1.php` | `9167B8F80E374D7EE12DCB6C260816C02F2FA0DBFBF911757C0D94AA2E8BC82B` |
| `resources/ai-support/evaluations/v1.php` | `B1D08BA5AEFF53FA3D4C04D8ADBE9F54C9EAB7802DB1743A8A298A2CC0860500` |

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

Result: **40 tests passed, 367 assertions, 0 failures**.

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

### Draft-only editorial findings

The safety, authorization, lifecycle, and source structure passed. Two grammatical strings should be corrected before KB approval or candidate-model evaluation:

1. `KB-SUP-002` fact: change `in LoLo current United States scope` to `within LoLo's current United States scope`.
2. `KB-FAM-004` negative retrieval example: change `Show me another family account members.` to `Show me the members of another Family Account.`

These findings do not affect live users because every entry remains Draft and retrieval-ineligible. They do block claiming final content polish.

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

Phase 1A deployment and production Draft import are complete and verified. Correct the two editorial findings in both the repository manifest and governed production Drafts, rerun deterministic validation, and retain the new artifact checksum.

After that correction, the next product milestone is `DEC-012`: evaluate least-cost runtime candidates on the identical synthetic corpus. That work remains offline and cannot authorize production shadowing or a user-visible pilot. `DEC-014` still blocks production-data shadowing, and `DEC-015` still blocks a truthful user-visible human-response promise.
