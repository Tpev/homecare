#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${HOMECARE_APP_DIR:-/var/www/homecare}"
DEPLOY_ROOT="${HOMECARE_DEPLOY_ROOT:-/var/www/homecare-deploy}"
RELEASES_DIR="$DEPLOY_ROOT/releases"
SHARED_DIR="$DEPLOY_ROOT/shared"
SHARED_ENV="$SHARED_DIR/.env"
SHARED_STORAGE="$SHARED_DIR/storage"
REPOSITORY_DIR="$DEPLOY_ROOT/repository.git"
PREVIOUS_LINK="$DEPLOY_ROOT/previous"
BRANCH="${HOMECARE_DEPLOY_BRANCH:-master}"
DEPLOY_USER="${HOMECARE_DEPLOY_USER:-ubuntu}"
WEB_GROUP="${HOMECARE_WEB_GROUP:-www-data}"
KEEP_RELEASES="${HOMECARE_KEEP_RELEASES:-5}"
PHP_BIN="${HOMECARE_PHP_BIN:-php}"
COMPOSER_BIN="${HOMECARE_COMPOSER_BIN:-composer}"
NPM_BIN="${HOMECARE_NPM_BIN:-npm}"
GO_BIN="${HOMECARE_GO_BIN:-/usr/local/go/bin/go}"
FPM_SERVICE="${HOMECARE_FPM_SERVICE:-php8.3-fpm}"
VOICE_AGENT_SERVICE="${HOMECARE_VOICE_AGENT_SERVICE:-homecare-voice-agent}"
CONTENT_MCP_SERVICE="${HOMECARE_CONTENT_MCP_SERVICE:-homecare-content-mcp}"
LOCK_FILE="${HOMECARE_DEPLOY_LOCK:-/tmp/homecare-deploy.lock}"
APP_HEALTH_HOST="${HOMECARE_APP_HEALTH_HOST:-carelolo.com}"
APP_HEALTH_URL="${HOMECARE_APP_HEALTH_URL:-https://carelolo.com/up}"
VOICE_HEALTH_URL="${HOMECARE_VOICE_HEALTH_URL:-http://127.0.0.1:8088/healthz}"
CONTENT_MCP_HEALTH_URL="${HOMECARE_CONTENT_MCP_HEALTH_URL:-http://127.0.0.1:8090/healthz}"
# This host-specific hook controls the real queue/scheduler topology. There is
# deliberately no guessed production worker unit or implicit queue:restart fallback.
RUNTIME_HOOK="${HOMECARE_RUNTIME_HOOK:-/usr/local/libexec/homecare-runtime-control}"
SCREENING_STAGE_ROOT="${HOMECARE_SCREENING_STAGE_ROOT:-/home/$DEPLOY_USER/.homecare-screening}"

MODE="${1:-deploy}"
SWITCHED=0
BOOTSTRAP_MOVED=0
OLD_RELEASE=""
NEW_RELEASE=""
LEGACY_RELEASE=""
DEPLOY_COMMIT=""
RUNTIME_PAUSED=0
FPM_STOPPED=0
SCREENING_FIRST_ROLLOUT=0
SCREENING_ACTIVATION_ATTEMPTED=0
DEPLOY_COMPLETED=0

log() {
  printf '%s\n' "$*"
}

die() {
  log "ERROR: $*"
  exit 1
}

usage() {
  cat <<'EOF'
Usage:
  ./deploy.sh             Build and atomically activate the latest master release.
  ./deploy.sh --rollback  Atomically return to the previously active release.
  ./deploy.sh --status    Show the current and previous releases without changing anything.
  ./deploy.sh --screening-check REV
                         Stage runtime files from Git REV and inspect server setup only.
  ./deploy.sh --screening-setup REV --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
                         Apply the reviewed runtime setup without deploying application code.
  ./deploy.sh --help      Show this help.

The first normal deployment converts /var/www/homecare from an in-place checkout
to a stable symlink automatically. Screening releases require a configured
runtime-control hook. The first screening cutover briefly drains PHP-FPM so
legacy access can be finalized safely. No Laravel maintenance mode is used.
EOF
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

screening_runtime_from_git() {
  local action="$1" revision="$2" commit driver_path expected_driver actual_driver
  local stage_root app_real deploy_real forbidden stage mode source entry
  local -a sources=(
    docs/deployment/screening-supervisor-setup.sh
    docs/deployment/screening-supervisor-runtime-control.sh
    docs/deployment/screening-runtime-entrypoint.sh
    scripts/deployment/check-screening-queue.php
  ) names=()

  for command_name in git readlink mktemp sha256sum stat sudo; do
    require_command "$command_name"
  done
  [[ -n "$revision" && "$revision" != -* ]] || die "Supply a locally available Git revision."
  [[ "$APP_DIR" == /* && "$DEPLOY_ROOT" == /* && -f "$APP_DIR/artisan" ]] \
    || die "Screening setup requires the existing application and absolute deployment paths."
  commit="$(git -C "$APP_DIR" rev-parse --verify --end-of-options "$revision^{commit}")" \
    || die "Git revision is unavailable. Fetch the deployment mirror first."
  [[ "$commit" =~ ^[0-9a-f]{40,64}$ ]] || die "Could not resolve one Git commit."
  driver_path="$(readlink -f -- "${BASH_SOURCE[0]}")"
  expected_driver="$(git -C "$APP_DIR" rev-parse --verify "$commit:deploy.sh")" \
    || die "The selected Git commit has no deployment driver."
  actual_driver="$(git -C "$APP_DIR" hash-object --no-filters -- "$driver_path")"
  [[ "$actual_driver" == "$expected_driver" ]] \
    || die "Running deploy.sh differs from the selected Git commit. Obtain the driver from that same commit."

  # Validate every source before creating staging files or invoking sudo. Git
  # objects, rather than the live or dirty worktree, are the delivery authority.
  for source in "${sources[@]}"; do
    entry="$(git -C "$APP_DIR" ls-tree "$commit" -- "$source")"
    [[ "$entry" == '100644 blob '* || "$entry" == '100755 blob '* ]] \
      || die "Git commit is missing a regular runtime file: $source"
    names+=("${source##*/}")
  done

  [[ "$SCREENING_STAGE_ROOT" == /* && ! -L "$SCREENING_STAGE_ROOT" ]] \
    || die "Screening staging must use an absolute directory, not a symbolic link."
  stage_root="$(readlink -m -- "$SCREENING_STAGE_ROOT")"
  app_real="$(readlink -f -- "$APP_DIR")"
  deploy_real="$(readlink -m -- "$DEPLOY_ROOT")"
  for forbidden in "$app_real" "$deploy_real"; do
    case "$stage_root/" in
      "$forbidden/"*) die "Screening staging must stay outside the application and deployment directories." ;;
    esac
  done
  if [[ ! -e "$stage_root" ]]; then
    (umask 077; mkdir -p -m 0700 -- "$stage_root")
  fi
  [[ -d "$stage_root" && ! -L "$stage_root" && -O "$stage_root" ]] \
    || die "Screening staging must be a directory owned by the calling account."
  mode="$(stat -c '%a' -- "$stage_root")"
  [[ "$mode" =~ ^[0-7]{3,4}$ && $((8#$mode & 077)) -eq 0 ]] \
    || die "Screening staging must have no group or other access (mode 0700)."
  stage="$(umask 077; mktemp -d "$stage_root/${commit:0:12}.XXXXXX")"
  for source in "${sources[@]}"; do
    (umask 077; git -C "$APP_DIR" show "$commit:$source" > "$stage/${source##*/}")
  done
  (umask 077; printf '%s\n' "$commit" > "$stage/REVISION")
  (cd "$stage"; umask 077; sha256sum -- "${names[@]}" > SHA256SUMS; sha256sum --check SHA256SUMS)
  bash -n "$stage/screening-supervisor-setup.sh"
  bash -n "$stage/screening-supervisor-runtime-control.sh"
  bash -n "$stage/screening-runtime-entrypoint.sh"
  log "Screening Git revision: $commit"
  log "Screening Git staging directory: $stage"
  if [[ "$action" == check ]]; then
    log "Inspecting server setup only; application code, configuration and services stay unchanged."
    sudo bash "$stage/screening-supervisor-setup.sh" --check "$stage"
  else
    sudo bash "$stage/screening-supervisor-setup.sh" --apply "$stage" \
      --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
  fi
}

resolved_path() {
  readlink -f -- "$1" 2>/dev/null || true
}

current_release() {
  if [[ -L "$APP_DIR" ]]; then
    resolved_path "$APP_DIR"
  elif [[ -d "$APP_DIR" ]]; then
    printf '%s\n' "$APP_DIR"
  fi
}

run_artisan() {
  local release="$1"
  shift
  (cd "$release" && "$PHP_BIN" artisan "$@")
}

is_screening_release() {
  [[ -f "$1/app/Console/Commands/ScreeningRollout.php" ]]
}

runtime_hook() {
  "$RUNTIME_HOOK" "$1" "$APP_DIR"
}

preflight_screening_runtime() {
  [[ "$RUNTIME_HOOK" == /* && -x "$RUNTIME_HOOK" ]] \
    || die "Screening deployment requires an executable HOMECARE_RUNTIME_HOOK (see docs/deployment/screening-runtime-control.md)."
  runtime_hook preflight
}

validate_screening_queue_config() {
  local release="$1"
  local checker="$release/scripts/deployment/check-screening-queue.php"
  [[ -f "$checker" ]] || die "Screening release is missing its queue configuration check."
  # The production Supervisor worker consumes database/default with timeout=120.
  # Inspect the warmed candidate cache, not the old live cache or just .env.
  "$PHP_BIN" "$checker" "$release/bootstrap/cache/config.php" 120 \
    || die "Screening queue configuration is unsafe; the release was not activated."
}

preflight_first_screening_cutover() {
  local signal kill_mode
  signal="$(systemctl show "$FPM_SERVICE" --property=KillSignal --value)"
  kill_mode="$(systemctl show "$FPM_SERVICE" --property=KillMode --value)"
  [[ "$signal" == SIGQUIT || "$signal" == 3 ]] \
    || die "$FPM_SERVICE must use KillSignal=SIGQUIT for the first screening drain."
  [[ "$kill_mode" == control-group || "$kill_mode" == mixed ]] \
    || die "$FPM_SERVICE must drain its complete process group."
  [[ "$(systemctl show "$FPM_SERVICE" --property=SendSIGKILL --value)" == no ]] \
    || die "$FPM_SERVICE must use SendSIGKILL=no to avoid killing in-flight approvals."
  [[ -z "$(systemctl show "$FPM_SERVICE" --property=ExecStop --value)" ]] \
    || die "$FPM_SERVICE has a custom ExecStop; configure a reviewed graceful stop before the first rollout."
  [[ -z "$(systemctl show "$FPM_SERVICE" --property=TriggeredBy --value)" ]] \
    || die "$FPM_SERVICE has an activation source; disable it before the first screening rollout."
}

pause_screening_runtime() {
  local fpm_cgroup
  # Set flags before side effects: a hook or stop can fail after partial success.
  RUNTIME_PAUSED=1
  log "Pausing scheduler starts and draining old queue workers..."
  runtime_hook pause
  if [[ "$SCREENING_FIRST_ROLLOUT" -eq 1 ]]; then
    FPM_STOPPED=1
    log "Briefly draining PHP-FPM to finalize legacy caregiver access..."
    sudo systemctl stop "$FPM_SERVICE"
    [[ "$(systemctl show "$FPM_SERVICE" --property=ActiveState --value)" == inactive \
      && "$(systemctl show "$FPM_SERVICE" --property=MainPID --value)" == 0 \
      && "$(systemctl show "$FPM_SERVICE" --property=ControlPID --value)" == 0 ]] \
      || die "PHP-FPM has not fully drained; legacy reconciliation was not run."
    fpm_cgroup="$(systemctl show "$FPM_SERVICE" --property=ControlGroup --value)"
    if [[ -n "$fpm_cgroup" ]]; then
      [[ "$fpm_cgroup" == /* && "$fpm_cgroup" != *'..'* \
        && -f "/sys/fs/cgroup$fpm_cgroup/cgroup.events" ]] \
        || die "Cannot verify PHP-FPM's remaining process group is empty."
      grep -qx 'populated 0' "/sys/fs/cgroup$fpm_cgroup/cgroup.events" \
        || die "PHP-FPM still has live child processes; legacy reconciliation was not run."
    fi
    run_artisan "$NEW_RELEASE" screening:rollout --prepare --no-interaction
  fi
}

prepare_screening_cutover() {
  local state
  is_screening_release "$NEW_RELEASE" || return 0
  state="$(run_artisan "$NEW_RELEASE" screening:rollout --status --no-interaction)"
  case "$state" in
    pending)
      SCREENING_FIRST_ROLLOUT=1
      preflight_first_screening_cutover
      ;;
    active) ;;
    *) die "Cannot determine the durable screening rollout state; refusing activation." ;;
  esac
  pause_screening_runtime
}

rollback_target_is_safe() {
  local state
  is_screening_release "$OLD_RELEASE" && return 0
  is_screening_release "$NEW_RELEASE" || return 0
  # A command can commit and then fail. Read the DB again; unknown state must
  # never restore an old release that bypasses screening decisions/new jobs.
  state="$(run_artisan "$NEW_RELEASE" screening:rollout --status --no-interaction 2>/dev/null)" || return 1
  [[ "$state" == pending && "$SCREENING_ACTIVATION_ATTEMPTED" -eq 0 ]]
}

atomic_symlink() {
  local target="$1"
  local link="$2"
  local temporary="${link}.next.$$"

  sudo rm -f -- "$temporary"
  sudo ln -s -- "$target" "$temporary"
  sudo mv -Tf -- "$temporary" "$link"
}

atomic_exchange() {
  local first="$1"
  local second="$2"

  sudo python3 - "$first" "$second" <<'PY'
import ctypes
import os
import sys

at_fdcwd = -100
rename_exchange = 2
libc = ctypes.CDLL(None, use_errno=True)
result = libc.renameat2(
    at_fdcwd,
    os.fsencode(sys.argv[1]),
    at_fdcwd,
    os.fsencode(sys.argv[2]),
    rename_exchange,
)
if result != 0:
    error = ctypes.get_errno()
    raise OSError(error, os.strerror(error), f"{sys.argv[1]} <-> {sys.argv[2]}")
PY
}

set_previous_release() {
  local target="$1"
  local temporary="${PREVIOUS_LINK}.next.$$"

  sudo rm -f -- "$temporary"
  sudo ln -s -- "$target" "$temporary"
  sudo mv -Tf -- "$temporary" "$PREVIOUS_LINK"
}

app_is_healthy() {
  curl --fail --silent --show-error \
    --connect-timeout 3 \
    --max-time 10 \
    --resolve "${APP_HEALTH_HOST}:443:127.0.0.1" \
    "$APP_HEALTH_URL" >/dev/null
}

voice_is_healthy() {
  curl --fail --silent --show-error \
    --connect-timeout 3 \
    --max-time 10 \
    "$VOICE_HEALTH_URL" >/dev/null
}

content_mcp_service_is_installed() {
  systemctl cat "$CONTENT_MCP_SERVICE" >/dev/null 2>&1
}

content_mcp_is_healthy() {
  curl --fail --silent --show-error \
    --connect-timeout 3 \
    --max-time 10 \
    "$CONTENT_MCP_HEALTH_URL" >/dev/null
}

wait_for_health() {
  local check="$1"
  local label="$2"
  local attempt

  for attempt in {1..20}; do
    if "$check"; then
      log "$label health check passed."
      return 0
    fi
    sleep 1
  done

  log "$label health check failed after 20 attempts."
  return 1
}

restart_runtime_for_active_release() {
  if [[ "$FPM_STOPPED" -eq 1 || "$(systemctl show "$FPM_SERVICE" --property=ActiveState --value)" == inactive ]]; then
    log "Starting PHP-FPM against the active release..."
    sudo systemctl start "$FPM_SERVICE"
    FPM_STOPPED=0
  else
    log "Gracefully reloading PHP-FPM..."
    sudo systemctl reload "$FPM_SERVICE"
  fi

  if [[ "$RUNTIME_PAUSED" -eq 1 ]]; then
    log "Starting drained queue workers and resuming the scheduler..."
    runtime_hook resume
    RUNTIME_PAUSED=0
  elif ! is_screening_release "$APP_DIR"; then
    run_artisan "$APP_DIR" queue:restart
  fi

  log "Restarting voice-agent..."
  sudo systemctl restart "$VOICE_AGENT_SERVICE"

  if content_mcp_service_is_installed; then
    log "Restarting the additive Content MCP service..."
    sudo systemctl restart "$CONTENT_MCP_SERVICE" \
      || log "Content MCP restart failed; the live Laravel release remains active. Inspect $CONTENT_MCP_SERVICE."
  else
    log "Content MCP systemd unit is not installed yet; skipping its optional restart."
  fi
}

rollback_after_failure() {
  local exit_code="$1"
  local actual_release activation_state
  trap - ERR EXIT
  [[ "$exit_code" -ne 0 ]] || return 0

  log "Deployment failed. The active release will be preserved."

  # A rename may have succeeded immediately before an interruption or a failed
  # wrapper returned. Inspect reality rather than only the post-rename flag.
  actual_release="$(current_release)"
  if [[ "$DEPLOY_COMPLETED" -eq 0 && -n "$NEW_RELEASE" && "$actual_release" == "$NEW_RELEASE" && -n "$OLD_RELEASE" ]]; then
    SWITCHED=1
    BOOTSTRAP_MOVED=0
  fi

  if [[ "$SWITCHED" -eq 1 && -n "$OLD_RELEASE" && -d "$OLD_RELEASE" ]]; then
    if rollback_target_is_safe; then
      # New producers may already have restarted consumers. Drain those before
      # switching back, just as on a forward deployment.
      if is_screening_release "$APP_DIR" && [[ "$RUNTIME_PAUSED" -eq 0 ]]; then
        RUNTIME_PAUSED=1
        if ! runtime_hook pause; then
          log "Cannot drain the current runtime; retaining the active release for recovery."
          SWITCHED=0
        fi
      fi
      if [[ "$SWITCHED" -eq 1 ]]; then
        log "Rolling the application symlink back to $OLD_RELEASE..."
        if atomic_symlink "$OLD_RELEASE" "$APP_DIR"; then
          log "Rollback selected. Database migrations were not reversed."
        else
          log "Automatic rollback failed; inspect the application symlink before recovery."
        fi
      fi
    else
      log "Screening activation is committed or uncertain. Refusing rollback to a pre-screening release."
      log "Keeping the screening release active; repair it or deploy a screening-compatible release."
    fi
  elif [[ "$BOOTSTRAP_MOVED" -eq 1 && -n "$LEGACY_RELEASE" ]]; then
    log "Restoring the bootstrap storage pointer; the original application stayed live."
    atomic_symlink "$APP_DIR/storage" "$SHARED_STORAGE" || true
    if [[ -L "$LEGACY_RELEASE" ]]; then
      sudo rm -f -- "$LEGACY_RELEASE" || true
    fi
  fi

  # A retry may return to an earlier screening release that was itself paused
  # before activation. Compatibility does not prove this DB boundary is active.
  if [[ "$SCREENING_FIRST_ROLLOUT" -eq 1 ]] && is_screening_release "$APP_DIR"; then
    activation_state="$(run_artisan "$APP_DIR" screening:rollout --status --no-interaction 2>/dev/null)" || activation_state=unknown
    if [[ "$activation_state" != active ]]; then
      log "Screening activation is not confirmed. PHP-FPM and background writers remain paused."
      log "Repair the database/activation error, then rerun this deployment script; do not resume old code."
      log "Failed release retained for inspection: $NEW_RELEASE"
      exit "$exit_code"
    fi
  fi

  if [[ "$RUNTIME_PAUSED" -eq 1 || "$FPM_STOPPED" -eq 1 || "$SWITCHED" -eq 1 ]]; then
    # Explicit checks are necessary here: the EXIT handler must keep reporting
    # recovery failures instead of exiting on the first unsuccessful operation.
    if [[ -f "$APP_DIR/artisan" ]]; then
      if [[ "$FPM_STOPPED" -eq 1 ]]; then
        sudo systemctl start "$FPM_SERVICE" || log "PHP-FPM recovery failed; operator action required."
      else
        sudo systemctl reload "$FPM_SERVICE" || log "PHP-FPM reload failed; operator action required."
      fi
      if [[ "$RUNTIME_PAUSED" -eq 1 ]]; then
        runtime_hook resume || log "Runtime recovery failed; use the hook's resume action after repair."
      fi
      sudo systemctl restart "$VOICE_AGENT_SERVICE" || true
      if content_mcp_service_is_installed; then
        sudo systemctl restart "$CONTENT_MCP_SERVICE" || true
      fi
      wait_for_health app_is_healthy "Application recovery" || true
    else
      log "Application path is unavailable; runtime remains paused pending operator recovery."
    fi
  fi

  if [[ -n "$NEW_RELEASE" ]]; then
    log "Failed release retained for inspection: $NEW_RELEASE"
  fi

  exit "$exit_code"
}

show_status() {
  local current
  local previous

  current="$(current_release)"
  previous="$(resolved_path "$PREVIOUS_LINK")"

  if [[ -L "$APP_DIR" ]]; then
    log "Deployment layout: atomic releases"
  elif [[ -d "$APP_DIR" ]]; then
    log "Deployment layout: legacy in-place checkout (the next deploy will convert it)"
  else
    log "Deployment layout: application path is missing"
  fi

  log "Current release: ${current:-none}"
  log "Previous release: ${previous:-none}"

  if [[ -d "$RELEASES_DIR" ]]; then
    log "Available releases:"
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '  %f\n' | sort -r
  fi
}

prepare_shared_state() {
  local shared_storage_real
  local screening_private

  sudo mkdir -p "$RELEASES_DIR" "$SHARED_DIR"
  sudo chown "$DEPLOY_USER:$WEB_GROUP" "$DEPLOY_ROOT" "$RELEASES_DIR" "$SHARED_DIR"
  sudo chmod 775 "$DEPLOY_ROOT" "$RELEASES_DIR" "$SHARED_DIR"

  if [[ ! -f "$SHARED_ENV" ]]; then
    [[ -f "$APP_DIR/.env" ]] || die "Cannot initialize shared .env because $APP_DIR/.env is missing."
    log "Initializing the shared production environment file..."
    sudo install -m 640 -o "$DEPLOY_USER" -g "$WEB_GROUP" "$APP_DIR/.env" "$SHARED_ENV"
  fi

  if [[ -L "$SHARED_STORAGE" && ! -e "$SHARED_STORAGE" ]]; then
    die "Shared storage link is broken: $SHARED_STORAGE"
  fi

  if [[ ! -e "$SHARED_STORAGE" ]]; then
    [[ -d "$APP_DIR/storage" && ! -L "$APP_DIR/storage" ]] \
      || die "Cannot initialize shared storage from $APP_DIR/storage."

    log "Initializing a stable pointer to the current durable storage..."
    sudo ln -s -- "$APP_DIR/storage" "$SHARED_STORAGE"
  fi

  [[ "$APP_DIR/storage" -ef "$SHARED_STORAGE" ]] \
    || die "$APP_DIR/storage must point to $SHARED_STORAGE before an atomic deployment."

  shared_storage_real="$(resolved_path "$SHARED_STORAGE")"
  [[ -n "$shared_storage_real" && -d "$shared_storage_real" ]] \
    || die "Shared storage target is unavailable: $SHARED_STORAGE"

  sudo mkdir -p \
    "$SHARED_STORAGE/app/public" \
    "$SHARED_STORAGE/logs" \
    "$SHARED_STORAGE/framework/cache/data" \
    "$SHARED_STORAGE/framework/sessions" \
    "$SHARED_STORAGE/framework/testing" \
    "$SHARED_STORAGE/framework/views"
  sudo touch "$SHARED_STORAGE/logs/laravel.log"
  screening_private="$shared_storage_real/app/private/screening"
  [[ ! -L "$shared_storage_real/app" && ! -L "$shared_storage_real/app/private" && ! -L "$screening_private" ]] \
    || die "Private screening storage must not traverse a symlink."
  sudo mkdir -p -m 2770 "$screening_private"
  [[ -z "$(sudo find "$screening_private" -type l -print -quit)" ]] \
    || die "Private screening storage contains a symlink; inspect it before deploying."

  # Never run the generic public-storage modes over evidence, even briefly.
  sudo chown -R "$DEPLOY_USER:$WEB_GROUP" "$screening_private"
  sudo find "$screening_private" -type d -exec chmod 2770 {} +
  sudo find "$screening_private" -type f -exec chmod 660 {} +
  sudo find "$shared_storage_real" -path "$screening_private" -prune -o -exec chown -h "$DEPLOY_USER:$WEB_GROUP" {} +
  sudo find "$shared_storage_real" -path "$screening_private" -prune -o -type d -exec chmod 775 {} +
  sudo find "$shared_storage_real" -path "$screening_private" -prune -o -type f -exec chmod 664 {} +
}

prepare_repository() {
  local source_release="$1"
  local origin_url

  if [[ ! -d "$REPOSITORY_DIR" ]]; then
    origin_url="$(git -C "$source_release" remote get-url origin)"
    [[ -n "$origin_url" ]] || die "The current checkout has no origin remote."
    log "Initializing the local deployment repository..."
    git clone --mirror "$origin_url" "$REPOSITORY_DIR"
  fi

  log "Fetching the latest $BRANCH commit..."
  git --git-dir="$REPOSITORY_DIR" fetch --prune origin \
    "+refs/heads/$BRANCH:refs/heads/$BRANCH"
  DEPLOY_COMMIT="$(git --git-dir="$REPOSITORY_DIR" rev-parse "refs/heads/$BRANCH")"
}

create_release() {
  local current="$1"
  local release_name
  local compiled_views

  release_name="$(date -u +%Y%m%d%H%M%S)-${DEPLOY_COMMIT:0:12}-$$"
  NEW_RELEASE="$RELEASES_DIR/$release_name"
  [[ ! -e "$NEW_RELEASE" ]] || die "Release already exists: $NEW_RELEASE"

  log "Creating release $release_name..."
  git clone --shared --no-checkout "$REPOSITORY_DIR" "$NEW_RELEASE"
  git -C "$NEW_RELEASE" checkout --detach "$DEPLOY_COMMIT"

  if is_screening_release "$NEW_RELEASE"; then
    preflight_screening_runtime
  elif is_screening_release "$current"; then
    die "Refusing to deploy a pre-screening release over a screening-enabled application."
  fi

  case "$(resolved_path "$NEW_RELEASE")" in
    "$(resolved_path "$RELEASES_DIR")"/*) ;;
    *) die "Refusing to prepare a release outside $RELEASES_DIR." ;;
  esac

  rm -rf -- "$NEW_RELEASE/storage"
  ln -s "$SHARED_STORAGE" "$NEW_RELEASE/storage"
  ln -s "$SHARED_ENV" "$NEW_RELEASE/.env"

  compiled_views="$NEW_RELEASE/bootstrap/cache/views"
  mkdir -p "$compiled_views" "$NEW_RELEASE/voice-agent/bin"
  chmod 775 "$NEW_RELEASE/bootstrap/cache" "$compiled_views" "$NEW_RELEASE/voice-agent/bin"

  log "Installing PHP dependencies in the inactive release..."
  (cd "$NEW_RELEASE" && "$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader)

  log "Installing Node dependencies in the inactive release..."
  (cd "$NEW_RELEASE" && "$NPM_BIN" ci --no-audit --no-fund)

  log "Building frontend assets in the inactive release..."
  (cd "$NEW_RELEASE" && "$NPM_BIN" run build)

  log "Installing locked Content MCP dependencies in the inactive release..."
  (cd "$NEW_RELEASE/integrations/lolo-content-mcp" && "$NPM_BIN" ci --no-audit --no-fund)

  log "Building the hosted Content MCP in the inactive release..."
  (cd "$NEW_RELEASE/integrations/lolo-content-mcp" && "$NPM_BIN" run build)

  if [[ -d "$current/public/build/assets" && -d "$NEW_RELEASE/public/build/assets" ]]; then
    log "Retaining the previous hashed assets for in-flight browser requests..."
    cp -a -n "$current/public/build/assets/." "$NEW_RELEASE/public/build/assets/"
  fi

  if [[ ! -L "$NEW_RELEASE/public/storage" ]]; then
    ln -s ../storage/app/public "$NEW_RELEASE/public/storage"
  fi

  log "Building voice-agent in the inactive release..."
  (cd "$NEW_RELEASE/voice-agent" && "$GO_BIN" build -o bin/voice-agent ./cmd/server)
  chmod 775 "$NEW_RELEASE/voice-agent/bin/voice-agent"

  log "Running backward-compatible database migrations while the current release stays live..."
  run_artisan "$NEW_RELEASE" migrate --force

  log "Generating responsive caregiver photos against shared storage..."
  run_artisan "$NEW_RELEASE" caregiver-photos:generate-variants --no-interaction

  log "Building Laravel caches in the inactive release..."
  # view:cache starts by clearing compiled views. Persist a release-local path
  # so warming this release cannot delete templates still used by live workers.
  VIEW_COMPILED_PATH="$compiled_views" run_artisan "$NEW_RELEASE" config:cache
  if is_screening_release "$NEW_RELEASE"; then
    validate_screening_queue_config "$NEW_RELEASE"
  fi
  run_artisan "$NEW_RELEASE" route:cache
  run_artisan "$NEW_RELEASE" view:cache

  sudo chown -R "$DEPLOY_USER:$WEB_GROUP" "$NEW_RELEASE/bootstrap/cache" "$NEW_RELEASE/voice-agent/bin"
  sudo find "$NEW_RELEASE/bootstrap/cache" -type d -exec chmod 775 {} \;
  sudo find "$NEW_RELEASE/bootstrap/cache" -type f -exec chmod 664 {} \;

  [[ -f "$NEW_RELEASE/public/build/manifest.json" ]] \
    || die "The release has no Vite manifest."
  [[ -x "$NEW_RELEASE/voice-agent/bin/voice-agent" ]] \
    || die "The release has no executable voice-agent binary."
  [[ -f "$NEW_RELEASE/integrations/lolo-content-mcp/dist/http.js" ]] \
    || die "The release has no hosted Content MCP build."

  log "Validating the inactive Laravel release..."
  run_artisan "$NEW_RELEASE" route:list --json >/dev/null
  run_artisan "$NEW_RELEASE" migrate:status >/dev/null
  sudo nginx -t
}

activate_release() {
  local current="$1"

  OLD_RELEASE="$current"

  if [[ -L "$APP_DIR" ]]; then
    set_previous_release "$OLD_RELEASE"
    log "Atomically activating $NEW_RELEASE..."
    atomic_symlink "$NEW_RELEASE" "$APP_DIR"
    SWITCHED=1
    return
  fi

  LEGACY_RELEASE="$RELEASES_DIR/legacy-$(date -u +%Y%m%d%H%M%S)-$(git -C "$APP_DIR" rev-parse --short=12 HEAD)"
  [[ ! -e "$LEGACY_RELEASE" ]] || die "Legacy release target already exists: $LEGACY_RELEASE"

  log "Preparing the one-time atomic conversion of $APP_DIR into the stable release symlink..."
  cd "$DEPLOY_ROOT"
  sudo ln -s -- "$NEW_RELEASE" "$LEGACY_RELEASE"
  BOOTSTRAP_MOVED=1

  # Before the exchange, only the inactive release follows SHARED_STORAGE.
  # After the exchange, LEGACY_RELEASE is the old physical application
  # directory, so this same pointer keeps uploads, sessions, and logs intact.
  atomic_symlink "$LEGACY_RELEASE/storage" "$SHARED_STORAGE"

  OLD_RELEASE="$LEGACY_RELEASE"
  log "Atomically exchanging the live directory with the prepared release..."
  atomic_exchange "$APP_DIR" "$LEGACY_RELEASE"
  SWITCHED=1
  BOOTSTRAP_MOVED=0
  set_previous_release "$OLD_RELEASE"
}

safe_remove_release() {
  local release="$1"
  local current="$2"
  local previous="$3"
  local resolved_release

  resolved_release="$(resolved_path "$release")"
  case "$resolved_release" in
    "$(resolved_path "$RELEASES_DIR")"/*) ;;
    *) log "Skipping unsafe release cleanup target: $release"; return ;;
  esac

  if [[ "$resolved_release" == "$current" || "$resolved_release" == "$previous" ]]; then
    return
  fi

  if [[ ! -L "$release/storage" ]]; then
    log "Skipping $release because its storage directory is not a symlink."
    return
  fi

  log "Removing old release $release..."
  sudo rm -rf --one-file-system -- "$release"
}

cleanup_old_releases() {
  local current
  local previous
  local release
  local kept=0
  local -a releases=()

  current="$(resolved_path "$APP_DIR")"
  previous="$(resolved_path "$PREVIOUS_LINK")"
  mapfile -t releases < <(
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
      | sort -nr \
      | cut -d' ' -f2-
  )

  for release in "${releases[@]}"; do
    if [[ "$release" == "$current" || "$release" == "$previous" || "$kept" -lt "$KEEP_RELEASES" ]]; then
      kept=$((kept + 1))
      continue
    fi
    safe_remove_release "$release" "$current" "$previous"
  done
}

perform_rollback() {
  local current
  local previous

  [[ -L "$APP_DIR" ]] || die "Rollback requires the atomic release layout. Run one normal deploy first."
  current="$(resolved_path "$APP_DIR")"
  previous="$(resolved_path "$PREVIOUS_LINK")"
  [[ -n "$previous" && -d "$previous" ]] || die "No previous release is available."
  [[ "$current" != "$previous" ]] || die "Current and previous releases are identical."

  if is_screening_release "$current" && ! is_screening_release "$previous"; then
    die "Rollback to a pre-screening release is disabled: it would bypass screening decisions and cannot consume screening jobs."
  fi

  OLD_RELEASE="$current"
  NEW_RELEASE="$previous"
  if is_screening_release "$previous"; then
    preflight_screening_runtime
    validate_screening_queue_config "$previous"
    prepare_screening_cutover
  fi
  log "Atomically rolling back from $current to $previous..."
  atomic_symlink "$previous" "$APP_DIR"
  SWITCHED=1
  set_previous_release "$current"
  if [[ "$SCREENING_FIRST_ROLLOUT" -eq 1 ]]; then
    SCREENING_ACTIVATION_ATTEMPTED=1
    run_artisan "$APP_DIR" screening:rollout --activate --no-interaction
  fi
  restart_runtime_for_active_release
  wait_for_health app_is_healthy "Application"
  wait_for_health voice_is_healthy "Voice-agent"
  if content_mcp_service_is_installed; then
    wait_for_health content_mcp_is_healthy "Content MCP" \
      || log "Content MCP is unavailable after rollback; the Laravel application and voice service are healthy."
  fi
  SWITCHED=0
  log "Rollback complete. Database migrations were intentionally not reversed."
}

case "$MODE" in
  --screening-check)
    [[ "$#" -eq 2 ]] || die "Usage: deploy.sh --screening-check REV"
    screening_runtime_from_git check "$2"
    exit 0
    ;;
  --screening-setup)
    [[ "$#" -eq 4 && "$3" == --confirm-single-host-runtime && "$4" == --accept-existing-supervisor-stop-timeout ]] \
      || die "Usage: deploy.sh --screening-setup REV --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout"
    screening_runtime_from_git setup "$2"
    exit 0
    ;;
  --help|-h)
    usage
    exit 0
    ;;
  --status)
    show_status
    exit 0
    ;;
  deploy|--rollback)
    ;;
  *)
    usage
    die "Unknown option: $MODE"
    ;;
esac

[[ "$KEEP_RELEASES" =~ ^[0-9]+$ && "$KEEP_RELEASES" -ge 2 ]] \
  || die "HOMECARE_KEEP_RELEASES must be an integer of at least 2."

for command_name in curl flock git grep python3 readlink sudo systemctl; do
  require_command "$command_name"
done

exec 9>"$LOCK_FILE"
flock -n 9 || die "Another deploy is already running."
trap 'rollback_after_failure "$?"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$MODE" == "--rollback" ]]; then
  perform_rollback
  trap - EXIT INT TERM
  exit 0
fi

for command_name in "$PHP_BIN" "$COMPOSER_BIN" "$NPM_BIN"; do
  require_command "$command_name"
done

if [[ ! -x "$GO_BIN" ]]; then
  GO_BIN="$(command -v go || true)"
fi
[[ -n "$GO_BIN" && -x "$GO_BIN" ]] \
  || die "Go binary not found. Install Go or set HOMECARE_GO_BIN."

[[ -d "$APP_DIR" ]] || die "Application directory not found: $APP_DIR"

log "Starting HomeCare atomic deployment..."
log "The current release stays live during the build; first screening activation includes a brief request drain."

CURRENT_RELEASE="$(current_release)"
prepare_shared_state
prepare_repository "$CURRENT_RELEASE"
create_release "$CURRENT_RELEASE"
prepare_screening_cutover
activate_release "$CURRENT_RELEASE"
if [[ "$SCREENING_FIRST_ROLLOUT" -eq 1 ]]; then
  SCREENING_ACTIVATION_ATTEMPTED=1
  run_artisan "$APP_DIR" screening:rollout --activate --no-interaction
fi
restart_runtime_for_active_release
wait_for_health app_is_healthy "Application"
wait_for_health voice_is_healthy "Voice-agent"
if content_mcp_service_is_installed; then
  wait_for_health content_mcp_is_healthy "Content MCP" \
    || log "Content MCP is unavailable; the Laravel application release remains active."
fi

SWITCHED=0
BOOTSTRAP_MOVED=0
DEPLOY_COMPLETED=1
cleanup_old_releases
trap - EXIT INT TERM

log "Deployment complete at commit $DEPLOY_COMMIT"
log "Active release: $(resolved_path "$APP_DIR")"
log "No Laravel maintenance mode or deployment-long 503 was used."
if is_screening_release "$APP_DIR"; then
  log "Screening rollout is active. Dataset imports and Didit identity backfill were not run by deployment."
fi

if [[ "$OLD_RELEASE" == "$LEGACY_RELEASE" && -n "$LEGACY_RELEASE" ]]; then
  log "One-time setup complete. Run 'cd /var/www/homecare' before your next server command."
fi
