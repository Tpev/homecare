#!/usr/bin/env bash
# Source deployment functions only. All service/Artisan actions are synthetic.
set -Eeuo pipefail

case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS="winsymlinks:nativestrict" ;;
esac

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-rollout-recovery.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
[[ "$SANDBOX" == "$TEMP_ROOT"/homecare-rollout-recovery.* ]] || exit 1
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$TEMP_ROOT"/homecare-rollout-recovery.* && "$resolved" == "$SANDBOX" ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT

# Windows directory junctions exercise real link resolution without requiring
# Developer Mode/admin symlink privileges. Linux uses native symbolic links.
case "$(uname -s)" in
  MINGW*|MSYS*)
    mkdir -p "$SANDBOX/bin"
    cat > "$SANDBOX/bin/ln" <<'LINK'
#!/usr/bin/env bash
set -Eeuo pipefail
target="${@: -2:1}"
destination="${@: -1}"
[[ "$target" == "$TEST_SANDBOX/"* && "$destination" == "$TEST_SANDBOX/"* && -d "$target" ]] || exit 99
HOMECARE_TEST_JUNCTION_TARGET="$(cygpath -aw "$target")" HOMECARE_TEST_JUNCTION_PATH="$(cygpath -aw "$destination")" \
  /c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe -NoProfile -NonInteractive \
  -Command '$ErrorActionPreference = "Stop"; New-Item -ItemType Junction -Path $env:HOMECARE_TEST_JUNCTION_PATH -Target $env:HOMECARE_TEST_JUNCTION_TARGET | Out-Null'
LINK
    chmod 755 "$SANDBOX/bin/ln"
    export TEST_SANDBOX="$SANDBOX" PATH="$SANDBOX/bin:$PATH"
    ;;
esac

# Stop before the main entry point, so no migration, deploy lock, service or
# repository command from deploy.sh can execute when this fixture is sourced.
awk '
  /^case "\$MODE" in/ { found = 1; exit }
  { print }
  END { if (!found) exit 2 }
' "$PROJECT_DIR/deploy.sh" > "$SANDBOX/deploy-functions.sh"
grep -q '^rollback_after_failure()' "$SANDBOX/deploy-functions.sh"
bash -n "$SANDBOX/deploy-functions.sh"

cat > "$SANDBOX/run-case.sh" <<'RUNNER'
#!/usr/bin/env bash
set -Eeuo pipefail
source "$TEST_DEFINITIONS"

# Override every external action reachable from the tested functions. No sudo,
# systemctl, Artisan, network, or runtime hook executable is ever invoked.
APP_DIR="$TEST_CASE_DIR/current"
NEW_RELEASE="$TEST_CASE_DIR/new"
OLD_RELEASE="$TEST_CASE_DIR/earlier"
FPM_SERVICE=test-php-fpm
VOICE_AGENT_SERVICE=test-voice
RUNTIME_PAUSED=1
FPM_STOPPED=1
SCREENING_FIRST_ROLLOUT=1
SCREENING_ACTIVATION_ATTEMPTED=1
SWITCHED=1

sudo() {
  [[ "$1" == systemctl ]] || { printf 'Unexpected sudo call\n' >&2; return 99; }
  shift
  systemctl "$@"
}
systemctl() {
  printf 'systemctl %s\n' "$*" >> "$TEST_CASE_DIR/events"
  if [[ "$1" == show ]]; then
    case "$*" in
      *--property=ActiveState*) printf 'inactive\n' ;;
      *--property=MainPID*|*--property=ControlPID*) printf '0\n' ;;
      *--property=ControlGroup*) printf '\n' ;;
      *) return 99 ;;
    esac
  fi
}
runtime_hook() {
  printf 'runtime %s\n' "$1" >> "$TEST_CASE_DIR/events"
  case "$1" in
    pause) touch "$TEST_CASE_DIR/paused" ;;
    resume) rm -- "$TEST_CASE_DIR/paused" ;;
    *) return 99 ;;
  esac
}
run_artisan() {
  printf 'artisan %s\n' "$*" >> "$TEST_CASE_DIR/events"
  [[ "$2" == screening:rollout ]] || return 99
  case "$3" in
    --status)
      [[ "$TEST_STATE" != unknown ]] || return 74
      printf '%s\n' "$TEST_STATE"
      ;;
    --prepare) return 73 ;;
    *) return 99 ;;
  esac
}
atomic_symlink() {
  [[ "$1" == "$TEST_CASE_DIR/"* && "$2" == "$APP_DIR" ]] || return 99
  printf 'swap %s\n' "$1" >> "$TEST_CASE_DIR/events"
  ln -s -- "$1" "$TEST_CASE_DIR/next"
  mv -Tf -- "$TEST_CASE_DIR/next" "$2"
}
wait_for_health() { printf 'health checked\n' >> "$TEST_CASE_DIR/events"; }
content_mcp_service_is_installed() { return 1; }

trap 'rollback_after_failure "$?"' EXIT
case "$TEST_SCENARIO" in
  before-prepare)
    # Earlier failed activation left this screening release selected and paused.
    # The new attempt fails before activate_release has even set OLD_RELEASE.
    OLD_RELEASE=''
    SWITCHED=0
    SCREENING_ACTIVATION_ATTEMPTED=0
    RUNTIME_PAUSED=0
    FPM_STOPPED=0
    pause_screening_runtime
    printf 'Failed preparation unexpectedly succeeded\n' >&2
    exit 99
    ;;
  after-swap)
    # Recovery may select the earlier screening release, but it shares the same
    # pending/unknown durable boundary and must not resume on that basis alone.
    exit 73
    ;;
  unrecorded-swap)
    # The real symlink changed before a signal prevented SWITCHED=1 assignment.
    SWITCHED=0
    SCREENING_ACTIVATION_ATTEMPTED=0
    exit 73
    ;;
  first-activation)
    exit 73
    ;;
  *) exit 99 ;;
esac
RUNNER

fail() {
  printf 'Recovery smoke failure: %s\n' "$*" >&2
  cat "$CASE_DIR/output.log" "$CASE_DIR/events" >&2
  exit 1
}

run_case() {
  local name="$1" state="$2" scenario="$3" earlier_screening="$4" selected="$5" status
  CASE_DIR="$SANDBOX/$name"
  mkdir -p "$CASE_DIR/earlier/app/Console/Commands" "$CASE_DIR/new/app/Console/Commands"
  touch "$CASE_DIR/earlier/artisan" "$CASE_DIR/new/artisan" "$CASE_DIR/new/app/Console/Commands/ScreeningRollout.php"
  if [[ "$earlier_screening" == yes ]]; then
    touch "$CASE_DIR/earlier/app/Console/Commands/ScreeningRollout.php"
  fi
  ln -s -- "$CASE_DIR/$selected" "$CASE_DIR/current"
  [[ -L "$CASE_DIR/current" ]] || fail 'Filesystem links are required by this isolated test.'
  touch "$CASE_DIR/paused" "$CASE_DIR/events"
  if TEST_CASE_DIR="$CASE_DIR" TEST_STATE="$state" TEST_SCENARIO="$scenario" \
    TEST_DEFINITIONS="$SANDBOX/deploy-functions.sh" bash "$SANDBOX/run-case.sh" > "$CASE_DIR/output.log" 2>&1; then
    status=0
  else
    status=$?
  fi
  [[ "$status" -eq 73 ]] || fail "$name did not preserve the original failure exit status (got $status)."
}

assert_selected() {
  [[ "$(readlink -f -- "$CASE_DIR/current")" == "$CASE_DIR/$1" ]] \
    || fail "Unexpected selected release; expected $1."
}

assert_held() {
  [[ -f "$CASE_DIR/paused" ]] || fail 'Runtime pause state was cleared before activation.'
  if grep -Eq '^runtime resume$|^systemctl (start|reload) test-php-fpm$' "$CASE_DIR/events"; then
    fail 'FPM or background writers resumed without confirmed activation.'
  fi
  grep -q 'Screening activation is not confirmed' "$CASE_DIR/output.log" \
    || fail 'Recovery did not explain why activation remains paused.'
}

assert_resumed() {
  [[ ! -e "$CASE_DIR/paused" ]] || fail 'Confirmed safe recovery did not restore the paused runtime.'
  grep -qx 'systemctl start test-php-fpm' "$CASE_DIR/events" || fail 'FPM was not restored.'
  grep -qx 'runtime resume' "$CASE_DIR/events" || fail 'Background writers were not restored.'
}

run_case retry-prepare-fails pending before-prepare yes earlier
assert_selected earlier
assert_held
grep -q 'screening:rollout --prepare' "$CASE_DIR/events" || fail 'Preparation failure was not exercised.'

run_case retry-after-swap pending after-swap yes new
assert_selected earlier
assert_held
grep -qx "swap $CASE_DIR/earlier" "$CASE_DIR/events" || fail 'Earlier screening release rollback was not exercised.'

run_case retry-unknown unknown after-swap yes new
assert_selected earlier
assert_held

run_case retry-committed active after-swap yes new
assert_selected earlier
assert_resumed

run_case first-unknown unknown first-activation no new
assert_selected new
assert_held

run_case first-committed active first-activation no new
assert_selected new
assert_resumed

run_case swap-before-flag pending unrecorded-swap no new
assert_selected earlier
assert_resumed
grep -qx "swap $CASE_DIR/earlier" "$CASE_DIR/events" || fail 'Actual symlink change was not detected with SWITCHED=0.'

printf 'Screening rollout recovery smoke passed (7 isolated cases).\n'
