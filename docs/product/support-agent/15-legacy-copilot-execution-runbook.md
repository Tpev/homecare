# Legacy AI Care-Request Copilot Retirement Runbook

Status: Implementation-ready; production execution not authorized by this document

Last updated: August 13, 2026

Decision authority: `DEC-005` and `DEC-011`

Required production execution approval: Security/privacy owner and database/operations owner

## Purpose

This runbook is the repository-specific procedure for permanently removing the legacy AI care-request copilot while preserving ordinary care requests, support tickets, and human-support messages. It covers the application repository and primary relational database exactly. Production topology outside this repository must be resolved in the execution record before the destructive command is authorized.

The build may remove legacy execution paths and add guarded tooling. It must not run the destructive command against production merely because the tooling exists.

## Exact repository inventory

### Primary relational targets to destroy

| Table | Content | Relationship | Destruction order |
| --- | --- | --- | --- |
| `ai_request_messages` | User/assistant text, structured turn data, latency and token data | `ai_request_session_id` references `ai_request_sessions.id` with cascade delete | First |
| `ai_request_sessions` | Draft JSON, missing fields, quality score, model, timestamps, user and published-request references | `family_user_id` references `users.id`; `published_care_request_id` references `care_requests.id` with `nullOnDelete` in the opposite direction | Second |

Deleting a session must never delete its `published_care_request_id`. The foreign key points from the legacy session to the durable care request, so deleting the session does not cascade into `care_requests`.

### Relational targets to preserve

- `care_requests` and every child/domain record of a care request
- `support_tickets`, `support_ticket_messages`, and `support_ticket_activities`
- Users and Family Account records
- Booking, payment, caregiver, notification, and audit domain records
- `legacy_copilot_destruction_runs`, which is content-free evidence of execution

### Legacy application execution paths to remove

- Route `/family/requests/create/ai` and route name `family.requests.create_ai`
- `App\Livewire\Family\AiRequestCopilot` and its Blade view
- `App\Models\AiRequestSession` and `App\Models\AiRequestMessage`
- `App\Contracts\AiCopilotResponder`
- `App\Services\AiCopilot\*`
- The legacy OpenAI client and provider binding used only by that copilot
- `FEATURE_AI_REQUEST_COPILOT` and the legacy OpenAI environment/configuration keys
- Legacy copilot tests and navigation references

The deployed migrations that originally created the tables remain immutable. A new follow-up migration drops the tables only after it verifies that both are empty. It never deletes their rows.

## Production topology checklist that must be resolved

Repository inspection found no legacy-copilot embeddings, search indexes, fixtures derived from production, analytics tables, caches, or external export jobs. That is repository evidence, not proof of the production topology. Before production execution, the operators must name and verify each actual destination:

- Primary database connection and database name
- Read replicas and delayed replicas
- Database snapshots and continuous backup generations
- Warehouse, analytics, or BI destinations
- Search or vector indexes
- Application and edge caches
- Error monitoring and log destinations
- Manual exports and operator workstations
- Production-derived test fixtures or sanitized clones

For each destination, record one of: **not present**, **verified zero**, **destroyed**, or **expires on YYYY-MM-DD with access blocked and retention not extendable**. The destructive command requires an explicit assertion that this checklist was completed, but it cannot verify systems that are not represented in this repository.

## Build and deployment sequence

1. Deploy code that removes the route, UI, model-call binding, services, models, and all writes to the legacy tables.
2. Verify `/family/requests/create/ai` returns 404 and the ordinary `/family/requests/create` form still works.
3. Run the human-support preservation suite and confirm family/caregiver chat remains available.
4. Apply only the migration that creates `legacy_copilot_destruction_runs`; do not yet apply the later legacy-table drop or foundation migrations in production:

   ```text
   php artisan migrate --path=database/migrations/2026_08_13_100000_create_legacy_copilot_destruction_runs_table.php --force
   ```
5. Resolve the production topology checklist and backup handling above.
6. Run the destruction command without `--execute` to inspect table counts and the exact confirmation phrase.
7. Obtain the two named approvals and record the operator, approvers, backup status, code version, and exceptions.
8. Run the guarded destruction command once with the exact environment, database confirmation, and execution arguments.
9. Verify both legacy tables contain zero rows and preserved-domain counts/invariants still pass.
10. Run the normal remaining deployment migrations. The next follow-up migration refuses to drop either legacy table while it contains rows and requires successful production destruction evidence; the control-plane, KB, and runtime-foundation migrations then follow in order.
11. Re-run the verification suite and archive the content-free execution evidence.

## Guarded command contract

Dry run:

```text
php artisan ai-support:destroy-legacy-copilot-data --environment=production
```

The dry run prints counts only. It does not print message bodies, draft JSON, user identifiers, or care-request details.

Production execution shape:

```text
php artisan ai-support:destroy-legacy-copilot-data \
  --environment=production \
  --execute \
  --confirm="DESTROY-LEGACY-COPILOT-DATA:production:<database-name>" \
  --operator="<named operator>" \
  --approver="<security/privacy approver>" \
  --approver="<database/operations approver>" \
  --derived-targets-verified \
  --backup-status="<destroyed or final expiry date and restore control>"
```

The command must fail closed when:

- The supplied environment is not the running application environment.
- `--execute` is absent.
- The confirmation phrase does not include the resolved current database name.
- The operator, two approvers, derived-target assertion, or backup status is missing.
- Either table changes unexpectedly during the transaction.
- Zero-row verification fails.
- The content-free audit record cannot be written atomically with deletion.

## Verification queries and invariants

Record counts before execution without copying content:

```sql
select count(*) from ai_request_messages;
select count(*) from ai_request_sessions;
select count(*) from care_requests;
select count(*) from support_tickets;
select count(*) from support_ticket_messages;
```

Required postconditions:

- `ai_request_messages` count is zero or the table is absent after schema removal.
- `ai_request_sessions` count is zero or the table is absent after schema removal.
- `care_requests` count is unchanged by the destruction transaction.
- `support_tickets` and `support_ticket_messages` counts are unchanged by the destruction transaction.
- No application route, Livewire component, service binding, navigation item, or test can invoke the legacy copilot.
- Ordinary care-request creation and human support remain usable.

The command records the preservation counts before and after. A mismatch rolls back the database transaction and produces no successful audit record.

## Backups and restore control

The database/operations owner must destroy containing backups where allowed. If selective deletion is impossible, record the immutable final expiry date, restrict ordinary access, prohibit retention extension, and track that date as unfinished extinction work.

Every restore procedure for a backup that can contain the legacy tables must keep the restored environment inaccessible, deploy the retirement code and audit migration, rerun this destruction command for the restored database, verify zero rows, and only then permit application or administrator access.

Legacy content is never a rollback source. Application rollback must keep the legacy feature absent and must not recreate the route or write path.

## Failure handling

- A database error rolls back message deletion, session deletion, preservation checks, and audit insertion together.
- A derived-store or backup failure leaves the production destruction work open even if the primary database is clean.
- The legacy runtime remains disabled while a failure is investigated.
- Do not export rows for debugging or place content in incident tickets. Use counts, target names, error codes, and restricted database logs.
- An exception requires exact scope, authority, access restriction, and a dated destruction condition; it cannot be reused as KB or evaluation data.

## Content-free completion evidence

The retained evidence contains only:

- Environment and hashed database reference
- Execution timestamp, named operator, and named approvers
- Application/code and migration versions
- Before/after aggregate counts
- Target checklist status
- Verification result
- Backup-extinction status
- Approved exception references

It contains no message text, draft JSON, model payload, care details, user email, user name, or reversible copy of deleted content.

Phase 0 destruction is not fully complete until the primary and derived targets are verified and the final containing backup is destroyed or reaches its recorded expiry under the restore-time deletion control.
