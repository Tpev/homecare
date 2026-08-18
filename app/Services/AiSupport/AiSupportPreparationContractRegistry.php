<?php

namespace App\Services\AiSupport;

use DomainException;

class AiSupportPreparationContractRegistry
{
    public const VERSION = 'v1';

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return [
            'care_profile_v1' => [
                'label' => 'care receiver profile',
                'target' => 'family.care_profile.create',
                'fields' => [
                    'preferred_name', 'full_name', 'date_of_birth', 'pronouns', 'relationship_to_family',
                    'about_them', 'interests_and_comforts', 'good_visit_notes', 'communication_notes',
                    'everyday_health_context', 'mobility_level', 'mobility_notes', 'routine_notes',
                    'food_and_drink_notes', 'personal_care_preferences', 'sleep_overnight_notes',
                    'safety_notes', 'additional_contact_name', 'additional_contact_relationship',
                    'additional_contact_phone', 'additional_contact_email',
                ],
            ],
            'care_request_reuse_v1' => [
                'label' => 'care request copy',
                'target' => 'family.new_care_request',
                'fields' => [
                    'source_request_id', 'recipient_profile_id', 'recipient_name', 'task_ids', 'task_notes',
                    'additional_info', 'address_line1', 'address_line2', 'city', 'state', 'postal_code',
                    'home_access_notes', 'request_type', 'schedule',
                ],
            ],
            'caregiver_message_v1' => [
                'label' => 'caregiver message',
                'target' => 'family.message',
                'fields' => ['conversation_id', 'subject', 'message'],
            ],
            'submitted_hours_correction_v1' => [
                'label' => 'submitted-hours correction',
                'target' => 'family.request.timesheet',
                'fields' => [
                    'care_request_id', 'booking_id', 'correction_id', 'issue_type',
                    'proposed_start', 'proposed_end', 'reason',
                ],
            ],
            'support_intake_v1' => [
                'label' => 'support handoff details',
                'target' => 'support.center',
                'fields' => ['category', 'subject', 'description', 'route_name', 'resource_type', 'resource_id'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function definition(string $contractId): array
    {
        $definition = $this->all()[$contractId] ?? null;
        if (! is_array($definition)) {
            throw new DomainException('Unknown AI Support preparation contract.');
        }

        return $definition;
    }
}
