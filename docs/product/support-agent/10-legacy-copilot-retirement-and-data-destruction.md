# Legacy Copilot Retirement and Data Destruction

Status: Accepted execution contract under `DEC-005` and `DEC-011`

Accepted: August 13, 2026

Owners: Engineering and product

Required execution approval: Security/privacy and the database/operations owner

## Objective

Remove the legacy AI care-request copilot and permanently destroy its historical conversation data without deleting valid care-domain records, human-support conversations, or deployed migration history.

This document authorizes planning and implementation of the scoped removal. It does not authorize an operator to run an unreviewed production deletion command. Production execution requires the reviewed runbook, resolved targets, backup handling, and named approvers defined below.

## Data that must be destroyed

The destruction inventory must find and delete:

- Every row in `ai_request_messages`, including `content_text`, `structured_json`, token counts, latency, roles, timestamps, and session references.
- Every row in `ai_request_sessions`, including draft data, missing-field data, quality scores, model identifiers, timestamps, user/account references, and published-request links.
- Copies in read replicas, reporting stores, search indexes, caches, queues, dead-letter payloads, application logs, analytics payloads, ad hoc exports, generated summaries, embeddings, and production-derived test fixtures.
- Any attachment, object-store artifact, or third-party retained copy discovered during implementation that contains legacy session/message content or identifiers.

No legacy transcript, draft, prompt/response pair, summary, or structured turn may be copied into:

- The new support-ticket conversation
- The new support-agent event store
- The governed KB
- Evaluation or fine-tuning datasets
- Product analytics intended for continued use
- A migration archive or rollback package

## Data that must not be destroyed

The deletion must preserve:

- Valid `care_requests` and their ordinary domain relationships, including requests previously published through the legacy interface.
- Existing support tickets, human-support messages, assignments, unread state, and ordinary support audit history.
- Users, family accounts, caregivers, bookings, visits, payments, and other non-legacy domain records.
- Deployed migration files and migration-history records. Schema removal uses new follow-up migrations.
- A content-free destruction audit containing only environment, execution time, operator, approvers, code and migration version, before/after counts, target checklist, verification result, backup-extinction status, and approved exceptions.

The `published_care_request_id` foreign key points from a legacy session to a care request. Deleting the legacy session must never delete the referenced care request.

## Required execution sequence

### 1. Freeze creation

- Disable the legacy feature flag, route, UI entry points, scheduled work, and all code paths that create or update legacy rows.
- Verify with application tests and production telemetry that no legacy writes continue.
- Keep the ordinary manual care-request flow and human support chat available.

### 2. Resolve the complete target inventory

- Confirm the production table and relationship inventory against the deployed schema.
- Search replicas, logs, analytics, object storage, caches, queues, data exports, and test-data pipelines for legacy content or identifiers.
- Record counts and storage locations without copying message content into the runbook or audit.
- Identify any legal hold or mandatory retention restriction. An exception requires named privacy/legal approval, exact scope, reason, access restriction, and destruction date; it must not silently become reusable product data.

### 3. Prove non-target preservation

- Record counts and integrity checks for linked `care_requests` and other protected domain records.
- Test deletion against a production-like schema and representative relationships.
- Prove that message deletion cascades or executes in the correct order without cascading into `care_requests`, users, or support data.
- Review the resolved absolute environment and database targets before production execution.

### 4. Delete active and derived data

- Delete legacy messages and sessions using a reviewed, idempotent operation with explicit environment guards.
- Purge identified replicas, caches, search indexes, queues, logs, analytics stores, exports, summaries, embeddings, and derived fixtures.
- Remove the legacy tables through new follow-up migrations only after active writes are frozen and deletion verification passes.
- Do not create a content export or restorable legacy-data archive before deletion.

### 5. Handle backups and restoration

- Identify every backup or snapshot generation that can contain the deleted data.
- Delete affected backups when this can be done without destroying records LoLo is required to preserve.
- When a backup is immutable or cannot be selectively purged, record its final expiration date, prevent ordinary access, and prohibit extending its retention.
- The restore runbook must automatically or mandatorily reapply the legacy-data deletion before a restored environment accepts traffic or user/administrator access.
- `DEC-011` is not fully extinguished until the last containing backup has been deleted or expired; track this as a dated completion condition.

### 6. Verify and close

- Verify zero legacy session/message rows or absence of the retired tables in every active database and replica.
- Verify no legacy content or identifiers remain in each inventoried derivative target.
- Verify linked care requests and ordinary support records remain intact.
- Verify the legacy route, UI, bindings, jobs, configuration, analytics, and tests cannot execute.
- Verify the manual care-request flow and human support chat still work.
- Store the content-free destruction audit and obtain engineering plus security/privacy sign-off.

## Failure and rollback rules

- Stop before deletion if the exact production target, relationship behavior, or protected-record boundary cannot be proven.
- Stop if new legacy writes occur after the freeze.
- If deletion partially fails, keep the feature disabled, keep all surviving legacy data inaccessible, and rerun the idempotent operation after correction.
- Never restore deleted legacy content as an application rollback. Roll back code with the legacy feature still disabled and complete deletion separately.
- Any accidental deletion of a protected non-target record is a data incident and follows the incident-response process.

## Completion evidence

Phase 0 cannot close until evidence shows:

- Legacy creation paths are disabled and removed.
- Active and derived legacy data targets pass zero-record or absent-target verification.
- Published care requests and human-support data pass preservation checks.
- No legacy content was migrated into the new support agent, KB, or evaluations.
- The content-free destruction audit is approved.
- Each containing backup is either destroyed or has a recorded immutable expiration date and restore-time re-deletion control.

## Implementation handoff

Before writing deletion code, GPT-5.6 Sol or an engineer must produce a repository- and environment-specific runbook that names exact tables, foreign keys, replicas, stores, commands or migrations, verification queries, feature controls, owners, and rollback behavior. Assumptions about production topology are not acceptable substitutes for that inventory.
