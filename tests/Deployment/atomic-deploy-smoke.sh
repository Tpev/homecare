#!/usr/bin/env bash
set -Eeuo pipefail

case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS="winsymlinks:nativestrict" ;;
esac

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SANDBOX="$(mktemp -d "${TMPDIR:-/tmp}/homecare-deploy-smoke.XXXXXX")"
APP_DIR="$SANDBOX/homecare"
DEPLOY_ROOT="$SANDBOX/homecare-deploy"
ORIGIN_DIR="$SANDBOX/origin.git"
AUTHOR_DIR="$SANDBOX/author"
MOCK_BIN="$SANDBOX/mock-bin"

cleanup() {
  case "$SANDBOX" in
    "${TMPDIR:-/tmp}"/homecare-deploy-smoke.*) rm -rf -- "$SANDBOX" ;;
  esac
}
trap cleanup EXIT

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
exit 0
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
if [[ "${1:-}" != "artisan" ]]; then
  exit 0
fi
case "${2:-}" in
  config:cache) touch bootstrap/cache/config.php ;;
  route:cache) touch bootstrap/cache/routes.php ;;
  view:cache) mkdir -p storage/framework/views ;;
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
  mkdir -p public/build/assets
  printf '{}\n' > public/build/manifest.json
  printf 'new asset\n' > public/build/assets/app-new.js
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
  "$APP_DIR/voice-agent/cmd/server"
cp "$PROJECT_DIR/deploy.sh" "$APP_DIR/deploy.sh"
chmod 775 "$APP_DIR/deploy.sh"
printf '#!/usr/bin/env php\n' > "$APP_DIR/artisan"
printf '{}\n' > "$APP_DIR/composer.json"
printf '{}\n' > "$APP_DIR/package.json"
printf 'package main\n' > "$APP_DIR/voice-agent/cmd/server/main.go"
printf 'old asset\n' > "$APP_DIR/public/build/assets/app-old.js"
printf '{}\n' > "$APP_DIR/public/build/manifest.json"
printf 'release one\n' > "$APP_DIR/VERSION"
git -C "$APP_DIR" add .
git -C "$APP_DIR" commit -m "Release one" >/dev/null
git -C "$APP_DIR" push -u origin master >/dev/null

printf 'APP_ENV=production\nAPP_KEY=base64:test\n' > "$APP_DIR/.env"
printf 'durable session\n' > "$APP_DIR/storage/framework/sessions/preserved-session"

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
  "$PROJECT_DIR/deploy.sh" "$@"
}

if ! deploy > "$SANDBOX/first-deploy.log" 2>&1; then
  cat "$SANDBOX/first-deploy.log" >&2
  exit 1
fi
[[ -L "$APP_DIR" ]]
[[ -f "$APP_DIR/storage/framework/sessions/preserved-session" ]]
[[ -f "$APP_DIR/public/build/assets/app-old.js" ]]
grep -q 'No Laravel maintenance mode or deployment-long 503 was used.' "$SANDBOX/first-deploy.log"
! grep -q 'Entering maintenance mode' "$SANDBOX/first-deploy.log"

first_release="$(readlink -f "$APP_DIR")"
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

if ! deploy --rollback > "$SANDBOX/rollback.log" 2>&1; then
  cat "$SANDBOX/rollback.log" >&2
  exit 1
fi
[[ "$(readlink -f "$APP_DIR")" == "$first_release" ]]
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
grep -q 'Rolling the application symlink back' "$SANDBOX/failed-deploy.log"

deploy --status > "$SANDBOX/status.log"
grep -q 'Deployment layout: atomic releases' "$SANDBOX/status.log"

printf 'Atomic deployment smoke test passed.\n'
