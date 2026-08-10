<?php

namespace App\Services\CareRecipientProfiles;

use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyRecipientProfile;
use Illuminate\Support\Facades\DB;

class CareRecipientProfileBackfill
{
    public function __construct(private readonly CareRecipientProfileSnapshotBuilder $snapshots) {}

    /** @return array{created:int,versions:int,attached:int,skipped:int} */
    public function run(): array
    {
        $result = ['created' => 0, 'versions' => 0, 'attached' => 0, 'skipped' => 0];

        FamilyRecipientProfile::query()->withoutGlobalScopes()
            ->whereNotNull('family_account_id')
            ->orderBy('id')
            ->each(function (FamilyRecipientProfile $legacy) use (&$result): void {
                DB::transaction(function () use ($legacy, &$result): void {
                    $account = FamilyAccount::query()->lockForUpdate()->find($legacy->family_account_id);
                    if (! $account) {
                        $result['skipped']++;

                        return;
                    }

                    $profile = CareRecipientProfile::query()->withoutGlobalScopes()
                        ->where('legacy_family_recipient_profile_id', $legacy->id)
                        ->first();

                    if (! $profile) {
                        $preferredName = trim((string) $legacy->full_name) ?: null;
                        // Legacy free-text care notes remain family-draft content until a family
                        // member explicitly previews and saves them under the new sharing model.
                        $meaningful = trim((string) $legacy->mobility_level) !== '';
                        $profile = CareRecipientProfile::query()->withoutGlobalScopes()->create([
                            'family_account_id' => $account->id,
                            'legacy_family_user_id' => $account->owner_user_id,
                            'legacy_family_recipient_profile_id' => $legacy->id,
                            'status' => $meaningful && $preferredName ? CareRecipientProfile::STATUS_READY : CareRecipientProfile::STATUS_DRAFT,
                            'recipient_is_requester' => (bool) $legacy->recipient_is_requester,
                            'full_name' => $legacy->full_name,
                            'preferred_name' => $preferredName,
                            'date_of_birth' => $legacy->date_of_birth,
                            'relationship_to_family' => $legacy->relationship_to_family,
                            'about_them' => $legacy->care_notes,
                            'mobility_level' => $this->legacyMobility($legacy->mobility_level),
                            'mobility_notes' => $this->legacyMobility($legacy->mobility_level) ? null : $legacy->mobility_level,
                            'include_additional_contact' => (bool) $legacy->include_third_party_contact,
                            'additional_contact_name' => $legacy->third_party_full_name,
                            'additional_contact_relationship' => $legacy->third_party_relationship_to_recipient,
                            'additional_contact_phone' => $legacy->third_party_phone,
                            'additional_contact_email' => $legacy->third_party_email,
                            'last_reviewed_at' => $meaningful && $preferredName ? ($legacy->updated_at ?: now()) : null,
                            'revision' => 1,
                        ]);
                        $result['created']++;
                    }

                    if (! $account->default_care_recipient_profile_id) {
                        $account->forceFill(['default_care_recipient_profile_id' => $profile->id])->save();
                    }

                    if ($profile->status === CareRecipientProfile::STATUS_READY && ! $profile->latest_ready_version_id) {
                        $shareableProfile = $profile->replicate();
                        $shareableProfile->setAttribute('about_them', null);
                        $version = CareRecipientProfileVersion::query()->create([
                            'care_recipient_profile_id' => $profile->id,
                            'version_number' => 1,
                            'created_by_user_id' => null,
                            'candidate_snapshot' => $this->snapshots->candidate($shareableProfile),
                            'assigned_snapshot' => $this->snapshots->assigned($shareableProfile),
                        ]);
                        $profile->forceFill(['latest_ready_version_id' => $version->id])->saveQuietly();
                        $result['versions']++;
                    }

                    FamilyAccountActivityLog::query()->firstOrCreate(
                        [
                            'family_account_id' => $account->id,
                            'action' => 'care_profile_backfilled',
                            'actor_user_id' => null,
                        ],
                        ['metadata' => ['care_recipient_profile_id' => $profile->id]],
                    );

                    if ($profile->latest_ready_version_id) {
                        $this->attachClearMatches($profile, $result);
                    }
                });
            });

        return $result;
    }

    /** @param array{created:int,versions:int,attached:int,skipped:int} $result */
    private function attachClearMatches(CareRecipientProfile $profile, array &$result): void
    {
        CareRecipient::query()
            ->whereNull('care_recipient_profile_id')
            ->whereHas('careRequest', fn ($query) => $query
                ->where('family_account_id', $profile->family_account_id)
                ->where('status', CareRequest::STATUS_OPEN))
            ->with('careRequest:id,family_account_id,status')
            ->get()
            ->each(function (CareRecipient $recipient) use ($profile, &$result): void {
                $selfMatch = (bool) $profile->recipient_is_requester && $recipient->receivesCareAsRequester();
                $profileName = $this->normalizedName($profile->full_name ?: $profile->preferred_name);
                $recipientName = $this->normalizedName($recipient->full_name);
                $nameMatch = $profileName !== '' && $recipientName !== '' && $profileName === $recipientName;

                if (! $selfMatch && ! $nameMatch) {
                    return;
                }

                $recipient->forceFill([
                    'care_recipient_profile_id' => $profile->id,
                    'care_recipient_profile_version_id' => $profile->latest_ready_version_id,
                ])->save();
                $result['attached']++;
            });
    }

    private function legacyMobility(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'independent' => 'independent',
            'standby_support' => 'someone_nearby',
            'transfer_assistance' => 'transfer_help',
            'wheelchair_user' => 'uses_aid',
            'bedbound' => 'hands_on',
            default => array_key_exists($normalized, CareRecipientProfile::MOBILITY_LEVELS) ? $normalized : null,
        };
    }

    private function normalizedName(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?: '';
    }
}
