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
if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot || "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot-forward ]]; then
  # The operator launches the script already installed by the previous deploy.
  # Fetching a candidate does not replace that running driver.
  git -C "$PROJECT_DIR" -c safe.directory="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")" \
    show 27a5307cab710c589eb1c2e91f5fba1edf3ba902:deploy.sh > "$DEPLOY_SCRIPT"
  export MOCK_REAL_PHP="${HOMECARE_SMOKE_REAL_PHP:-$(command -v php || true)}"
  [[ -x "$MOCK_REAL_PHP" ]] || { printf 'private-pilot smoke requires HOMECARE_SMOKE_REAL_PHP.\n' >&2; exit 1; }
  export MOCK_REAL_PREPAID_CONFIG=1
else
  cp "$PROJECT_DIR/deploy.sh" "$DEPLOY_SCRIPT"
fi
chmod 775 "$DEPLOY_SCRIPT"

cleanup() {
  [[ "$(dirname "$SANDBOX")" == "$SANDBOX_PARENT" && "$(basename "$SANDBOX")" == homecare-deploy-smoke.* \
    && "$(readlink -f "$SANDBOX")" == "$SANDBOX" ]] && rm -rf -- "$SANDBOX"
}
trap cleanup EXIT
trap 'printf "Smoke test failed at line %s: %s\n" "$LINENO" "$BASH_COMMAND" >&2; tail -n 30 "$SANDBOX"/*.log >&2' ERR

if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot-forward ]]; then
  # Isolate the already-compatible branch of the actual previously installed
  # driver. The full private-pilot focus below exercises activation and rollback.
  awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
    "$DEPLOY_SCRIPT" > "$SANDBOX/previous-driver-functions.sh"
  HOMECARE_APP_DIR="$SANDBOX/current" HOMECARE_DEPLOY_ROOT="$SANDBOX/deploy" \
    HOMECARE_PHP_BIN="$MOCK_REAL_PHP" source "$SANDBOX/previous-driver-functions.sh"
  current="$SANDBOX/current"
  candidate="$SANDBOX/candidate"
  for release in "$current" "$candidate"; do
    mkdir -p "$release/bootstrap/cache" "$release/config" "$release/docs/deployment" "$release/scripts/deployment"
    printf 'homecare-prepaid-care-hours-v1\n' > "$release/$PREPAID_MANIFEST"
  done
  git -C "$PROJECT_DIR" -c safe.directory="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")" \
    show 27a5307cab710c589eb1c2e91f5fba1edf3ba902:config/care_hours.php > "$current/config/care_hours.php"
  # This historical v1-to-private-pilot rehearsal must not substitute the later
  # family-wallet v2 configuration into a v1-labelled synthetic candidate.
  git -C "$PROJECT_DIR" -c safe.directory="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")" \
    show f030f0a2:config/care_hours.php > "$candidate/config/care_hours.php"
  cp -- "$PROJECT_DIR/scripts/deployment/check-prepaid-release-config.php" "$candidate/scripts/deployment/check-prepaid-release-config.php"
  cat > "$SANDBOX/build-configs.php" <<'EOF'
<?php
function env($key, $default = null) {
    return match ($key) {
        'CARE_HOURS_ENABLED', 'CARE_HOURS_GIFTS_ENABLED' => false,
        default => $default,
    };
}
foreach (array_slice($argv, 1) as $release) {
    $configuration = ['care_hours' => require $release.'/config/care_hours.php'];
    file_put_contents($release.'/bootstrap/cache/config.php', '<?php return '.var_export($configuration, true).';');
}
$before = require $argv[1].'/bootstrap/cache/config.php';
$after = require $argv[2].'/bootstrap/cache/config.php';
exit($before['care_hours']['enabled'] === false && $after['care_hours']['enabled'] === true
    && $after['care_hours']['gifts_enabled'] === true && $after['care_hours']['pilot_only'] === true
    && $after['care_hours']['pilot_emails'] === ['peverelli.t@gmail.com'] ? 0 : 1);
EOF
  "$MOCK_REAL_PHP" "$SANDBOX/build-configs.php" "$current" "$candidate"
  current_hash="$(sha256sum "$current/bootstrap/cache/config.php" | cut -d ' ' -f1)"
  candidate_hash="$(sha256sum "$candidate/bootstrap/cache/config.php" | cut -d ' ' -f1)"
  validate_prepaid_release_config "$current" "$candidate" > "$SANDBOX/forward-check.log" 2>&1
  grep -q 'cached flags are valid booleans' "$SANDBOX/forward-check.log"
  ! grep -q 'first-adoption exposure' "$SANDBOX/forward-check.log"
  [[ "$(sha256sum "$current/bootstrap/cache/config.php" | cut -d ' ' -f1)" == "$current_hash" ]]
  [[ "$(sha256sum "$candidate/bootstrap/cache/config.php" | cut -d ' ' -f1)" == "$candidate_hash" ]]
  printf 'Private pilot forward configuration smoke passed (actual previous driver and disabled config, current enabled candidate, no pin or environment change, read-only validation).\n'
  exit 0
fi

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

cat > "$MOCK_BIN/sync" <<'EOF'
#!/usr/bin/env bash
[[ "${MOCK_FLOOR_SYNC_FAIL:-0}" != 1 ]]
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
if [[ "${1##*/}" == check-prepaid-release-config.php ]]; then
  printf 'prepaid-config-check %s\n' "${3:-existing}" >> "$MOCK_EVENTS"
  [[ "${MOCK_PREPAID_CONFIG_FAIL:-0}" != 1 ]]
  [[ "${3:-}" != --match-sales-state || "${MOCK_PREPAID_ROLLBACK_CONFIG_FAIL:-0}" != 1 ]]
  if [[ "${MOCK_REAL_PREPAID_CONFIG:-0}" == 1 ]]; then
    exec "$MOCK_REAL_PHP" "$@"
  fi
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
    if [[ "${MOCK_REAL_PREPAID_CONFIG:-0}" == 1 && -f config/care_hours.php ]]; then
      "$MOCK_REAL_PHP" "$MOCK_CONFIG_BUILDER" "$(pwd -P)"
    fi
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
set -Eeuo pipefail
if [[ -n "${MOCK_FOCUS_APP:-}" ]]; then
  [[ "$(readlink -f "$MOCK_FOCUS_APP")" == "$MOCK_EXPECT_ACTIVE_RELEASE" ]]
  [[ "$(cat "$MOCK_FOCUS_APP/VERSION")" == 'release one' ]]
  [[ "$(cat "$MOCK_FOCUS_APP/bootstrap/cache/views/compiled.php")" == 'release one' ]]
  [[ "$(cat "$MOCK_FOCUS_APP/storage/framework/sessions/preserved-session")" == 'durable session' ]]
  [[ "$(sha256sum "$MOCK_FOCUS_APP/.env" | cut -d ' ' -f1)" == "$MOCK_EXPECT_ENV_HASH" ]]
  printf 'focus-build-active-unchanged\n' >> "$MOCK_EVENTS"
fi
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

# Evaluate the real candidate config and checker with only this isolated .env.
# Other Laravel/build/provider commands remain mocked; the separate warmup smoke
# covers actual Laravel config:cache with the same shipping configuration.
export MOCK_CONFIG_BUILDER="$SANDBOX/build-prepaid-config.php"
cat > "$MOCK_CONFIG_BUILDER" <<'EOF'
<?php
$settings = [];
foreach (file($argv[1].'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $settings[trim($key)] = match (strtolower(trim($value))) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        default => trim($value),
    };
}
function env($key, $default = null) {
    global $settings;
    return $settings[$key] ?? $default;
}
$configuration = ['care_hours' => require $argv[1].'/config/care_hours.php'];
file_put_contents($argv[1].'/bootstrap/cache/config.php', '<?php return '.var_export($configuration, true).';');
EOF
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
if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot ]]; then
  # Historical disabled defaults must not prevent the approved pilot, and a
  # stale wider audience must never turn this release into a public launch.
  printf 'CARE_HOURS_ENABLED=false\nCARE_HOURS_GIFTS_ENABLED=false\nCARE_HOURS_PILOT_ONLY=false\nCARE_HOURS_PILOT_EMAILS=other@example.test,peverelli.t@gmail.com\n' >> "$APP_DIR/.env"
fi
printf 'durable session\n' > "$APP_DIR/storage/framework/sessions/preserved-session"
printf 'legacy compiled view\n' > "$APP_DIR/storage/framework/views/legacy-preserved.php"

deploy() {
  HOMECARE_APP_DIR="$APP_DIR" \
  HOMECARE_EXPECTED_COMMIT="${TEST_EXPECTED_COMMIT:-}" \
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

if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == plain-prepaid || "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot ]]; then
  # Exercise the real normal entrypoint and complete activation with no optional
  # commit pin, without repeating the full failure/recovery matrix below.
  [[ -z "${TEST_EXPECTED_COMMIT:-}" ]]
  awk '/^prepaid_compatibility_files\(\)/ { reading=1; next } reading && /^EOF$/ { exit } reading && !/cat <<\x27EOF\x27/ { print }' \
    "$DEPLOY_SCRIPT" > "$SANDBOX/prepaid-paths"
  while IFS= read -r path; do
    [[ -n "$path" && "$path" != /* && "$path" != *..* ]] || exit 1
    if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot ]]; then
      mkdir -p "$(dirname "$AUTHOR_DIR/$path")"
      if [[ "$path" == config/care_hours.php ]]; then
        git -C "$PROJECT_DIR" -c safe.directory="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")" \
          show f030f0a2:config/care_hours.php > "$AUTHOR_DIR/$path"
      else
        cp -- "$PROJECT_DIR/$path" "$AUTHOR_DIR/$path"
      fi
    elif [[ ! -f "$AUTHOR_DIR/$path" ]]; then
      mkdir -p "$(dirname "$AUTHOR_DIR/$path")"
      printf 'synthetic prepaid source: %s\n' "$path" > "$AUTHOR_DIR/$path"
    fi
  done < "$SANDBOX/prepaid-paths"
  mkdir -p "$AUTHOR_DIR/docs/deployment"
  "$DEPLOY_SCRIPT" --prepaid-manifest "$AUTHOR_DIR" > "$AUTHOR_DIR/docs/deployment/prepaid-care-hours-v1.sha256"
  printf 'plain prepaid release\n' > "$AUTHOR_DIR/VERSION"
  git -C "$AUTHOR_DIR" add .
  git -C "$AUTHOR_DIR" commit -m "Plain prepaid deployment fixture" >/dev/null
  git -C "$AUTHOR_DIR" push origin master >/dev/null
  plain_commit="$(git -C "$AUTHOR_DIR" rev-parse HEAD)"
  shared_env_hash="$(sha256sum "$DEPLOY_ROOT/shared/.env" | cut -d ' ' -f1)"
  old_view_hash="$(sha256sum "$first_release/bootstrap/cache/views/compiled.php" | cut -d ' ' -f1)"

  if ! MOCK_FOCUS_APP="$APP_DIR" MOCK_EXPECT_ACTIVE_RELEASE="$first_release" \
    MOCK_EXPECT_ENV_HASH="$shared_env_hash" deploy > "$SANDBOX/plain-prepaid.log" 2>&1; then
    cat "$SANDBOX/plain-prepaid.log" >&2
    exit 1
  fi
  plain_release="$(readlink -f "$APP_DIR")"
  [[ "$plain_release" != "$first_release" ]]
  [[ "$(git -C "$plain_release" rev-parse HEAD)" == "$plain_commit" ]]
  [[ "$(readlink -f "$DEPLOY_ROOT/previous")" == "$first_release" ]]
  [[ "$(cat "$DEPLOY_ROOT/compatibility/prepaid-care-hours.floor")" == homecare-prepaid-care-hours-v1 ]]
  [[ "$(cat "$APP_DIR/storage/framework/sessions/preserved-session")" == 'durable session' ]]
  [[ "$(cat "$APP_DIR/VERSION")" == 'plain prepaid release' ]]
  [[ "$(cat "$APP_DIR/bootstrap/cache/views/compiled.php")" == 'plain prepaid release' ]]
  [[ "$(cat "$first_release/VERSION")" == 'release one' ]]
  [[ "$(sha256sum "$first_release/bootstrap/cache/views/compiled.php" | cut -d ' ' -f1)" == "$old_view_hash" ]]
  [[ "$(sha256sum "$DEPLOY_ROOT/shared/.env" | cut -d ' ' -f1)" == "$shared_env_hash" ]]
  [[ "$(sha256sum "$APP_DIR/.env" | cut -d ' ' -f1)" == "$shared_env_hash" ]]
  grep -q "Selected immutable deployment commit $plain_commit" "$SANDBOX/plain-prepaid.log"
  grep -q "Deployment complete at commit $plain_commit" "$SANDBOX/plain-prepaid.log"
  grep -q 'focus-build-active-unchanged' "$MOCK_EVENTS"
  grep -q 'prepaid-config-check --require-disabled' "$MOCK_EVENTS"
  ! grep -Eq 'artisan (down|migrate:(rollback|reset|refresh))' "$MOCK_EVENTS"
  ! grep -Eq 'artisan (screening:(import|refresh-datasets|sync)|.*refresh-identities)' "$MOCK_EVENTS"
  if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot ]]; then
    "$MOCK_REAL_PHP" -r '$c = require $argv[1]; $h = $c["care_hours"]; exit($h["enabled"] === true && $h["gifts_enabled"] === true && $h["pilot_only"] === true && $h["pilot_emails"] === ["peverelli.t@gmail.com"] ? 0 : 1);' \
      "$APP_DIR/bootstrap/cache/config.php"
    grep -q 'first-adoption exposure is the approved private pilot' "$SANDBOX/plain-prepaid.log"
  fi

  # The successful no-pin path still rejects an incomplete candidate before
  # migrations/runtime changes and cannot return to the prior legacy release.
  migrations_before="$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")"
  pauses_before="$(grep -c 'runtime pause' "$MOCK_EVENTS" || true)"
  git -C "$AUTHOR_DIR" rm app/Services/CareHours/CareHourAccess.php >/dev/null
  git -C "$AUTHOR_DIR" commit -m "Incomplete prepaid fixture" >/dev/null
  git -C "$AUTHOR_DIR" push origin master >/dev/null
  if deploy > "$SANDBOX/plain-prepaid-invalid.log" 2>&1; then exit 1; fi
  grep -q 'CareHourAccess.php' "$SANDBOX/plain-prepaid-invalid.log"
  [[ "$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")" == "$migrations_before" ]]
  [[ "$(grep -c 'runtime pause' "$MOCK_EVENTS" || true)" == "$pauses_before" ]]
  [[ "$(readlink -f "$APP_DIR")" == "$plain_release" ]]
  if deploy --rollback > "$SANDBOX/plain-prepaid-legacy-rollback.log" 2>&1; then exit 1; fi
  grep -q 'Prepaid rollback compatibility check failed' "$SANDBOX/plain-prepaid-legacy-rollback.log"
  [[ "$(readlink -f "$APP_DIR")" == "$plain_release" ]]
  [[ "$(sha256sum "$DEPLOY_ROOT/shared/.env" | cut -d ' ' -f1)" == "$shared_env_hash" ]]
  if [[ "${HOMECARE_DEPLOY_SMOKE_FOCUS:-}" == private-pilot ]]; then
    # Restore the complete candidate, then model an explicit operator pause.
    # Successive ordinary deployments must never reopen it or rewrite .env.
    git -C "$AUTHOR_DIR" revert --no-edit HEAD >/dev/null
    git -C "$AUTHOR_DIR" push origin master >/dev/null
    printf 'CARE_HOURS_PRIVATE_PILOT_ENABLED=false\n' >> "$DEPLOY_ROOT/shared/.env"
    paused_env_hash="$(sha256sum "$DEPLOY_ROOT/shared/.env" | cut -d ' ' -f1)"
    for iteration in 1 2; do
      if ! deploy > "$SANDBOX/private-pilot-paused-$iteration.log" 2>&1; then
        cat "$SANDBOX/private-pilot-paused-$iteration.log" >&2
        exit 1
      fi
      "$MOCK_REAL_PHP" -r '$c = require $argv[1]; $h = $c["care_hours"]; exit($h["enabled"] === false && $h["gifts_enabled"] === false && $h["pilot_only"] === true && $h["pilot_emails"] === ["peverelli.t@gmail.com"] ? 0 : 1);' \
        "$APP_DIR/bootstrap/cache/config.php"
      [[ "$(sha256sum "$DEPLOY_ROOT/shared/.env" | cut -d ' ' -f1)" == "$paused_env_hash" ]]
      if [[ "$iteration" == 1 ]]; then
        paused_release="$(readlink -f "$APP_DIR")"
        if deploy --rollback > "$SANDBOX/private-pilot-pause-rollback.log" 2>&1; then exit 1; fi
        grep -q 'Prepaid rollback configuration check failed' "$SANDBOX/private-pilot-pause-rollback.log"
        [[ "$(readlink -f "$APP_DIR")" == "$paused_release" ]]
      fi
    done
    printf 'Private pilot deployment smoke passed (previous installed driver, real candidate config/checker, stale disabled and broad settings, exact account activation, unchanged environment, durable pause across redeploys, pause-preserving rollback).\n'
    exit 0
  fi
  printf 'Plain prepaid atomic deployment smoke passed (no pin, complete activation, immutable source, preserved live state/session/environment, manifest rejection, legacy rollback blocked).\n'
  exit 0
fi

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

# First prepaid activation pins a permanent floor, even before any sale and with
# no database polling. Exercise the actual driver with its full required inventory.
prepaid_legacy_release="$(readlink -f "$APP_DIR")"
prepaid_legacy_commit="$(git -C "$AUTHOR_DIR" rev-parse HEAD)"
awk '/^prepaid_compatibility_files\(\)/ { reading=1; next } reading && /^EOF$/ { exit } reading && !/cat <<\x27EOF\x27/ { print }' \
  "$DEPLOY_SCRIPT" > "$SANDBOX/prepaid-paths"
while IFS= read -r path; do
  [[ -n "$path" && "$path" != /* && "$path" != *..* ]] || exit 1
  if [[ ! -f "$AUTHOR_DIR/$path" ]]; then
    mkdir -p "$(dirname "$AUTHOR_DIR/$path")"
    printf 'synthetic prepaid source: %s\n' "$path" > "$AUTHOR_DIR/$path"
  fi
done < "$SANDBOX/prepaid-paths"
mkdir -p "$AUTHOR_DIR/docs/deployment"
"$DEPLOY_SCRIPT" --prepaid-manifest "$AUTHOR_DIR" > "$AUTHOR_DIR/docs/deployment/prepaid-care-hours-v1.sha256"
git -C "$AUTHOR_DIR" add .
git -C "$AUTHOR_DIR" commit -m "First prepaid-compatible release" >/dev/null
git -C "$AUTHOR_DIR" push origin master >/dev/null
floor="$DEPLOY_ROOT/compatibility/prepaid-care-hours.floor"

# Optional explicit pins still reject a mismatch before migrations, pauses or activation.
migrations_before="$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")"
pauses_before="$(grep -c 'runtime pause' "$MOCK_EVENTS")"
if TEST_EXPECTED_COMMIT="$prepaid_legacy_commit" deploy > "$SANDBOX/prepaid-moved-branch.log" 2>&1; then exit 1; fi
grep -q 'not reviewed commit' "$SANDBOX/prepaid-moved-branch.log"
if TEST_EXPECTED_COMMIT=master deploy > "$SANDBOX/prepaid-invalid-pin.log" 2>&1; then exit 1; fi
grep -q 'must be a full lowercase Git commit SHA' "$SANDBOX/prepaid-invalid-pin.log"
[[ "$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")" == "$migrations_before" ]]
[[ "$(grep -c 'runtime pause' "$MOCK_EVENTS")" == "$pauses_before" ]]
[[ "$(readlink -f "$APP_DIR")" == "$prepaid_legacy_release" && ! -f "$floor" ]]

# Cached enabled/malformed flags abort the first introduction before any drain,
# traffic switch or compatibility floor. The PHP helper has separate real tests.
if MOCK_PREPAID_CONFIG_FAIL=1 deploy > "$SANDBOX/prepaid-enabled-introduction.log" 2>&1; then exit 1; fi
grep -q "Selected immutable deployment commit $(git --git-dir="$ORIGIN_DIR" rev-parse refs/heads/master)" "$SANDBOX/prepaid-enabled-introduction.log"
grep -q 'Prepaid cached configuration is unsafe' "$SANDBOX/prepaid-enabled-introduction.log"
grep -q 'prepaid-config-check --require-disabled' "$MOCK_EVENTS"
[[ "$(grep -c 'runtime pause' "$MOCK_EVENTS")" == "$pauses_before" ]]
[[ "$(readlink -f "$APP_DIR")" == "$prepaid_legacy_release" && ! -f "$floor" ]]

# A failed durable flush cannot switch releases; its conservative floor remains.
if MOCK_FLOOR_SYNC_FAIL=1 deploy > "$SANDBOX/prepaid-before-switch.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$prepaid_legacy_release" && -f "$floor" ]]
grep -q 'cannot confirm durable floor persistence' "$SANDBOX/prepaid-before-switch.log"
[[ ! -f "$MOCK_STATE/paused" ]]

# A post-switch failure must keep first prepaid code, not select its incompatible
# previous screening release. Existing screening recovery still resumes safely.
if MOCK_HEALTH_FAIL=1 deploy > "$SANDBOX/prepaid-first-health-failure.log" 2>&1; then exit 1; fi
first_prepaid_release="$(readlink -f "$APP_DIR")"
[[ "$first_prepaid_release" != "$prepaid_legacy_release" ]]
[[ "$(cat "$floor")" == homecare-prepaid-care-hours-v1 && ! -f "$MOCK_STATE/paused" ]]
grep -q 'Refusing a pre-prepaid release' "$SANDBOX/prepaid-first-health-failure.log"
if deploy --rollback > "$SANDBOX/prepaid-unsafe-manual.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$first_prepaid_release" ]]
grep -q 'Prepaid rollback compatibility check failed' "$SANDBOX/prepaid-unsafe-manual.log"

# Forward deployment to an old branch is the same prohibited compatibility loss.
git -C "$AUTHOR_DIR" branch pre-prepaid "$prepaid_legacy_commit"
git -C "$AUTHOR_DIR" push origin pre-prepaid >/dev/null
migrations_before="$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")"
if HOMECARE_DEPLOY_BRANCH=pre-prepaid deploy > "$SANDBOX/prepaid-unsafe-forward.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$first_prepaid_release" ]]
[[ "$(grep -c 'artisan migrate --force' "$MOCK_EVENTS")" == "$migrations_before" ]]
grep -q 'Refusing a pre-prepaid release' "$SANDBOX/prepaid-unsafe-forward.log"

# A missing floor is reconstructed from verified current code before refusing
# an incompatible rollback. Unknown state is preserved and blocks a switch.
rm -- "$floor"
if deploy --rollback > "$SANDBOX/prepaid-recover-floor.log" 2>&1; then exit 1; fi
[[ "$(cat "$floor")" == homecare-prepaid-care-hours-v1 ]]
printf 'unknown-version\n' > "$floor"
if deploy > "$SANDBOX/prepaid-unknown-floor.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$first_prepaid_release" && "$(cat "$floor")" == unknown-version ]]
printf 'homecare-prepaid-care-hours-v1\n' > "$floor"

# Verified prepaid-to-prepaid deployment and rollback remain available, even if
# the active handler has been damaged and needs repair from a retained release.
if ! deploy > "$SANDBOX/prepaid-compatible.log" 2>&1; then cat "$SANDBOX/prepaid-compatible.log" >&2; exit 1; fi
grep -q 'prepaid-config-check existing' "$MOCK_EVENTS"
second_prepaid_release="$(readlink -f "$APP_DIR")"
pauses_before="$(grep -c 'runtime pause' "$MOCK_EVENTS")"
if MOCK_PREPAID_ROLLBACK_CONFIG_FAIL=1 deploy --rollback > "$SANDBOX/prepaid-rollback-flags.log" 2>&1; then exit 1; fi
grep -q 'Prepaid rollback configuration check failed' "$SANDBOX/prepaid-rollback-flags.log"
[[ "$(readlink -f "$APP_DIR")" == "$second_prepaid_release" ]]
[[ "$(grep -c 'runtime pause' "$MOCK_EVENTS")" == "$pauses_before" ]]
printf 'damaged handler\n' > "$second_prepaid_release/app/Services/CareHours/CareHourWebhookService.php"
if ! deploy --rollback > "$SANDBOX/prepaid-repair-current.log" 2>&1; then cat "$SANDBOX/prepaid-repair-current.log" >&2; exit 1; fi
[[ "$(readlink -f "$APP_DIR")" == "$first_prepaid_release" ]]

# A retained target with changed source cannot claim compatibility just because
# its marker remains; this also catches a stale manifest on a partial deployment.
if deploy --rollback > "$SANDBOX/prepaid-corrupt-target.log" 2>&1; then exit 1; fi
grep -q 'required source is missing or changed' "$SANDBOX/prepaid-corrupt-target.log"
[[ "$(readlink -f "$APP_DIR")" == "$first_prepaid_release" ]]

cp "$first_prepaid_release/app/Services/CareHours/CareHourWebhookService.php" "$second_prepaid_release/app/Services/CareHours/CareHourWebhookService.php"
rm -- "$second_prepaid_release/resources/views/care-hours/partials/messages.blade.php"
if deploy --rollback > "$SANDBOX/prepaid-missing-claim-view.log" 2>&1; then exit 1; fi
grep -q 'partials/messages.blade.php' "$SANDBOX/prepaid-missing-claim-view.log"
cp "$first_prepaid_release/resources/views/care-hours/partials/messages.blade.php" "$second_prepaid_release/resources/views/care-hours/partials/messages.blade.php"
rm -- "$second_prepaid_release/database/migrations/2026_10_21_000100_create_care_hour_gifts.php"
if deploy --rollback > "$SANDBOX/prepaid-missing-migration.log" 2>&1; then exit 1; fi
grep -q 'create_care_hour_gifts.php' "$SANDBOX/prepaid-missing-migration.log"

# The permanent floor also allows a clean forward repair of damaged active code.
printf 'damaged active recovery\n' > "$first_prepaid_release/app/Console/Commands/ReconcileCareHourBookings.php"
if ! deploy > "$SANDBOX/prepaid-forward-repair.log" 2>&1; then cat "$SANDBOX/prepaid-forward-repair.log" >&2; exit 1; fi
[[ "$(readlink -f "$APP_DIR")" != "$first_prepaid_release" ]]

# Automatic health-failure rollback must not resurrect stale sales settings.
before_failed_deploy="$(readlink -f "$APP_DIR")"
if MOCK_HEALTH_FAIL=1 MOCK_PREPAID_ROLLBACK_CONFIG_FAIL=1 deploy > "$SANDBOX/prepaid-auto-rollback-flags.log" 2>&1; then exit 1; fi
[[ "$(readlink -f "$APP_DIR")" != "$before_failed_deploy" ]]
grep -q 'Prepaid rollback configuration differs or cannot be verified' "$SANDBOX/prepaid-auto-rollback-flags.log"
[[ ! -f "$MOCK_STATE/paused" ]]

# Deployment must not run network backfill, enrollment or long dataset imports.
! grep -Eq 'artisan (screening:(import|refresh-datasets|sync)|.*refresh-identities)' "$MOCK_EVENTS"
! grep -Eq 'artisan migrate:(rollback|reset|refresh)' "$MOCK_EVENTS"

printf 'Atomic deployment smoke test passed.\n'
