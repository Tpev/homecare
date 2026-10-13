#!/usr/bin/env bash
# Exercise Git delivery with an isolated repository and a harmless installer.
# No real application, service, network, or privileged command is invoked.
set -Eeuo pipefail

PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-screening-git.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
[[ "$SANDBOX" == "$TEMP_ROOT"/homecare-screening-git.* ]] || exit 1
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-screening-git.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT

APP="$SANDBOX/app"
mkdir -p "$APP/docs/deployment" "$APP/scripts/deployment" "$SANDBOX/bin" "$SANDBOX/staging"
chmod 700 "$SANDBOX/staging"
cp "$PROJECT_DIR/deploy.sh" "$SANDBOX/deploy.sh"
cp "$SANDBOX/deploy.sh" "$APP/deploy.sh"
touch "$APP/artisan"

cat > "$APP/docs/deployment/screening-supervisor-setup.sh" <<'INSTALLER'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$#" -ge 2 && ( "$1" == --check || "$1" == --apply || "$1" == --resume-check || "$1" == --resume ) ]] || exit 91
action="$1"
bundle="$2"
shift 2
backup=''
if [[ "$action" == --resume-check || "$action" == --resume ]]; then
  [[ "$#" -ge 1 && "$1" == "$TEST_EXPECTED_BACKUP" ]] || exit 94
  backup="$1"
  shift
fi
[[ "$bundle" == "$TEST_SANDBOX/"* ]] || exit 92
cd "$bundle"
sha256sum -c SHA256SUMS >/dev/null
grep -Fq -- "$TEST_EXPECTED_REVISION" REVISION
[[ "$(cat screening-supervisor-runtime-control.sh)" == committed-runtime ]]
[[ "$(cat screening-runtime-entrypoint.sh)" == committed-entrypoint ]]
[[ "$(cat check-screening-queue.php)" == committed-queue-check ]]
[[ "$(wc -l < SHA256SUMS)" -eq 4 ]]
printf 'installer %s' "$action" >> "$TEST_EVENTS"
if [[ -n "$backup" ]]; then printf ' %s' "$backup" >> "$TEST_EVENTS"; fi
if [[ "$#" -gt 0 ]]; then printf ' %s' "$@" >> "$TEST_EVENTS"; fi
printf '\n' >> "$TEST_EVENTS"
printf '%s\n' "$bundle" > "$TEST_BUNDLE_PATH"
if [[ "$action" == --check || "$action" == --resume-check ]]; then
  [[ "$#" -eq 0 ]] || exit 93
else
  [[ "$#" -eq 2 ]]
  [[ " $* " == *' --confirm-single-host-runtime '* ]]
  [[ " $* " == *' --accept-existing-supervisor-stop-timeout '* ]]
fi
exit "${TEST_INSTALLER_EXIT:-0}"
INSTALLER
printf 'committed-runtime\n' > "$APP/docs/deployment/screening-supervisor-runtime-control.sh"
printf 'committed-entrypoint\n' > "$APP/docs/deployment/screening-runtime-entrypoint.sh"
printf 'committed-queue-check\n' > "$APP/scripts/deployment/check-screening-queue.php"

REAL_GIT="$(command -v git)"
REAL_STAT="$(command -v stat)"
git -C "$APP" init -q
git -C "$APP" config user.name 'Offline screening smoke'
git -C "$APP" config user.email 'screening-smoke@example.invalid'
git -C "$APP" config core.autocrlf false
git -C "$APP" config commit.gpgsign false
git -C "$APP" add .
git -C "$APP" commit -qm 'Complete committed runtime fixture'
GOOD_REVISION="$(git -C "$APP" rev-parse HEAD)"

git -C "$APP" rm -q docs/deployment/screening-runtime-entrypoint.sh
git -C "$APP" commit -qm 'Missing required tracked runtime file'
MISSING_REVISION="$(git -C "$APP" rev-parse HEAD)"
git -C "$APP" reset --hard -q "$GOOD_REVISION"
printf '\n# A different deployment entrypoint revision.\n' >> "$APP/deploy.sh"
git -C "$APP" add deploy.sh
git -C "$APP" commit -qm 'Different deployment entrypoint'
MISMATCH_REVISION="$(git -C "$APP" rev-parse HEAD)"
git -C "$APP" reset --hard -q "$GOOD_REVISION"
LINK_BLOB="$(printf '../unexpected-runtime\n' | git -C "$APP" hash-object -w --stdin)"
git -C "$APP" update-index --cacheinfo "120000,$LINK_BLOB,docs/deployment/screening-runtime-entrypoint.sh"
git -C "$APP" commit -qm 'Runtime entrypoint stored as a Git symlink'
SYMLINK_REVISION="$(git -C "$APP" rev-parse HEAD)"
git -C "$APP" reset --hard -q "$GOOD_REVISION"
printf '#!/usr/bin/env bash\nif\n' > "$APP/docs/deployment/screening-runtime-entrypoint.sh"
git -C "$APP" add docs/deployment/screening-runtime-entrypoint.sh
git -C "$APP" commit -qm 'Runtime entrypoint with invalid shell syntax'
SYNTAX_REVISION="$(git -C "$APP" rev-parse HEAD)"
git -C "$APP" reset --hard -q "$GOOD_REVISION"

# Tracked local edits must never become the material passed to sudo.
printf 'dirty-runtime\n' > "$APP/docs/deployment/screening-supervisor-runtime-control.sh"
printf 'dirty-entrypoint\n' > "$APP/docs/deployment/screening-runtime-entrypoint.sh"
printf 'dirty-queue-check\n' > "$APP/scripts/deployment/check-screening-queue.php"
printf '#!/usr/bin/env bash\nexit 97\n' > "$APP/docs/deployment/screening-supervisor-setup.sh"

cat > "$SANDBOX/bin/sudo" <<'SUDO'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'sudo %s\n' "$*" >> "$TEST_EVENTS"
[[ "${1:-}" != -n ]] || shift
[[ "${1:-}" == bash ]] || exit 98
shift
[[ "${1:-}" == "$TEST_SANDBOX/"*/screening-supervisor-setup.sh ]] || exit 98
exec bash "$@"
SUDO
cat > "$SANDBOX/bin/git" <<'GIT'
#!/usr/bin/env bash
set -Eeuo pipefail
for argument in "$@"; do
  case "$argument" in
    fetch|pull|push|clone)
      printf 'forbidden git %s\n' "$*" >> "$TEST_EVENTS"
      exit 99
      ;;
  esac
done
exec "$TEST_REAL_GIT" "$@"
GIT
cat > "$SANDBOX/bin/forbidden" <<'FORBIDDEN'
#!/usr/bin/env bash
printf 'forbidden %s %s\n' "$(basename "$0")" "$*" >> "$TEST_EVENTS"
exit 99
FORBIDDEN
for name in php composer npm go systemctl supervisorctl flock curl; do
  cp "$SANDBOX/bin/forbidden" "$SANDBOX/bin/$name"
done
chmod 755 "$SANDBOX/bin/"*
export PATH="$SANDBOX/bin:$PATH" TEST_SANDBOX="$SANDBOX" TEST_REAL_GIT="$REAL_GIT"
case "$(uname -s)" in
  MINGW*|MSYS*)
    # NTFS does not retain Unix mode bits. Only the fixture's private staging
    # directories receive this adapter; path safety and ownership remain real.
    cat > "$SANDBOX/bin/stat" <<'STAT'
#!/usr/bin/env bash
set -Eeuo pipefail
target="${@: -1}"
if [[ "$*" == *'%a'* && ( "$target" == "$TEST_SANDBOX/staging" || "$target" == "$TEST_SANDBOX/staging/"* ) ]]; then
  printf '700\n'
else
  exec "$TEST_REAL_STAT" "$@"
fi
STAT
    chmod 755 "$SANDBOX/bin/stat"
    export TEST_REAL_STAT="$REAL_STAT"
    ;;
esac
export HOMECARE_APP_DIR="$APP" HOMECARE_DEPLOY_ROOT="$SANDBOX/deploy-root"
export HOMECARE_SCREENING_STAGE_ROOT="$SANDBOX/staging"
export HOMECARE_DEPLOY_LOCK="$SANDBOX/deploy.lock"
export TEST_EXPECTED_REVISION="$GOOD_REVISION"
export TEST_EXPECTED_BACKUP=/var/backups/homecare-screening-runtime/20261013T165702Z-1659597
CHECKS=0

fail() {
  printf 'Git delivery smoke failure: %s\n' "$*" >&2
  cat "$CASE_DIR/output.log" "$CASE_DIR/events" >&2
  exit 1
}
run_case() {
  local name="$1" expected="$2" actual
  shift 2
  CASE_DIR="$SANDBOX/case-$name"
  mkdir -p "$CASE_DIR"
  export TEST_EVENTS="$CASE_DIR/events" TEST_BUNDLE_PATH="$CASE_DIR/bundle-path"
  : > "$TEST_EVENTS"
  if bash "$SANDBOX/deploy.sh" "$@" > "$CASE_DIR/output.log" 2>&1; then
    LAST_EXIT=0
    actual=success
  else
    LAST_EXIT=$?
    actual=failure
  fi
  [[ "$actual" == "$expected" ]] || fail "$name expected $expected, got $actual."
  ! grep -q '^forbidden ' "$TEST_EVENTS" || fail "$name reached a forbidden command."
  [[ ! -e "$HOMECARE_DEPLOY_LOCK" ]] || fail "$name opened the regular deploy lock."
  [[ ! -e "$HOMECARE_DEPLOY_ROOT" ]] || fail "$name created the deployment root."
  [[ "$(cat "$APP/docs/deployment/screening-supervisor-runtime-control.sh")" == dirty-runtime ]] \
    || fail "$name changed the application checkout."
  CHECKS=$((CHECKS + 1))
}
assert_no_privilege() {
  [[ ! -s "$TEST_EVENTS" ]] || fail 'Invalid input reached the privileged installer.'
}

run_case read-only success --screening-check "$GOOD_REVISION"
[[ "$(grep -c '^sudo ' "$TEST_EVENTS")" -eq 1 ]] || fail 'Read-only check did not call the installer exactly once.'
grep -qx 'installer --check' "$TEST_EVENTS" || fail 'Read-only check did not select --check only.'
! grep -q -- '--apply' "$TEST_EVENTS" || fail 'Read-only check attempted apply.'
grep -Fq -- "$GOOD_REVISION" "$CASE_DIR/output.log" || fail 'Check omitted commit provenance.'
grep -Fq -- "$(cat "$TEST_BUNDLE_PATH")" "$CASE_DIR/output.log" || fail 'Check omitted staging provenance.'

run_case setup success --screening-setup "$GOOD_REVISION" \
  --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
[[ "$(grep -c '^sudo ' "$TEST_EVENTS")" -eq 1 ]] || fail 'Setup did not call the installer exactly once.'
grep -q '^installer --apply ' "$TEST_EVENTS" || fail 'Setup did not select --apply.'

run_case recovery-check success --screening-recovery-check "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP"
[[ "$(grep -c '^sudo ' "$TEST_EVENTS")" -eq 1 ]] || fail 'Recovery check did not call the installer exactly once.'
grep -Fxq "installer --resume-check $TEST_EXPECTED_BACKUP" "$TEST_EVENTS" || fail 'Recovery check changed the backup argument or action.'
! grep -Eq '^installer --(apply|resume) ' "$TEST_EVENTS" || fail 'Recovery check attempted a mutation.'

run_case resume-setup success --screening-resume-setup "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP" \
  --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
grep -Fxq "installer --resume $TEST_EXPECTED_BACKUP --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout" "$TEST_EVENTS" \
  || fail 'Resume did not pass the exact checkpoint and confirmations.'

TEST_INSTALLER_EXIT=72 run_case recovery-refusal failure --screening-recovery-check "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP"
[[ "$LAST_EXIT" -eq 72 ]] || fail 'Recovery refusal exit status was not preserved.'
run_case recovery-missing-backup failure --screening-recovery-check "$GOOD_REVISION"
assert_no_privilege
run_case recovery-extra-flag failure --screening-recovery-check "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP" --confirm-single-host-runtime
assert_no_privilege
for invalid_backup in relative-backup /tmp/backup --help /var/backups/homecare-screening-runtime/../elsewhere /var/backups/homecare-screening-runtime/not-a-checkpoint; do
  run_case "recovery-bad-backup-$CHECKS" failure --screening-recovery-check "$GOOD_REVISION" "$invalid_backup"
  assert_no_privilege
done
run_case resume-no-confirmations failure --screening-resume-setup "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP"
assert_no_privilege
run_case resume-one-confirmation failure --screening-resume-setup "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP" --confirm-single-host-runtime
assert_no_privilege
run_case resume-unknown-argument failure --screening-resume-setup "$GOOD_REVISION" "$TEST_EXPECTED_BACKUP" \
  --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout --unexpected
assert_no_privilege
run_case resume-invalid-backup failure --screening-resume-setup "$GOOD_REVISION" /tmp/backup \
  --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout
assert_no_privilege

TEST_INSTALLER_EXIT=71 run_case installer-failure failure --screening-check "$GOOD_REVISION"
[[ "$LAST_EXIT" -eq 71 ]] || fail 'Installer exit status was not preserved.'
grep -qx 'installer --check' "$TEST_EVENTS" || fail 'Installer failure case did not reach the read-only check.'
run_case absent-revision failure --screening-check
assert_no_privilege
run_case unknown-revision failure --screening-check unknown-fixture-revision
assert_no_privilege
run_case option-as-revision failure --screening-check --help
assert_no_privilege
run_case missing-file failure --screening-check "$MISSING_REVISION"
assert_no_privilege
run_case mismatched-entrypoint failure --screening-check "$MISMATCH_REVISION"
assert_no_privilege
run_case git-symlink-file failure --screening-check "$SYMLINK_REVISION"
assert_no_privilege
run_case invalid-shell-syntax failure --screening-check "$SYNTAX_REVISION"
assert_no_privilege
run_case extra-check-argument failure --screening-check "$GOOD_REVISION" --confirm-single-host-runtime
assert_no_privilege
run_case setup-no-confirmations failure --screening-setup "$GOOD_REVISION"
assert_no_privilege
run_case setup-one-confirmation failure --screening-setup "$GOOD_REVISION" --confirm-single-host-runtime
assert_no_privilege
run_case setup-other-confirmation failure --screening-setup "$GOOD_REVISION" --accept-existing-supervisor-stop-timeout
assert_no_privilege
run_case setup-unknown-argument failure --screening-setup "$GOOD_REVISION" \
  --confirm-single-host-runtime --accept-existing-supervisor-stop-timeout --unexpected
assert_no_privilege
mv "$APP/artisan" "$APP/artisan-hidden"
run_case missing-artisan failure --screening-check "$GOOD_REVISION"
assert_no_privilege
mv "$APP/artisan-hidden" "$APP/artisan"
mkdir "$SANDBOX/non-repository"
touch "$SANDBOX/non-repository/artisan"
HOMECARE_APP_DIR="$SANDBOX/non-repository" run_case no-git-repository failure --screening-check "$GOOD_REVISION"
assert_no_privilege

HOMECARE_SCREENING_STAGE_ROOT="$APP" run_case stage-is-app failure --screening-check "$GOOD_REVISION"
assert_no_privilege
HOMECARE_SCREENING_STAGE_ROOT="$APP/private-stage" run_case stage-under-app failure --screening-check "$GOOD_REVISION"
assert_no_privilege
[[ ! -e "$APP/private-stage" ]] || fail 'Unsafe stage directory was created inside the live app.'
HOMECARE_SCREENING_STAGE_ROOT="$HOMECARE_DEPLOY_ROOT" run_case stage-is-deploy-root failure --screening-check "$GOOD_REVISION"
assert_no_privilege

case "$(uname -s)" in
  MINGW*|MSYS*)
    HOMECARE_TEST_JUNCTION_TARGET="$(cygpath -aw "$APP")" HOMECARE_TEST_JUNCTION_PATH="$(cygpath -aw "$SANDBOX/app-alias")" \
      /c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe -NoProfile -NonInteractive \
      -Command '$ErrorActionPreference = "Stop"; New-Item -ItemType Junction -Path $env:HOMECARE_TEST_JUNCTION_PATH -Target $env:HOMECARE_TEST_JUNCTION_TARGET | Out-Null'
    ;;
  *) ln -s -- "$APP" "$SANDBOX/app-alias" ;;
esac
HOMECARE_SCREENING_STAGE_ROOT="$SANDBOX/app-alias" run_case stage-through-link failure --screening-check "$GOOD_REVISION"
assert_no_privilege

printf 'Screening Git delivery smoke passed (%s checks; no production or network access).\n' "$CHECKS"
