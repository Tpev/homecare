#!/usr/bin/env bash
set -Eeuo pipefail

case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS="winsymlinks:nativestrict" ;;
esac

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SANDBOX="$(mktemp -d "${TMPDIR:-/tmp}/homecare-deploy-smoke.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
SANDBOX_PARENT="$(dirname "$SANDBOX")"
APP_DIR="$SANDBOX/homecare"
DEPLOY_ROOT="$SANDBOX/homecare-deploy"
ORIGIN_DIR="$SANDBOX/origin.git"
AUTHOR_DIR="$SANDBOX/author"
MOCK_BIN="$SANDBOX/mock-bin"
DEPLOY_SCRIPT="$SANDBOX/deploy-under-test.sh"
cp "$PROJECT_DIR/deploy.sh" "$DEPLOY_SCRIPT"
chmod 775 "$DEPLOY_SCRIPT"

cleanup() {
  [[ "$(dirname "$SANDBOX")" == "$SANDBOX_PARENT" && "$(basename "$SANDBOX")" == homecare-deploy-smoke.* \
    && "$(readlink -f "$SANDBOX")" == "$SANDBOX" ]] && rm -rf -- "$SANDBOX"
}
trap cleanup EXIT
trap 'printf "Smoke test failed at line %s: %s\n" "$LINENO" "$BASH_COMMAND" >&2; tail -n 30 "$SANDBOX"/*.log >&2' ERR

mkdir -p "$MOCK_BIN"

cat > "$MOCK_BIN/sudo" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == "chown" ]]; then
  exit 0
fi
if [[ "${1:-}" == "install" ]]; then
  source_path="${@: -2:1}"
  destination="${@: -1}"
  cp -- "$source_path" "$destination"
  chmod 640 "$destination"
  exit 0
fi
exec "$@"
EOF

cat > "$MOCK_BIN/systemctl" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'service %s\n' "$*" >> "$MOCK_EVENTS"
if [[ "$1" == show ]]; then
  case "$3" in
    --property=KillSignal) echo SIGQUIT ;;
    --property=KillMode) echo control-group ;;
    --property=SendSIGKILL) echo no ;;
    --property=ActiveState) if [[ -f "$MOCK_STATE/fpm-stopped" ]]; then echo inactive; else echo active; fi ;;
    --property=MainPID|--property=ControlPID) echo 0 ;;
  esac
elif [[ "$2" == php8.3-fpm ]]; then
  case "$1" in
    stop) touch "$MOCK_STATE/fpm-stopped" ;;
    start) rm -f "$MOCK_STATE/fpm-stopped" ;;
  esac
fi
exit 0
EOF

cat > "$MOCK_BIN/chown" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF

# Native symlinks need Windows Developer Mode/admin rights. Directory junctions
# exercise the same Bash link/switch branches without changing host policy.
# Only the immutable .env fixture is copied; Linux runs use real symlinks.
case "$(uname -s)" in
  MINGW*|MSYS*)
    cat > "$MOCK_BIN/mkdir" <<'EOF'
#!/usr/bin/env bash
arguments=()
while [[ "$#" -gt 0 ]]; do
  if [[ "$1" == -m && "${2:-}" == 2770 ]]; then shift 2; else arguments+=("$1"); shift; fi
done
/usr/bin/mkdir "${arguments[@]}"
EOF
    cat > "$MOCK_BIN/chmod" <<'EOF'
#!/usr/bin/env bash
if [[ "${1:-}" == 2770 ]]; then shift; set -- 770 "$@"; fi
/usr/bin/chmod "$@"
EOF
    cat > "$MOCK_BIN/ln" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
target="${@: -2:1}"
destination="${@: -1}"
if [[ "$target" != /* ]]; then target="$(dirname "$destination")/$target"; fi
if [[ -d "$target" ]]; then
  HOMECARE_TEST_JUNCTION_TARGET="$(cygpath -aw "$target")" HOMECARE_TEST_JUNCTION_PATH="$(cygpath -aw "$destination")" \
    /c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe -NoProfile -NonInteractive \
    -Command '$ErrorActionPreference = "Stop"; New-Item -ItemType Junction -Path $env:HOMECARE_TEST_JUNCTION_PATH -Target $env:HOMECARE_TEST_JUNCTION_TARGET | Out-Null'
else
  [[ "$(basename "$destination")" == .env ]]
  cp -- "$target" "$destination"
fi
EOF
    ;;
esac

cat > "$MOCK_BIN/runtime-control" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'runtime %s\n' "$1" >> "$MOCK_EVENTS"
case "$1" in
  preflight) [[ "${MOCK_PREFLIGHT_FAIL:-0}" != 1 ]] ;;
  pause)
    touch "$MOCK_STATE/paused"
    [[ "${MOCK_PAUSE_FAIL:-0}" != 1 ]]
    ;;
  resume)
    [[ -f "$2/artisan" ]]
    printf 'resumed %s\n' "$(readlink -f "$2")" >> "$MOCK_EVENTS"
    rm -f "$MOCK_STATE/paused"
    ;;
esac
EOF

cat > "$MOCK_BIN/nginx" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF

cat > "$MOCK_BIN/flock" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF

cat > "$MOCK_BIN/curl" <<'EOF'
#!/usr/bin/env bash
if [[ "${MOCK_HEALTH_FAIL:-0}" == "1" ]]; then
  exit 1
fi
exit 0
EOF

cat > "$MOCK_BIN/sleep" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF

cat > "$MOCK_BIN/python3" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
first="$2"
second="$3"
temporary="${second}.exchange"
mv -- "$first" "$temporary"
mv -- "$second" "$first"
mv -- "$temporary" "$second"
EOF

cat > "$MOCK_BIN/php" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1##*/}" == check-screening-queue.php ]]; then
  printf 'queue-config-check %s\n' "$3" >> "$MOCK_EVENTS"
  [[ "$3" == 120 && "${MOCK_QUEUE_CONFIG_FAIL:-0}" != 1 ]]
  exit
fi
if [[ "${1:-}" != "artisan" ]]; then
  exit 0
fi
printf 'artisan %s\n' "${*:2}" >> "$MOCK_EVENTS"
case "${2:-}" in
  screening:rollout)
    case "$3" in
      --status)
        [[ "${MOCK_STATUS_FAIL:-0}" != 1 ]] || exit 1
        if [[ "${MOCK_STATUS_FAIL_AFTER_ACTIVATE:-0}" == 1 && -f "$MOCK_STATE/active" ]]; then exit 1; fi
        if [[ -f "$MOCK_STATE/active" ]]; then echo active; else echo pending; fi
        ;;
      --prepare)
        [[ -f "$MOCK_STATE/paused" && -f "$MOCK_STATE/fpm-stopped" ]]
        touch "$MOCK_STATE/prepared"
        [[ "${MOCK_PREPARE_FAIL:-0}" != 1 ]] || exit 1
        if [[ "${MOCK_INTERRUPT:-0}" == 1 ]]; then kill -TERM "$PPID"; exit 143; fi
        echo prepared
        ;;
      --activate)
        [[ -f "$MOCK_STATE/prepared" && -f "$MOCK_STATE/paused" && -f "$MOCK_STATE/fpm-stopped" ]]
        touch "$MOCK_STATE/active"
        [[ "${MOCK_ACTIVATE_FAIL:-0}" != 1 ]] || exit 1
        echo active
        ;;
    esac
    ;;
  config:cache)
    touch bootstrap/cache/config.php
    printf '%s\n' "${VIEW_COMPILED_PATH:-$(pwd -P)/storage/framework/views}" > bootstrap/cache/view-path
    ;;
  route:cache) touch bootstrap/cache/routes.php ;;
  view:cache)
    # Model Laravel using the persisted config even after the environment
    # override ends, including its destructive view:clear before recompiling.
    compiled_views="$(cat bootstrap/cache/view-path)"
    mkdir -p "$compiled_views"
    find "$compiled_views" -maxdepth 1 -type f -delete
    cp VERSION "$compiled_views/compiled.php"
    ;;
  list) exit 0 ;;
esac
exit 0
EOF

cat > "$MOCK_BIN/composer" <<'EOF'
#!/usr/bin/env bash
mkdir -p vendor
exit 0
EOF

cat > "$MOCK_BIN/npm" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == "run" && "${2:-}" == "build" ]]; then
  if [[ "$PWD" == */integrations/lolo-content-mcp ]]; then
    mkdir -p dist
    printf '// mocked Content MCP build\n' > dist/http.js
  else
    mkdir -p public/build/assets
    printf '{}\n' > public/build/manifest.json
    printf 'new asset\n' > public/build/assets/app-new.js
  fi
fi
exit 0
EOF

cat > "$MOCK_BIN/go" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
output=""
while [[ "$#" -gt 0 ]]; do
  if [[ "$1" == "-o" ]]; then
    output="$2"
    shift 2
    continue
  fi
  shift
done
[[ -n "$output" ]]
mkdir -p "$(dirname "$output")"
printf '#!/usr/bin/env bash\nexit 0\n' > "$output"
chmod 775 "$output"
EOF

chmod 775 "$MOCK_BIN"/*
export PATH="$MOCK_BIN:$PATH"
export MOCK_STATE="$SANDBOX/state" MOCK_EVENTS="$SANDBOX/events.log"
mkdir -p "$MOCK_STATE"
touch "$MOCK_EVENTS"

git init --bare "$ORIGIN_DIR" >/dev/null
git init -b master "$APP_DIR" >/dev/null
git -C "$APP_DIR" config user.name "Deployment Test"
git -C "$APP_DIR" config user.email "deployment-test@example.invalid"
git -C "$APP_DIR" remote add origin "$ORIGIN_DIR"

mkdir -p \
  "$APP_DIR/bootstrap/cache" \
  "$APP_DIR/public/build/assets" \
  "$APP_DIR/storage/app/public" \
  "$APP_DIR/storage/framework/cache/data" \
  "$APP_DIR/storage/framework/sessions" \
  "$APP_DIR/storage/framework/testing" \
  "$APP_DIR/storage/framework/views" \
  "$APP_DIR/storage/logs" \
  "$APP_DIR/integrations/lolo-content-mcp" \
  "$APP_DIR/voice-agent/cmd/server"
cp "$PROJECT_DIR/deploy.sh" "$APP_DIR/deploy.sh"
chmod 775 "$APP_DIR/deploy.sh"
printf '#!/usr/bin/env php\n' > "$APP_DIR/artisan"
printf '{}\n' > "$APP_DIR/composer.json"
printf '{}\n' > "$APP_DIR/package.json"
printf '{}\n' > "$APP_DIR/integrations/lolo-content-mcp/package.json"
printf 'package main\n' > "$APP_DIR/voice-agent/cmd/server/main.go"
printf 'old asset\n' > "$APP_DIR/public/build/assets/app-old.js"
printf '{}\n' > "$APP_DIR/public/build/manifest.json"
printf 'release one\n' > "$APP_DIR/VERSION"
git -C "$APP_DIR" add .
git -C "$APP_DIR" commit -m "Release one" >/dev/null
git -C "$APP_DIR" push -u origin master >/dev/null

printf 'APP_ENV=production\nAPP_KEY=base64:test\n' > "$APP_DIR/.env"
printf 'durable session\n' > "$APP_DIR/storage/framework/sessions/preserved-session"
printf 'legacy compiled view\n' > "$APP_DIR/storage/framework/views/legacy-preserved.php"

deploy() {
  HOMECARE_APP_DIR="$APP_DIR" \
  HOMECARE_DEPLOY_ROOT="$DEPLOY_ROOT" \
  HOMECARE_DEPLOY_USER="ignored" \
  HOMECARE_WEB_GROUP="ignored" \
  HOMECARE_DEPLOY_LOCK="$SANDBOX/deploy.lock" \
  HOMECARE_PHP_BIN="$MOCK_BIN/php" \
  HOMECARE_COMPOSER_BIN="$MOCK_BIN/composer" \
  HOMECARE_NPM_BIN="$MOCK_BIN/npm" \
  HOMECARE_GO_BIN="$MOCK_BIN/go" \
  HOMECARE_RUNTIME_HOOK="${TEST_RUNTIME_HOOK:-$MOCK_BIN/runtime-control}" \
  "$DEPLOY_SCRIPT" "$@"
}

if ! deploy > "$SANDBOX/first-deploy.log" 2>&1; then
  cat "$SANDBOX/first-deploy.log" >&2
  exit 1
fi
[[ -L "$APP_DIR" ]]
[[ -f "$APP_DIR/storage/framework/sessions/preserved-session" ]]
[[ -f "$APP_DIR/public/build/assets/app-old.js" ]]
[[ "$(cat "$APP_DIR/storage/framework/views/legacy-preserved.php")" == 'legacy compiled view' ]]
grep -q 'No Laravel maintenance mode or deployment-long 503 was used.' "$SANDBOX/first-deploy.log"
! grep -q 'Entering maintenance mode' "$SANDBOX/first-deploy.log"

first_release="$(readlink -f "$APP_DIR")"
original_legacy_release="$(readlink -f "$DEPLOY_ROOT/previous")"
[[ "$(cat "$first_release/bootstrap/cache/view-path")" == "$first_release/bootstrap/cache/views" ]]
[[ "$(cat "$first_release/bootstrap/cache/views/compiled.php")" == 'release one' ]]
git clone "$ORIGIN_DIR" "$AUTHOR_DIR" >/dev/null
git -C "$AUTHOR_DIR" config user.name "Deployment Test"
git -C "$AUTHOR_DIR" config user.email "deployment-test@example.invalid"
printf 'release two\n' > "$AUTHOR_DIR/VERSION"
git -C "$AUTHOR_DIR" add VERSION
git -C "$AUTHOR_DIR" commit -m "Release two" >/dev/null
git -C "$AUTHOR_DIR" push origin master >/dev/null

if ! deploy > "$SANDBOX/second-deploy.log" 2>&1; then
  cat "$SANDBOX/second-deploy.log" >&2
  exit 1
fi
second_release="$(readlink -f "$APP_DIR")"
[[ "$second_release" != "$first_release" ]]
[[ "$(readlink -f "$DEPLOY_ROOT/previous")" == "$first_release" ]]
[[ -f "$APP_DIR/storage/framework/sessions/preserved-session" ]]
[[ "$(cat "$second_release/bootstrap/cache/view-path")" == "$second_release/bootstrap/cache/views" ]]
[[ "$(cat "$second_release/bootstrap/cache/views/compiled.php")" == 'release two' ]]
[[ "$(cat "$first_release/bootstrap/cache/views/compiled.php")" == 'release one' ]]
[[ -f "$APP_DIR/storage/framework/views/legacy-preserved.php" ]]

if ! deploy --rollback > "$SANDBOX/rollback.log" 2>&1; then
  cat "$SANDBOX/rollback.log" >&2
  exit 1
fi
[[ "$(readlink -f "$APP_DIR")" == "$first_release" ]]
[[ "$(cat "$APP_DIR/bootstrap/cache/view-path")" == "$first_release/bootstrap/cache/views" ]]
[[ "$(cat "$APP_DIR/bootstrap/cache/views/compiled.php")" == 'release one' ]]
grep -q 'Rollback complete.' "$SANDBOX/rollback.log"

printf 'release three\n' > "$AUTHOR_DIR/VERSION"
git -C "$AUTHOR_DIR" add VERSION
git -C "$AUTHOR_DIR" commit -m "Release three" >/dev/null
git -C "$AUTHOR_DIR" push origin master >/dev/null
before_failed_deploy="$(readlink -f "$APP_DIR")"

if MOCK_HEALTH_FAIL=1 deploy > "$SANDBOX/failed-deploy.log" 2>&1; then
  echo "Expected the failed health check to return a non-zero status." >&2
  exit 1
fi
[[ "$(readlink -f "$APP_DIR")" == "$before_failed_deploy" ]]
[[ "$(cat "$APP_DIR/bootstrap/cache/views/compiled.php")" == 'release one' ]]
grep -q 'Rolling the application symlink back' "$SANDBOX/failed-deploy.log"

deploy --status > "$SANDBOX/status.log"
grep -q 'Deployment layout: atomic releases' "$SANDBOX/status.log"

mkdir -p "$AUTHOR_DIR/app/Console/Commands"
mkdir -p "$AUTHOR_DIR/scripts/deployment"
cp "$PROJECT_DIR/scripts/deployment/check-screening-queue.php" "$AUTHOR_DIR/scripts/deployment/check-screening-queue.php"
printf '<?php // screening-compatible fixture\n' > "$AUTHOR_DIR/app/Console/Commands/ScreeningRollout.php"
printf 'screening release\n' > "$AUTHOR_DIR/VERSION"
git -C "$AUTHOR_DIR" add .
git -C "$AUTHOR_DIR" commit -m "First screening release" >/dev/null
git -C "$AUTHOR_DIR" push origin master >/dev/null
legacy_release="$(readlink -f "$APP_DIR")"
mkdir -p "$APP_DIR/storage/app/private/screening/audit"
printf 'private evidence\n' > "$APP_DIR/storage/app/private/screening/audit/preserved.jsonl"

# Missing topology, partial worker pause and a failed reconciliation all leave
# the old release selected; partial pauses/stops must be undone by the EXIT trap.
if TEST_RUNTIME_HOOK="$SANDBOX/missing-hook" deploy > "$SANDBOX/missing-hook.log" 2>&1; then exit 1; fi
grep -q 'requires an executable HOMECARE_RUNTIME_HOOK' "$SANDBOX/missing-hook.log"
[[ "$(readlink -f "$APP_DIR")" == "$legacy_release" ]]
pause_count="$(grep -c 'runtime pause' "$MOCK_EVENTS" || true)"
if MOCK_QUEUE_CONFIG_FAIL=1 deploy > "$SANDBOX/unsafe-queue.log" 2>&1; then exit 1; fi
grep -q 'Screening queue configuration is unsafe' "$SANDBOX/unsafe-queue.log"
[[ "$(readlink -f "$APP_DIR")" == "$legacy_release" ]]
[[ "$(grep -c 'runtime pause' "$MOCK_EVENTS" || true)" == "$pause_count" ]]
[[ ! -f "$MOCK_STATE/paused" && ! -f "$MOCK_STATE/fpm-stopped" && ! -f "$MOCK_STATE/active" ]]
for failure in pause prepare interrupt; do
  export MOCK_PAUSE_FAIL=0 MOCK_PREPARE_FAIL=0 MOCK_INTERRUPT=0
  case "$failure" in
    pause) export MOCK_PAUSE_FAIL=1 ;;
    prepare) export MOCK_PREPARE_FAIL=1 ;;
    interrupt) export MOCK_INTERRUPT=1 ;;
  esac
  if deploy > "$SANDBOX/$failure.log" 2>&1; then
    echo "Expected $failure to fail." >&2; exit 1
  fi
  [[ "$(readlink -f "$APP_DIR")" == "$legacy_release" ]]
  [[ ! -f "$MOCK_STATE/paused" && ! -f "$MOCK_STATE/fpm-stopped" && ! -f "$MOCK_STATE/active" ]]
done
unset MOCK_PAUSE_FAIL MOCK_PREPARE_FAIL MOCK_INTERRUPT

# The DB boundary may commit before a command fails. An unavailable recheck must
# never restore legacy code or resume writers until a retry confirms activation.
if MOCK_ACTIVATE_FAIL=1 MOCK_STATUS_FAIL_AFTER_ACTIVATE=1 deploy > "$SANDBOX/boundary-failure.log" 2>&1; then exit 1; fi
screening_release="$(readlink -f "$APP_DIR")"
[[ "$screening_release" != "$legacy_release" && -f "$MOCK_STATE/active" ]]
[[ -f "$MOCK_STATE/paused" && -f "$MOCK_STATE/fpm-stopped" ]]
grep -q 'Refusing rollback to a pre-screening release' "$SANDBOX/boundary-failure.log"
grep -q 'background writers remain paused' "$SANDBOX/boundary-failure.log"
if ! deploy > "$SANDBOX/boundary-recovery.log" 2>&1; then cat "$SANDBOX/boundary-recovery.log" >&2; exit 1; fi
[[ ! -f "$MOCK_STATE/paused" && ! -f "$MOCK_STATE/fpm-stopped" ]]
# Restore the first screening release (safe rollback) to exercise the prohibited
# legacy rollback still recorded immediately behind it.
if ! deploy --rollback > "$SANDBOX/boundary-recovery-rollback.log" 2>&1; then cat "$SANDBOX/boundary-recovery-rollback.log" >&2; exit 1; fi
# The previous link is a toggle, so explicitly restore its original fixture here.
rm -f "$DEPLOY_ROOT/previous"
ln -s "$original_legacy_release" "$DEPLOY_ROOT/previous"
if deploy --rollback > "$SANDBOX/unsafe-rollback.log" 2>&1; then exit 1; fi
grep -q 'Rollback to a pre-screening release is disabled' "$SANDBOX/unsafe-rollback.log"
[[ "$(readlink -f "$APP_DIR")" == "$screening_release" ]]

if MOCK_STATUS_FAIL=1 deploy > "$SANDBOX/unknown-status.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$screening_release" && ! -f "$MOCK_STATE/paused" ]]

# Later deployments drain consumers but never re-run grandfathering or stop FPM.
prepare_count="$(grep -c 'artisan screening:rollout --prepare' "$MOCK_EVENTS")"
stop_count="$(grep -c 'service stop php8.3-fpm' "$MOCK_EVENTS")"
if ! deploy > "$SANDBOX/screening-retry.log" 2>&1; then cat "$SANDBOX/screening-retry.log" >&2; exit 1; fi
[[ "$(grep -c 'artisan screening:rollout --prepare' "$MOCK_EVENTS")" == "$prepare_count" ]]
[[ "$(grep -c 'service stop php8.3-fpm' "$MOCK_EVENTS")" == "$stop_count" ]]
[[ "$(cat "$APP_DIR/storage/app/private/screening/audit/preserved.jsonl")" == 'private evidence' ]]
case "$(uname -s)" in
  MINGW*|MSYS*) ;; # Windows ACLs do not implement Unix mode bits.
  *)
    [[ "$(stat -c '%a' "$APP_DIR/storage/app/private/screening/audit")" == 2770 ]]
    [[ "$(stat -c '%a' "$APP_DIR/storage/app/private/screening/audit/preserved.jsonl")" == 660 ]]
    ;;
esac
if ! deploy --rollback > "$SANDBOX/safe-screening-rollback.log" 2>&1; then cat "$SANDBOX/safe-screening-rollback.log" >&2; exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$screening_release" ]]
if MOCK_HEALTH_FAIL=1 deploy > "$SANDBOX/screening-health-failure.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$screening_release" ]]
[[ ! -f "$MOCK_STATE/paused" ]]
grep -q 'Rolling the application symlink back' "$SANDBOX/screening-health-failure.log"

# Deployment must not run network backfill, enrollment or long dataset imports.
! grep -Eq 'artisan (screening:(import|refresh-datasets|sync)|.*refresh-identities)' "$MOCK_EVENTS"

printf 'Atomic deployment smoke test passed.\n'
