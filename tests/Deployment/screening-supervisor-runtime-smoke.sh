#!/usr/bin/env bash
# Temporary fixtures only. Never invokes host sudo, Supervisor, cron, systemd or PHP.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-supervisor-runtime.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
[[ "$SANDBOX" == "$TEMP_ROOT"/homecare-supervisor-runtime.* ]] || exit 1
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-supervisor-runtime.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
mkdir -p "$SANDBOX/bin" "$SANDBOX/app/bootstrap/cache" "$SANDBOX/state" "$SANDBOX/installed"
touch "$SANDBOX/app/artisan" "$SANDBOX/app/bootstrap/cache/config.php" "$SANDBOX/installed/queue-check.php"
printf 'run-lease-sentinel\n' > "$SANDBOX/state/run.lock"
touch "$SANDBOX/state/start.lock"
chmod 750 "$SANDBOX/state"
chmod 640 "$SANDBOX/state/run.lock" "$SANDBOX/state/start.lock"
cp "$PROJECT_DIR/docs/deployment/screening-supervisor-runtime-control.sh" "$SANDBOX/installed/control"
cp "$PROJECT_DIR/docs/deployment/screening-runtime-entrypoint.sh" "$SANDBOX/installed/entrypoint"
chmod 755 "$SANDBOX/installed/control" "$SANDBOX/installed/entrypoint"
export MOCK_ROOT="$SANDBOX" HOMECARE_RUNTIME_CONFIG="$SANDBOX/runtime.conf"

cat > "$SANDBOX/bin/stat" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
format="$2"; path="${@: -1}"
if [[ "${MOCK_BAD_PERMISSIONS:-0}" == 1 && "$path" == "$MOCK_ROOT/state/run.lock" && "$format" == %a ]]; then printf '666\n'; exit; fi
case "$format" in
  %u) printf '0\n' ;;
  %U) printf 'ubuntu\n' ;;
  %G) printf 'www-data\n' ;;
  %d:%i) printf '1:%s:%s\n' "$(basename "$path")" "${MOCK_LOCK_REPLACED:-0}" ;;
  %a)
    case "$path" in
      */state) printf '750\n' ;;
      */operation.lock) printf '600\n' ;;
      */start.lock|*/run.lock|*/paused|*/runtime.conf) printf '640\n' ;;
      *) printf '755\n' ;;
    esac
    ;;
  *) exit 99 ;;
esac
MOCK
cat > "$SANDBOX/bin/id" <<'MOCK'
#!/usr/bin/env bash
[[ "$*" == -un ]] || exit 99
printf 'ubuntu\n'
MOCK
cat > "$SANDBOX/bin/chgrp" <<'MOCK'
#!/usr/bin/env bash
[[ "$1" == www-data && "$2" == "$MOCK_ROOT/state/"* ]]
MOCK
cat > "$SANDBOX/bin/sync" <<'MOCK'
#!/usr/bin/env bash
[[ "$1" == -f && "$2" == "$MOCK_ROOT/state"* ]] || exit 99
printf 'sync %s\n' "$2" >> "$MOCK_ROOT/actions"
MOCK
cat > "$SANDBOX/bin/flock" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'flock %s\n' "$*" >> "$MOCK_ROOT/actions"
[[ "${MOCK_OPERATION_BUSY:-0}" != 1 || "$*" != '-n 9' ]] || exit 1
[[ "${MOCK_DRAIN_TIMEOUT:-0}" != 1 || "$*" != '-x -w 1 8' ]] || exit 1
if [[ "${MOCK_GATE_RACE:-0}" == 1 && "$*" == '-s 7' ]]; then
  printf 'concurrently paused\n' > "$MOCK_ROOT/state/paused"
fi
MOCK
cat > "$SANDBOX/bin/systemctl" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$1" == show ]] || exit 99
unit="$2"; property="${3#--property=}"
if [[ "$unit" == homecare-worker.service ]]; then
  case "$property" in
    LoadState) printf 'loaded\n' ;;
    ActiveState) printf '%s\n' "${MOCK_DUPLICATE_STATE:-inactive}" ;;
    UnitFileState) printf 'disabled\n' ;;
    *) exit 99 ;;
  esac
else
  case "$property" in
    ActiveState) printf '%s\n' "${MOCK_SERVICE_STATE:-active}" ;;
    UnitFileState) printf 'enabled\n' ;;
    *) exit 99 ;;
  esac
fi
MOCK
cat > "$SANDBOX/bin/sudo" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$1" == -n ]] || exit 99
shift
command="$1"; shift
kind="$(cat "$MOCK_ROOT/worker")"
if [[ "$kind" == auto ]]; then
  if [[ -e "$MOCK_ROOT/state/paused" ]]; then kind=waiting; else kind=php; fi
fi
if [[ "$kind" == transition && -f "$MOCK_ROOT/transition-observed" ]]; then kind=php; fi
case "$command" in
  crontab) [[ "$*" == -l ]] || exit 99; cat "$MOCK_ROOT/root-cron" ;;
  supervisorctl)
    [[ "${MOCK_SUPERVISOR_FAILURE:-0}" != 1 ]] || exit 1
    case "$1" in
      status)
        [[ "$2" == homecare-worker:homecare-worker_00 ]] || exit 99
        if [[ "$kind" == stopped ]]; then printf 'homecare-worker:homecare-worker_00 STOPPED\n'; exit 3; fi
        printf 'homecare-worker:homecare-worker_00 RUNNING pid 4321, uptime 0:05:00\n'
        ;;
      pid) printf '4321\n' ;;
      signal)
        [[ "$*" == 'signal TERM homecare-worker:homecare-worker_00' ]] || exit 99
        printf 'signal TERM\n' >> "$MOCK_ROOT/actions"
        [[ "${MOCK_SIGNAL_FAILURE:-0}" != 1 ]] || exit 1
        printf 'auto\n' > "$MOCK_ROOT/worker"
        ;;
      *) exit 99 ;;
    esac
    ;;
  readlink)
    case "$1" in
      /proc/4321/exe)
        case "$kind" in
          waiting|waiting-lease|transition) printf '/usr/bin/bash\n' ;;
          wrong-exe) printf '/usr/bin/unexpected\n' ;;
          *) readlink -f "$MOCK_ROOT/bin/php" ;;
        esac
        ;;
      /proc/4321/fd/8)
        [[ "$kind" != no-lease ]] || exit 1
        readlink -f "$MOCK_ROOT/state/run.lock"
        ;;
      *) exit 99 ;;
    esac
    ;;
  cat)
    [[ "$1" == /proc/4321/cmdline ]] || exit 99
    if [[ "$kind" == waiting || "$kind" == waiting-lease || "$kind" == transition ]]; then
      printf '%s\0' bash "$MOCK_ROOT/installed/entrypoint" worker "$MOCK_ROOT/app"
    elif [[ "$kind" == wrong-args ]]; then
      printf '%s\0' "$MOCK_ROOT/bin/php" "$MOCK_ROOT/app/artisan" queue:work redis
    else
      printf '%s\0' "$MOCK_ROOT/bin/php" "$MOCK_ROOT/app/artisan" queue:work database --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600
    fi
    ;;
  test)
    [[ "$*" == '! -e /proc/4321/fd/8' ]] || exit 99
    if [[ "$kind" == transition ]]; then
      touch "$MOCK_ROOT/transition-observed"
      printf 'admitted-wrapper-transition\n' >> "$MOCK_ROOT/actions"
      exit 1
    fi
    [[ "$kind" == waiting ]]
    ;;
  *) exit 99 ;;
esac
MOCK
cat > "$SANDBOX/bin/php" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "$1" == -r ]]; then [[ "${MOCK_NO_PCNTL:-0}" != 1 ]]; exit; fi
if [[ "$1" == "$MOCK_ROOT/installed/queue-check.php" ]]; then
  printf 'queue-check %s\n' "$*" >> "$MOCK_ROOT/actions"
  [[ "$2" == "$MOCK_ROOT/app/bootstrap/cache/config.php" && "$3" == 120 && "${MOCK_BAD_QUEUE:-0}" != 1 ]]
  exit
fi
[[ "$1" == "$MOCK_ROOT/app/artisan" ]] || exit 99
lease=''
read -r -u 8 lease
[[ "$lease" == run-lease-sentinel ]] || exit 98
if { : <&7; } 2>/dev/null; then exit 97; fi
printf 'executed %s\n' "$*" >> "$MOCK_ROOT/actions"
printf 'umask %s\n' "$(umask)" >> "$MOCK_ROOT/actions"
# Child processes inherit the same descriptor, as foreground Laravel jobs must.
bash -c 'read -r -u 8 inherited || [[ -n "$inherited" ]]; : <&8' || exit 96
MOCK
chmod 755 "$SANDBOX/bin/"*
export PATH="$SANDBOX/bin:$PATH"

reset_fixture() {
  unset MOCK_BAD_PERMISSIONS MOCK_OPERATION_BUSY MOCK_DRAIN_TIMEOUT MOCK_GATE_RACE MOCK_DUPLICATE_STATE MOCK_SERVICE_STATE
  unset MOCK_SUPERVISOR_FAILURE MOCK_SIGNAL_FAILURE MOCK_NO_PCNTL MOCK_BAD_QUEUE
  unset MOCK_LOCK_REPLACED
  rm -f -- "$SANDBOX/state/paused" "$SANDBOX/state/operation.lock" "$SANDBOX/transition-observed"
  printf 'php\n' > "$SANDBOX/worker"
  : > "$SANDBOX/actions"
  cat > "$HOMECARE_RUNTIME_CONFIG" <<CONFIG
SUPERVISOR_AND_ROOT_CRON_CONFIRMED=yes
STATE_DIR='$SANDBOX/state'
ENTRYPOINT='$SANDBOX/installed/entrypoint'
PHP_BIN='$SANDBOX/bin/php'
QUEUE_CHECK='$SANDBOX/installed/queue-check.php'
SUPERVISOR_CONFIG='$SANDBOX/supervisor.conf'
DEPLOY_USER=ubuntu
WEB_GROUP=www-data
DRAIN_TIMEOUT=1
CONFIG
  cat > "$SANDBOX/supervisor.conf" <<CONFIG
[program:homecare-worker]
process_name=%(program_name)s_%(process_num)02d
command=$SANDBOX/installed/entrypoint worker $SANDBOX/app
user=www-data
numprocs=1
autostart=true
autorestart=true
CONFIG
  printf '* * * * * %s scheduler %s >> /var/log/homecare-scheduler.log 2>&1\n' "$SANDBOX/installed/entrypoint" "$SANDBOX/app" > "$SANDBOX/root-cron"
}
pass_count=0
pass() { pass_count=$((pass_count + 1)); }
fail() { printf 'Supervisor runtime smoke failure: %s\n' "$*" >&2; cat "$SANDBOX/output" "$SANDBOX/actions" >&2; exit 1; }
control() { bash "$SANDBOX/installed/control" "$1" "$SANDBOX/app" > "$SANDBOX/output" 2>&1; }
expect_failure() { if control "$1"; then fail "$2"; fi; }
assert_paused() { [[ -f "$SANDBOX/state/paused" ]] || fail 'Persistent marker was lost.'; }
assert_not_executed() { if grep -q '^executed ' "$SANDBOX/actions"; then fail 'Paused admission ran PHP work.'; fi; }

reset_fixture
control preflight || fail 'Valid wrapped topology rejected.'
pass

control pause || fail 'Live worker did not drain.'
assert_paused
grep -qx 'signal TERM' "$SANDBOX/actions" || fail 'Live PHP worker was not gracefully signalled.'
signal_line="$(grep -n '^signal TERM$' "$SANDBOX/actions" | cut -d: -f1)"
publish_line="$(grep -n "^sync $SANDBOX/state$" "$SANDBOX/actions" | head -1 | cut -d: -f1)"
[[ "$publish_line" -lt "$signal_line" ]] || fail 'Worker was signalled before durable admission pause.'
pass

: > "$SANDBOX/actions"
before="$(cat "$SANDBOX/state/paused")"
control pause || fail 'Repeated paused wrapper drain was not idempotent.'
[[ "$(cat "$SANDBOX/state/paused")" == "$before" ]] || fail 'Repeated pause replaced its topology.'
if grep -q '^signal ' "$SANDBOX/actions"; then fail 'Idle paused wrapper was signalled unnecessarily.'; fi
pass

control resume || fail 'Previously paused wrapper did not resume.'
[[ ! -e "$SANDBOX/state/paused" ]] || fail 'Successful resume left admission paused.'
pass

reset_fixture
printf 'transition\n' > "$SANDBOX/worker"
control pause || fail 'Admitted wrapper was not retried through its PHP exec transition.'
assert_paused
transition_line="$(grep -n '^admitted-wrapper-transition$' "$SANDBOX/actions" | cut -d: -f1)"
signal_line="$(grep -n '^signal TERM$' "$SANDBOX/actions" | cut -d: -f1)"
[[ -n "$transition_line" && "$transition_line" -lt "$signal_line" ]] || fail 'Worker was signalled before verified PHP transition.'
pass

reset_fixture
printf 'waiting-lease\n' > "$SANDBOX/worker"
expect_failure pause 'Unresolved admitted wrapper was treated as safely idle.'
assert_paused
if grep -q '^signal ' "$SANDBOX/actions"; then fail 'Unverified bash wrapper was signalled.'; fi
if grep -q '^flock -x -w 1 8$' "$SANDBOX/actions"; then fail 'Unresolved admitted wrapper was accepted for the drain.'; fi
pass

reset_fixture
export MOCK_DRAIN_TIMEOUT=1
expect_failure pause 'Drain timeout unexpectedly succeeded.'
assert_paused
unset MOCK_DRAIN_TIMEOUT
control pause || fail 'Drain retry after timeout failed.'
pass

reset_fixture
export MOCK_SIGNAL_FAILURE=1
expect_failure pause 'Supervisor signal failure unexpectedly succeeded.'
assert_paused
pass

reset_fixture
control pause || fail 'Pause before failed resume failed.'
printf 'stopped\n' > "$SANDBOX/worker"
expect_failure resume 'Unhealthy worker unexpectedly resumed.'
assert_paused
printf 'auto\n' > "$SANDBOX/worker"
control resume || fail 'Resume retry failed.'
pass

reset_fixture
control pause || fail 'Pause before queue validation failed.'
export MOCK_BAD_QUEUE=1
expect_failure resume 'Unsafe cached retry/timeout settings opened admission.'
assert_paused
pass

reset_fixture
control pause || fail 'Pause before configuration drift failed.'
printf '\nprocess=other-worker\n' >> "$SANDBOX/state/paused"
expect_failure resume 'Changed saved topology was accepted.'
assert_paused
pass

reset_fixture
control pause || fail 'Pause before same-path config change failed.'
printf '\n# changed in place\n' >> "$HOMECARE_RUNTIME_CONFIG"
expect_failure resume 'Changed configuration contents at the same path were accepted.'
assert_paused
pass

reset_fixture
control pause || fail 'Pause before replaced lock identity failed.'
export MOCK_LOCK_REPLACED=1
expect_failure resume 'Replaced persistent lock identity was accepted.'
assert_paused
pass

reset_fixture
mkdir "$SANDBOX/state/paused"
expect_failure preflight 'Directory masquerading as pause marker was accepted.'
bash "$SANDBOX/installed/entrypoint" scheduler "$SANDBOX/app" > "$SANDBOX/output" 2>&1 || fail 'Special pause marker did not close scheduler admission.'
assert_not_executed
rmdir "$SANDBOX/state/paused"
pass

for kind in stopped wrong-exe wrong-args no-lease; do
  reset_fixture
  printf '%s\n' "$kind" > "$SANDBOX/worker"
  expect_failure preflight "Invalid supervised worker $kind was accepted."
  pass
done

for setting in MOCK_BAD_PERMISSIONS MOCK_OPERATION_BUSY MOCK_SUPERVISOR_FAILURE MOCK_NO_PCNTL; do
  reset_fixture
  export "$setting=1"
  expect_failure preflight "$setting was accepted."
  pass
done

reset_fixture
export MOCK_DUPLICATE_STATE=active
expect_failure preflight 'Duplicate active systemd worker was accepted.'
pass

reset_fixture
export MOCK_SERVICE_STATE=inactive
expect_failure preflight 'Stopped cron/Supervisor service was accepted.'
pass

reset_fixture
printf '* * * * * php %s/artisan schedule:run\n' "$SANDBOX/app" >> "$SANDBOX/root-cron"
expect_failure preflight 'Unwrapped duplicate cron was accepted.'
pass

reset_fixture
control pause || fail 'Pause before wrapper restart failed.'
: > "$SANDBOX/actions"
bash "$SANDBOX/installed/entrypoint" scheduler "$SANDBOX/app" > "$SANDBOX/output" 2>&1 || fail 'Paused scheduler did not skip successfully.'
assert_not_executed
pass

reset_fixture
export MOCK_GATE_RACE=1
bash "$SANDBOX/installed/entrypoint" scheduler "$SANDBOX/app" > "$SANDBOX/output" 2>&1 || fail 'Concurrent scheduler pause did not skip.'
assert_not_executed
if grep -q '^flock -s 8$' "$SANDBOX/actions"; then fail 'Paused admission acquired a run lease.'; fi
pass

reset_fixture
printf 'paused\n' > "$SANDBOX/state/paused"
bash "$SANDBOX/installed/entrypoint" worker "$SANDBOX/app" > "$SANDBOX/output" 2>&1 &
wrapper_pid=$!
sleep 2
kill -0 "$wrapper_pid" || fail 'Paused Supervisor wrapper did not stay running.'
assert_not_executed
if grep -q '^flock -s 8$' "$SANDBOX/actions"; then fail 'Waiting worker retained a run lease.'; fi
kill -TERM "$wrapper_pid"
wait "$wrapper_pid" 2>/dev/null || true
pass

reset_fixture
if bash "$SANDBOX/installed/entrypoint" worker "$SANDBOX/app" > "$SANDBOX/output" 2>&1; then
  grep -q '^executed .*queue:work database --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600$' "$SANDBOX/actions" \
    || fail 'Worker arguments changed.'
  grep -qx 'umask 0007' "$SANDBOX/actions" || fail 'Worker did not use its shared-group private umask.'
else
  fail 'Admitted worker did not inherit FD 8 or release FD 7.'
fi
pass

reset_fixture
(umask 0022; bash "$SANDBOX/installed/entrypoint" scheduler "$SANDBOX/app") > "$SANDBOX/output" 2>&1 || fail 'Admitted scheduler did not retain its lease.'
grep -q '^executed .* schedule:run$' "$SANDBOX/actions" || fail 'Scheduler was not foreground schedule:run.'
grep -qx 'umask 0022' "$SANDBOX/actions" || fail 'Root scheduler changed its inherited umask for unrelated application files.'
pass

reset_fixture
export MOCK_BAD_QUEUE=1
if bash "$SANDBOX/installed/entrypoint" worker "$SANDBOX/app" > "$SANDBOX/output" 2>&1; then fail 'Unsafe queue settings started PHP worker.'; fi
assert_not_executed
pass

printf 'Supervisor runtime smoke passed (%s isolated cases).\n' "$pass_count"
printf 'Service processes, POSIX ownership, and flock are mocked; verify real Linux locking and cross-user access before production rollout.\n'
