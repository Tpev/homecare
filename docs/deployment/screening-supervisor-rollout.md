# Production screening setup: Supervisor and root cron

This is a proposed, reviewed setup for the host inspected on October 13, 2026. These files do not imply that any server changes or deployment have happened. Deliver all reviewed files through Git and run the check and setup through `deploy.sh`. The setup changes runtime configuration and resumes the **same application release**; the screening application deployment, dataset imports, and Didit backfill are separate operations.

**Delivery status:** the new Git delivery modes and associated changes must be reviewed, committed, and pushed before the server commands below can be used. Local workspace changes are not available on the server. No production setup or deployment is authorized by this runbook.

## Inspected topology

| Component | Production configuration | Required preparation |
| --- | --- | --- |
| Worker | Supervisor `homecare-worker:homecare-worker_00`, `www-data`, database/default queue, timeout 120 seconds | Wrap its existing command in the persistent admission gate |
| Scheduler | Root crontab invokes `cd /var/www/homecare && php artisan schedule:run` every minute | Replace only that line with the scheduler wrapper |
| Duplicate worker | Enabled but failed `homecare-worker.service` | Verify no live processes, disable it, clear its failed state |
| PHP-FPM | `php8.3-fpm.service`, pool `www-data:www-data`, SIGTERM with kill escalation | Add SIGQUIT, `SendSIGKILL=no`, 120-second graceful-stop limit |
| Deployment account | `ubuntu`, initially outside `www-data` | Add the trusted supplementary group; use a fresh login |
| Queue reservation | Cached database `retry_after=90`, worker timeout 120 | Set reservation to 180 in shared `.env` and the active configuration cache |
| Stable application path | `/var/www/homecare` points into `/var/www/homecare-deploy/releases` | Preserve this symlink and the durable storage target |

The 90-second reservation is shorter than the worker's 120-second timeout. Fixing it before starting the wrapped worker prevents a still-running job becoming eligible for another worker too early, following [Laravel's timeout and reservation requirements](https://laravel.com/docs/12.x/queues#job-expiration). The installer changes only this cached value; it does not boot Laravel or rebuild the active release's compiled-view paths. It preserves every other cached setting and environment entry without printing credentials.

## Review the Git revision

Review `deploy.sh` and these version-controlled helpers together:

- `docs/deployment/screening-supervisor-setup.sh`
- `docs/deployment/screening-supervisor-runtime-control.sh`
- `docs/deployment/screening-runtime-entrypoint.sh`
- `scripts/deployment/check-screening-queue.php`

The previously prepared archive is superseded as a delivery method. Do not upload an archive or manually copy runtime files. `deploy.sh` obtains the four helpers from the exact requested Git revision and stages them privately under `/home/ubuntu/.homecare-screening/`, outside the application and deployment directories. It refuses a driver whose contents differ from `deploy.sh` at that revision, so a check cannot silently mix scripts from different commits. The setup script remains an implementation helper; operators invoke it through `deploy.sh`.

Before applying, independently confirm that this host has the complete queue and scheduler inventory: no other hosts, additional cron tables, timers, Horizon, manually started queue workers, detached scheduled commands, or external service restarters. The wrapper can account for work launched through its entrypoints; it cannot fence an undiscovered direct Artisan launcher.

Use a window without long-running jobs. The one-time bootstrap must retire the existing direct Supervisor worker before it can install the wrapper. Its **existing** `stopwaitsecs=3600` allows Supervisor to force-kill work after one hour. The apply command explicitly acknowledges this existing limit. Do not apply if a current job may exceed that limit. After bootstrap, the new deployment hook uses graceful TERM plus admission locks and does not use `supervisorctl stop` or force-kill a timed-out drain.

## Read-only setup check

After the reviewed changes have been committed and pushed to the remote `master` branch, run this complete block in the existing `ubuntu` terminal in mRemoteNG. It fetches Git objects into the deployment mirror and creates private review files; it does not change the live checkout, installed runtime configuration, services, application database, or active release. It does not run a deployment:

```bash
(
  set -eu
  test "$(hostname)" = ip-172-31-16-32
  test "$(id -un)" = ubuntu
  git --git-dir=/var/www/homecare-deploy/repository.git fetch origin '+refs/heads/master:refs/heads/master'
  screening_revision=$(git --git-dir=/var/www/homecare-deploy/repository.git rev-parse --verify 'refs/heads/master^{commit}')
  umask 077
  screening_driver=$(mktemp /home/ubuntu/homecare-screening-deploy.XXXXXX)
  git --git-dir=/var/www/homecare-deploy/repository.git show "$screening_revision:deploy.sh" > "$screening_driver"
  printf 'Screening revision: %s\nDeploy driver: %s\n' "$screening_revision" "$screening_driver"
  bash "$screening_driver" --screening-check "$screening_revision"
)
```

The inspected server already uses the atomic deployment mirror. Fetch that mirror's `origin`, as shown above: the active release's own `origin` points to the local mirror and is not the upstream repository. No checkout, restore, or pull of application files is needed.

Keep the full printed Git revision and driver path with the check output. The subshell's `set -eu` stops on a failed fetch or extraction; no command falls through to a normal deployment. The check and driver use the same pinned revision even if `master` later advances. Stop after the check and review its output before authorizing any setup.

Successful completion includes `Setup: Read-only check complete; no files or service state changed.` This message describes the server inspection performed after Git staging; the private driver and staging files are intentional local-to-server writes. No Laravel boot or database access is required.

The check requires the inspected Supervisor command and exactly one matching old scheduler line. It refuses an already installed or partially installed runtime, a changed topology, a running duplicate worker, unexpected storage links, and unsafe queue configuration. It does not claim that other hosts or unrelated launch mechanisms have been discovered.

Unlike the installer's `--check`, the runtime controller's `preflight` opens its private operation lock. Do not run that controller action when the authorization is strictly read-only.

## Apply runtime preparation, separately from deployment

Run only after the server configuration changes are authorized and the read-only output has been reviewed. Substitute the exact full revision and driver path printed by the successful check; do not resolve `master` again for this step:

```bash
(
  set -eu
  screening_revision='<full Git revision from the successful check>'
  screening_driver='<deploy driver path from the successful check>'
  bash "$screening_driver" --screening-setup "$screening_revision" \
    --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
)
```

This mode invokes the version-controlled setup helper with elevated privileges. It does not invoke the normal deployment path. The driver verifies and stages the same Git revision again before running the setup, so it does not trust an edited staging directory from the earlier check.

The installer serializes with the deployment lock and rechecks production state under the lock. It then:

1. Makes a root-only backup under `/var/backups/homecare-screening-runtime/<timestamp>-<pid>`. This contains the original root crontab, Supervisor configuration, shared environment, cached application configuration, and the hashes of the Git-staged helpers. Keep it private because it contains production secrets.
2. Removes only the old application scheduler line, gracefully stops the legacy Supervisor worker, and waits for all existing application Artisan tasks and old cron shell invocations to finish. At least one full cron minute plus five seconds passes after the crontab edit. Other root cron entries are preserved. PHP-FPM keeps serving the existing application throughout setup.
3. Installs trusted root-owned entrypoints and configuration, `ubuntu:www-data` state directory mode `0750`, precreated locks, and a persistent pause marker. No wrapped PHP worker or scheduler can enter while this marker exists.
4. Sets `DB_QUEUE_RETRY_AFTER=180` in the shared environment and atomically changes only `queue.connections.database.retry_after` in the current cached array. The original cached compiled-view path and all other values remain intact.
5. Gives the screening storage subtree directories mode `2770` and files `0660`, with `ubuntu:www-data` ownership. It adds `ubuntu` to `www-data`, configures the FPM graceful-stop drop-in without restarting FPM, and disables the verified empty duplicate worker service.
6. Replaces only the Supervisor worker command, reloads that program, and starts its wrapper behind the closed gate. It installs the canonical root scheduler line, verifies the actual wrapped worker, then resumes workers and scheduling on the unchanged application release.

The runtime hook remains `/usr/local/libexec/homecare-runtime-control`, matching `deploy.sh`'s default. It reads `/etc/homecare/runtime.conf`. The persistent pause state lives in `/var/lib/homecare-runtime`; never replace its lock files or delete its marker to bypass a failed check.

The controller runs as `ubuntu` and uses that account's noninteractive sudo access to inspect root cron, Supervisor, and the specific process descriptors, and to signal the configured Supervisor process. Verify the existing authorized sudo configuration can perform these exact actions. Do not grant the web account sudo access. If privilege checks fail, the installer leaves work paused for repair and reports its backup path.

## Validate setup before deploying

Start a fresh `ubuntu` SSH login so its new supplementary group is effective. Then inspect:

```bash
id
readlink -f /var/www/homecare
sudo supervisorctl status homecare-worker:homecare-worker_00
systemctl show homecare-worker.service -p ActiveState -p UnitFileState -p MainPID -p ControlPID
systemctl show php8.3-fpm.service -p ActiveState -p KillSignal -p KillMode -p SendSIGKILL -p TimeoutStopUSec -p ExecStop -p TriggeredBy
stat -c '%U %G %a %n' /var/lib/homecare-runtime /var/lib/homecare-runtime/start.lock /var/lib/homecare-runtime/run.lock /etc/homecare/runtime.conf
/usr/bin/php /usr/local/libexec/homecare-screening-queue-check.php /var/www/homecare/bootstrap/cache/config.php 120
/usr/local/libexec/homecare-runtime-control preflight /var/www/homecare
```

Expect the same active release, one RUNNING Supervisor worker, the duplicate unit inactive and disabled, FPM still active, and a queue safety check that passes. There should be no persistent `paused` marker after successful setup. The hook also checks the root cron line and the worker's inherited execution lock; a RUNNING process alone is insufficient.

As part of the authorized setup, verify both service accounts can use private files:

```bash
sudo -u ubuntu bash -c 'umask 0007; p=/var/www/homecare/storage/app/private/screening/.deployment-permission-probe; test ! -e "$p" && (set -o noclobber; printf "deploy\n" > "$p")'
sudo -u www-data bash -c 'p=/var/www/homecare/storage/app/private/screening/.deployment-permission-probe; test -r "$p" && printf "web\n" >> "$p"'
sudo -u ubuntu rm -- /var/www/homecare/storage/app/private/screening/.deployment-permission-probe
```

Do not rerun the bootstrap installer against an already installed runtime. Routine deployments use the installed hook; configuration changes require a fresh inventory review.

## Failure recovery

The installer deliberately avoids an automatic broad rollback of the root crontab, environment, or cached configuration. Such a rollback could undo concurrent work or reintroduce the unsafe 90-second queue reservation. A failed step reports the exact private backup directory. The active application release remains selected; background work may remain paused.

- If failure happens before wrapped runtime installation, inspect the backed-up crontab and Supervisor state. Resume the old setup only after confirming the active release still predates screening, no wrapper worker is running, and restoring the old scheduler will not duplicate another trigger. Compare current files before restoring anything. The original worker's 120/90 reservation mismatch still needs correction.
- If the wrapped configuration and canonical cron are installed, preserve the original `runtime.conf`, marker, and lock files. Repair the reported permission, queue configuration, or process problem. From a fresh `ubuntu` login, run the hook's `preflight`, then `resume`, only after confirming that the selected release is eligible to run.
- If setup was interrupted halfway through, use the private backup and installed-file inventory to finish that specific stage. The installer refuses a second bootstrap attempt; it does not overwrite partially installed runtime state.
- After the first screening activation, never recover by returning to pre-screening application code. Follow the activation boundary in the [deployment runbook](zero-downtime-deployments.md).

Do not delete private backups, edit the pause marker's topology fingerprint, change lock-file inodes, restore a stale full crontab, or run `supervisorctl update` across unrelated programs as a shortcut.

Do not reboot the host during the first screening cutover. The persistent marker fences the wrapped queue and root scheduler; it does not prevent PHP-FPM from starting during boot. After a host crash, inspect the selected release and durable screening activation state before resuming services. An installed marker alone does not prove that the database boundary was activated or that HTTP requests may safely resume.

## Subsequent release and data operations

Commit and push the reviewed application release before a normal `deploy.sh` deployment. The `--screening-check` and `--screening-setup` modes are preparation only. Once runtime setup is verified, the separately authorized deployment builds the inactive release, performs additive migrations, drains the wrapped runtime, briefly drains FPM for the first legacy cutover, reconciles legacy access, and activates screening. See [zero-downtime-deployments.md](zero-downtime-deployments.md) for the exact deployment procedure and rollback boundary.

Check and setup leave `/var/www/homecare/deploy.sh` on the old active release. For the **first screening application deployment**, use the recorded new Git-extracted driver, only after separate deployment authorization. Substitute its full saved path:

```bash
(
  set -eu
  screening_driver='<deploy driver path from the successful check>'
  bash "$screening_driver"
)
```

This command performs a real deployment and fetches the current remote `master`; confirm that branch contains the intended reviewed application release before authorizing it. Do not run it as part of the read-only check. Once a release containing the new driver is active, later deployments can use `/var/www/homecare/deploy.sh` normally.

Dataset imports and `screening:sync --refresh-identities` are separate, deliberately scheduled operations from [the screening runbook](../caregiver-screening.md). Do not add either to this setup script or the deployment critical path.

## Local verification

```bash
bash -n docs/deployment/screening-supervisor-setup.sh
bash tests/Deployment/screening-git-delivery-smoke.sh
bash tests/Deployment/screening-supervisor-setup-smoke.sh
bash tests/Deployment/screening-supervisor-runtime-smoke.sh
bash tests/Deployment/atomic-deploy-smoke.sh
bash tests/Deployment/screening-rollout-recovery-smoke.sh
php artisan test tests/Unit/Queue/ScreeningQueueConfigurationCheckTest.php tests/Feature/Screening/ScreeningRolloutTest.php tests/Feature/Screening/ScreeningPrivateFilesTest.php --compact
```

The Git delivery smoke test uses a temporary Git repository and mocked elevation to exercise revision selection, driver verification, staging, check/apply argument handling, and refusal paths without contacting production. The isolated setup smoke test executes the installer's exact text transformations against temporary fixtures. It covers cron preservation, placement under the original cron environment, refusal of ambiguous input, environment preservation, the targeted cached configuration change, and matching controller/setup fingerprints. The runtime and deployment tests mock service management and production identities. Windows checks do not establish Linux ownership, supplementary groups, service behavior, native process permissions, or production flock inheritance; verify these on the target Linux host during the authorized setup. PHP application tests use their isolated test database. No local test establishes that production can drain an actual long-running job.
