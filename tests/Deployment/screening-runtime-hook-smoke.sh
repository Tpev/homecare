#!/usr/bin/env bash
# Isolated fake systemd/cgroup tests. Never invokes the host's service manager.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-runtime-smoke.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
[[ "$SANDBOX" == "$TEMP_ROOT"/homecare-runtime-smoke.* ]] || exit 1
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$TEMP_ROOT"/homecare-runtime-smoke.* && "$resolved" == "$SANDBOX" ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
mkdir -p "$SANDBOX/bin" "$SANDBOX/app" "$SANDBOX/state" "$SANDBOX/properties" "$SANDBOX/cgroups"
chmod 700 "$SANDBOX/state"
touch "$SANDBOX/app/artisan" "$SANDBOX/cgroups/cgroup.controllers"
HOOK="$PROJECT_DIR/docs/deployment/screening-runtime-control.example.sh"
export MOCK_ROOT="$SANDBOX"

cat > "$SANDBOX/bin/systemctl" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
action="$1"
unit="${@: -1}"
property_dir="$MOCK_ROOT/properties/$unit"
if [[ "$action" == show ]]; then
  property="${2#--property=}"
  if [[ "$property" == *,* ]]; then
    IFS=, read -ra names <<< "$property"
    for name in "${names[@]}"; do
      value=''
      if [[ -f "$property_dir/$name" ]]; then IFS= read -r value < "$property_dir/$name" || true; fi
      printf '%s=%s\n' "$name" "$value"
    done
    exit
  fi
  if [[ "$unit" == care-scheduler.service && "$property" == ActiveState && "${MOCK_SCHEDULER_DRAIN:-0}" == 1 && "$(cat "$MOCK_ROOT/properties/care-scheduler.timer/ActiveState")" == inactive ]]; then
    printf 'inactive\n' > "$property_dir/ActiveState"
    printf '0\n' > "$property_dir/MainPID"
    printf '0\n' > "$property_dir/ControlPID"
    printf 'populated 0\n' > "$MOCK_ROOT/cgroups/$unit/cgroup.events"
    printf 'natural-drain %s\n' "$unit" >> "$MOCK_ROOT/actions"
  fi
  IFS= read -r value < "$property_dir/$property" || true
  printf '%s\n' "$value"
  exit
fi
printf '%s %s\n' "$action" "$unit" >> "$MOCK_ROOT/actions"
if [[ "$action" == stop ]]; then
  [[ "${MOCK_FAIL_STOP:-}" != "$unit" ]] || exit 1
  if [[ "${MOCK_REMAIN_WRITER:-}" == "$unit" ]]; then
    printf 'deactivating\n' > "$property_dir/ActiveState"
  else
    printf 'inactive\n' > "$property_dir/ActiveState"
    printf '0\n' > "$property_dir/MainPID"
    printf '0\n' > "$property_dir/ControlPID"
    printf 'populated 0\n' > "$MOCK_ROOT/cgroups/$unit/cgroup.events"
  fi
elif [[ "$action" == start ]]; then
  [[ "${MOCK_FAIL_START:-}" != "$unit" ]] || exit 1
  printf 'active\n' > "$property_dir/ActiveState"
  printf '321\n' > "$property_dir/MainPID"
  printf 'populated 1\n' > "$MOCK_ROOT/cgroups/$unit/cgroup.events"
else
  exit 2
fi
MOCK
cat > "$SANDBOX/bin/sudo" <<'MOCK'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$1" == systemctl ]] || exit 2
exec "$MOCK_ROOT/bin/systemctl" "${@:2}"
MOCK
# The hook's lock invocation is checked here; real flock concurrency is an OS concern.
cat > "$SANDBOX/bin/flock" <<'MOCK'
#!/usr/bin/env bash
[[ "$1" == -n && "$2" == 9 ]]
MOCK
cat > "$SANDBOX/bin/php" <<'MOCK'
#!/usr/bin/env bash
[[ "${MOCK_NO_PCNTL:-0}" != 1 ]]
MOCK
chmod 755 "$SANDBOX/bin/"*
export PATH="$SANDBOX/bin:$PATH"
export HOMECARE_RUNTIME_CONFIG="$SANDBOX/runtime.conf"

set_property() { printf '%s\n' "$3" > "$SANDBOX/properties/$1/$2"; }
reset_fixture() {
  unset MOCK_FAIL_STOP MOCK_FAIL_START MOCK_REMAIN_WRITER MOCK_SCHEDULER_DRAIN MOCK_NO_PCNTL
  rm -f -- "$SANDBOX/state/pause-state" "$SANDBOX/actions"
  cat > "$HOMECARE_RUNTIME_CONFIG" <<CONFIG
SOLE_SCHEDULER_CONFIRMED=yes
ALL_QUEUE_CONSUMERS_CONFIRMED=yes
QUEUE_UNITS=(care-queue-one.service care-queue-two.service)
SCHEDULER_TIMER=care-scheduler.timer
SCHEDULER_SERVICE=care-scheduler.service
STATE_DIR='$SANDBOX/state'
DRAIN_TIMEOUT=1
CGROUP_ROOT='$SANDBOX/cgroups'
CONFIG
  local unit property
  for unit in care-queue-one.service care-queue-two.service care-scheduler.timer care-scheduler.service; do
    mkdir -p "$SANDBOX/properties/$unit" "$SANDBOX/cgroups/$unit"
    for property in ExecStartPre ExecStartPost ExecStop ExecStopPost TriggeredBy Triggers; do set_property "$unit" "$property" ''; done
    set_property "$unit" LoadState loaded
    set_property "$unit" NeedDaemonReload no
    set_property "$unit" RefuseManualStart no
    set_property "$unit" RefuseManualStop no
    set_property "$unit" ActiveState active
    set_property "$unit" WorkingDirectory "$SANDBOX/app"
    set_property "$unit" Type simple
    set_property "$unit" KillMode control-group
    set_property "$unit" SendSIGKILL no
    set_property "$unit" KillSignal 15
    set_property "$unit" TimeoutStopUSec infinity
    set_property "$unit" MainPID 123
    set_property "$unit" ControlPID 0
    set_property "$unit" ControlGroup "/$unit"
    set_property "$unit" ExecStart "{ path=$SANDBOX/bin/php ; argv[]=$SANDBOX/bin/php $SANDBOX/app/artisan queue:work --sleep=3 ; ignore_errors=no ; }"
    printf 'populated 1\n' > "$SANDBOX/cgroups/$unit/cgroup.events"
  done
  set_property care-queue-two.service ActiveState inactive
  set_property care-queue-two.service MainPID 0
  printf 'populated 0\n' > "$SANDBOX/cgroups/care-queue-two.service/cgroup.events"
  set_property care-scheduler.timer Triggers care-scheduler.service
  set_property care-scheduler.service TriggeredBy care-scheduler.timer
  set_property care-scheduler.service Type oneshot
  set_property care-scheduler.service RemainAfterExit no
  set_property care-scheduler.service Restart no
  set_property care-scheduler.service TimeoutStartUSec infinity
  set_property care-scheduler.service ExecStart "{ path=$SANDBOX/bin/php ; argv[]=$SANDBOX/bin/php $SANDBOX/app/artisan schedule:run ; ignore_errors=no ; }"
  set_property care-scheduler.service ActiveState inactive
  set_property care-scheduler.service MainPID 0
  printf 'populated 0\n' > "$SANDBOX/cgroups/care-scheduler.service/cgroup.events"
}

CHECKS=0
ok() { CHECKS=$((CHECKS + 1)); }
run_hook() { bash "$HOOK" "$1" "$SANDBOX/app"; }
succeeds() { run_hook "$1" > "$SANDBOX/result" 2>&1 || { cat "$SANDBOX/result" >&2; exit 1; }; }
fails() { if run_hook "$1" > "$SANDBOX/result" 2>&1; then printf 'Expected %s failure.\n' "$1" >&2; exit 1; fi; }

reset_fixture
succeeds preflight
[[ ! -f "$SANDBOX/actions" && ! -f "$SANDBOX/state/pause-state" ]]; ok
succeeds pause
[[ "$(head -n 1 "$SANDBOX/actions")" == 'stop care-scheduler.timer' ]]
! grep -q 'stop care-scheduler.service' "$SANDBOX/actions"
grep -qx 'restart=care-queue-one.service' "$SANDBOX/state/pause-state"
! grep -qx 'restart=care-queue-two.service' "$SANDBOX/state/pause-state"
cp "$SANDBOX/state/pause-state" "$SANDBOX/original-state"
succeeds pause
cmp "$SANDBOX/original-state" "$SANDBOX/state/pause-state"; ok
succeeds resume
[[ ! -e "$SANDBOX/state/pause-state" ]]
[[ "$(tail -n 2 "$SANDBOX/actions")" == $'start care-queue-one.service\nstart care-scheduler.timer' ]]
! grep -q 'start care-queue-two.service' "$SANDBOX/actions"
succeeds resume; ok

reset_fixture
export MOCK_FAIL_STOP=care-queue-one.service
fails pause
[[ -f "$SANDBOX/state/pause-state" && "$(cat "$SANDBOX/properties/care-scheduler.timer/ActiveState")" == inactive ]]
unset MOCK_FAIL_STOP
succeeds resume
[[ "$(cat "$SANDBOX/properties/care-scheduler.timer/ActiveState")" == active ]]; ok

reset_fixture
succeeds pause
export MOCK_FAIL_START=care-queue-one.service
fails resume
[[ -f "$SANDBOX/state/pause-state" ]]
! grep -q 'start care-scheduler.timer' "$SANDBOX/actions"
unset MOCK_FAIL_START
succeeds resume; ok

reset_fixture
set_property care-scheduler.service ActiveState activating
set_property care-scheduler.service MainPID 456
export MOCK_SCHEDULER_DRAIN=1
succeeds pause
[[ "$(head -n 3 "$SANDBOX/actions")" == $'stop care-scheduler.timer\nnatural-drain care-scheduler.service\nstop care-queue-one.service' ]]; ok

reset_fixture
set_property care-scheduler.service ActiveState activating
set_property care-scheduler.service MainPID 456
fails pause
[[ "$(cat "$SANDBOX/actions")" == 'stop care-scheduler.timer' ]]
[[ -f "$SANDBOX/state/pause-state" ]]; ok

reset_fixture
export MOCK_REMAIN_WRITER=care-queue-one.service
fails pause
grep -q 'no force kill' "$SANDBOX/result"
[[ -f "$SANDBOX/state/pause-state" ]]
! grep -q 'kill' "$SANDBOX/actions"; ok

reset_fixture
set_property care-scheduler.service ControlPID 999
fails pause
! grep -q 'stop care-queue-one.service' "$SANDBOX/actions"; ok

reset_fixture
printf 'populated 1\n' > "$SANDBOX/cgroups/care-scheduler.service/cgroup.events"
fails pause
! grep -q 'stop care-queue-one.service' "$SANDBOX/actions"; ok

for bad in signal killmode sigkill timeout stopcommand releasepath extratrigger shellwrapper schedulerwork schedulerdeadline daemonreload; do
  reset_fixture
  case "$bad" in
    signal) set_property care-queue-one.service KillSignal 9 ;;
    killmode) set_property care-queue-one.service KillMode process ;;
    sigkill) set_property care-queue-one.service SendSIGKILL yes ;;
    timeout) set_property care-queue-one.service TimeoutStopUSec '1min 30s' ;;
    stopcommand) set_property care-queue-one.service ExecStop '/bin/kill -9 $MAINPID' ;;
    releasepath) set_property care-queue-one.service ExecStart '{ path=/usr/bin/php ; argv[]=/usr/bin/php /releases/old/artisan queue:work ; }' ;;
    extratrigger) set_property care-scheduler.service TriggeredBy 'care-scheduler.timer unexpected.timer' ;;
    shellwrapper) set_property care-queue-one.service ExecStart "{ path=/bin/bash ; argv[]=/bin/bash $SANDBOX/app/artisan queue:work ; }" ;;
    schedulerwork) set_property care-scheduler.service ExecStart "{ path=/usr/bin/php ; argv[]=/usr/bin/php $SANDBOX/app/artisan schedule:work ; }" ;;
    schedulerdeadline) set_property care-scheduler.service TimeoutStartUSec '1min 30s' ;;
    daemonreload) set_property care-queue-one.service NeedDaemonReload yes ;;
  esac
  fails preflight
  [[ ! -f "$SANDBOX/actions" ]]; ok
done

reset_fixture
printf 'SOLE_SCHEDULER_CONFIRMED=no\n' >> "$HOMECARE_RUNTIME_CONFIG"
fails preflight
[[ ! -f "$SANDBOX/actions" ]]; ok
reset_fixture
printf 'QUEUE_UNITS=()\n' >> "$HOMECARE_RUNTIME_CONFIG"
fails preflight; ok
reset_fixture
export MOCK_NO_PCNTL=1
fails preflight; ok
reset_fixture
succeeds pause
printf 'restart=unlisted.service\n' >> "$SANDBOX/state/pause-state"
fails resume; ok
reset_fixture
succeeds pause
printf 'SCHEDULER_TIMER=changed.timer\n' >> "$HOMECARE_RUNTIME_CONFIG"
fails resume
[[ -f "$SANDBOX/state/pause-state" ]]; ok
reset_fixture
mkdir -p "$SANDBOX/app/state"
printf "STATE_DIR='%s/app/state'\n" "$SANDBOX" >> "$HOMECARE_RUNTIME_CONFIG"
fails preflight; ok

printf 'Runtime hook smoke test passed (%s checks).\n' "$CHECKS"
