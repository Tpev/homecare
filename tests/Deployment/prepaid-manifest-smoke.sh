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
while IFS= read -r path; do
  [[ "$path" != /* && "$path" != *..* ]] || exit 1
  mkdir -p "$(dirname "$fixture/$path")"
  cp -- "$PROJECT_DIR/$path" "$fixture/$path"
done < <(prepaid_compatibility_files)
mkdir -p "$fixture/docs/deployment"
cp -- "$PROJECT_DIR/$PREPAID_MANIFEST" "$fixture/$PREPAID_MANIFEST"
[[ "$(prepaid_release_version "$fixture")" == 1 ]]

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
rm -- "$fixture/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php"
reject '2026_10_26_000100_create_care_hour_request_preferences.php'
cp -- "$PROJECT_DIR/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php" "$fixture/database/migrations/2026_10_26_000100_create_care_hour_request_preferences.php"
printf 'incompatible replacement\n' > "$fixture/app/Http/Controllers/CareHours/GiftClaimController.php"
reject 'GiftClaimController.php'
cp -- "$PROJECT_DIR/app/Http/Controllers/CareHours/GiftClaimController.php" "$fixture/app/Http/Controllers/CareHours/GiftClaimController.php"
printf 'extra manifest entry\n' >> "$fixture/$PREPAID_MANIFEST"
reject 'manifest version or inventory is unknown'
printf 'Prepaid manifest smoke passed (full inventory, missing family/admin writers, dispute and eligibility handlers, changed claim handler, extra entry).\n'
