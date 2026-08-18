<?php

namespace App\Services\CareRecipientProfiles;

use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyRecipientProfile;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareRecipientProfileService
{
    private const TEXT_FIELDS = [
        'full_name', 'preferred_name', 'pronouns', 'relationship_to_family', 'about_them',
        'interests_and_comforts', 'good_visit_notes', 'communication_notes', 'everyday_health_context',
        'mobility_notes', 'routine_notes', 'food_and_drink_notes', 'personal_care_preferences',
        'sleep_overnight_notes', 'distress_triggers', 'calming_approaches', 'safety_notes',
        'caregiver_do_notes', 'caregiver_avoid_notes', 'additional_contact_name',
        'additional_contact_relationship', 'additional_contact_phone', 'additional_contact_email',
        'assigned_escalation_notes',
    ];

    private const WRITABLE_FIELDS = [
        'recipient_is_requester', 'full_name', 'preferred_name', 'date_of_birth', 'age_range',
        'pronouns', 'relationship_to_family', 'about_them', 'interests_and_comforts',
        'good_visit_notes', 'communication_preferences', 'communication_notes',
        'everyday_health_context', 'support_areas', 'support_details', 'mobility_level',
        'mobility_notes', 'routine_notes', 'food_and_drink_notes', 'personal_care_preferences',
        'sleep_overnight_notes', 'comfort_needs', 'distress_triggers', 'calming_approaches',
        'safety_items', 'safety_notes', 'caregiver_quality_preferences', 'caregiver_do_notes',
        'caregiver_avoid_notes', 'include_additional_contact', 'additional_contact_name',
        'additional_contact_relationship', 'additional_contact_phone', 'additional_contact_email',
        'assigned_escalation_notes',
    ];

    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly CareRecipientProfileSnapshotBuilder $snapshots,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveDraft(User $actor, ?CareRecipientProfile $profile, array $data, ?int $expectedRevision = null): CareRecipientProfile
    {
        $account = $this->familyAccounts->account($actor);

        return DB::transaction(function () use ($actor, $profile, $data, $expectedRevision, $account): CareRecipientProfile {
            if ($profile) {
                $locked = CareRecipientProfile::query()->forFamilyAccount($account)->lockForUpdate()->findOrFail($profile->id);
                $this->ensureRevision($locked, $expectedRevision);
            } else {
                $locked = new CareRecipientProfile([
                    'family_account_id' => $account->id,
                    'legacy_family_user_id' => $account->owner_user_id,
                    'created_by_user_id' => $actor->id,
                    'status' => CareRecipientProfile::STATUS_DRAFT,
                    'revision' => 0,
                ]);
            }

            if ($locked->isArchived()) {
                throw ValidationException::withMessages(['profile' => 'Restore this care profile before editing it.']);
            }

            $locked->fill($this->clean($data));
            $locked->updated_by_user_id = $actor->id;
            $locked->revision = ((int) $locked->revision) + 1;
            $locked->save();

            $this->ensureDefault($locked, $account->id);
            $this->log($locked, $actor, $profile ? 'care_profile_draft_updated' : 'care_profile_created');

            return $locked->fresh(['latestReadyVersion', 'updatedBy']);
        });
    }

    /** @param array<string, mixed> $data */
    public function makeReady(
        User $actor,
        CareRecipientProfile $profile,
        array $data,
        int $expectedRevision,
        bool $sharingAcknowledged,
    ): CareRecipientProfile {
        $account = $this->familyAccounts->account($actor);

        return DB::transaction(function () use ($actor, $profile, $data, $expectedRevision, $sharingAcknowledged, $account): CareRecipientProfile {
            $locked = CareRecipientProfile::query()->forFamilyAccount($account)->lockForUpdate()->findOrFail($profile->id);
            $this->ensureRevision($locked, $expectedRevision);
            $locked->fill($this->clean($data));

            if (trim((string) $locked->preferred_name) === '') {
                throw ValidationException::withMessages(['preferred_name' => 'Add the name this person prefers caregivers to use.']);
            }
            if (! $locked->hasMeaningfulShareableContent()) {
                throw ValidationException::withMessages(['profile' => 'Add at least one helpful detail before sharing this care profile.']);
            }
            if (! $locked->sharing_acknowledged_at && ! $sharingAcknowledged) {
                throw ValidationException::withMessages(['sharingAcknowledged' => 'Confirm that you understand what caregivers will see.']);
            }

            $locked->forceFill([
                'status' => CareRecipientProfile::STATUS_READY,
                'updated_by_user_id' => $actor->id,
                'last_reviewed_at' => now(),
                'revision' => ((int) $locked->revision) + 1,
                'sharing_acknowledged_at' => $locked->sharing_acknowledged_at ?: now(),
                'sharing_acknowledged_by_user_id' => $locked->sharing_acknowledged_by_user_id ?: $actor->id,
            ])->save();

            $versionNumber = ((int) $locked->versions()->max('version_number')) + 1;
            $version = CareRecipientProfileVersion::query()->create([
                'care_recipient_profile_id' => $locked->id,
                'version_number' => $versionNumber,
                'created_by_user_id' => $actor->id,
                'candidate_snapshot' => $this->snapshots->candidate($locked),
                'assigned_snapshot' => $this->snapshots->assigned($locked),
            ]);
            $locked->forceFill(['latest_ready_version_id' => $version->id])->saveQuietly();

            $this->ensureDefault($locked, $account->id);
            $this->mirrorLegacyDefault($locked);
            $this->log($locked, $actor, 'care_profile_ready', ['version_id' => $version->id]);

            return $locked->fresh(['latestReadyVersion', 'updatedBy']);
        });
    }

    public function archive(User $actor, CareRecipientProfile $profile): CareRecipientProfile
    {
        return $this->changeArchiveState($actor, $profile, true);
    }

    public function restore(User $actor, CareRecipientProfile $profile): CareRecipientProfile
    {
        return $this->changeArchiveState($actor, $profile, false);
    }

    public function makeDefault(User $actor, CareRecipientProfile $profile): void
    {
        $account = $this->familyAccounts->account($actor);
        DB::transaction(function () use ($actor, $profile, $account): void {
            $owned = CareRecipientProfile::query()
                ->forFamilyAccount($account)
                ->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)
                ->lockForUpdate()
                ->findOrFail($profile->id);
            $lockedAccount = $account->newQuery()->lockForUpdate()->findOrFail($account->id);
            $lockedAccount->forceFill(['default_care_recipient_profile_id' => $owned->id])->save();
            $owned->setRelation('familyAccount', $lockedAccount);
            $this->mirrorLegacyDefault($owned);
            $this->log($owned, $actor, 'care_profile_made_default');
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function clean(array $data): array
    {
        $clean = Arr::only($data, self::WRITABLE_FIELDS);
        foreach (self::TEXT_FIELDS as $field) {
            if (! array_key_exists($field, $clean)) {
                continue;
            }
            $value = trim((string) $clean[$field]);
            $clean[$field] = $value === '' ? null : $value;
        }

        $clean['recipient_is_requester'] = (bool) ($clean['recipient_is_requester'] ?? false);
        $clean['include_additional_contact'] = (bool) ($clean['include_additional_contact'] ?? false);
        $clean['date_of_birth'] = ($clean['date_of_birth'] ?? null) ?: null;
        $clean['age_range'] = $this->enum($clean['age_range'] ?? null, CareRecipientProfile::AGE_RANGES, 'age range');
        $clean['mobility_level'] = $this->enum($clean['mobility_level'] ?? null, CareRecipientProfile::MOBILITY_LEVELS, 'mobility choice');
        $clean['communication_preferences'] = $this->enumList($clean['communication_preferences'] ?? [], CareRecipientProfile::COMMUNICATION_OPTIONS, 'communication choice');
        $clean['support_areas'] = $this->enumList($clean['support_areas'] ?? [], CareRecipientProfile::SUPPORT_AREAS, 'support choice');
        $clean['comfort_needs'] = $this->enumList($clean['comfort_needs'] ?? [], CareRecipientProfile::COMFORT_NEEDS, 'comfort choice');
        $clean['safety_items'] = $this->enumList($clean['safety_items'] ?? [], CareRecipientProfile::SAFETY_ITEMS, 'safety choice');
        $clean['caregiver_quality_preferences'] = array_slice(
            $this->enumList($clean['caregiver_quality_preferences'] ?? [], CareRecipientProfile::CAREGIVER_QUALITIES, 'caregiver quality'),
            0,
            5,
        );

        $supportAreas = array_flip($clean['support_areas']);
        $details = [];
        foreach ((array) ($clean['support_details'] ?? []) as $key => $value) {
            if (! is_string($key) || ! isset($supportAreas[$key], CareRecipientProfile::SUPPORT_AREAS[$key])) {
                throw ValidationException::withMessages(['support_details' => 'A support detail is not recognized.']);
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $details[$key] = mb_substr($value, 0, 300);
            }
        }
        $clean['support_details'] = $details;

        if (! $clean['include_additional_contact']) {
            foreach (['additional_contact_name', 'additional_contact_relationship', 'additional_contact_phone', 'additional_contact_email'] as $field) {
                $clean[$field] = null;
            }
        }

        return $clean;
    }

    /** @return list<string> */
    public function writableFields(): array
    {
        return self::WRITABLE_FIELDS;
    }

    /** @param array<string,mixed> $patch @return array<string,mixed> */
    public function mergedData(?CareRecipientProfile $profile, array $patch): array
    {
        $unknown = array_diff(array_keys($patch), self::WRITABLE_FIELDS);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['profile' => 'One proposed profile field is not supported.']);
        }

        $base = [];
        foreach (self::WRITABLE_FIELDS as $field) {
            $base[$field] = $profile?->getAttribute($field);
        }

        return $this->clean(array_replace($base, $patch));
    }

    private function changeArchiveState(User $actor, CareRecipientProfile $profile, bool $archive): CareRecipientProfile
    {
        $account = $this->familyAccounts->account($actor);

        return DB::transaction(function () use ($actor, $profile, $archive, $account): CareRecipientProfile {
            $locked = CareRecipientProfile::query()->forFamilyAccount($account)->lockForUpdate()->findOrFail($profile->id);
            $locked->forceFill([
                'status' => $archive ? CareRecipientProfile::STATUS_ARCHIVED : ($locked->latest_ready_version_id ? CareRecipientProfile::STATUS_READY : CareRecipientProfile::STATUS_DRAFT),
                'archived_at' => $archive ? now() : null,
                'updated_by_user_id' => $actor->id,
                'revision' => ((int) $locked->revision) + 1,
            ])->save();

            if ($archive && (int) $account->default_care_recipient_profile_id === (int) $locked->id) {
                $replacement = CareRecipientProfile::query()->forFamilyAccount($account)
                    ->where('id', '!=', $locked->id)
                    ->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)
                    ->oldest('id')
                    ->first();
                $account->forceFill(['default_care_recipient_profile_id' => $replacement?->id])->save();
                if ($replacement) {
                    $this->mirrorLegacyDefault($replacement);
                }
            }

            $this->log($locked, $actor, $archive ? 'care_profile_archived' : 'care_profile_restored');

            return $locked->fresh();
        });
    }

    private function ensureRevision(CareRecipientProfile $profile, ?int $expected): void
    {
        if ($expected !== null && (int) $profile->revision !== $expected) {
            $editor = $profile->updatedBy?->name ?: 'Another family member';
            throw ValidationException::withMessages([
                'profile' => $editor.' updated this profile while you were editing. Review the latest information before saving your changes.',
            ]);
        }
    }

    private function ensureDefault(CareRecipientProfile $profile, int $accountId): void
    {
        $account = $profile->familyAccount()->lockForUpdate()->findOrFail($accountId);
        if (! $account->default_care_recipient_profile_id) {
            $account->forceFill(['default_care_recipient_profile_id' => $profile->id])->save();
        }
    }

    private function mirrorLegacyDefault(CareRecipientProfile $profile): void
    {
        $account = $profile->familyAccount;
        if ((int) $account->default_care_recipient_profile_id !== (int) $profile->id) {
            return;
        }

        $legacy = FamilyRecipientProfile::query()->withoutGlobalScopes()->updateOrCreate(
            // The legacy table still guarantees one row per original family owner.
            // Older rows may not have been account-scoped yet, so looking them up by
            // family_account_id can miss the row and violate the owner uniqueness key.
            ['family_user_id' => $profile->legacy_family_user_id],
            [
                'family_account_id' => $profile->family_account_id,
                'family_user_id' => $profile->legacy_family_user_id,
                'recipient_is_requester' => $profile->recipient_is_requester,
                'full_name' => $profile->full_name ?: $profile->preferred_name,
                'date_of_birth' => $profile->date_of_birth,
                'mobility_level' => $profile->mobility_level,
                'relationship_to_family' => $profile->relationship_to_family,
                'care_notes' => $profile->about_them,
                'include_third_party_contact' => $profile->include_additional_contact,
                'third_party_full_name' => $profile->additional_contact_name,
                'third_party_relationship_to_recipient' => $profile->additional_contact_relationship,
                'third_party_phone' => $profile->additional_contact_phone,
                'third_party_email' => $profile->additional_contact_email,
            ],
        );

        CareRecipientProfile::query()->withoutGlobalScopes()
            ->where('family_account_id', $profile->family_account_id)
            ->where('id', '!=', $profile->id)
            ->where('legacy_family_recipient_profile_id', $legacy->id)
            ->update(['legacy_family_recipient_profile_id' => null]);

        if ((int) $profile->legacy_family_recipient_profile_id !== (int) $legacy->id) {
            $profile->forceFill(['legacy_family_recipient_profile_id' => $legacy->id])->saveQuietly();
        }
    }

    /** @param array<string, mixed> $metadata */
    private function log(CareRecipientProfile $profile, ?User $actor, string $action, array $metadata = []): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $profile->family_account_id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'metadata' => array_merge(['care_recipient_profile_id' => $profile->id], $metadata),
        ]);
    }

    /** @param array<string, string> $options */
    private function enum(mixed $value, array $options, string $label): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! array_key_exists($value, $options)) {
            throw ValidationException::withMessages(['profile' => 'An unknown '.$label.' was submitted.']);
        }

        return $value;
    }

    /** @param array<string, string> $options @return list<string> */
    private function enumList(mixed $values, array $options, string $label): array
    {
        $values = array_values(array_unique(array_filter((array) $values, 'is_string')));
        if (array_diff($values, array_keys($options)) !== []) {
            throw ValidationException::withMessages(['profile' => 'An unknown '.$label.' was submitted.']);
        }

        return $values;
    }
}
