#!/usr/bin/env bash
# Full current inventory validation in an isolated local copy. No app is booted.
set -Eeuo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
SANDBOX="$(mktemp -d "$TEMP_ROOT/homecare-prepaid-manifest.XXXXXX")"
SANDBOX="$(cd "$SANDBOX" && pwd -P)"
cleanup() {
  local resolved
  resolved="$(cd "$SANDBOX" && pwd -P)"
  [[ "$resolved" == "$SANDBOX" && "$resolved" == "$TEMP_ROOT"/homecare-prepaid-manifest.* ]] || exit 1
  rm -rf -- "$resolved"
}
trap cleanup EXIT
awk '/^case "\$MODE" in/ { found=1; exit } { print } END { if (!found) exit 2 }' \
  "$PROJECT_DIR/deploy.sh" > "$SANDBOX/functions.sh"
source "$SANDBOX/functions.sh"
fixture="$SANDBOX/release"
version="$(prepaid_manifest_contract "$PROJECT_DIR")"
while IFS= read -r path; do
  [[ "$path" != /* && "$path" != *..* ]] || exit 1
  mkdir -p "$(dirname "$fixture/$path")"
  cp -- "$PROJECT_DIR/$path" "$fixture/$path"
done < <(prepaid_compatibility_files_for_version "$version")
mkdir -p "$fixture/docs/deployment"
cp -- "$PROJECT_DIR/$PREPAID_MANIFEST" "$fixture/$PREPAID_MANIFEST"
[[ "$(prepaid_release_version "$fixture")" == "$version" ]]

reject() {
  if prepaid_release_version "$fixture" > "$SANDBOX/rejected.log" 2>&1; then
    printf 'Incomplete or changed prepaid package unexpectedly verified.\n' >&2; exit 1
  fi
  grep -q "$1" "$SANDBOX/rejected.log"
}
rm -- "$fixture/app/Livewire/Family/ManageCareRequest.php"
reject 'ManageCareRequest.php'
cp -- "$PROJECT_DIR/app/Livewire/Family/ManageCareRequest.php" "$fixture/app/Livewire/Family/ManageCareRequest.php"
rm -- "$fixture/app/Livewire/Admin/CareRequestShow.php"
reject 'CareRequestShow.php'
cp -- "$PROJECT_DIR/app/Livewire/Admin/CareRequestShow.php" "$fixture/app/Livewire/Admin/CareRequestShow.php"
rm -- "$fixture/app/Livewire/Admin/CareRequestsIndex.php"
reject 'CareRequestsIndex.php'
cp -- "$PROJECT_DIR/app/Livewire/Admin/CareRequestsIndex.php" "$fixture/app/Livewire/Admin/CareRequestsIndex.php"
rm -- "$fixture/app/Services/CareHours/PrepaidShortfallDisputeService.php"
reject 'PrepaidShortfallDisputeService.php'
cp -- "$PROJECT_DIR/app/Services/CareHours/PrepaidShortfallDisputeService.php" "$fixture/app/Services/CareHours/PrepaidShortfallDisputeService.php"
rm -- "$fixture/app/Services/CareHours/CareHourEligibilityService.php"
reject 'CareHourEligibilityService.php'
cp -- "$PROJECT_DIR/app/Services/CareHours/CareHourEligibilityService.php" "$fixture/app/Services/CareHours/CareHourEligibilityService.php"
rm -- "$fixture/app/Services/CareHours/CareHourAccess.php"
reject 'CareHourAccess.php'
cp -- "$PROJECT_DIR/app/Services/CareHours/CareHourAccess.php" "$fixture/app/Services/CareHours/CareHourAccess.php"
rm -- "$fixture/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php"
reject '2026_10_26_000100_create_care_hour_request_preferences.php'
cp -- "$PROJECT_DIR/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php" "$fixture/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php"
printf 'incompatible replacement\n' > "$fixture/app/Http/Controllers/CareHours/GiftClaimController.php"
reject 'GiftClaimController.php'
cp -- "$PROJECT_DIR/app/Http/Controllers/CareHours/GiftClaimController.php" "$fixture/app/Http/Controllers/CareHours/GiftClaimController.php"
printf 'extra manifest entry\n' >> "$fixture/$PREPAID_MANIFEST"
reject 'manifest version or inventory is unknown'
if [[ "$version" == 2 ]]; then
  cp -- "$PROJECT_DIR/$PREPAID_MANIFEST" "$fixture/$PREPAID_MANIFEST"
  for requested in default 1; do
    args=("$fixture")
    if [[ "$requested" != default ]]; then args+=("$requested"); fi
    if print_prepaid_manifest "${args[@]}" > "$SANDBOX/downgrade-manifest" 2> "$SANDBOX/downgrade.log"; then
      printf 'Family-wallet sources were mislabeled as v1.\n' >&2; exit 1
    fi
    [[ ! -s "$SANDBOX/downgrade-manifest" ]]
    grep -q 'family-wallet sources require manifest v2' "$SANDBOX/downgrade.log"
  done
  rm -- "$fixture/database/migrations/2026_10_30_000100_create_care_hour_family_wallets.php"
  reject '2026_10_30_000100_create_care_hour_family_wallets.php'
  if print_prepaid_manifest "$fixture" 1 > "$SANDBOX/downgrade-manifest" 2> "$SANDBOX/downgrade.log"; then
    printf 'Family-wallet configuration was mislabeled as v1.\n' >&2; exit 1
  fi
  [[ ! -s "$SANDBOX/downgrade-manifest" ]]
  grep -q 'family-wallet configuration requires manifest v2' "$SANDBOX/downgrade.log"
fi
printf 'Prepaid manifest smoke passed (full inventory, missing/changed sources, extra entry, family-wallet v1 generation rejected).\n'
