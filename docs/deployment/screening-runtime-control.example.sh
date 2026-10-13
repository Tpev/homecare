#!/usr/bin/env bash
# Install a reviewed copy outside release directories. See screening-runtime-control.md.
set -Eeuo pipefail
umask 077

fail() { printf 'Runtime control: %s\n' "$*" >&2; exit 1; }
log() { printf 'Runtime control: %s\n' "$*"; }

ACTION="${1:-}"
APP_DIR="${2:-}"
CONFIG="${HOMECARE_RUNTIME_CONFIG:-/etc/homecare/runtime.conf}"
[[ "$ACTION" =~ ^(preflight|pause|resume)$ && "$#" -eq 2 ]] \
  || fail 'Usage: runtime-control preflight|pause|resume /absolute/stable/app'
[[ "$APP_DIR" =~ ^/[a-zA-Z0-9_./-]+$ && -f "$APP_DIR/artisan" ]] \
  || fail 'APP_DIR must be an existing absolute stable application path without spaces.'
[[ "$CONFIG" == /* && -f "$CONFIG" && ! -L "$CONFIG" ]] \
  || fail 'Provide an absolute trusted, regular runtime configuration file.'

# This is an operator-owned shell configuration, never application/user input.
source "$CONFIG"
[[ "${SOLE_SCHEDULER_CONFIRMED:-}" == yes && "${ALL_QUEUE_CONSUMERS_CONFIRMED:-}" == yes ]] \
  || fail 'Explicit sole-scheduler and complete-consumer inventory acknowledgements are required.'
[[ "$(declare -p QUEUE_UNITS 2>/dev/null)" == 'declare -a '* && "${#QUEUE_UNITS[@]}" -gt 0 ]] \
  || fail 'QUEUE_UNITS must explicitly name every queue worker in a nonempty array.'
[[ "${DRAIN_TIMEOUT:-}" =~ ^[1-9][0-9]*$ ]] || fail 'DRAIN_TIMEOUT must be positive seconds.'
[[ "${STATE_DIR:-}" == /* && -d "$STATE_DIR" && ! -L "$STATE_DIR" ]] \
  || fail 'STATE_DIR must be an existing private directory outside release directories.'
for command in systemctl sudo readlink flock grep sed mv stat; do
  command -v "$command" >/dev/null || fail "Missing command: $command"
done
APP_REAL="$(readlink -f -- "$APP_DIR")"
STATE_REAL="$(readlink -f -- "$STATE_DIR")"
STATE_MODE="$(stat -c '%a' -- "$STATE_DIR")"
[[ -O "$STATE_DIR" && "$STATE_MODE" =~ ^[0-7]{3,4}$ && $((8#$STATE_MODE & 077)) -eq 0 ]] \
  || fail 'STATE_DIR must be owned by the calling deployment account with no group/other access.'
case "$STATE_REAL/" in
  "$APP_DIR/"*|"$APP_REAL/"*) fail 'STATE_DIR must be outside the stable app and release directory.' ;;
esac
CGROUP_ROOT="${CGROUP_ROOT:-/sys/fs/cgroup}"
[[ "$CGROUP_ROOT" == /* && -f "$CGROUP_ROOT/cgroup.controllers" ]] \
  || fail 'This example requires a readable cgroup v2 hierarchy.'
STATE_FILE="$STATE_DIR/pause-state"
[[ ! -L "$STATE_FILE" && ! -L "$STATE_DIR/lock" ]] || fail 'State files must not be symlinks.'
exec 9>"$STATE_DIR/lock"
flock -n 9 || fail 'Another runtime-control operation is running.'

declare -A SEEN_UNITS=()
for unit in "${QUEUE_UNITS[@]}" "${SCHEDULER_TIMER:-}" "${SCHEDULER_SERVICE:-}"; do
  [[ "$unit" =~ ^[a-zA-Z0-9_@.-]+\.(service|timer)$ && -z "${SEEN_UNITS[$unit]:-}" ]] \
    || fail 'Supply distinct actual systemd unit names.'
  SEEN_UNITS[$unit]=1
done
[[ "$SCHEDULER_TIMER" == *.timer && "$SCHEDULER_SERVICE" == *.service ]] \
  || fail 'The scheduler requires one timer and its oneshot service.'
for unit in "${QUEUE_UNITS[@]}"; do
  [[ "$unit" == *.service ]] || fail 'Queue consumers must be services.'
done

declare -A UNIT_PROPERTIES=()
inspect_unit() {
  local unit="$1" values line name
  values="$(systemctl show --property=LoadState,NeedDaemonReload,RefuseManualStart,RefuseManualStop,Triggers,TriggeredBy,Type,RemainAfterExit,Restart,TimeoutStartUSec,KillMode,SendSIGKILL,TimeoutStopUSec,KillSignal,ExecStart,WorkingDirectory,ExecStartPre,ExecStartPost,ExecStop,ExecStopPost "$unit")" \
    || fail "Cannot inspect $unit."
  while IFS= read -r line; do
    [[ "$line" == *=* ]] || fail "Invalid systemd property response for $unit."
    name="${line%%=*}"
    UNIT_PROPERTIES["$unit:$name"]="${line#*=}"
  done <<< "$values"
}
property() {
  if [[ -v "UNIT_PROPERTIES[$1:$2]" ]]; then
    printf '%s\n' "${UNIT_PROPERTIES[$1:$2]}"
  else
    systemctl show --property="$2" --value "$1"
  fi
}
expect() {
  local actual
  actual="$(property "$1" "$2")" || fail "Cannot inspect $1 $2."
  [[ "$actual" == "$3" ]] || fail "$1 requires $2=$3 (found $actual)."
}

check_command() {
  local unit="$1" command="$2" value arguments executable tail
  value="$(property "$unit" ExecStart)" || fail "Cannot inspect $unit ExecStart."
  [[ "$value" == *'argv[]='* ]] || fail "$unit has no inspectable direct ExecStart."
  arguments="${value#*argv[]=}"
  [[ "$arguments" != *'argv[]='* ]] || fail "$unit has multiple ExecStart commands."
  arguments="${arguments%% ;*}"
  executable="${arguments%% *}"
  [[ "$executable" =~ ^/[a-zA-Z0-9_./+-]*/php([0-9.]+)?$ ]] \
    || fail "$unit must directly execute PHP without a shell wrapper."
  tail="${arguments#"$executable "}"
  [[ "$tail" == "$APP_DIR/artisan $command" || "$tail" == "$APP_DIR/artisan $command "* ]] \
    || fail "$unit must run the stable APP_DIR/artisan $command."
  if [[ "$command" == queue:work ]]; then
    "$executable" -r 'exit(extension_loaded("pcntl") ? 0 : 1);' \
      || fail "$unit requires PHP pcntl for graceful SIGTERM handling."
  fi
  expect "$unit" WorkingDirectory "$APP_DIR"
  expect "$unit" ExecStartPre ''
  expect "$unit" ExecStartPost ''
  expect "$unit" ExecStop ''
  expect "$unit" ExecStopPost ''
}

validate_units() {
  local unit signal type
  for unit in "${QUEUE_UNITS[@]}" "$SCHEDULER_TIMER" "$SCHEDULER_SERVICE"; do
    inspect_unit "$unit"
    expect "$unit" LoadState loaded
    expect "$unit" NeedDaemonReload no
    [[ "$(property "$unit" RefuseManualStart)" != yes && "$(property "$unit" RefuseManualStop)" != yes ]] \
      || fail "$unit refuses manual lifecycle control."
  done
  expect "$SCHEDULER_TIMER" Triggers "$SCHEDULER_SERVICE"
  expect "$SCHEDULER_TIMER" TriggeredBy ''
  expect "$SCHEDULER_SERVICE" TriggeredBy "$SCHEDULER_TIMER"
  expect "$SCHEDULER_SERVICE" Type oneshot
  expect "$SCHEDULER_SERVICE" RemainAfterExit no
  expect "$SCHEDULER_SERVICE" Restart no
  expect "$SCHEDULER_SERVICE" TimeoutStartUSec infinity
  check_command "$SCHEDULER_SERVICE" schedule:run
  for unit in "${QUEUE_UNITS[@]}"; do
    type="$(property "$unit" Type)"
    [[ "$type" == simple || "$type" == exec ]] || fail "$unit must be a simple/exec queue:work service."
    expect "$unit" TriggeredBy ''
    expect "$unit" KillMode control-group
    expect "$unit" SendSIGKILL no
    expect "$unit" TimeoutStopUSec infinity
    signal="$(property "$unit" KillSignal)"
    [[ "$signal" == 15 || "$signal" == SIGTERM ]] || fail "$unit must use graceful SIGTERM."
    check_command "$unit" queue:work
  done
}

topology() {
  printf 'version=1\napp=%s\ntimer=%s\nscheduler=%s\n' "$APP_DIR" "$SCHEDULER_TIMER" "$SCHEDULER_SERVICE"
  printf 'queue=%s\n' "${QUEUE_UNITS[@]}"
}

RESTART_UNITS=()
load_state() {
  [[ -e "$STATE_FILE" ]] || return 0
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || fail 'Invalid pause-state file.'
  [[ "$(sed '/^restart=/d' "$STATE_FILE")" == "$(topology)" ]] \
    || fail 'Saved pause state differs from configured topology; restore the matching config to recover.'
  local line unit
  declare -A saved=()
  while IFS= read -r line; do
    [[ "$line" == restart=* ]] || continue
    unit="${line#restart=}"
    [[ -n "${SEEN_UNITS[$unit]:-}" && "$unit" != "$SCHEDULER_SERVICE" && -z "${saved[$unit]:-}" ]] \
      || fail 'Invalid saved restart unit.'
    saved[$unit]=1
    RESTART_UNITS+=("$unit")
  done < "$STATE_FILE"
}

capture_state() {
  [[ ! -e "$STATE_FILE" ]] || return 0
  local unit state temporary="$STATE_FILE.next.$$"
  for unit in "${QUEUE_UNITS[@]}" "$SCHEDULER_TIMER"; do
    state="$(property "$unit" ActiveState)"
    case "$state" in
      active|activating|reloading) RESTART_UNITS+=("$unit") ;;
      inactive|failed) ;;
      *) fail "$unit has ambiguous initial state $state; wait before deploying." ;;
    esac
  done
  # Publish the complete restart inventory before any mutation, including a partial pause failure.
  (set -o noclobber; { topology; for unit in "${RESTART_UNITS[@]}"; do printf 'restart=%s\n' "$unit"; done; } > "$temporary")
  mv -T -- "$temporary" "$STATE_FILE"
}

cgroup_empty() {
  local group directory
  group="$(property "$1" ControlGroup)" || return 1
  [[ -n "$group" ]] || return 0
  [[ "$group" == /* && "$group" != *'..'* ]] || return 1
  directory="$CGROUP_ROOT$group"
  [[ ! -d "$directory" ]] || grep -qx 'populated 0' "$directory/cgroup.events"
}

inactive() {
  local state
  state="$(property "$1" ActiveState)" || return 1
  [[ "$state" == inactive || "$state" == failed ]] || return 1
  [[ "$1" == *.timer ]] && return 0
  [[ "$(property "$1" MainPID)" == 0 && "$(property "$1" ControlPID)" == 0 ]] && cgroup_empty "$1"
}

wait_inactive() {
  local unit="$1" deadline=$((SECONDS + DRAIN_TIMEOUT))
  until inactive "$unit"; do
    (( SECONDS < deadline )) || fail "$unit did not drain within $DRAIN_TIMEOUT seconds; state retained, no force kill."
    sleep 1
  done
}

validate_units
load_state
case "$ACTION" in
  preflight)
    log 'Configured timer and queue topology validated.'
    ;;
  pause)
    capture_state
    sudo systemctl stop --no-block "$SCHEDULER_TIMER"
    wait_inactive "$SCHEDULER_TIMER"
    # Never stop the scheduler service: existing foreground scheduled work finishes naturally.
    wait_inactive "$SCHEDULER_SERVICE"
    for unit in "${QUEUE_UNITS[@]}"; do sudo systemctl stop --no-block "$unit"; done
    for unit in "${QUEUE_UNITS[@]}"; do wait_inactive "$unit"; done
    inactive "$SCHEDULER_TIMER" && inactive "$SCHEDULER_SERVICE" \
      || fail 'Scheduler restarted during the drain; topology is not exclusive.'
    log 'Scheduler and queue writers drained; recovery state retained.'
    ;;
  resume)
    if [[ ! -e "$STATE_FILE" ]]; then log 'No paused runtime state to restore.'; exit 0; fi
    # Capture writes queues first and timer last. Keep this ordering even if state was edited.
    for unit in "${QUEUE_UNITS[@]}" "$SCHEDULER_TIMER"; do
      for recorded in "${RESTART_UNITS[@]}"; do
        [[ "$unit" == "$recorded" ]] || continue
        sudo systemctl start "$unit"
        [[ "$(property "$unit" ActiveState)" == active ]] || fail "$unit failed to resume; state retained."
        if [[ "$unit" == *.service ]]; then
          [[ "$(property "$unit" MainPID)" =~ ^[1-9][0-9]*$ ]] || fail "$unit has no live worker; state retained."
        fi
      done
    done
    rm -- "$STATE_FILE"
    log 'Previously active workers and timer restored; pause state cleared.'
    ;;
esac
