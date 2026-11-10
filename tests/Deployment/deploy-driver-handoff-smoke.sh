#!/usr/bin/env bash
# Disposable local Git only; never boot Laravel or control host services.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-driver-handoff.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-driver-handoff.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
fail() { printf 'Driver handoff smoke failure: %s\n' "$*" >&2; exit 1; }
reject() { if ( "$@" ) > "$SANDBOX/rejected.log" 2>&1; then fail "Unexpected success: $*"; fi; }
awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$PROJECT_DIR/deploy.sh" > "$SANDBOX/functions.sh"
source "$SANDBOX/functions.sh"
DEPLOY_ROOT="$SANDBOX/deploy"
REPOSITORY_DIR="$DEPLOY_ROOT/repository.git"
LOCK_FILE="$SANDBOX/deploy.lock"
EXPECTED_COMMIT=''
author="$SANDBOX/author"
mkdir -p "$DEPLOY_ROOT" "$author" "$SANDBOX/live"
printf 'live source stays unchanged\n' > "$SANDBOX/live/deploy.sh"
live_hash="$(sha256sum "$SANDBOX/live/deploy.sh")"
git init -b master "$author" >/dev/null
git -C "$author" config user.name 'Driver Handoff Test'
git -C "$author" config user.email 'driver-handoff@example.invalid'

# The selected driver uses the actual continuation verifier and source checker,
# with a tiny manifest so the handoff tests isolate inventory changes efficiently.
cat > "$author/deploy.sh" <<'EOF'
#!/usr/bin/env bash
# homecare-deploy-handoff-v1
set -Eeuo pipefail
source "$TEST_FUNCTIONS"
DEPLOY_DRIVER_SOURCE="$0"
DEPLOY_COMMIT="$2"
[[ "$1" == --deploy-selected && "$#" == 2 ]]
verify_deployment_continuation
[[ "$(cat /dev/fd/9)" == 'lock must survive exec' ]]
printf 'lock retained\n' >&9
# On hosts with flock, an independent competing opener must still fail.
if command -v flock >/dev/null 2>&1; then
  if (exec 9>&-; flock -n "$LOCK_FILE" -c true); then exit 41; fi
fi
prepaid_compatibility_files() { printf '%s\n' app/first.php; }
prepaid_wallet_compatibility_files() { printf '%s\n' app/second.php; }
[[ "$(prepaid_release_version "$TEST_CANDIDATE")" == 2 ]]
printf '%s\n' "$DEPLOY_COMMIT" > "$TEST_RESULT"
EOF
# Make the old inventory one item shorter; it must reject the new manifest.
prepaid_compatibility_files() { printf '%s\n' app/first.php; }
prepaid_wallet_compatibility_files() { printf '%s\n' app/second.php; }
candidate="$SANDBOX/candidate"
mkdir -p "$candidate/app" "$candidate/docs/deployment"
printf 'first\n' > "$candidate/app/first.php"
printf 'second\n' > "$candidate/app/second.php"
print_prepaid_manifest "$candidate" 2 > "$candidate/$PREPAID_MANIFEST"
prepaid_wallet_compatibility_files() { :; }
reject prepaid_release_version "$candidate"
grep -q 'running driver expects 2 for v2' "$SANDBOX/rejected.log"

git -C "$author" add deploy.sh
git -C "$author" commit -m 'Compatible selected driver' >/dev/null
selected="$(git -C "$author" rev-parse HEAD)"
# Move master after capturing selection. The handoff must still use selected.
printf '\nexit 99\n' >> "$author/deploy.sh"
git -C "$author" commit -am 'Later branch tip' >/dev/null
git clone --mirror "$author" "$REPOSITORY_DIR" >/dev/null 2>&1
export HOMECARE_DEPLOY_ROOT="$DEPLOY_ROOT" HOMECARE_DEPLOY_LOCK="$LOCK_FILE"
export TEST_FUNCTIONS="$SANDBOX/functions.sh" TEST_CANDIDATE="$candidate" TEST_RESULT="$SANDBOX/result"
DEPLOY_COMMIT="$selected"
DEPLOY_DRIVER_SOURCE="$SANDBOX/live/deploy.sh"
# No lock, wrong lock, mismatched expected commit, dirty driver and missing Git
# driver all fail before the candidate marker. No escape hatch is accepted.
reject handoff_deployment_driver
grep -q 'inherited deployment lock' "$SANDBOX/rejected.log"
exec 9<>"$SANDBOX/wrong.lock"
reject handoff_deployment_driver
exec 9>&-
exec 9<>"$LOCK_FILE"
printf 'lock must survive exec\n' >&9
if command -v flock >/dev/null 2>&1; then flock -n 9; fi
reject verify_deployment_continuation
grep -q 'differs from the selected Git commit' "$SANDBOX/rejected.log"
git --git-dir="$REPOSITORY_DIR" show "$selected:deploy.sh" > "$SANDBOX/matching.sh"
DEPLOY_DRIVER_SOURCE="$SANDBOX/matching.sh"
EXPECTED_COMMIT="$(git -C "$author" rev-parse HEAD)" reject verify_deployment_continuation
grep -q 'differs from HOMECARE_EXPECTED_COMMIT' "$SANDBOX/rejected.log"
verify_deployment_continuation
handoff_deployment_driver
[[ ! -e "$TEST_RESULT" ]] || fail 'Matching driver unexpectedly re-executed.'
DEPLOY_DRIVER_SOURCE="$SANDBOX/live/deploy.sh"
(handoff_deployment_driver) > "$SANDBOX/handoff.log" 2>&1 || { cat "$SANDBOX/handoff.log"; fail 'Handoff failed.'; }
[[ "$(cat "$TEST_RESULT")" == "$selected" ]] || fail 'Handoff followed a moving branch.'
[[ "$(sha256sum "$SANDBOX/live/deploy.sh")" == "$live_hash" ]] || fail 'Handoff changed the live driver.'
[[ ! -e "$DEPLOY_ROOT/releases" ]] || fail 'Handoff touched release preparation.'
grep -q 'same lock; no second fetch' "$SANDBOX/handoff.log"
staged="$(find "$DEPLOY_ROOT" -name deploy.sh -print)"
[[ "$(git --git-dir="$REPOSITORY_DIR" hash-object --no-filters "$staged")" == "$(selected_deployment_driver_blob)" ]]
# A damaged candidate is still rejected by the handed-off validator.
printf 'damaged\n' > "$candidate/app/second.php"
rm -- "$TEST_RESULT"
# Restore the descriptor's contents before another child checks inheritance.
: > "$LOCK_FILE"
exec 9<>"$LOCK_FILE"
printf 'lock must survive exec\n' >&9
if command -v flock >/dev/null 2>&1; then flock -n 9; fi
reject handoff_deployment_driver
grep -q 'required source is missing or changed' "$SANDBOX/rejected.log"
[[ ! -e "$TEST_RESULT" ]] || fail 'Corrupt source reached deployment marker.'
# A commit without a driver cannot fall back to the installed script.
git -C "$author" rm deploy.sh >/dev/null
git -C "$author" commit -m 'Missing driver fixture' >/dev/null
git --git-dir="$REPOSITORY_DIR" fetch origin '+refs/heads/master:refs/heads/master' >/dev/null 2>&1
DEPLOY_COMMIT="$(git -C "$author" rev-parse HEAD)"
reject handoff_deployment_driver
grep -q 'regular deployment driver' "$SANDBOX/rejected.log"
exec 9>&-
printf 'Deployment driver handoff smoke passed (old inventory rejection, Git-selected driver, moved branch, inherited descriptor, unchanged live checkout, tampered sources, wrong driver/pin/lock, missing driver).\n'
