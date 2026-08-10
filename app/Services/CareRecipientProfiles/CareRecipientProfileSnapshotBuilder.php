<?php

namespace App\Services\CareRecipientProfiles;

use App\Models\CareRecipientProfile;
use Illuminate\Validation\ValidationException;

class CareRecipientProfileSnapshotBuilder
{
    public const PROHIBITED_CANDIDATE_KEYS = [
        'date_of_birth', 'dob', 'full_name', 'legal_name', 'last_name',
        'address', 'exact_address', 'address_line1', 'address_line2', 'home_access', 'home_access_notes',
        'phone', 'email', 'contact', 'contacts', 'additional_contact', 'emergency_contact',
        'billing', 'payment', 'family_notes', 'family_only_notes', 'medication', 'medications', 'medication_dosage',
        'family_account_id', 'user_id', 'profile_id', 'version_id', 'request_id',
    ];

    /** @return array<string, mixed> */
    public function candidate(CareRecipientProfile $profile): array
    {
        $snapshot = $this->withoutEmpty([
            'preferred_name' => $profile->displayName(),
            'relationship_context' => $profile->recipient_is_requester
                ? 'Care for me'
                : $this->text($profile->relationship_to_family),
            'age_range' => CareRecipientProfile::AGE_RANGES[$profile->age_range] ?? null,
            'pronouns' => $this->text($profile->pronouns),
            'last_reviewed_at' => $profile->last_reviewed_at?->toDateString() ?? now()->toDateString(),
            'sections' => $this->withoutEmpty([
                'at_a_glance' => $this->withoutEmpty([
                    'about' => $this->text($profile->about_them),
                    'interests_and_comforts' => $this->text($profile->interests_and_comforts),
                    'good_visit' => $this->text($profile->good_visit_notes),
                    'everyday_health_context' => $this->text($profile->everyday_health_context),
                ]),
                'communication' => $this->withoutEmpty([
                    'preferences' => $this->labels($profile->communication_preferences, CareRecipientProfile::COMMUNICATION_OPTIONS),
                    'notes' => $this->text($profile->communication_notes),
                ]),
                'support_and_mobility' => $this->withoutEmpty([
                    'support_areas' => $this->labels($profile->support_areas, CareRecipientProfile::SUPPORT_AREAS),
                    'support_details' => $this->supportDetails($profile),
                    'mobility' => CareRecipientProfile::MOBILITY_LEVELS[$profile->mobility_level] ?? null,
                    'mobility_notes' => $this->text($profile->mobility_notes),
                ]),
                'routine_and_comfort' => $this->withoutEmpty([
                    'routine' => $this->text($profile->routine_notes),
                    'food_and_drink' => $this->text($profile->food_and_drink_notes),
                    'personal_care' => $this->text($profile->personal_care_preferences),
                    'sleep_and_overnight' => $this->text($profile->sleep_overnight_notes),
                    'comfort_needs' => $this->labels($profile->comfort_needs, CareRecipientProfile::COMFORT_NEEDS),
                    'may_feel_uncomfortable_when' => $this->text($profile->distress_triggers),
                    'what_helps' => $this->text($profile->calming_approaches),
                ]),
                'important_for_safety' => $this->withoutEmpty([
                    'items' => $this->labels($profile->safety_items, CareRecipientProfile::SAFETY_ITEMS),
                    'notes' => $this->text($profile->safety_notes),
                ]),
                'family_expectations' => $this->withoutEmpty([
                    'qualities' => $this->labels($profile->caregiver_quality_preferences, CareRecipientProfile::CAREGIVER_QUALITIES),
                    'please_do' => $this->text($profile->caregiver_do_notes),
                    'please_avoid' => $this->text($profile->caregiver_avoid_notes),
                ]),
            ]),
        ]);

        $this->assertCandidateSafe($snapshot);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function assigned(CareRecipientProfile $profile): array
    {
        $snapshot = $this->candidate($profile);
        $snapshot['full_name'] = $this->text($profile->full_name);

        if ($profile->include_additional_contact) {
            $snapshot['contacts_and_care_coordination'] = $this->withoutEmpty([
                'name' => $this->text($profile->additional_contact_name),
                'relationship' => $this->text($profile->additional_contact_relationship),
                'phone' => $this->text($profile->additional_contact_phone),
                'email' => $this->text($profile->additional_contact_email),
                'escalation_note' => $this->text($profile->assigned_escalation_notes),
            ]);
        } elseif ($this->text($profile->assigned_escalation_notes) !== null) {
            $snapshot['contacts_and_care_coordination'] = [
                'escalation_note' => $this->text($profile->assigned_escalation_notes),
            ];
        }

        return $this->withoutEmpty($snapshot);
    }

    /** @param array<string, mixed> $snapshot */
    public function assertCandidateSafe(array $snapshot): void
    {
        $prohibited = array_map('strtolower', self::PROHIBITED_CANDIDATE_KEYS);
        $found = [];
        $walk = function (array $values) use (&$walk, &$found, $prohibited): void {
            foreach ($values as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), $prohibited, true)) {
                    $found[] = $key;
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($snapshot);

        if ($found !== []) {
            throw ValidationException::withMessages([
                'profile' => 'The caregiver preview contains a private field and was not saved.',
            ]);
        }
    }

    /** @param array<int, string>|null $selected @param array<string, string> $options @return list<string> */
    private function labels(?array $selected, array $options): array
    {
        return collect($selected ?? [])
            ->filter(fn ($key) => is_string($key) && array_key_exists($key, $options))
            ->map(fn ($key) => $options[$key])
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private function supportDetails(CareRecipientProfile $profile): array
    {
        $selected = array_flip($profile->support_areas ?? []);

        return collect($profile->support_details ?? [])
            ->filter(fn ($value, $key) => isset($selected[$key])
                && isset(CareRecipientProfile::SUPPORT_AREAS[$key])
                && $this->text($value) !== null)
            ->mapWithKeys(fn ($value, $key) => [CareRecipientProfile::SUPPORT_AREAS[$key] => trim((string) $value)])
            ->all();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function withoutEmpty(array $values): array
    {
        return array_filter($values, static fn ($value) => ! ($value === null || $value === '' || $value === []));
    }
}
