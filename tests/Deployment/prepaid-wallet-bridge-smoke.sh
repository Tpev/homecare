#!/usr/bin/env bash
# Offline proof of the v1 bridge -> v2 delivery boundary. No application boot.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-wallet-bridge.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-wallet-bridge.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$PROJECT_DIR/deploy.sh" > "$SANDBOX/functions.sh"
source "$SANDBOX/functions.sh"
git_root="$(cygpath -am "$PROJECT_DIR" 2>/dev/null || printf '%s' "$PROJECT_DIR")"
base="${HOMECARE_WALLET_BRIDGE_BASE:-f030f0a2}"
git -C "$PROJECT_DIR" -c safe.directory="$git_root" show "$base:deploy.sh" > "$SANDBOX/old-driver.sh"
bridge="$SANDBOX/bridge"
wallet="$SANDBOX/wallet"
mkdir -p "$bridge/docs/deployment"
mapfile -t sources < <(prepaid_compatibility_files)
git -C "$PROJECT_DIR" -c safe.directory="$git_root" archive "$base" -- "${sources[@]}" | tar -x -C "$bridge"
cp -- "$PROJECT_DIR/deploy.sh" "$bridge/deploy.sh"
cp -- "$PROJECT_DIR/scripts/deployment/check-prepaid-release-config.php" "$bridge/scripts/deployment/check-prepaid-release-config.php"
print_prepaid_manifest "$bridge" 1 > "$bridge/$PREPAID_MANIFEST"
print_prepaid_manifest "$bridge" > "$SANDBOX/default-bridge-manifest"
cmp -- "$bridge/$PREPAID_MANIFEST" "$SANDBOX/default-bridge-manifest"
bash "$SANDBOX/old-driver.sh" --check-prepaid-release "$bridge" > "$SANDBOX/bridge.log"
grep -q 'compatibility v1 verified' "$SANDBOX/bridge.log"

# The v2 fixture uses current reviewed sources, isolated from ongoing work, and
# verifies delivery format only. Ledger behavior has separate application tests.
mkdir -p "$wallet/docs/deployment"
while IFS= read -r path; do
  [[ "$path" != /* && "$path" != *..* ]] || exit 1
  mkdir -p "$(dirname "$wallet/$path")"
  cp -- "$PROJECT_DIR/$path" "$wallet/$path"
done < <(prepaid_compatibility_files_for_version 2)
print_prepaid_manifest "$wallet" 2 > "$wallet/$PREPAID_MANIFEST"
if print_prepaid_manifest "$wallet" > "$SANDBOX/downgrade-manifest" 2> "$SANDBOX/downgrade.log"; then
  printf 'Default generation mislabeled the family-wallet sources as v1.\n' >&2
  exit 1
fi
[[ ! -s "$SANDBOX/downgrade-manifest" ]]
grep -q 'family-wallet sources require manifest v2' "$SANDBOX/downgrade.log"
if bash "$SANDBOX/old-driver.sh" --check-prepaid-release "$wallet" > "$SANDBOX/old-rejection.log" 2>&1; then
  printf 'Old recipient driver accepted the wallet contract.\n' >&2
  exit 1
fi
grep -q 'manifest version or inventory is unknown' "$SANDBOX/old-rejection.log"
bash "$bridge/deploy.sh" --check-prepaid-release "$wallet" > "$SANDBOX/wallet.log"
grep -q 'compatibility v2 verified' "$SANDBOX/wallet.log"
printf 'Wallet bridge smoke passed (actual old driver accepts v1 bridge, rejects direct v2; bridge validates v2 inventory).\n'
