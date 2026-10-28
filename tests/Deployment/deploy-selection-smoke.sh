#!/usr/bin/env bash
# Test release selection using only disposable local Git repositories.
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-deploy-selection.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-deploy-selection.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
fail() { printf 'Deployment selection smoke failure: %s\n' "$*" >&2; exit 1; }

awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$PROJECT_DIR/deploy.sh" > "$SANDBOX/functions.sh"
source "$SANDBOX/functions.sh"

origin="$SANDBOX/origin.git"
author="$SANDBOX/author"
current="$SANDBOX/current"
DEPLOY_ROOT="$SANDBOX/deploy"
REPOSITORY_DIR="$DEPLOY_ROOT/repository.git"
RELEASES_DIR="$DEPLOY_ROOT/releases"
BRANCH=master
EXPECTED_COMMIT=''
DEPLOY_COMMIT=''
mkdir -p "$DEPLOY_ROOT"

git init --bare "$origin" >/dev/null
git init -b master "$author" >/dev/null
git -C "$author" config user.name 'Deployment Selection Test'
git -C "$author" config user.email 'deployment-selection@example.invalid'
git -C "$author" remote add origin "$origin"
printf 'first\n' > "$author/VERSION"
git -C "$author" add VERSION
git -C "$author" commit -m 'First local fixture release' >/dev/null
git -C "$author" push origin master >/dev/null
first="$(git -C "$author" rev-parse HEAD)"
git clone --branch master "$origin" "$current" >/dev/null

# The normal no-argument workflow captures exactly the fetched commit.
validate_expected_commit
prepare_repository "$current" > "$SANDBOX/no-pin.log" 2>&1
[[ "$DEPLOY_COMMIT" == "$first" ]] || fail 'No-pin deployment did not select the fetched commit.'
[[ ! -e "$RELEASES_DIR" ]] || fail 'Selection created a release before validation.'

# An explicit pin is still supported and must match.
EXPECTED_COMMIT="$first"
validate_expected_commit
prepare_repository "$current" > "$SANDBOX/matching-pin.log" 2>&1
[[ "$DEPLOY_COMMIT" == "$first" ]] || fail 'Matching explicit pin changed the selected commit.'

printf 'second\n' > "$author/VERSION"
git -C "$author" commit -am 'Second local fixture release' >/dev/null
git -C "$author" push origin master >/dev/null
second="$(git -C "$author" rev-parse HEAD)"

# Moving both remote and mirror refs after selection cannot mutate the captured
# SHA. Checking out that SHA must still produce the first selected release.
git --git-dir="$REPOSITORY_DIR" fetch origin '+refs/heads/master:refs/heads/master' >/dev/null 2>&1
[[ "$(git --git-dir="$REPOSITORY_DIR" rev-parse refs/heads/master)" == "$second" ]]
[[ "$DEPLOY_COMMIT" == "$first" ]] || fail 'Branch movement changed an already selected release.'
git clone --shared --no-checkout "$REPOSITORY_DIR" "$SANDBOX/candidate" >/dev/null 2>&1
git -C "$SANDBOX/candidate" checkout --detach "$DEPLOY_COMMIT" >/dev/null 2>&1
[[ "$(cat "$SANDBOX/candidate/VERSION")" == first ]] || fail 'Captured SHA did not retain the selected source.'

# A separate process preserves set -e behavior even when a failure is expected.
# The marker represents the first build step and must never run on rejection.
cat > "$SANDBOX/run-selection.sh" <<'EOF'
#!/usr/bin/env bash
set -Eeuo pipefail
source "$TEST_FUNCTIONS"
REPOSITORY_DIR="$TEST_REPOSITORY"
RELEASES_DIR="$TEST_RELEASES"
BRANCH=master
EXPECTED_COMMIT="$TEST_EXPECTED_COMMIT"
DEPLOY_COMMIT="$TEST_PREVIOUS_COMMIT"
validate_expected_commit
prepare_repository "$TEST_CURRENT"
mkdir -p "$RELEASES_DIR"
printf '%s\n' "$DEPLOY_COMMIT" > "$RELEASES_DIR/selected"
EOF
run_selection() {
  TEST_FUNCTIONS="$SANDBOX/functions.sh" TEST_REPOSITORY="$REPOSITORY_DIR" \
  TEST_RELEASES="$RELEASES_DIR" TEST_CURRENT="$current" \
  TEST_EXPECTED_COMMIT="$1" TEST_PREVIOUS_COMMIT="$DEPLOY_COMMIT" \
    bash "$SANDBOX/run-selection.sh"
}

if run_selection "$first" > "$SANDBOX/mismatch.log" 2>&1; then fail 'A stale explicit pin succeeded.'; fi
grep -q 'not reviewed commit' "$SANDBOX/mismatch.log"
[[ ! -e "$RELEASES_DIR" ]] || fail 'Pin mismatch reached the build marker.'

for invalid in master ABCDEF 123456789012345678901234567890123456789g; do
  if run_selection "$invalid" > "$SANDBOX/invalid-pin.log" 2>&1; then fail 'Malformed explicit pin succeeded.'; fi
  grep -q 'must be a full lowercase Git commit SHA' "$SANDBOX/invalid-pin.log"
  [[ ! -e "$RELEASES_DIR" ]] || fail 'Malformed pin reached the build marker.'
done

# A later ordinary invocation selects the newly fetched branch tip.
EXPECTED_COMMIT=''
prepare_repository "$current" > "$SANDBOX/new-no-pin.log" 2>&1
[[ "$DEPLOY_COMMIT" == "$second" ]] || fail 'Subsequent no-pin deployment did not select the new commit.'

# A failed fetch must terminate rather than deploy the mirror's stale branch.
git --git-dir="$REPOSITORY_DIR" remote set-url origin "$SANDBOX/nonexistent-origin.git"
if run_selection '' > "$SANDBOX/fetch-failed.log" 2>&1; then fail 'Failed fetch reused a stale commit.'; fi
[[ ! -e "$RELEASES_DIR" ]] || fail 'Failed fetch reached the build marker.'
git --git-dir="$REPOSITORY_DIR" remote set-url origin "$origin"

printf 'Deployment selection smoke passed (automatic immutable selection, optional matching pin, mismatched/malformed pins, branch movement, subsequent release, failed fetch).\n'
