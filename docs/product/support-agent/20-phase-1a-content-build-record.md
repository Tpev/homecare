# Phase 1A Governed Content Build Record

Status: Repository implementation complete; production deployment and Draft import pending

Recorded: August 14, 2026

Owner: Product and engineering

Implementation commit: `688de776dc0fa9a18ebe9c8108ac298eec73db70`

Authority: `DEC-016`, `DEC-022`, `DEC-023`, `DEC-025`, and `DEC-032` through `DEC-046`

## Outcome

The approved English-only initial knowledge package is now reproducible, schema-validated, executable offline, and ready for a controlled Draft-only import after deployment.

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

## Controlled deployment and import sequence

After the implementation commit is deployed through the normal `deploy.sh` process:

1. Run `php artisan ai-support:validate-initial-content`.
2. Run `php artisan ai-support:import-initial-kb` and inspect the dry-run plan.
3. Resolve any reported conflict through the normal Admin lifecycle; do not bypass or overwrite it.
4. Only when the plan reports 12 creates or approved identical no-ops and zero conflicts, run `php artisan ai-support:import-initial-kb --apply --actor-email="AUTHORIZED_ADMIN_EMAIL"`.
5. Run the dry-run command again and require 12 identical no-ops and zero creates/conflicts.
6. Inspect the Admin overview and KB list. Require 12 working, 12 Draft, 0 published, accurate paused/overdue counts, and no active pilot grant or enabled AI control.

The production apply step is an explicit operator action. It is not authorized merely by deploying this commit.

## Remaining gate

Phase 1A repository implementation is complete. Production still requires controlled deployment, dry-run evidence, explicit Draft import, and Admin inspection.

After that evidence is recorded, the next product milestone is `DEC-012`: evaluate least-cost runtime candidates on the identical synthetic corpus. That work remains offline and cannot authorize production shadowing or a user-visible pilot. `DEC-014` still blocks production-data shadowing, and `DEC-015` still blocks a truthful user-visible human-response promise.
