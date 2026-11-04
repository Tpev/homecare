#!/usr/bin/env bash
# Read-only deployment validation through the actual already-shipped v2 driver.
# No Laravel boot, database, provider, server access or application activation.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-prepaid-public.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-prepaid-public.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
fail() { printf 'Public prepaid forward smoke failure: %s\n' "$*" >&2; exit 1; }
reject() {
  if ( "$@" ) > "$SANDBOX/rejected.log" 2>&1; then fail "Unexpected success: $*"; fi
}
REAL_PHP="${HOMECARE_SMOKE_REAL_PHP:-$(command -v php || true)}"
[[ -x "$REAL_PHP" ]] || fail 'Set HOMECARE_SMOKE_REAL_PHP to local PHP.'
git_root="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")"
installed=6151c79540ffb3397b39cf1b91f135709fe1985a
git -C "$PROJECT_DIR" -c safe.directory="$git_root" show "$installed:deploy.sh" > "$SANDBOX/installed-driver.sh"
awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$SANDBOX/installed-driver.sh" > "$SANDBOX/functions.sh"
HOMECARE_APP_DIR="$SANDBOX/current" HOMECARE_DEPLOY_ROOT="$SANDBOX/deploy" \
  HOMECARE_PHP_BIN="$REAL_PHP" source "$SANDBOX/functions.sh"
current="$SANDBOX/current"
candidate="$SANDBOX/candidate"
paused="$SANDBOX/paused"
legacy_pause="$SANDBOX/legacy-pause"
for release in "$current" "$candidate" "$paused" "$legacy_pause"; do
  mkdir -p "$release/bootstrap/cache" "$release/config" "$release/docs/deployment" "$release/scripts/deployment"
  # Only configuration handoff is tested here. Manifest/source integrity has its
  # own full-inventory smoke suite and is not replaced by this header fixture.
  printf 'homecare-prepaid-care-hours-v2\n' > "$release/$PREPAID_MANIFEST"
done
git -C "$PROJECT_DIR" -c safe.directory="$git_root" show "$installed:config/care_hours.php" > "$current/config/care_hours.php"
git -C "$PROJECT_DIR" -c safe.directory="$git_root" show "$installed:scripts/deployment/check-prepaid-release-config.php" > "$current/scripts/deployment/check-prepaid-release-config.php"
for release in "$candidate" "$paused" "$legacy_pause"; do
  cp -- "$PROJECT_DIR/config/care_hours.php" "$release/config/care_hours.php"
  cp -- "$PROJECT_DIR/scripts/deployment/check-prepaid-release-config.php" "$release/scripts/deployment/check-prepaid-release-config.php"
done
mkdir -p "$SANDBOX/shared" "$SANDBOX/public-pause" "$SANDBOX/private-pause"
cat > "$SANDBOX/shared/.env" <<'EOF'
CARE_HOURS_ENABLED=false
CARE_HOURS_GIFTS_ENABLED=false
CARE_HOURS_PILOT_ONLY=true
CARE_HOURS_PILOT_EMAILS=old-pilot@example.test
EOF
cp -- "$SANDBOX/shared/.env" "$SANDBOX/public-pause/.env"
printf 'CARE_HOURS_PUBLIC_ENABLED=false\n' >> "$SANDBOX/public-pause/.env"
cp -- "$SANDBOX/shared/.env" "$SANDBOX/private-pause/.env"
printf 'CARE_HOURS_PRIVATE_PILOT_ENABLED=false\n' >> "$SANDBOX/private-pause/.env"
shared_hash="$(sha256sum "$SANDBOX/shared/.env" | cut -d ' ' -f1)"
cat > "$SANDBOX/cache-config.php" <<'EOF'
<?php
require $argv[1].'/vendor/autoload.php';
// Exercise the real Laravel env conversion and candidate configuration source.
// Clear only this child process's rollout variables; never load the root .env.
foreach (['CARE_HOURS_PUBLIC_ENABLED', 'CARE_HOURS_PRIVATE_PILOT_ENABLED', 'CARE_HOURS_ENROLLMENT_ENABLED',
    'CARE_HOURS_SALES_ENABLED', 'CARE_HOURS_CONTRIBUTIONS_ENABLED', 'CARE_HOURS_WELCOME_BONUS_ENABLED'] as $key) {
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}
Dotenv\Dotenv::create(Illuminate\Support\Env::getRepository(), $argv[3])->load();
$flags = require $argv[2].'/config/care_hours.php';
$public = $argv[4] === 'public';
$enabled = $argv[5] === 'enabled';
if ($flags['enabled'] !== $enabled || $flags['gifts_enabled'] !== $enabled
    || $flags['pilot_only'] !== ! $public || $flags['wallet_scope'] !== 'family'
    || $flags['pilot_emails'] !== ($public ? [] : ['peverelli.t@gmail.com'])) {
    throw new RuntimeException('Actual release configuration did not match the expected rollout.');
}
file_put_contents($argv[2].'/bootstrap/cache/config.php', '<?php return '.var_export(['care_hours' => $flags], true).';');
EOF
php_config() { "$REAL_PHP" -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client "$SANDBOX/cache-config.php" "$PROJECT_DIR" "$@"; }
php_config "$current" "$SANDBOX/shared" pilot enabled
php_config "$candidate" "$SANDBOX/shared" public enabled
php_config "$paused" "$SANDBOX/public-pause" public disabled
php_config "$legacy_pause" "$SANDBOX/private-pause" public disabled
sha256sum "$current/bootstrap/cache/config.php" "$candidate/bootstrap/cache/config.php" \
  "$paused/bootstrap/cache/config.php" "$legacy_pause/bootstrap/cache/config.php" > "$SANDBOX/cache-hashes"

validate_prepaid_release_config "$current" "$candidate" > "$SANDBOX/forward.log" 2>&1
grep -q 'cached flags are valid booleans' "$SANDBOX/forward.log"
! grep -q 'first-adoption exposure' "$SANDBOX/forward.log"
validate_prepaid_release_config "$candidate" "$candidate" > "$SANDBOX/redeploy.log" 2>&1
validate_prepaid_release_config "$candidate" "$paused" > "$SANDBOX/pause.log" 2>&1
validate_prepaid_release_config "$candidate" "$legacy_pause" > "$SANDBOX/legacy-pause.log" 2>&1
validate_prepaid_rollback_config "$candidate" "$candidate" > "$SANDBOX/rollback-match.log" 2>&1
validate_prepaid_rollback_config "$paused" "$legacy_pause" > "$SANDBOX/rollback-paused.log" 2>&1
reject validate_prepaid_rollback_config "$candidate" "$current"
grep -q 'Rollback must preserve' "$SANDBOX/rejected.log"
reject validate_prepaid_rollback_config "$paused" "$candidate"
grep -q 'Rollback must preserve' "$SANDBOX/rejected.log"
reject validate_prepaid_rollback_config "$candidate" "$paused"
grep -q 'Rollback must preserve' "$SANDBOX/rejected.log"
sha256sum --check "$SANDBOX/cache-hashes" > "$SANDBOX/cache-check.log"
[[ "$(sha256sum "$SANDBOX/shared/.env" | cut -d ' ' -f1)" == "$shared_hash" ]] || fail 'Shared environment changed.'
printf 'Public prepaid forward smoke passed (installed 6151c795 driver, actual candidate config/checker, public defaults, both pauses, protected rollback, unchanged environment/caches).\n'
