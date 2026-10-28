# Atomic Production Deployments

Status: Current production deployment procedure

Owner: Engineering

Last updated: October 22, 2026

## Outcome

`deploy.sh` builds a complete release while the current application remains live, then switches `/var/www/homecare` to the prepared release atomically. It never runs `php artisan down`. The **first caregiver-screening activation** briefly drains PHP-FPM after the build so approvals completed on old code receive legacy access. Requests during that short cutover may receive a gateway error; it is not a zero-downtime cutover. Subsequent screening deployments use a graceful reload and drain queue/scheduler consumers before switching.

Nginx, the scheduler, queues and voice agent use the stable `/var/www/homecare` path. Screening deployments require an operator-configured runtime hook that stops scheduler triggers, waits for running jobs, drains old queue workers, and verifies resumed workers. The inspected production host uses [the Supervisor and root-cron setup](screening-supervisor-rollout.md). A separate [systemd example](screening-runtime-control.md) is available for hosts with that topology.

## First screening rollout prerequisites

Deliver screening runtime preparation through Git and the two explicit `deploy.sh` modes. `--screening-check <full-Git-revision>` stages reviewed helpers outside the active application and inspects server configuration without changing it. `--screening-setup <full-Git-revision> --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout` applies the separately authorized runtime preparation while keeping the same application release selected. Neither mode runs a deployment, imports datasets, or performs Didit backfill. Use the pinned-revision commands in the [Supervisor rollout runbook](screening-supervisor-rollout.md); do not upload an archive or copy runtime files manually. The new modes must be committed and pushed before those commands can be used on the server.

Install the runtime hook outside the releases at `/usr/local/libexec/homecare-runtime-control`, or set `HOMECARE_RUNTIME_HOOK` to its absolute executable path. Keep the hook and its parent directory operator-owned and unwritable by the web account. Configure the actual full worker/scheduler inventory. Use the Supervisor adapter for the inspected production host; the systemd example requires a timer and direct `queue:work` services. Horizon, remote workers and long-lived `schedule:work` require another adapter. Do not leave duplicate schedulers running.

Screening releases validate their warmed configuration cache before pausing production, including during a manual rollback. The current production contract is `database` / `default`, worker timeout `120`, and `DB_QUEUE_RETRY_AFTER=180` (strictly greater than the timeout). A stale or unsafe cached retry interval aborts activation. The one-time Supervisor setup updates both the shared environment and the existing release's cached retry interval, preserving its compiled-view path and other cached settings. Changing the topology or job timeout requires reviewing the adapter and this deployment check together.

Configure the effective PHP-FPM service to drain requests gracefully. The first-cutover preflight requires `KillSignal=SIGQUIT`, `KillMode=control-group` or `mixed`, `SendSIGKILL=no`, no custom `ExecStop`, and no socket/timer/path activation source. Review the installed unit and its helper commands before applying a drop-in. A typical drop-in for the actual configured FPM unit contains:

```ini
[Service]
KillSignal=SIGQUIT
KillMode=control-group
SendSIGKILL=no
TimeoutStopSec=120
```

Reload systemd after changing its configuration. An unfinished drain fails the deployment before reconciliation; the script never force-kills in-flight approvals. The request pause begins only after old background jobs finish. Long imports can cause the runtime hook to time out before the site is paused, so schedule those separately.

Ensure the deploy, FPM, queue and scheduler identities share the trusted `HOMECARE_WEB_GROUP` (default `www-data`). Private screening directories use `2770`, files `0660`, and group inheritance. Test read/write access as the actual Linux service users before the rollout; the general `775`/`664` storage pass explicitly excludes this subtree. Keep it out of Nginx aliases and public symlinks.

The first rollout follows this order: build and additive migrations while old code runs; drain background writers; gracefully stop FPM; DB-only legacy reconciliation; atomic application switch; durable activation; start FPM and resume the configured runtime; health checks. Profiles activated by old code during the build are included. The permanent activation row prevents repeat deployments from granting legacy access to new caregivers. Neither dataset imports nor `screening:sync --refresh-identities` is called by this flow.

## Release layout

```text
/var/www/homecare -> /var/www/homecare-deploy/releases/<active-release>
/var/www/homecare-deploy/
  repository.git/       local deployment mirror
  releases/             prepared and retained application releases
    <release>/bootstrap/cache/views/  compiled Blade views for that release
  shared/.env           production environment file
  shared/storage -> ... durable uploads, logs, sessions, and framework state
  compatibility/prepaid-care-hours.floor  permanent minimum prepaid contract
  previous -> ...       immediately preceding release
```

The deployment keeps at least five recent releases. It never deletes the active release, the previous release, or a directory containing non-symlinked durable storage.

The deployment root uses `0755`, and its operator-owned `compatibility` directory uses `0750`. The permanent prepaid floor is outside web-writable shared application storage. Back it up and restore it together with the deployment metadata and database. Do not remove it when pruning releases or restoring application files.

Compiled Blade views are stored separately for each release. During `config:cache`, the script sets `VIEW_COMPILED_PATH` to that release's `bootstrap/cache/views` directory; Laravel saves the absolute path in its configuration cache. Subsequent web requests and Artisan commands use that cached path without changing the shared `.env`. Directory ownership and permissions follow the existing `bootstrap/cache` rules so PHP-FPM workers can compile views when needed.

This isolation matters because `view:cache` first runs `view:clear`. Preparing a new release must not clear templates used by the active release. Existing sessions, uploads, application cache and logs remain in shared storage; the legacy shared compiled-view directory is left intact for any previous release that still uses it. Rollback restores the previous release and its own cached view path. Avoid clearing the production configuration cache outside this deployment procedure, because an uncached application falls back to the shared environment's configured view path.

## First deployment

The inspected October 13 server already uses this atomic release layout. Its first screening rollout requires the Git-delivered runtime preparation above before a separately authorized application deployment. The legacy conversion instructions below apply only to a server that has not yet adopted the atomic deployment layout.

The screening check and setup leave the old `/var/www/homecare/deploy.sh` unchanged. After runtime setup is verified and the first screening application deployment is separately authorized, invoke the new Git-extracted driver using the full path recorded during the check:

```bash
(
  set -eu
  screening_driver='<deploy driver path from the successful check>'
  bash "$screening_driver"
)
```

This is a real deployment that fetches the current remote `master`; confirm it contains the intended reviewed application release before authorizing the command. It is not part of the read-only check. Use the active checkout's `./deploy.sh` for subsequent deployments only after a release containing the new driver is active.

If the server still has the legacy in-place deployment script, obtain only the new deployment script first. Do not pull the whole application into the live checkout:

```bash
cd /var/www/homecare
git fetch origin master
git restore --source=origin/master -- deploy.sh
./deploy.sh
cd /var/www/homecare
./deploy.sh --status
```

Fetching does not change the live checkout, and restoring only `deploy.sh` does not alter code or assets served by the application. The new script then prepares the complete latest release away from the live path before the atomic switch.

The first run:

1. copies `.env` to the protected shared directory;
2. establishes a stable shared-storage pointer without copying or recreating production data;
3. builds and validates the new release away from the live path;
4. uses Linux `RENAME_EXCHANGE` to atomically exchange the original directory and the prepared symlink;
5. keeps the original checkout as the previous release and durable-storage owner; and
6. starts/reloads PHP-FPM and resumes the configured runtime before checking Laravel and voice-agent health, following the first-screening drain rules above.

The final `cd` is needed only because the shell that launched the first deployment still has the original directory as its working directory after the atomic exchange.

### First prepaid deployment

The deployment fetches Git; it cannot deploy uncommitted working-tree files. Use a reviewed, committed and pushed release containing the complete feature, all six additive migrations (including request funding preferences), the matching manifest and the updated driver. Include any subsequent pilot-access changes in that same reviewed release. Deployment and feature enablement remain separately authorized operations.

Use the updated driver from that exact reviewed revision for the first prepaid deployment. An older already-running `deploy.sh` does not acquire the new checks when it fetches new application code. Use the existing Git-delivery procedure above to obtain only the reviewed driver: restore `deploy.sh` from the full reviewed commit rather than an unpinned branch name, or use the matching Git-extracted driver prepared by the screening procedure. Do not pull the whole feature into the live checkout. The selected deployment branch must still point to the reviewed commit when the driver fetches it.

Set `HOMECARE_EXPECTED_COMMIT` to that full lowercase commit SHA when invoking the reviewed driver. A missing pin on a prepaid candidate, malformed SHA, or changed branch tip aborts before dependencies, migrations or runtime drains. The pin selects no alternate revision: a moved branch must be reviewed again or restored through the normal release process.

The first prepaid candidate must have `care_hours.enabled=false` and `care_hours.gifts_enabled=false` in its newly warmed configuration cache. The driver checks that cache and refuses activation if either flag is enabled or missing/malformed. It does not rewrite the shared `.env` or silently turn features off. This disabled introduction still applies the additive migrations and pins the permanent compatibility floor before switching code. Prepare the compatible recovery procedure described below before deployment.

### Restricted prepaid pilot

The shipping configuration has `CARE_HOURS_PILOT_ONLY=true` and `CARE_HOURS_PILOT_EMAILS=peverelli.t@gmail.com`. This limits new prepaid discovery and access to the signed-in family user with that email address. It is an additional restriction: it does not override the master, sales or gift switches, and the first deployment must still introduce the feature disabled. Existing paid entitlements and financial recovery remain supported when new access is restricted.

The deployment checker requires a boolean `pilot_only` and a list of valid email strings in `pilot_emails` in every warmed cache, even when the pilot restriction is intentionally disabled. An empty list denies everyone while pilot mode is on; it never means public access. Changes to the allowed addresses or disabling the restriction are deliberate exposure changes and require a reviewed forward release/configuration change. Do not remove or change the allowlist to work around a failed deployment.

Deploying this code does not create a staging platform or make production accounts use Stripe test mode. The family's pilot purchases still use the payment environment configured on the deployed app. This runbook does not authorize production access, live payment tests or activation.

## Normal deployment

Once the reviewed driver is installed, prepaid deployments require the full SHA of the reviewed, pushed release:

```bash
cd /var/www/homecare
HOMECARE_EXPECTED_COMMIT='<full lowercase SHA of the reviewed pushed release>' ./deploy.sh
```

Legacy releases without prepaid support may omit the pin. Normal subsequent prepaid deployments validate the cached exposure flags and pilot settings and preserve the values loaded from the shared `.env`; they do not reset an already authorized launch to disabled. Keep the driver current through the reviewed Git-delivery procedure whenever it changes. No command here authorizes production access or activation.

The script performs these operations in order:

1. acquires an exclusive deployment lock;
2. fetches the selected branch (default `master`) into the local mirror and verifies the expected commit;
3. creates a new inactive release and verifies its prepaid source contract and required commit pin;
4. links the protected `.env` and durable storage;
5. installs production Composer and npm dependencies;
6. builds frontend, hosted Content MCP, and voice-agent artifacts;
7. retains the previous hashed frontend assets for in-flight browser requests;
8. runs database migrations and photo generation while the old release remains live;
9. builds Laravel configuration, route, and view caches inside the inactive release, with a release-specific compiled-view path, and checks prepaid flags in the warmed configuration before activation;
10. validates Laravel routes, migration state, the Vite manifest, the voice binary, and Nginx configuration;
11. drains configured scheduler/queue workers and atomically changes the active symlink, including the first-screening reconciliation and activation when needed;
12. starts/reloads PHP-FPM, resumes verified workers and scheduler, restarts the voice agent, and restarts the Content MCP when its additive systemd unit is installed;
13. checks `https://carelolo.com/up` through local Nginx and the local voice-agent health endpoint, then checks Content MCP health when installed without risking the healthy website release if only MCP is unavailable; and
14. removes only eligible old releases.

Screening releases require the runtime hook; `queue:restart` alone cannot prove old workers have stopped and is not used for these releases. Dataset imports and Didit backfill remain separate commands in the [screening runbook](../caregiver-screening.md).

## Status and rollback

Read the active layout without changing it:

```bash
./deploy.sh --status
```

Return to the immediately preceding application release:

```bash
./deploy.sh --rollback
```

Rollback atomically changes the symlink, gracefully reloads PHP-FPM, restarts the runtime workers, and repeats application/voice health checks plus the optional Content MCP check. It intentionally does **not** reverse database migrations.

For prepaid releases, manual and automatic rollback require the retained target's cached prepaid switches to match the currently active values: `enabled`, `enrollment_enabled`, `sales_enabled`, `contributions_enabled`, `gifts_enabled`, `welcome_enabled` and `pilot_only`. The effective `pilot_emails` set must match too; whitespace, capitalization, duplicates and ordering are normalized before comparison. A rollback must not reopen paused sales, change an authorized promotion or expand pilot access. The check refuses missing, malformed or different values before switching; it rewrites neither cache nor the shared `.env`. If the active cache is damaged or the retained release has different settings, use a reviewed forward release built from the intended shared configuration instead of bypassing the check. Normal forward deployment remains available for this repair.

If a failure happens before the switch, the prior release remains selected and paused services are restored where safe. Failure recovery runs on nonzero exit and INT/TERM, inspects the actual symlink target, and retains failed releases for inspection. If a failure happens after switching, the script drains and returns to a compatible prior release.

**After first screening activation, rollback to pre-screening code is prohibited**, including automatic rollback after a failed health check. That code would bypass new access controls and cannot consume screening jobs. The new release stays selected for repair or a screening-compatible replacement. If the activation command fails after possibly committing, recovery rechecks the durable DB state. An unknown/pending boundary keeps PHP-FPM and background writers paused; fix the database/application error and rerun `deploy.sh` to recover. Do not manually start old code, delete the hook's saved state, reverse migrations or discard audit files. SIGKILL, host failure and reboot cannot be recovered by shell traps; inspect release, DB boundary and persistent hook state before restarting services.

### Permanent prepaid compatibility floor

Before activating the first prepaid-compatible release, the driver atomically records and flushes `compatibility/prepaid-care-hours.floor` with contract `homecare-prepaid-care-hours-v1`. This is deliberately more conservative than counting purchases: **pre-prepaid rollback is prohibited even with sales disabled and before the first sale**. Sampling the database cannot prove that no in-flight payment, webhook, gift claim, reserved hours or payout will create an obligation after the check. The floor does not depend on a feature switch, database availability, current balances, or a later refund.

Manual rollback, automatic failure recovery and forward deployment to an older branch all require a verified compatible target once the floor is pinned. A failed health check on the first prepaid release therefore retains that release for repair; it cannot return to the preceding pre-prepaid release. A failure to flush the floor aborts before the switch, leaves the old release selected, and retains any recorded floor. The next attempt must use compatible code. Prepare and review a prepaid-compatible recovery release or a forward repair procedure **before** the first prepaid deployment. A zero-downtime or automatic return to pre-prepaid code is not promised for this boundary.

The checked-in `docs/deployment/prepaid-care-hours-v1.sha256` contains the fixed inventory and fingerprints of required handlers, payment/ledger/claim/dispute services, the pilot access guard, models, all six prepaid migrations plus the pricing-agreement dependency, recovery commands, scheduler and HTTP routes, gift authentication and rendering dependencies, and the shared family/admin/booking/regular-care integrations. The driver validates paths, version, inventory and normalized text hashes without booting Laravel or querying the database. A marker alone does not establish compatibility. Missing, changed or partial target sources fail before migrations or runtime drains. A retained package without the pilot access guard cannot satisfy the current inventory. The screening checks remain cumulative: satisfying the prepaid contract never bypasses a screening activation restriction.

A missing floor can be reconstructed from an independently verified active prepaid release. With an intact v1 floor, a damaged active source file does not prevent switching to an independently verified v1 repair. Unknown floor contents, unsafe state paths, or a newer/unknown declared contract fail closed; the v1 driver does not guess compatibility. There is no bypass or floor-reset flag. The driver never reverses database migrations, deletes ledger history, clears holds, or changes feature switches.

These checks protect the supported deployment path, not arbitrary root or operator actions. Do not invoke an older deployment driver, manually retarget the application symlink, restore only an old code tree while discarding the floor, or edit a manifest merely to make an incompatible target pass. Those actions bypass the safety boundary. A future contract version requires an explicitly reviewed driver and migration/recovery plan. Source hashes establish reviewed package integrity, not a proof of business semantics; changes still need their normal tests and review.

#### Local manifest maintenance

The current validation work is local only. The user has explicitly prohibited assistant SSH or production-server access; this document does not authorize such access or request an operator-run server check. Production deployment, filesystem durability, service permissions and server state were not verified by these tests.

After intentionally changing a listed source, review its prepaid compatibility and tests, then refresh the manifest explicitly in the **local source checkout**. The deployment never generates or repairs a candidate's manifest automatically. This command does not contact any server, boot the application, query a database or change feature switches:

```bash
bash deploy.sh --prepaid-manifest "$PWD" > docs/deployment/prepaid-care-hours-v1.sha256.next &&
  mv docs/deployment/prepaid-care-hours-v1.sha256.next docs/deployment/prepaid-care-hours-v1.sha256
bash deploy.sh --check-prepaid-release "$PWD"
git diff -- docs/deployment/prepaid-care-hours-v1.sha256
```

Commit the reviewed manifest with the matching code and driver. Text fingerprints normalize CRLF to LF for Windows/Linux checkouts. If adding a required dependency, extend the explicit inventory in `prepaid_compatibility_files`, refresh the manifest, and run the deployment smoke tests. A deployment-root filesystem must support atomic rename and `sync -f`; failure aborts activation. The offline Windows harness mocks that durability call and does not establish Linux filesystem or permission behavior.

## Required migration rule

Every migration executed by this deployment must be compatible with both the old and new application release during the short overlap. Use expand-and-contract changes:

1. add new tables, columns, or indexes and deploy code that tolerates both schemas;
2. migrate or backfill data separately when needed; and
3. remove obsolete schema only in a later deployment after no active release uses it.

Do not combine a destructive rename/drop with code that requires the new schema in one deployment. A symlink rollback cannot undo a database migration.

## What users may notice

On normal deployments the website remains available and no maintenance page is enabled. A request already being processed finishes on its existing PHP-FPM worker; new requests use the new release after the graceful reload. The first screening cutover includes the brief request drain described above. Existing sessions and uploads remain in shared storage.

The voice agent and hosted Content MCP are still restarted after the application switch, so an inbound voice or Codex request arriving at that exact moment may see a very short interruption. This does not make the website unavailable. An MCP-only failure is logged as an additive-service problem and does not roll the healthy Laravel release back.

## Configuration

Defaults reflect the previously documented production host; this local audit has not verified its current state. Operators may override them with scoped environment variables such as `HOMECARE_KEEP_RELEASES`, `HOMECARE_FPM_SERVICE`, or `HOMECARE_GO_BIN`. Do not repurpose system environment variables or put secrets in the script; production secrets remain only in the shared `.env`.

## Offline deployment regression check

`bash tests/Deployment/screening-git-delivery-smoke.sh` verifies the check/setup modes against a temporary Git repository with mocked elevation. It checks pinned revisions, matching deployment drivers, private Git staging, argument handling, and refusal paths. No server setup or application deployment is performed by the local test.

`bash tests/Deployment/atomic-deploy-smoke.sh` uses temporary repositories and mocked PHP, npm, Composer, Go, service and health commands. It checks atomic conversion, release-local views, durable sessions/evidence, normal rollback, missing runtime configuration, partial drains, interrupted/failed preparation, uncertain activation, recovery retries, blocked old-code rollback and compatible rollback. It asserts that deployment never invokes imports or Didit backfill. On Windows, directory junctions model directory links and the immutable test `.env` is copied; Unix permission assertions run on Linux only. The real Linux `renameat2` call, systemd services and production account permissions are not exercised by this offline harness.

The same harness exercises first prepaid activation with a failed durable flush and failed health check, incompatible manual rollback and forward deployment, missing/unknown floor state, compatible repair of damaged active handlers, and rejection of missing handler, claim-view and migration dependencies. It also checks missing/invalid/moved commit pins, rejection of unsafe first-introduction cached settings before drains, and refusal of manual/automatic rollback when cached switches differ. Its original legacy-only and screening cases remain in the suite. `bash tests/Deployment/prepaid-rollback-state-smoke.sh` isolates floor/contract failures, safe repairs and cumulative screening decisions using small synthetic files. Neither test deploys an application, accesses production, or runs real migrations, payments or service commands.

`php tests/Deployment/prepaid-release-config-smoke.php` tests the actual cached-config helper, including missing/unreadable/malformed caches, strict boolean flags, disabled first adoption, all six rollback switches and sanitized error output. It does not boot Laravel or contact a database/provider.

`php tests/Deployment/prepaid-laravel-warmup-smoke.php` runs real package discovery, fresh SQLite migrations, config/route/view caches, route/command/scheduler discovery and the read-only health command in a disposable `tmp/` environment. It verifies default master/gift flags remain disabled, checks hashes of the main environment/database/bootstrap caches, and disables PHP network functions for the child processes. It uses installed local dependencies; it does not certify a fresh production Composer installation, MySQL behavior, real Linux service control or current production configuration.

`bash tests/Deployment/prepaid-manifest-smoke.sh` copies the current required sources into a disposable local directory, verifies the real checked-in manifest, and proves that a missing shared booking writer, either admin cancellation writer, the shortfall dispute handler, a changed gift-claim handler or an unexpected manifest entry cannot pass the contract. It uses the complete current inventory rather than the small state-machine fixture.

`bash tests/Deployment/screening-runtime-hook-smoke.sh` separately tests the example hook with mock services and a fake cgroup tree. Application tests under `tests/Feature/Screening/ScreeningRolloutTest.php` use an isolated SQLite database to test legacy reconciliation, retries and permanent activation closure.

`bash tests/Deployment/screening-rollout-recovery-smoke.sh` exercises seven isolated recovery cases, including retrying an earlier paused screening release, unknown/committed activation and a symlink swap interrupted before its in-memory flag is set. It sources only deployment function definitions and replaces all service, Artisan and network actions with fixtures.
