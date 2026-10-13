# Screening runtime control example

For the inspected production server (`homecare-worker` under Supervisor and the root crontab), use [the Supervisor and cron runbook](screening-supervisor-rollout.md) and its separate adapter. The example below is for a different topology.

This is an operator-installed example for **one Linux host, systemd, cgroup v2, foreground Laravel `schedule:run`, and directly supervised `queue:work` services**. It has no default queue unit names. It does not support cron, Supervisor, Horizon, `schedule:work`, containers, multiple hosts, shell wrappers, detached scheduled tasks, or external processes that restart these units. Supply a topology-specific hook for those arrangements; do not acknowledge this example's inventory contract when it is untrue.

The deployment script calls the absolute executable in `HOMECARE_RUNTIME_HOOK` with `preflight|pause|resume` and the stable application path as its second argument. It controls PHP-FPM separately. The hook runs as the deployment account, reads units with `systemctl show`, and uses `sudo systemctl` only to stop/start the explicitly configured timer and queues.

## Install and inventory before the first screening release

1. Inspect all cron tables, systemd units/timers, Supervisor/Horizon processes, remote workers, manually launched Artisan processes, and any monitoring that restarts them. Inventory every consumer of this application's queues and every scheduler. Migrate to the topology below or implement another hook. Stop and disable the previous scheduler when replacing it; running cron and a timer together violates this contract.
2. Install reviewed copies of `screening-runtime-control.example.sh` and the configuration **outside all application releases**. Use an operator-owned configuration that the web account cannot modify. Create a private state directory owned by the deployment account. Do not delete it while a deployment is paused.
3. Configure actual unit names, paths, accounts, queue names, retry/timeout settings, and the deployment account's narrowly scoped sudo permissions. `queue:work` needs PHP's `pcntl` extension for graceful signal handling. The service must use the same PHP configuration when checked by operators. Jobs must finish without daemonizing or escaping their service cgroup.
4. Reload systemd after reviewing the units, start the intended workers/timer, and run the hook's `preflight` before deploying. A timeout or failed preflight aborts activation; it never authorizes a force kill.

Example installation (adjust the deployment user/group):

```bash
sudo install -d -m 0755 /etc/homecare /usr/local/libexec
sudo install -d -m 0700 -o ubuntu -g ubuntu /var/lib/homecare-runtime
sudo install -m 0755 -o root -g root docs/deployment/screening-runtime-control.example.sh /usr/local/libexec/homecare-runtime-control
sudo install -m 0640 -o root -g ubuntu /path/to/reviewed-runtime.conf /etc/homecare/runtime.conf
HOMECARE_RUNTIME_HOOK=/usr/local/libexec/homecare-runtime-control
export HOMECARE_RUNTIME_HOOK
"$HOMECARE_RUNTIME_HOOK" preflight /var/www/homecare
```

`HOMECARE_RUNTIME_CONFIG` can point to another absolute trusted configuration file. It is sourced as shell code. The hook refuses missing configuration, incomplete acknowledgements, non-array or empty queue inventories, unknown/changed units, stale systemd configuration, unsafe stop settings, release-specific paths, and unexpected scheduler triggers. Unit and application paths must not contain spaces. The state directory must exist outside the stable app and current release; operators must also keep it outside every other release directory.

Example `/etc/homecare/runtime.conf` (these are example unit names, not automatically discovered defaults):

```bash
# Set yes only after inspecting the actual complete topology.
# Includes: no cron/schedule:work/background schedule commands, no external
# starters during deployment, and all writers/consumers reside on this host.
SOLE_SCHEDULER_CONFIRMED=yes
ALL_QUEUE_CONSUMERS_CONFIRMED=yes
QUEUE_UNITS=(homecare-queue-default.service homecare-queue-notifications.service)
SCHEDULER_TIMER=homecare-scheduler.timer
SCHEDULER_SERVICE=homecare-scheduler.service
STATE_DIR=/var/lib/homecare-runtime
DRAIN_TIMEOUT=1800
```

The acknowledgement covers topology that systemd cannot discover reliably. Do not manually start units while a deploy holds them paused. Every automated deploy must use the deployment lock and this hook. The timer is stopped, not masked; exclusive control is therefore part of the contract. The hook serializes its own invocations with `flock`.

## Example units

Each queue service must directly execute an absolute PHP path and the stable `/var/www/homecare/artisan` path. Adapt the connection, queues and job timeout to actual production settings; the example queues below are illustrative. Laravel's worker timeout must be less than that connection's `retry_after`. Ensure `php --ri pcntl` succeeds for the PHP binary/configuration used by these units.

```ini
# /etc/systemd/system/homecare-queue-default.service
[Unit]
Description=HomeCare default queue consumer
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/homecare
ExecStart=/usr/bin/php8.3 /var/www/homecare/artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=5
KillSignal=SIGTERM
KillMode=control-group
SendSIGKILL=no
TimeoutStopSec=infinity
UMask=0007

[Install]
WantedBy=multi-user.target
```

Create the second service with its actual queue arguments or remove it from the inventory if only one consumer exists. Inventory every service instance if using a template. No custom `ExecStartPre`, `ExecStartPost`, `ExecStop` or `ExecStopPost` is allowed by this example; those need a reviewed custom hook. Workers must support graceful SIGTERM; `SendSIGKILL=no` prevents systemd escalating the stop to SIGKILL. A stalled job leaves deployment paused for operator recovery rather than being forcibly terminated. These behaviors follow the [systemd service](https://github.com/systemd/systemd/blob/main/man/systemd.service.xml) and [systemd kill](https://github.com/systemd/systemd/blob/main/man/systemd.kill.xml) specifications.

```ini
# /etc/systemd/system/homecare-scheduler.service
[Unit]
Description=HomeCare foreground scheduled tasks
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/homecare
ExecStart=/usr/bin/php8.3 /var/www/homecare/artisan schedule:run
RemainAfterExit=no
Restart=no
# A dataset refresh may take much longer than systemd's default start timeout.
TimeoutStartSec=infinity
UMask=0007
```

```ini
# /etc/systemd/system/homecare-scheduler.timer
[Unit]
Description=Run HomeCare's sole scheduler every minute

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s
Persistent=false
Unit=homecare-scheduler.service

[Install]
WantedBy=timers.target
```

`pause` stops the timer first, waits for the oneshot service's foreground work to end naturally, then sends graceful stop requests to queue services. It never calls `stop` on the scheduler service. Successful draining requires inactive/failed service state, `MainPID=0`, `ControlPID=0`, and an empty cgroup including descendant processes. It also confirms the scheduler did not restart. The global `DRAIN_TIMEOUT` applies separately to each wait; long source imports can delay deployment until they finish.

## Recovery and repeatability

Before the first stop, the hook atomically saves the configured topology and the originally active queue/timer inventory in `STATE_DIR/pause-state`. Repeating `pause` preserves that original inventory. `resume` starts only previously active queues, checks their state and live PID, then starts the previously active timer. It never replays a scheduler invocation that already completed. Originally inactive services remain inactive.

Partial pause/start failures retain the file. After the deployment selects a safe release at the stable application path, it can retry `resume`; already active units may be started idempotently. The state file is removed only after all required units resume successfully. A changed configuration fails closed instead of restarting a different topology: restore the original matching configuration and investigate. Do not edit/delete saved state merely to bypass a failed resume. A host crash may leave paused state behind; inspect active units, current release, and screening activation state before recovery.

Manual recovery, **after confirming the stable app points to an eligible release**:

```bash
/usr/local/libexec/homecare-runtime-control preflight /var/www/homecare
/usr/local/libexec/homecare-runtime-control resume /var/www/homecare
```

The hook does not choose releases, reverse migrations, or decide whether crossing the first-screening boundary permits rollback. Those are deployment-script responsibilities. A system reboot starts enabled units independently of this saved state; operational procedures must prevent reboot/restarts during the cutover or use a custom persistent maintenance barrier.

## Isolated regression tests

```bash
bash -n docs/deployment/screening-runtime-control.example.sh
bash -n tests/Deployment/screening-runtime-hook-smoke.sh
bash tests/Deployment/screening-runtime-hook-smoke.sh
```

The tests use temporary files and mocked `systemctl`, `sudo`, and `flock`, with a fake cgroup tree. They never contact production or the host service manager. They cover inventory/settings validation, stable execution paths, natural scheduler draining, original-state preservation, partial stop/start recovery, repeated operations, and PID/cgroup leftovers that must block activation.
