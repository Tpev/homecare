#!/usr/bin/env bash
# Installed root-owned outside application releases. Never remove or replace lock files.
set -Eeuo pipefail
fail() { printf 'Runtime entrypoint: %s\n' "$*" >&2; exit 1; }
trusted_file() {
  local mode
  [[ -f "$1" && ! -L "$1" && "$(stat -c '%u' -- "$1")" == 0 ]] || fail "Untrusted file: $1"
  mode="$(stat -c '%a' -- "$1")"
  [[ "$mode" =~ ^[0-7]{3,4}$ && $((8#$mode & 022)) -eq 0 ]] || fail "Writable trusted file: $1"
}

ACTION="${1:-}"
APP_DIR="${2:-}"
[[ "$#" -eq 2 && "$ACTION" =~ ^(worker|scheduler)$ && "$APP_DIR" =~ ^/[a-zA-Z0-9_./-]+$ && -f "$APP_DIR/artisan" ]] \
  || fail 'Usage: runtime-entrypoint worker|scheduler /absolute/stable/app'
# The root scheduler retains its existing umask for unrelated application files.
# Screening evidence sets its own private modes; the www-data worker shares its group.
if [[ "$ACTION" == worker ]]; then umask 0007; fi
CONFIG="${HOMECARE_RUNTIME_CONFIG:-/etc/homecare/runtime.conf}"
[[ "$CONFIG" == /* ]] || fail 'Configuration must be an absolute path.'
trusted_file "$CONFIG"
# This is an operator-owned configuration, never application or request input.
source "$CONFIG"
[[ "${SUPERVISOR_AND_ROOT_CRON_CONFIRMED:-}" == yes ]] || fail 'The complete Supervisor/root-cron inventory must be confirmed.'
for value in "${STATE_DIR:-}" "${ENTRYPOINT:-}" "${PHP_BIN:-}" "${QUEUE_CHECK:-}"; do
  [[ "$value" =~ ^/[a-zA-Z0-9_./+-]+$ && "$value" != *'/../'* ]] || fail 'Invalid configured path.'
done
[[ -n "${DEPLOY_USER:-}" && -n "${WEB_GROUP:-}" && -x "$PHP_BIN" ]] || fail 'Runtime identities and PHP are required.'
trusted_file "$ENTRYPOINT"
trusted_file "$QUEUE_CHECK"
[[ "$ENTRYPOINT" -ef "${BASH_SOURCE[0]}" ]] || fail 'Configured entrypoint differs from the running wrapper.'
[[ -d "$STATE_DIR" && ! -L "$STATE_DIR" && "$(stat -c '%U' -- "$STATE_DIR")" == "$DEPLOY_USER" \
  && "$(stat -c '%G' -- "$STATE_DIR")" == "$WEB_GROUP" && "$(stat -c '%a' -- "$STATE_DIR")" == 750 ]] \
  || fail 'State directory must be deployment-owned, shared-group, mode 0750.'
for lock in start.lock run.lock; do
  path="$STATE_DIR/$lock"
  [[ -f "$path" && ! -L "$path" && "$(stat -c '%U' -- "$path")" == "$DEPLOY_USER" \
    && "$(stat -c '%G' -- "$path")" == "$WEB_GROUP" && "$(stat -c '%a' -- "$path")" == 640 ]] \
    || fail 'Precreated lock files must be deployment-owned, shared-group, mode 0640.'
done

# Linux flock permits both shared and exclusive locks on read-only descriptors.
# FD 7 closes the check/admission race; FD 8 survives exec and foreground children.
exec 7<"$STATE_DIR/start.lock"
while true; do
  flock -s 7
  if [[ -e "$STATE_DIR/paused" || -L "$STATE_DIR/paused" ]]; then
    flock -u 7
    [[ "$ACTION" == worker ]] || exit 0
    sleep 1
    continue
  fi
  if [[ "$ACTION" == worker ]]; then
    "$PHP_BIN" "$QUEUE_CHECK" "$APP_DIR/bootstrap/cache/config.php" 120 \
      || fail 'Cached queue configuration is unsafe; worker was not started.'
  fi
  exec 8<"$STATE_DIR/run.lock"
  flock -s 8
  flock -u 7
  exec 7<&-
  cd "$APP_DIR"
  if [[ "$ACTION" == worker ]]; then
    exec "$PHP_BIN" "$APP_DIR/artisan" queue:work database --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600
  fi
  exec "$PHP_BIN" "$APP_DIR/artisan" schedule:run
done
