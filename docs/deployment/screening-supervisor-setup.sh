#!/usr/bin/env bash
# One-time, operator-reviewed setup for the inspected production host.
# --check reads configuration only. --apply changes runtime configuration, not releases.
set -Eeuo pipefail
umask 077

APP_DIR=/var/www/homecare
DEPLOY_ROOT=/var/www/homecare-deploy
SHARED_ENV="$DEPLOY_ROOT/shared/.env"
SUPERVISOR_CONFIG=/etc/supervisor/conf.d/homecare-worker.conf
PROCESS=homecare-worker:homecare-worker_00
FPM_SERVICE=php8.3-fpm.service
DUPLICATE_UNIT=homecare-worker.service
STATE_DIR=/var/lib/homecare-runtime
ENTRYPOINT=/usr/local/libexec/homecare-runtime-entrypoint
CONTROL=/usr/local/libexec/homecare-runtime-control
QUEUE_CHECK=/usr/local/libexec/homecare-screening-queue-check.php
RUNTIME_CONFIG=/etc/homecare/runtime.conf
CONFIG="$RUNTIME_CONFIG"
PHP_BIN=/usr/bin/php
OLD_CRON='* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1'
NEW_CRON='* * * * * /usr/local/libexec/homecare-runtime-entrypoint scheduler /var/www/homecare >> /var/log/homecare-scheduler.log 2>&1'
OLD_COMMAND='/usr/bin/php /var/www/homecare/artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600'
BACKUP=''
CHANGED=0

fail() { printf 'Setup: %s\n' "$*" >&2; exit 1; }
log() { printf 'Setup: %s\n' "$*"; }

# Keep this fingerprint identical to the controller's topology() function.
topology() {
  printf 'version=1\napp=%s\nstate=%s\nentrypoint=%s\nphp=%s\nqueue_check=%s\nsupervisor_config=%s\nprocess=%s\n' \
    "$APP_DIR" "$STATE_DIR" "$ENTRYPOINT" "$PHP_BIN" "$QUEUE_CHECK" "$SUPERVISOR_CONFIG" "$PROCESS"
  local key path digest
  for key in config supervisor entrypoint queue_check; do
    case "$key" in
      config) path="$CONFIG" ;;
      supervisor) path="$SUPERVISOR_CONFIG" ;;
      entrypoint) path="$ENTRYPOINT" ;;
      queue_check) path="$QUEUE_CHECK" ;;
    esac
    digest="$(sha256sum "$path")" || return 1
    printf '%s_sha256=%s\n' "$key" "${digest%% *}"
  done
  printf 'start_lock_id=%s\nrun_lock_id=%s\n' "$(stat -c '%d:%i' -- "$STATE_DIR/start.lock")" "$(stat -c '%d:%i' -- "$STATE_DIR/run.lock")"
}

assert_duplicate_inactive() {
  local group
  case "$(systemctl show "$DUPLICATE_UNIT" -p ActiveState --value)" in inactive|failed) ;; *) fail 'The duplicate systemd worker is running; reconcile its jobs before setup.' ;; esac
  [[ "$(systemctl show "$DUPLICATE_UNIT" -p MainPID --value)" == 0 && "$(systemctl show "$DUPLICATE_UNIT" -p ControlPID --value)" == 0 ]] \
    || fail 'The duplicate systemd worker still has a process.'
  group="$(systemctl show "$DUPLICATE_UNIT" -p ControlGroup --value)"
  if [[ -n "$group" ]]; then
    [[ "$group" == /* && "$group" != *'..'* ]] || fail 'Unexpected duplicate worker cgroup path.'
    [[ ! -d "/sys/fs/cgroup$group" ]] || grep -qx 'populated 0' "/sys/fs/cgroup$group/cgroup.events" \
      || fail 'The duplicate worker has live descendants.'
  fi
}

usage() {
  cat <<'EOF'
Usage:
  sudo bash screening-supervisor-setup.sh --check /absolute/reviewed/bundle
  sudo bash screening-supervisor-setup.sh --apply /absolute/reviewed/bundle \
    --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout

The bundle must contain screening-runtime-entrypoint.sh,
screening-supervisor-runtime-control.sh, and check-screening-queue.php.
Apply temporarily pauses scheduled work and gracefully stops the current worker.
Its EXISTING Supervisor settings permit SIGKILL after 3600 seconds. Do not use
apply if a job may exceed that limit. New runtime drains never force-kill work.
This script does not deploy, change the application symlink, boot Laravel,
run migrations, import datasets, or backfill Didit.
EOF
}

[[ "$#" -ge 1 ]] || { usage; exit 1; }
[[ "$1" != --help ]] || { usage; exit 0; }
MODE="$1"
[[ "$MODE" == --check || "$MODE" == --apply ]] || fail 'Choose --check or --apply.'
[[ "$#" -ge 2 && "$2" == /* ]] || fail 'Supply an absolute reviewed bundle directory.'
BUNDLE="$(readlink -f -- "$2")"
[[ -d "$BUNDLE" ]] || fail 'Bundle directory is missing.'
if [[ "$MODE" == --apply ]]; then
  [[ "$#" -eq 4 && "$3" == --confirm-single-host-runtime && "$4" == --accept-existing-supervisor-stop-timeout ]] \
    || fail 'Apply requires both explicit inventory and existing-stop-timeout acknowledgements.'
else
  [[ "$#" -eq 2 ]] || fail 'Unexpected check arguments.'
fi
[[ "$EUID" -eq 0 ]] || fail 'Run as root to inspect the root crontab and protected configuration.'
for command in python3 php supervisorctl systemctl crontab sudo install stat readlink flock getent id; do
  command -v "$command" >/dev/null || fail "Missing command: $command"
done

verify_original_configuration() {
  [[ -L "$APP_DIR" && -f "$APP_DIR/artisan" ]] || fail 'Expected the inspected atomic application layout.'
  APP_REAL="$(readlink -f -- "$APP_DIR")"
  case "$APP_REAL/" in "$DEPLOY_ROOT/releases/"*) ;; *) fail 'Unexpected active release location.' ;; esac
  case "$BUNDLE/" in "$APP_DIR/"*|"$DEPLOY_ROOT/"*|"$APP_REAL/"*) fail 'Stage the reviewed bundle outside the live application and all releases.' ;; esac
  [[ "$(readlink -f -- "$APP_DIR/.env")" == "$(readlink -f -- "$SHARED_ENV")" && -f "$SHARED_ENV" && ! -L "$SHARED_ENV" ]] \
    || fail 'Expected the application .env to reference the regular shared environment file.'
  [[ -f "$APP_DIR/bootstrap/cache/config.php" && ! -L "$APP_DIR/bootstrap/cache/config.php" ]] \
    || fail 'Expected a regular production configuration cache.'
  for source in screening-runtime-entrypoint.sh screening-supervisor-runtime-control.sh check-screening-queue.php; do
    [[ -f "$BUNDLE/$source" && ! -L "$BUNDLE/$source" ]] || fail "Missing regular reviewed bundle file: $source"
  done
  [[ ! -e "$STATE_DIR" && ! -e "$ENTRYPOINT" && ! -e "$CONTROL" && ! -e "$QUEUE_CHECK" && ! -e "$RUNTIME_CONFIG" ]] \
    || fail 'Runtime setup already exists or is partially installed; inspect the prior private backup instead of rerunning bootstrap.'
  [[ ! -e /etc/systemd/system/php8.3-fpm.service.d/60-homecare-screening-drain.conf ]] \
    || fail 'The proposed FPM drop-in already exists; inspect before changing it.'
  getent passwd ubuntu >/dev/null && getent group www-data >/dev/null || fail 'Expected ubuntu and www-data accounts.'
  /usr/bin/php -r 'exit(extension_loaded("pcntl") ? 0 : 1);' || fail 'The worker PHP binary needs pcntl.'
  [[ "$(systemctl show "$FPM_SERVICE" -p ActiveState --value)" == active ]] || fail 'PHP-FPM must be healthy and active before setup.'
  for service in cron.service supervisor.service; do
    [[ "$(systemctl show "$service" -p ActiveState --value)" == active && "$(systemctl show "$service" -p UnitFileState --value)" == enabled ]] \
      || fail "Expected $service to be active and enabled."
  done
  [[ -z "$(systemctl show "$FPM_SERVICE" -p ExecStop --value)" && -z "$(systemctl show "$FPM_SERVICE" -p TriggeredBy --value)" ]] \
    || fail 'Unexpected FPM stop command or activation source needs separate review.'
  assert_duplicate_inactive
  [[ -f /sys/fs/cgroup/cgroup.controllers ]] || fail 'Expected Linux cgroup v2 on the inspected host.'
  python3 - "$SUPERVISOR_CONFIG" "$OLD_COMMAND" "$OLD_CRON" <<'PY'
import configparser, pathlib, subprocess, sys
path, expected_command, expected_cron = sys.argv[1:]
c = configparser.ConfigParser(interpolation=None, strict=True)
with open(path) as f:
    c.read_file(f)
assert c.sections() == ['program:homecare-worker'], 'Unexpected Supervisor program inventory in this file.'
p = c['program:homecare-worker']
expected = dict(command=expected_command, user='www-data', numprocs='1',
                autostart='true', autorestart='true', stopasgroup='true',
                killasgroup='true', stopwaitsecs='3600')
assert all(p.get(k, '').strip().lower() == v.lower() for k, v in expected.items()), 'Supervisor settings differ from inspected production.'
assert p.get('process_name', '%(program_name)s_%(process_num)02d') == '%(program_name)s_%(process_num)02d', 'Unexpected Supervisor process naming.'
assert p.get('stopsignal', 'TERM').upper() in ('TERM', 'SIGTERM'), 'Existing worker stop must use graceful TERM.'
assert 'environment' not in p, 'Review Supervisor environment overrides before applying.'
cron = subprocess.check_output(['crontab', '-u', 'root', '-l'], text=True)
assert cron.splitlines().count(expected_cron) == 1, 'The exact inspected scheduler line must occur once.'
other = [line for line in cron.splitlines() if line != expected_cron and not line.lstrip().startswith('#')
         and ('schedule:run' in line or 'schedule:work' in line)]
assert not other, 'Unexpected additional root scheduler needs review.'
PY
  [[ "$(supervisorctl status "$PROCESS" | awk '{print $2}')" == RUNNING ]] || fail 'Expected the inspected Supervisor worker to be running.'
  LEGACY_PID="$(supervisorctl pid "$PROCESS")"
  [[ "$LEGACY_PID" =~ ^[1-9][0-9]*$ ]] || fail 'Cannot identify the current worker PID.'
  STORAGE_REAL="$(readlink -f -- "$APP_DIR/storage")"
  [[ -d "$STORAGE_REAL" && "$(readlink -f -- "$DEPLOY_ROOT/shared/storage")" == "$STORAGE_REAL" ]] || fail 'Shared durable storage targets differ.'
  [[ ! -L "$STORAGE_REAL/app" && ! -L "$STORAGE_REAL/app/private" && ! -L "$STORAGE_REAL/app/private/screening" ]] \
    || fail 'Private screening storage must not traverse symlinks.'
  if [[ -d "$STORAGE_REAL/app/private/screening" ]]; then
    [[ -z "$(find "$STORAGE_REAL/app/private/screening" -type l -print -quit)" ]] || fail 'Screening storage contains a symlink.'
  fi
  sudo -u www-data /usr/bin/php -r '
    $c = require $argv[1];
    if (($c["queue"]["default"] ?? null) !== "database" ||
        ($c["queue"]["connections"]["database"]["driver"] ?? null) !== "database" ||
        ($c["queue"]["connections"]["database"]["queue"] ?? null) !== "default" ||
        !in_array($c["queue"]["connections"]["database"]["retry_after"] ?? null, [90, 180], true)) {
        fwrite(STDERR, "Unexpected cached queue settings; review before setup.\n"); exit(1);
    }
  ' "$APP_DIR/bootstrap/cache/config.php"
  log 'Inspected settings match. Independent inventory must also exclude other hosts, cron tables, timers and manually launched consumers.'
}

verify_original_configuration
[[ "$MODE" == --apply ]] || { log 'Read-only check complete; no files or service state changed.'; exit 0; }

exec 9>/var/lock/homecare-screening-setup.lock
flock -n 9 || fail 'Another setup is already running.'
# Serialize with the normal deployment script as well.
# Root must not open an existing ubuntu-owned file in sticky /tmp with O_CREAT:
# Ubuntu's fs.protected_regular can reject that even when the file is legitimate.
# Create a missing lock as its normal owner, then open read-only without truncation.
if [[ ! -e /tmp/homecare-deploy.lock && ! -L /tmp/homecare-deploy.lock ]]; then
  sudo -u ubuntu /bin/bash -c 'umask 022; set -o noclobber; : > /tmp/homecare-deploy.lock' \
    || fail 'The deployment lock appeared during setup; inspect it and retry.'
fi
[[ -f /tmp/homecare-deploy.lock && ! -L /tmp/homecare-deploy.lock \
  && "$(stat -c '%U' -- /tmp/homecare-deploy.lock)" == ubuntu ]] \
  || fail 'The deployment lock must be an ubuntu-owned regular file, not a symlink.'
case "$(stat -c '%G' -- /tmp/homecare-deploy.lock)" in ubuntu|www-data) ;; *) fail 'Unexpected deployment lock group.' ;; esac
deploy_lock_mode="$(stat -c '%a' -- /tmp/homecare-deploy.lock)"
[[ "$deploy_lock_mode" =~ ^[0-7]{3,4}$ && $((8#$deploy_lock_mode & 002)) -eq 0 ]] \
  || fail 'The deployment lock must not be world-writable.'
deploy_lock_identity="$(stat -c '%d:%i' -- /tmp/homecare-deploy.lock)"
exec 8</tmp/homecare-deploy.lock
[[ ! -L /tmp/homecare-deploy.lock \
  && "$(stat -Lc '%d:%i' -- /proc/self/fd/8)" == "$deploy_lock_identity" \
  && "$(stat -c '%d:%i' -- /tmp/homecare-deploy.lock)" == "$deploy_lock_identity" ]] \
  || fail 'The deployment lock changed while it was being opened.'
flock -n 8 || fail 'An application deployment is already running.'
verify_original_configuration

BACKUP="/var/backups/homecare-screening-runtime/$(date -u +%Y%m%dT%H%M%SZ)-$$"
install -d -m 0700 /var/backups/homecare-screening-runtime "$BACKUP"
cp -a -- "$SUPERVISOR_CONFIG" "$BACKUP/supervisor.conf"
cp -a -- "$SHARED_ENV" "$BACKUP/shared.env"
cp -a -- "$APP_DIR/bootstrap/cache/config.php" "$BACKUP/config.php"
crontab -u root -l > "$BACKUP/root.crontab"
systemctl show "$FPM_SERVICE" -p FragmentPath -p DropInPaths -p KillSignal -p KillMode -p SendSIGKILL -p TimeoutStopUSec > "$BACKUP/fpm-properties"
systemctl is-enabled "$DUPLICATE_UNIT" > "$BACKUP/duplicate-worker-enabled" || true
id ubuntu > "$BACKUP/deploy-account"
printf '%s\n' "$APP_REAL" > "$BACKUP/active-release"
sha256sum "$BUNDLE/screening-runtime-entrypoint.sh" "$BUNDLE/screening-supervisor-runtime-control.sh" "$BUNDLE/check-screening-queue.php" > "$BACKUP/bundle.sha256"

on_failure() {
  local status=$?
  trap - EXIT
  if [[ "$status" -ne 0 && "$CHANGED" -eq 1 ]]; then
    printf 'Setup failed. Do not deploy or delete paused state. Private recovery inventory: %s\n' "$BACKUP" >&2
    printf 'The scheduler and/or worker may remain paused. Follow screening-supervisor-rollout.md recovery; no automatic old-config rollback was attempted.\n' >&2
  fi
  exit "$status"
}
trap on_failure EXIT
CHANGED=1

# Preserve every unrelated root crontab line byte-for-byte.
python3 - "$BACKUP/root.crontab" "$BACKUP/root.paused.crontab" "$OLD_CRON" <<'PY'
import pathlib, sys
source, target, old = sys.argv[1:]
lines = pathlib.Path(source).read_bytes().splitlines(keepends=True)
assert sum(line.rstrip(b'\r\n') == old.encode() for line in lines) == 1
pathlib.Path(target).write_bytes(b''.join(line for line in lines if line.rstrip(b'\r\n') != old.encode()))
PY
crontab -u root "$BACKUP/root.paused.crontab"
CRON_REMOVED_AT=$SECONDS
wait_for_legacy_tasks() {
  local reload_wait="$1"
  log 'Waiting naturally for old cron shells and all application Artisan tasks.'
# Foreground schedule:run retains its child tasks; also wait for independent Artisan tasks.
# Never dump process environment or arbitrary command arguments.
python3 - "$APP_DIR" "$APP_REAL" "$reload_wait" <<'PY'
import os, pathlib, sys, time
app, real, minimum = sys.argv[1:]
deadline = time.monotonic() + 1800
reload_after = time.monotonic() + int(minimum)
while True:
    found = []
    for p in pathlib.Path('/proc').iterdir():
        if not p.name.isdecimal():
            continue
        try:
            args = (p / 'cmdline').read_bytes().split(b'\0')
            cwd = os.readlink(p / 'cwd')
            for i, arg in enumerate(args):
                old_shell = b'schedule:run' in arg and (app.encode() in arg or real.encode() in arg)
                if old_shell or arg in ((app + '/artisan').encode(), (real + '/artisan').encode()) or (arg == b'artisan' and cwd in (app, real)):
                    found.append(p.name)
                    break
        except (FileNotFoundError, ProcessLookupError, PermissionError):
            continue
    if not found and time.monotonic() >= reload_after:
        break
    if time.monotonic() >= deadline:
        raise SystemExit('Artisan tasks remain (PIDs ' + ','.join(found) + '); scheduler remains paused, no task was killed.')
    time.sleep(2)
PY
}
log 'Stopping the legacy worker gracefully with its existing 3600-second Supervisor stop limit.'
supervisorctl stop "$PROCESS"
[[ "$(supervisorctl status "$PROCESS" | awk '{print $2}')" == STOPPED && "$(supervisorctl pid "$PROCESS")" == 0 ]] \
  || fail 'Legacy worker did not reach STOPPED/PID 0.'
# Allow cron to reload the changed table across a complete minute boundary, while
# also waiting for already-forked old shell invocations and all foreground tasks.
# No PID is exempt: a worker that restarted before the stop is included as well.
cron_reload_wait=$((65 - (SECONDS - CRON_REMOVED_AT)))
if (( cron_reload_wait < 0 )); then cron_reload_wait=0; fi
wait_for_legacy_tasks "$cron_reload_wait"

# Prepare a persistent admission barrier before exposing any wrapped entrypoints.
usermod -a -G www-data ubuntu
install -d -m 0755 -o root -g root /usr/local/libexec /etc/homecare
install -d -m 0750 -o ubuntu -g www-data "$STATE_DIR"
install -m 0640 -o ubuntu -g www-data /dev/null "$STATE_DIR/start.lock"
install -m 0640 -o ubuntu -g www-data /dev/null "$STATE_DIR/run.lock"
install -m 0600 -o ubuntu -g www-data /dev/null "$STATE_DIR/operation.lock"
printf 'bootstrap incomplete; runtime admission is closed\n' > "$BACKUP/paused"
install -m 0640 -o ubuntu -g www-data "$BACKUP/paused" "$STATE_DIR/paused"
install -m 0755 -o root -g root "$BUNDLE/screening-runtime-entrypoint.sh" "$ENTRYPOINT"
install -m 0755 -o root -g root "$BUNDLE/screening-supervisor-runtime-control.sh" "$CONTROL"
install -m 0644 -o root -g root "$BUNDLE/check-screening-queue.php" "$QUEUE_CHECK"
cat > "$BACKUP/runtime.conf" <<'EOF'
STATE_DIR=/var/lib/homecare-runtime
ENTRYPOINT=/usr/local/libexec/homecare-runtime-entrypoint
PHP_BIN=/usr/bin/php
QUEUE_CHECK=/usr/local/libexec/homecare-screening-queue-check.php
SUPERVISOR_CONFIG=/etc/supervisor/conf.d/homecare-worker.conf
DRAIN_TIMEOUT=1800
DEPLOY_USER=ubuntu
WEB_GROUP=www-data
SUPERVISOR_AND_ROOT_CRON_CONFIRMED=yes
EOF
install -m 0640 -o root -g www-data "$BACKUP/runtime.conf" "$RUNTIME_CONFIG"

# Preserve all unrelated environment entries, including credentials, without printing them.
python3 - "$SHARED_ENV" <<'PY'
import os, pathlib, re, stat, sys
path = pathlib.Path(sys.argv[1])
original = path.stat()
lines = path.read_bytes().splitlines(keepends=True)
key = re.compile(rb'^\s*(?:export\s+)?DB_QUEUE_RETRY_AFTER\s*=')
hits = [i for i, line in enumerate(lines) if key.match(line)]
assert len(hits) <= 1, 'Duplicate DB_QUEUE_RETRY_AFTER keys need review.'
if hits:
    i = hits[0]
    ending = b'\r\n' if lines[i].endswith(b'\r\n') else b'\n'
    lines[i] = b'DB_QUEUE_RETRY_AFTER=180' + ending
else:
    if lines and not lines[-1].endswith(b'\n'):
        lines[-1] += b'\n'
    lines.append(b'DB_QUEUE_RETRY_AFTER=180\n')
temporary = path.with_name(path.name + '.screening-next-' + str(os.getpid()))
with temporary.open('xb') as f:
    f.write(b''.join(lines))
    f.flush()
    os.fsync(f.fileno())
os.chown(temporary, original.st_uid, original.st_gid)
os.chmod(temporary, stat.S_IMODE(original.st_mode))
os.replace(temporary, path)
PY

# Change this one cached value without booting Laravel or rebuilding live view paths.
CACHE="$APP_DIR/bootstrap/cache/config.php"
CACHE_NEXT="$CACHE.screening-next-$$"
sudo -u www-data /usr/bin/php /dev/stdin "$CACHE" "$CACHE_NEXT" <<'PHP'
<?php
umask(0077);
$path = $argv[1];
$next = $argv[2];
$before = require $path;
if (($before['queue']['default'] ?? null) !== 'database'
    || ($before['queue']['connections']['database']['driver'] ?? null) !== 'database'
    || ($before['queue']['connections']['database']['queue'] ?? null) !== 'default'
    || !in_array($before['queue']['connections']['database']['retry_after'] ?? null, [90, 180], true)) {
    throw new RuntimeException('Cached queue configuration changed; refusing the targeted update.');
}
$after = $before;
$after['queue']['connections']['database']['retry_after'] = 180;
$file = fopen($next, 'x');
if ($file === false) { throw new RuntimeException('Cannot create protected candidate configuration.'); }
$body = '<?php return '.var_export($after, true).';'.PHP_EOL;
if (fwrite($file, $body) !== strlen($body) || !fflush($file) || !fsync($file)) {
    throw new RuntimeException('Cannot durably write candidate configuration.');
}
fclose($file);
if ((require $next) !== $after) { throw new RuntimeException('Candidate configuration changed unexpectedly.'); }
PHP
chown --reference="$CACHE" "$CACHE_NEXT"
chmod --reference="$CACHE" "$CACHE_NEXT"
mv -T -- "$CACHE_NEXT" "$CACHE"
sudo -u www-data /usr/bin/php "$QUEUE_CHECK" "$CACHE" 120

SCREENING_DIR="$STORAGE_REAL/app/private/screening"
install -d -m 2770 -o ubuntu -g www-data "$SCREENING_DIR"
find "$SCREENING_DIR" -type d -exec chown ubuntu:www-data {} + -exec chmod 2770 {} +
find "$SCREENING_DIR" -type f -exec chown ubuntu:www-data {} + -exec chmod 0660 {} +

install -d -m 0755 -o root -g root /etc/systemd/system/php8.3-fpm.service.d
cat > "$BACKUP/fpm-drop-in.conf" <<'EOF'
[Service]
KillSignal=SIGQUIT
KillMode=control-group
SendSIGKILL=no
TimeoutStopSec=120
EOF
install -m 0644 -o root -g root "$BACKUP/fpm-drop-in.conf" /etc/systemd/system/php8.3-fpm.service.d/60-homecare-screening-drain.conf
systemctl daemon-reload
[[ "$(systemctl show "$FPM_SERVICE" -p SendSIGKILL --value)" == no ]] || fail 'FPM graceful-stop settings did not load.'
case "$(systemctl show "$FPM_SERVICE" -p KillSignal --value)" in SIGQUIT|3) ;; *) fail 'FPM stop signal was not updated.' ;; esac
assert_duplicate_inactive
systemctl disable "$DUPLICATE_UNIT"
systemctl reset-failed "$DUPLICATE_UNIT"
[[ "$(systemctl show "$DUPLICATE_UNIT" -p ActiveState --value)" == inactive ]] || fail 'Duplicate worker did not become inactive.'

# Rewrite only the Supervisor command; preserve logging and all unrelated options.
python3 - "$SUPERVISOR_CONFIG" "$BACKUP/supervisor.wrapped.conf" "$OLD_COMMAND" "$ENTRYPOINT worker $APP_DIR" <<'PY'
import pathlib, re, sys
source, target, old, new = sys.argv[1:]
text = pathlib.Path(source).read_text()
pattern = re.compile(r'(?m)^([ \t]*command[ \t]*=[ \t]*)' + re.escape(old) + r'[ \t]*$')
text, count = pattern.subn(lambda m: m.group(1) + new, text)
assert count == 1, 'Supervisor command changed during setup.'
pathlib.Path(target).write_text(text)
PY
install -m 0644 -o root -g root "$BACKUP/supervisor.wrapped.conf" "$SUPERVISOR_CONFIG"
# Finalize the marker only after every file covered by its fingerprint exists.
# The initial placeholder remains a closed gate until this atomic replacement.
topology > "$BACKUP/paused-final"
install -m 0640 -o ubuntu -g www-data "$BACKUP/paused-final" "$STATE_DIR/paused.next"
sync -f "$STATE_DIR/paused.next"
mv -T -- "$STATE_DIR/paused.next" "$STATE_DIR/paused"
sync -f "$STATE_DIR"
supervisorctl reread
supervisorctl update homecare-worker
worker_deadline=$((SECONDS + 30))
until [[ "$(supervisorctl status "$PROCESS" | awk '{print $2}')" == RUNNING ]]; do
  (( SECONDS < worker_deadline )) || fail 'Wrapped Supervisor worker failed to start behind the pause marker.'
  sleep 1
done

python3 - "$BACKUP/root.paused.crontab" "$BACKUP/root.crontab" "$BACKUP/root.wrapped.crontab" "$OLD_CRON" "$NEW_CRON" <<'PY'
import pathlib, subprocess, sys
paused, original, target, old, new = sys.argv[1:]
expected = pathlib.Path(paused).read_bytes()
actual = subprocess.check_output(['crontab', '-u', 'root', '-l'])
assert actual == expected, 'Root crontab changed during setup; refusing to overwrite concurrent edits.'
lines = pathlib.Path(original).read_bytes().splitlines(keepends=True)
assert sum(line.rstrip(b'\r\n') == old.encode() for line in lines) == 1
assert b''.join(line for line in lines if line.rstrip(b'\r\n') != old.encode()) == expected
result = []
for line in lines:
    if line.rstrip(b'\r\n') == old.encode():
        line = new.encode() + line[len(line.rstrip(b'\r\n')):]
    result.append(line)
pathlib.Path(target).write_bytes(b''.join(result))
PY
if [[ ! -e /var/log/homecare-scheduler.log ]]; then
  install -m 0640 -o root -g www-data /dev/null /var/log/homecare-scheduler.log
else
  [[ -f /var/log/homecare-scheduler.log && ! -L /var/log/homecare-scheduler.log ]] || fail 'Scheduler log must be a regular file.'
  chown root:www-data /var/log/homecare-scheduler.log
  chmod 0640 /var/log/homecare-scheduler.log
fi
crontab -u root "$BACKUP/root.wrapped.crontab"
sudo -u ubuntu "$CONTROL" preflight "$APP_DIR"
sudo -u ubuntu "$CONTROL" resume "$APP_DIR"
[[ "$(readlink -f -- "$APP_DIR")" == "$APP_REAL" ]] || fail 'The active release changed unexpectedly during setup.'
[[ "$(systemctl show "$FPM_SERVICE" -p ActiveState --value)" == active ]] || fail 'PHP-FPM is no longer active.'
CHANGED=0
log "Setup complete; the original application release is still active. Private backup: $BACKUP"
log 'Start a fresh ubuntu login so its new www-data supplementary group is effective before deployment.'
