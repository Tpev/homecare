#!/usr/bin/env bash
# Fixed single-host Supervisor + root-cron adapter. No stop/kill escalation is used.
set -Eeuo pipefail
umask 077
fail() { printf 'Runtime control: %s\n' "$*" >&2; exit 1; }
log() { printf 'Runtime control: %s\n' "$*"; }
trusted_file() {
  local mode
  [[ -f "$1" && ! -L "$1" && "$(stat -c '%u' -- "$1")" == 0 ]] || fail "Untrusted file: $1"
  mode="$(stat -c '%a' -- "$1")"
  [[ "$mode" =~ ^[0-7]{3,4}$ && $((8#$mode & 022)) -eq 0 ]] || fail "Writable trusted file: $1"
}

ACTION="${1:-}"
APP_DIR="${2:-}"
[[ "$#" -eq 2 && "$ACTION" =~ ^(preflight|pause|resume)$ && "$APP_DIR" =~ ^/[a-zA-Z0-9_./-]+$ && -f "$APP_DIR/artisan" ]] \
  || fail 'Usage: runtime-control preflight|pause|resume /absolute/stable/app'
CONFIG="${HOMECARE_RUNTIME_CONFIG:-/etc/homecare/runtime.conf}"
[[ "$CONFIG" == /* ]] || fail 'Configuration must be an absolute path.'
trusted_file "$CONFIG"
source "$CONFIG"
[[ "${SUPERVISOR_AND_ROOT_CRON_CONFIRMED:-}" == yes ]] || fail 'Explicit complete Supervisor/root-cron inventory acknowledgement is required.'
for value in "${STATE_DIR:-}" "${ENTRYPOINT:-}" "${PHP_BIN:-}" "${QUEUE_CHECK:-}" "${SUPERVISOR_CONFIG:-}"; do
  [[ "$value" =~ ^/[a-zA-Z0-9_./+-]+$ && "$value" != *'/../'* ]] || fail 'Invalid configured path.'
done
[[ "${DRAIN_TIMEOUT:-}" =~ ^[1-9][0-9]*$ && -n "${DEPLOY_USER:-}" && -n "${WEB_GROUP:-}" && -x "$PHP_BIN" ]] \
  || fail 'Runtime identities, executable PHP, and a positive DRAIN_TIMEOUT are required.'
[[ "$(id -un)" == "$DEPLOY_USER" ]] || fail 'Run the controller as the configured deployment account.'
for path in "${BASH_SOURCE[0]}" "$ENTRYPOINT" "$QUEUE_CHECK" "$SUPERVISOR_CONFIG"; do trusted_file "$path"; done
[[ -d "$STATE_DIR" && ! -L "$STATE_DIR" && "$(stat -c '%U' -- "$STATE_DIR")" == "$DEPLOY_USER" \
  && "$(stat -c '%G' -- "$STATE_DIR")" == "$WEB_GROUP" && "$(stat -c '%a' -- "$STATE_DIR")" == 750 ]] \
  || fail 'State directory must be deployment-owned, shared-group, mode 0750.'
STATE_REAL="$(readlink -f -- "$STATE_DIR")"
APP_REAL="$(readlink -f -- "$APP_DIR")"
case "$STATE_REAL/" in "$APP_DIR/"*|"$APP_REAL/"*) fail 'Persistent runtime state must be outside application releases.' ;; esac
for lock in start.lock run.lock; do
  path="$STATE_DIR/$lock"
  [[ -f "$path" && ! -L "$path" && "$(stat -c '%U' -- "$path")" == "$DEPLOY_USER" \
    && "$(stat -c '%G' -- "$path")" == "$WEB_GROUP" && "$(stat -c '%a' -- "$path")" == 640 ]] \
    || fail 'Precreated lock files must be deployment-owned, shared-group, mode 0640.'
done
[[ ! -L "$STATE_DIR/operation.lock" && ! -L "$STATE_DIR/paused" ]] || fail 'Runtime state must not contain symbolic links.'
if [[ -e "$STATE_DIR/operation.lock" ]]; then
  [[ -f "$STATE_DIR/operation.lock" && -O "$STATE_DIR/operation.lock" && "$(stat -c '%a' -- "$STATE_DIR/operation.lock")" == 600 ]] \
    || fail 'Unsafe operation lock.'
fi
exec 9>"$STATE_DIR/operation.lock"
flock -n 9 || fail 'Another runtime-control operation is running.'
PROCESS=homecare-worker:homecare-worker_00
PHP_REAL="$(readlink -f -- "$PHP_BIN")"
RUN_REAL="$(readlink -f -- "$STATE_DIR/run.lock")"

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
validate_marker() {
  [[ -e "$STATE_DIR/paused" || -L "$STATE_DIR/paused" ]] || return 0
  [[ -f "$STATE_DIR/paused" && ! -L "$STATE_DIR/paused" \
    && "$(stat -c '%U' -- "$STATE_DIR/paused")" == "$DEPLOY_USER" \
    && "$(stat -c '%G' -- "$STATE_DIR/paused")" == "$WEB_GROUP" \
    && "$(stat -c '%a' -- "$STATE_DIR/paused")" == 640 ]] || fail 'Unsafe persistent pause marker.'
  [[ "$(cat "$STATE_DIR/paused")" == "$(topology)" ]] \
    || fail 'Paused topology differs from configuration; restore the original configuration before recovery.'
}

validate_configuration() {
  local cron line count=0 load active enabled service
  # A narrowly accepted direct program prevents accidentally signalling another topology.
  awk -v entry="$ENTRYPOINT worker $APP_DIR" '
    /^[[:space:]]*[;#]/ || /^[[:space:]]*$/ { next }
    /^\[/ { if ($0 != "[program:homecare-worker]") exit 1; section++; next }
    { p=index($0,"="); if (!p) exit 1; key=substr($0,1,p-1); val=substr($0,p+1);
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", key); gsub(/^[[:space:]]+|[[:space:]]+$/, "", val);
      if (++seen[key] != 1) exit 1; values[key]=val }
    END { if (section != 1 || values["command"] != entry || values["user"] != "www-data" || values["numprocs"] != "1" ||
      values["process_name"] != "%(program_name)s_%(process_num)02d" || values["autostart"] != "true" || values["autorestart"] != "true") exit 1 }
  ' "$SUPERVISOR_CONFIG" || fail 'Supervisor configuration does not match the fixed wrapped worker topology.'
  for service in cron.service supervisor.service; do
    [[ "$(systemctl show "$service" --property=ActiveState --value)" == active \
      && "$(systemctl show "$service" --property=UnitFileState --value)" == enabled ]] \
      || fail "$service must be active and enabled."
  done
  load="$(systemctl show homecare-worker.service --property=LoadState --value)" || fail 'Cannot inspect the duplicate systemd worker.'
  if [[ "$load" != not-found ]]; then
    [[ "$load" == loaded || "$load" == masked ]] || fail 'Unexpected duplicate systemd worker load state.'
    active="$(systemctl show homecare-worker.service --property=ActiveState --value)" || fail 'Cannot inspect the duplicate systemd worker.'
    enabled="$(systemctl show homecare-worker.service --property=UnitFileState --value)" || fail 'Cannot inspect the duplicate systemd worker.'
    [[ "$active" == inactive && ( "$enabled" == disabled || "$enabled" == masked ) ]] \
      || fail 'The duplicate homecare-worker.service must be inactive and disabled/masked.'
  fi
  cron="$(sudo -n crontab -l)" || fail 'Cannot inspect the root crontab.'
  while IFS= read -r line; do
    [[ "$line" =~ ^[[:space:]]*# || "$line" =~ ^[[:space:]]*$ ]] && continue
    if [[ "$line" == *schedule:run* || "$line" == *schedule:work* || "$line" == *"$ENTRYPOINT"* ]]; then
      [[ "$line" == "* * * * * $ENTRYPOINT scheduler $APP_DIR >> /var/log/homecare-scheduler.log 2>&1" ]] \
        || fail 'Root cron contains an unwrapped or unexpected scheduler entry.'
      count=$((count + 1))
    fi
  done <<< "$cron"
  [[ "$count" -eq 1 ]] || fail 'Exactly one canonical root scheduler wrapper is required.'
}

worker_kind() {
  local status pid after executable descriptor
  local -a arguments=()
  status="$(sudo -n supervisorctl status "$PROCESS")" || return 1
  [[ "$status" =~ ^homecare-worker:homecare-worker_00[[:space:]]+RUNNING[[:space:]]+pid[[:space:]]+([0-9]+), ]] || return 1
  pid="${BASH_REMATCH[1]}"
  [[ "$pid" =~ ^[1-9][0-9]*$ ]] || return 1
  executable="$(sudo -n readlink "/proc/$pid/exe")" || return 1
  mapfile -d '' -t arguments < <(sudo -n cat "/proc/$pid/cmdline")
  after="$(sudo -n supervisorctl pid "$PROCESS")" || return 1
  [[ "$after" == "$pid" ]] || return 1
  if [[ "$executable" == "$PHP_REAL" && "${#arguments[@]}" -eq 9 \
    && "${arguments[0]}" == "$PHP_BIN" && "${arguments[1]}" == "$APP_DIR/artisan" \
    && "${arguments[2]}" == queue:work && "${arguments[3]}" == database \
    && "${arguments[4]}" == --queue=default && "${arguments[5]}" == --sleep=1 \
    && "${arguments[6]}" == --tries=3 && "${arguments[7]}" == --timeout=120 && "${arguments[8]}" == --max-time=3600 ]]; then
    descriptor="$(sudo -n readlink "/proc/$pid/fd/8")" || return 1
    [[ "$descriptor" == "$RUN_REAL" ]] || return 1
    printf 'php\n'
  elif [[ -e "$STATE_DIR/paused" && ( "$executable" == /usr/bin/bash || "$executable" == /bin/bash ) \
    && "${#arguments[@]}" -eq 4 && "${arguments[1]}" == "$ENTRYPOINT" \
    && "${arguments[2]}" == worker && "${arguments[3]}" == "$APP_DIR" ]]; then
    # An admitted wrapper can still be bash between releasing the start gate
    # and exec PHP. Only a wrapper without FD 8 is actually waiting outside work.
    sudo -n test ! -e "/proc/$pid/fd/8" || return 1
    printf 'waiting\n'
  else
    return 1
  fi
}

verified_worker_kind() {
  local kind deadline=$((SECONDS + 3))
  while true; do
    if kind="$(worker_kind)"; then printf '%s\n' "$kind"; return 0; fi
    (( SECONDS < deadline )) || return 1
    sleep 1
  done
}

publish_pause() {
  local temporary
  exec 7<"$STATE_DIR/start.lock"
  flock -x -w "$DRAIN_TIMEOUT" 7 || fail 'Could not close the runtime admission gate.'
  if [[ ! -e "$STATE_DIR/paused" ]]; then
    temporary="$(mktemp "$STATE_DIR/.paused.XXXXXX")"
    topology > "$temporary"
    chgrp "$WEB_GROUP" "$temporary"
    chmod 640 "$temporary"
    sync -f "$temporary"
    mv -T -- "$temporary" "$STATE_DIR/paused"
    sync -f "$STATE_DIR"
  fi
  flock -u 7
  exec 7<&-
}

drain() {
  local kind
  kind="$(verified_worker_kind)" || fail 'Cannot verify the supervised worker and its execution lease; gate remains paused.'
  if [[ "$kind" == php ]]; then
    sudo -n supervisorctl signal TERM "$PROCESS" || fail 'Graceful worker signal failed; gate remains paused.'
  fi
  exec 8<"$STATE_DIR/run.lock"
  flock -x -w "$DRAIN_TIMEOUT" 8 || fail 'Foreground work did not drain in time; gate remains paused, no job was force-killed.'
  flock -u 8
  exec 8<&-
}

validate_configuration
validate_marker
case "$ACTION" in
  preflight)
    "$PHP_BIN" -r 'exit(extension_loaded("pcntl") ? 0 : 1);' || fail 'Worker PHP must support pcntl for graceful termination.'
    "$PHP_BIN" "$QUEUE_CHECK" "$APP_DIR/bootstrap/cache/config.php" 120 \
      || fail 'Cached queue configuration is unsafe.'
    verified_worker_kind >/dev/null || fail 'Worker command, supervision, or inherited run lease is not verified.'
    log 'Supervisor/root-cron topology and worker lease verified.'
    ;;
  pause)
    publish_pause
    drain
    log 'Admission paused; worker and foreground scheduler leases drained.'
    ;;
  resume)
    "$PHP_BIN" "$QUEUE_CHECK" "$APP_DIR/bootstrap/cache/config.php" 120 \
      || fail 'Cached queue configuration is unsafe; admission remains paused.'
    exec 7<"$STATE_DIR/start.lock"
    flock -x -w "$DRAIN_TIMEOUT" 7 || fail 'Could not open the runtime admission gate.'
    if [[ -e "$STATE_DIR/paused" ]]; then rm -- "$STATE_DIR/paused"; fi
    sync -f "$STATE_DIR"
    flock -u 7
    exec 7<&-
    deadline=$((SECONDS + DRAIN_TIMEOUT))
    while (( SECONDS < deadline )); do
      if [[ "$(worker_kind || true)" == php ]]; then
        log 'Worker execution lease verified; scheduler admission resumed.'
        exit 0
      fi
      sleep 1
    done
    publish_pause
    drain
    fail 'Worker did not resume; gate was restored and foreground work drained.'
    ;;
esac
