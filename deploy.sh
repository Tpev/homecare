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

MODE="${1:-deploy}"
SWITCHED=0
BOOTSTRAP_MOVED=0
OLD_RELEASE=""
NEW_RELEASE=""
LEGACY_RELEASE=""
DEPLOY_COMMIT=""

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
  ./deploy.sh --help      Show this help.

The first normal deployment converts /var/www/homecare from an in-place checkout
to a stable symlink automatically. The live application is never placed in
Laravel maintenance mode.
EOF
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
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
  log "Gracefully reloading PHP-FPM..."
  sudo systemctl reload "$FPM_SERVICE"

  log "Restarting queue workers after their current jobs..."
  run_artisan "$APP_DIR" queue:restart || log "Queue restart signal failed; inspect the worker service."

  if run_artisan "$APP_DIR" list --raw 2>/dev/null | grep -qx 'horizon:terminate'; then
    run_artisan "$APP_DIR" horizon:terminate || log "Horizon termination signal failed."
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
  local exit_code=$?
  trap - ERR EXIT

  log "Deployment failed. The active release will be preserved."

  if [[ "$SWITCHED" -eq 1 && -n "$OLD_RELEASE" && -d "$OLD_RELEASE" ]]; then
    log "Rolling the application symlink back to $OLD_RELEASE..."
    if atomic_symlink "$OLD_RELEASE" "$APP_DIR"; then
      sudo systemctl reload "$FPM_SERVICE" || true
      run_artisan "$APP_DIR" queue:restart || true
      sudo systemctl restart "$VOICE_AGENT_SERVICE" || true
      if content_mcp_service_is_installed; then
        sudo systemctl restart "$CONTENT_MCP_SERVICE" || true
      fi
      wait_for_health app_is_healthy "Application rollback" || true
      log "Rollback attempt finished. Database migrations were not reversed."
    else
      log "Automatic rollback failed; point $APP_DIR to $OLD_RELEASE manually."
    fi
  elif [[ "$BOOTSTRAP_MOVED" -eq 1 && -n "$LEGACY_RELEASE" ]]; then
    log "Restoring the bootstrap storage pointer; the original application stayed live."
    atomic_symlink "$APP_DIR/storage" "$SHARED_STORAGE" || true
    if [[ -L "$LEGACY_RELEASE" ]]; then
      sudo rm -f -- "$LEGACY_RELEASE" || true
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
  sudo chown -R "$DEPLOY_USER:$WEB_GROUP" "$shared_storage_real"
  sudo find "$shared_storage_real" -type d -exec chmod 775 {} \;
  sudo find "$shared_storage_real" -type f -exec chmod 664 {} \;
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

  release_name="$(date -u +%Y%m%d%H%M%S)-${DEPLOY_COMMIT:0:12}"
  NEW_RELEASE="$RELEASES_DIR/$release_name"
  [[ ! -e "$NEW_RELEASE" ]] || die "Release already exists: $NEW_RELEASE"

  log "Creating release $release_name..."
  git clone --shared --no-checkout "$REPOSITORY_DIR" "$NEW_RELEASE"
  git -C "$NEW_RELEASE" checkout --detach "$DEPLOY_COMMIT"

  case "$(resolved_path "$NEW_RELEASE")" in
    "$(resolved_path "$RELEASES_DIR")"/*) ;;
    *) die "Refusing to prepare a release outside $RELEASES_DIR." ;;
  esac

  rm -rf -- "$NEW_RELEASE/storage"
  ln -s "$SHARED_STORAGE" "$NEW_RELEASE/storage"
  ln -s "$SHARED_ENV" "$NEW_RELEASE/.env"

  mkdir -p "$NEW_RELEASE/bootstrap/cache" "$NEW_RELEASE/voice-agent/bin"
  chmod 775 "$NEW_RELEASE/bootstrap/cache" "$NEW_RELEASE/voice-agent/bin"

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
  run_artisan "$NEW_RELEASE" config:cache
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

  OLD_RELEASE="$current"
  NEW_RELEASE="$previous"
  log "Atomically rolling back from $current to $previous..."
  atomic_symlink "$previous" "$APP_DIR"
  SWITCHED=1
  set_previous_release "$current"
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
trap rollback_after_failure ERR

if [[ "$MODE" == "--rollback" ]]; then
  perform_rollback
  trap - ERR
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

log "Starting zero-maintenance HomeCare deployment..."
log "The current release will stay live until the atomic switch."

CURRENT_RELEASE="$(current_release)"
prepare_shared_state
prepare_repository "$CURRENT_RELEASE"
create_release "$CURRENT_RELEASE"
activate_release "$CURRENT_RELEASE"
restart_runtime_for_active_release
wait_for_health app_is_healthy "Application"
wait_for_health voice_is_healthy "Voice-agent"
if content_mcp_service_is_installed; then
  wait_for_health content_mcp_is_healthy "Content MCP" \
    || log "Content MCP is unavailable; the Laravel application release remains active."
fi

SWITCHED=0
BOOTSTRAP_MOVED=0
cleanup_old_releases
trap - ERR

log "Deployment complete at commit $DEPLOY_COMMIT"
log "Active release: $(resolved_path "$APP_DIR")"
log "No Laravel maintenance mode or deployment-long 503 was used."

if [[ "$OLD_RELEASE" == "$LEGACY_RELEASE" && -n "$LEGACY_RELEASE" ]]; then
  log "One-time setup complete. Run 'cd /var/www/homecare' before your next server command."
fi
