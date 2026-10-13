# Caregiver screening

The existing **Admin → Work queues → Caregiver screening** page replaces caregiver approval and background-check badge overrides. Its route remains `/admin/caregivers/reviews`.

## Work access

| Caregiver | While waiting for screening | After an explicit decision |
| --- | --- | --- |
| New profile created after the screening cutover | Cannot take new work until initial approval | Approve enables work once profile requirements are complete; Suspend blocks new work |
| Profile already active at the screening cutover | Keeps legacy work access, including when Didit evidence is missing | Approve establishes the recurring cycle; Suspend blocks new work |
| Existing pending or suspended profile | Keeps its existing restriction | Requires an explicit approval; lifting an existing suspension requires an explanation |
| Previously approved caregiver due for a six-month review | Keeps access, even if overdue, a candidate is found, or sources fail | Only an explicit suspension blocks work |

The migration takes an initial legacy snapshot. The deployment then drains old requests and background writers and reconciles any profiles created or activated during the build. Only active, unsuspended profiles without an initial screening approval receive new legacy grants. `screening:rollout --activate` permanently closes legacy enrollment before the new application starts. Retries before activation can reconcile intervening old approvals; later deployments cannot grandfather new accounts. Statuses and bookings are unchanged. Six calendar months are measured from each completed admin decision, with the day clamped at month end. A unique open-profile key prevents duplicate pending reviews.

Suspension preserves login, account access, and existing commitments. It blocks applications, invitations, new hires, new regular-care commitments, added visits, and coverage assignments at their server-side entry points. Existing accepted recurring work can still materialize, with each resulting booking flagged for admin review. Existing visit reductions/moves do not automatically become cancellations. Suspension does not refund, cancel, or notify families automatically.

## Review workflow

1. The scheduler enrolls legacy profiles and due reviews; onboarding and successful Didit callbacks also synchronize the queue. Profiles without a confirmed successful Didit identity wait in **Awaiting KYC**.
2. Searches use the legal name from the provider's verified document, not the editable profile display name. Development bypasses and historical admin identity overrides do not qualify. Stored, signature-validated provider evidence may be reconciled through the existing Didit API when the verified name is missing.
3. Both required datasets must have a validated, active, fresh snapshot. A missing or stale snapshot, incomplete import, or failed initial import prevents finalization and never produces a successful zero-hit search. Published source gaps are preserved and disclosed: related records without a main profile retain all available names and evidence; records without any name cannot be searched by name. Coverage counts appear on dataset cards and remain attached to each search's source versions.
4. An admin claims the case for 20 minutes, compares name candidates and source details with the verified identity, chooses **No Match / Potential Match / Match**, then **Approve & Next / Suspend & Next**. Name equality only creates a candidate; it never confirms identity or takes an account action.
5. A reason is required for matches, suspension, and ruling out candidate records. **Keep Pending** records an investigation note and releases the claim without changing access. **Suspend new work now** is an explicit temporary restriction available even before the search is complete; it keeps the screening case open.
6. The affected-bookings panel lists private review flags. Admins open the existing commitment tools, take any needed action there, then record the review resolution. Resolving a flag itself does not change a booking.

The case version and reviewer lease prevent stale tabs or concurrent admins from deciding the same case. Changed identities or replaced datasets require a new search. Prior search evidence remains in audit history.

## Deployment and first enrollment

Use [the atomic deployment procedure](deployment/zero-downtime-deployments.md) and configure [runtime control](deployment/screening-runtime-control.md) before the first screening release. The hook must cover the actual queues and scheduler; the script deliberately fails if this configuration is missing. No production migration or account update is performed by the implementation's tests.

1. Run `./deploy.sh`. It builds while the current site remains live, applies the three additive screening migrations, protects private storage, drains workers/scheduler, and briefly stops PHP-FPM for the first cutover. It runs the DB-only `screening:rollout --prepare`, switches releases, and commits `--activate` before restarting PHP-FPM and the configured workers. Verify the aggregate `screening_rollout_prepared` audit counts against active profiles at cutover; pending/suspended accounts remain restricted. Do not run these deployment-internal commands against live old writers manually.
2. Import and validate the public datasets using the [data import runbook](caregiver-screening-data-imports.md). Raw files and staging data must remain private. An unavailable source delays decisions; it does not block legacy work.
3. Preview the enrollment counts:

   ```sh
   php artisan screening:sync --dry-run
   ```

4. Enroll profiles with `php artisan screening:sync`. Separately reconcile missing Didit evidence when ready:

   ```sh
   php artisan screening:sync --refresh-identities
   ```

   `--refresh-identities` calls Didit only when usable provider evidence is missing. API failures leave the case waiting for KYC; repeat this command after resolving the provider problem. Use the already supervised workers resumed by deployment; do not start unmanaged production consumers. Do not enable `DIDIT_BYPASS` to clear the queue.

5. Ensure the existing Laravel scheduler runs every minute. It refreshes source datasets daily at 03:00, synchronizes due screening cases daily at 05:00, and retries audit-file delivery every minute in the application's configured timezone. The commands prevent overlapping scheduled runs. Manual import/sync commands remain available.
6. Check the admin queue, both dataset-health cards, worker failures, and unresolved commitment flags. Review one legacy case and one new profile before broad rollout.
7. Plan source storage maintenance using the [retention command](caregiver-screening-retention.md). `screening:prune-indexes` previews obsolete searchable rows; adding `--execute` removes eligible rows while retaining active/recent versions, original archives, source metadata, and all review/audit evidence. Cleanup is not automatically scheduled.

Deployment restores the configured runtime but never imports sources, enrolls cases through `screening:sync`, or calls Didit. Source imports and backfill remain separate operator actions. Once activation commits, the script refuses rollback to pre-screening application code even if the first health check fails. It keeps the screening release for repair; if activation cannot be confirmed, writers stay paused until recovery. Never reverse the screening migrations or remove retained evidence.

## Audit and source scope

Every case enrollment, claim, search, decision, suspension, deferral, booking flag, and flag resolution writes a durable `screening_audit_events` row. Decision payloads retain the actor, verified identity, source versions, candidate references, reason, and before/after access state. Case and audit evidence survives account deletion through nullable references and retained snapshots.

Events are exported to private daily JSONL files under `storage/app/private/screening/audit`. Export uses file locking, UUID deduplication, flush/sync, and a retryable database outbox. An incomplete write is retained in a `.recovery` fragment before its unfinished tail is repaired. A complete malformed line stops export for manual recovery. Monitor pending `exported_at IS NULL` rows and application errors. Back up both the database and private audit files; restrict filesystem permissions to the application and authorized operators. Application-level immutability is not a substitute for protected backups or infrastructure-level write-once storage.

Deployment excludes `storage/app/private/screening` from general storage modes and applies directories `2770` and files `0660`, owned by the deployment user and trusted web group. The importer and audit exporter preserve those modes for future files and inherit the directory group. CLI, web, queue and scheduler users must belong to that group; verify their real Linux identities during setup. Storage symlinks managed by deployment are resolved at runtime; symlinks inside the private subtree are rejected. Public storage links only to `storage/app/public`.

```sh
php artisan screening:export-audit
```

Each case also has an admin-only JSONL download, with no-store response headers and an audit event for the download. Evidence must not be copied to public storage or family/caregiver notifications.

This implementation searches the imported NC sources by normalized full name and first/last name, including available source aliases. It does not conduct nationwide screening or automatically determine that a public record belongs to a caregiver. **No Match** means no qualifying name candidate in the specific imported source versions; admins make the identity and account decisions.

## Development verification (2026-10-13)

- Screening, admin queue, and Didit suites after deployment integration: **115 tests / 604 assertions passed**, with two native-symlink tests skipped on Windows. These cover cutover access and permanent activation, initial approval, six-month recurrence, explicit suspension, commitments, stale case versions, identity races, private-file creation, immutable audit delivery, real-layout parsing, name-component variants, combined lookup limits, source coverage, acquisition timezones, the SBI postback adapter, and historical index maintenance. POSIX modes and real service identities require Linux verification.
- Broader affected marketplace, regular-care, continuous-coverage, shared-care, and AI booking-action suites also passed after ready-caregiver fixtures received explicit screening approval timestamps. Assertions were not relaxed to bypass the new work-access policy.
- Desktop and mobile synthetic queue previews were inspected at 1440 and 390 pixels with no horizontal overflow. `npm run build` passed; existing font-resolution and bundle-size warnings remain.
- PHP formatting and scoped whitespace checks passed. Concurrency behavior is covered by stale-state tests and transaction/lock review; it has not been exercised against parallel transactions on a live MySQL server.
- Broader checks still have two unrelated failures outside the screening flows: `AdminUsersAndFunnelTest` expects HTTP 200 after family impersonation where the dashboard redirects, and `CaregiverRegressionTest` rejects the phrase “Find Caregivers” that appears in the directory's SEO title. Those flows and assertions were left unchanged.

See the [import runbook](caregiver-screening-data-imports.md) for full-file validation results and remaining deployment connectivity checks. Tests and previews do not approve, suspend, enroll, or modify real caregiver accounts.
