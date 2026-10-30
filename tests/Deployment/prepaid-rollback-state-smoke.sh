#!/usr/bin/env bash
# Focused state-machine tests; the atomic smoke test checks the full inventory.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-prepaid-rollback.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-prepaid-rollback.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$PROJECT_DIR/deploy.sh" > "$SANDBOX/functions.sh"
source "$SANDBOX/functions.sh"
DEPLOY_ROOT="$SANDBOX/deploy"
COMPATIBILITY_DIR="$DEPLOY_ROOT/compatibility"
PREPAID_FLOOR_FILE="$COMPATIBILITY_DIR/prepaid-care-hours.floor"
mkdir -p "$DEPLOY_ROOT" "$SANDBOX/legacy" "$SANDBOX/one" "$SANDBOX/two"
# Small synthetic inventory isolates guard state. The real fixed path list is
# exercised unchanged by atomic-deploy-smoke.sh, including handler integrity.
prepaid_compatibility_files() { printf '%s\n' app/handler.php app/recovery.php; }
prepaid_wallet_compatibility_files() { printf '%s\n' app/family-wallet.php; }
sudo() { if [[ "$1" != chown ]]; then "$@"; fi; }
sync() { [[ "${FAIL_FLUSH:-0}" != 1 ]]; }
# The standalone PHP smoke checks real caches; this suite isolates driver state.
validate_prepaid_rollback_config() { [[ "${FAIL_ROLLBACK_CONFIG:-0}" != 1 ]]; }
fail() { printf 'Prepaid rollback smoke failure: %s\n' "$*" >&2; exit 1; }
reject() { if "$@" > "$SANDBOX/rejected.log" 2>&1; then fail "Unexpected success: $*"; fi; }
for name in one two; do
  mkdir -p "$SANDBOX/$name/app" "$SANDBOX/$name/docs/deployment"
  printf 'handler\n' > "$SANDBOX/$name/app/handler.php"
  printf 'recovery\n' > "$SANDBOX/$name/app/recovery.php"
  print_prepaid_manifest "$SANDBOX/$name" > "$SANDBOX/$name/$PREPAID_MANIFEST"
done
one="$SANDBOX/one" two="$SANDBOX/two" legacy="$SANDBOX/legacy"

require_prepaid_compatibility "$legacy" "$legacy"
[[ ! -e "$PREPAID_FLOOR_FILE" ]] || fail 'Legacy-only deployment pinned a floor.'
require_prepaid_compatibility "$legacy" "$one"
[[ ! -e "$PREPAID_FLOOR_FILE" ]] || fail 'Inactive candidate pinned a floor during preflight.'
FAIL_FLUSH=1 reject require_prepaid_compatibility "$legacy" "$one" activate
[[ -f "$PREPAID_FLOOR_FILE" ]] || fail 'A failed flush relaxed the conservative floor.'
require_prepaid_compatibility "$legacy" "$one" activate
reject require_prepaid_compatibility "$legacy" "$legacy"
require_prepaid_compatibility "$one" "$two"

printf 'broken\n' > "$one/app/handler.php"
require_prepaid_compatibility "$one" "$two"
reject require_prepaid_compatibility "$two" "$one"
printf 'handler\n' > "$one/app/handler.php"
rm -- "$one/app/recovery.php"
require_prepaid_compatibility "$one" "$two"
reject require_prepaid_compatibility "$two" "$one"
printf 'recovery\n' > "$one/app/recovery.php"

cp "$one/$PREPAID_MANIFEST" "$SANDBOX/saved-manifest"
printf 'homecare-prepaid-care-hours-v3\n' > "$one/$PREPAID_MANIFEST"
reject require_prepaid_compatibility "$one" "$two"
cp "$SANDBOX/saved-manifest" "$one/$PREPAID_MANIFEST"
printf 'unknown\n' > "$PREPAID_FLOOR_FILE"
reject require_prepaid_compatibility "$one" "$two"
[[ "$(cat "$PREPAID_FLOOR_FILE")" == unknown ]] || fail 'Unknown floor was overwritten.'
rm -- "$PREPAID_FLOOR_FILE"
require_prepaid_compatibility "$one" "$two"
[[ "$(cat "$PREPAID_FLOOR_FILE")" == homecare-prepaid-care-hours-v1 ]] || fail 'Current compatible release did not recover its floor.'

# A partial release with no floor is unknown, not an innocent legacy release.
rm -- "$PREPAID_FLOOR_FILE"
mkdir -p "$legacy/config"
touch "$legacy/config/care_hours.php"
reject require_prepaid_compatibility "$legacy" "$two"
rm -- "$legacy/config/care_hours.php"
require_prepaid_compatibility "$one" "$two"

# Automatic rollback combines prepaid and screening safety; neither bypasses
# the other, and a failed screening-state read remains unsafe.
NEW_RELEASE="$two" OLD_RELEASE="$legacy"
reject rollback_target_is_safe
NEW_RELEASE="$two" OLD_RELEASE="$one"
is_screening_release() { [[ "$1" == "$two" ]]; }
run_artisan() { [[ "${FAIL_STATUS:-0}" != 1 ]] && printf '%s\n' "${SCREENING_STATE:-active}"; }
SCREENING_ACTIVATION_ATTEMPTED=1
reject rollback_target_is_safe
SCREENING_ACTIVATION_ATTEMPTED=0
SCREENING_STATE=pending rollback_target_is_safe
FAIL_STATUS=1 reject rollback_target_is_safe
is_screening_release() { return 0; }
rollback_target_is_safe
FAIL_ROLLBACK_CONFIG=1 reject rollback_target_is_safe
printf 'broken\n' > "$OLD_RELEASE/app/handler.php"
reject rollback_target_is_safe
printf 'unknown\n' > "$PREPAID_FLOOR_FILE"
reject rollback_target_is_safe

# The bridge can install v2, but a pinned v2 floor can never admit recipient-only
# handlers, including automatic rollback after a health failure or a failed flush.
wallet="$SANDBOX/wallet" repair="$SANDBOX/wallet-repair"
for release in "$wallet" "$repair"; do
  mkdir -p "$release/app" "$release/docs/deployment"
  printf 'handler\n' > "$release/app/handler.php"
  printf 'recovery\n' > "$release/app/recovery.php"
  printf 'family wallet\n' > "$release/app/family-wallet.php"
  print_prepaid_manifest "$release" 2 > "$release/$PREPAID_MANIFEST"
  [[ "$(prepaid_release_version "$release")" == 2 ]] || fail 'Wallet contract was not recognized.'
done
printf 'handler\n' > "$one/app/handler.php"
printf 'homecare-prepaid-care-hours-v1\n' > "$PREPAID_FLOOR_FILE"
require_prepaid_compatibility "$one" "$wallet"
[[ "$(prepaid_floor_version)" == 1 ]] || fail 'Preflight pinned an inactive wallet candidate.'
FAIL_FLUSH=1 reject require_prepaid_compatibility "$one" "$wallet" activate
[[ "$(prepaid_floor_version)" == 2 ]] || fail 'A failed wallet flush relaxed the floor.'
reject require_prepaid_compatibility "$wallet" "$one"
reject require_prepaid_compatibility "$wallet" "$legacy"
require_prepaid_compatibility "$wallet" "$repair" activate
persist_prepaid_floor 1
[[ "$(prepaid_floor_version)" == 2 ]] || fail 'The wallet floor was downgraded.'
NEW_RELEASE="$wallet" OLD_RELEASE="$one"
reject rollback_target_is_safe
OLD_RELEASE="$repair"
rollback_target_is_safe
printf 'broken\n' > "$wallet/app/family-wallet.php"
require_prepaid_compatibility "$wallet" "$repair"
reject require_prepaid_compatibility "$repair" "$wallet"
rm -- "$PREPAID_FLOOR_FILE"
printf 'family wallet\n' > "$wallet/app/family-wallet.php"
reject require_prepaid_compatibility "$wallet" "$one"
[[ "$(prepaid_floor_version)" == 2 ]] || fail 'Active wallet release did not recover its floor.'
printf 'homecare-prepaid-care-hours-v1\n' > "$PREPAID_FLOOR_FILE"
reject require_prepaid_compatibility "$wallet" "$one"
[[ "$(prepaid_floor_version)" == 2 ]] || fail 'Active wallet contract did not upgrade a stale floor.'
rm -- "$repair/app/family-wallet.php"
reject require_prepaid_compatibility "$wallet" "$repair"

printf 'Prepaid rollback state smoke passed (v1/v2 floors, integrity, repair, flush failure and screening cases).\n'
