<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportInteractionEvent;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AiSupportEventRecorder
{
    private const ALLOWED_FIELDS = [
        'support_ticket_message_id', 'pilot_grant_id', 'capability_id', 'reason_code',
        'model_configuration_version', 'prompt_schema_version', 'knowledge_version_ids',
        'navigation_target_id', 'tool_id', 'tool_version', 'result_code', 'latency_ms',
        'input_tokens', 'output_tokens', 'cost_microunits', 'safe_metadata',
    ];

    private const ALLOWED_METADATA_KEYS = [
        'ownership_from', 'ownership_to', 'transfer_priority', 'delivery_suppressed',
        'policy_result', 'validation_result', 'route_contract_version', 'confirmation_reference',
        'cached_input_tokens', 'provider_price_version', 'intent_id', 'resolution_source',
        'task_state', 'verifier_id', 'preparation_contract_id', 'repetition_count',
        'source_request_id',
    ];

    /** @param array<string, mixed> $fields */
    public function record(SupportTicket $ticket, string $eventType, array $fields = [], ?User $actor = null): AiSupportInteractionEvent
    {
        $this->ensureIdentifier($eventType, 'event type');
        $unknown = array_diff(array_keys($fields), self::ALLOWED_FIELDS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported interaction-event fields: '.implode(', ', $unknown));
        }

        $safeMetadata = (array) ($fields['safe_metadata'] ?? []);
        $unknownMetadata = array_diff(array_keys($safeMetadata), self::ALLOWED_METADATA_KEYS);
        if ($unknownMetadata !== []) {
            throw new InvalidArgumentException('Unsafe interaction metadata fields: '.implode(', ', $unknownMetadata));
        }

        foreach ($safeMetadata as $value) {
            if (is_array($value) || is_object($value)) {
                throw new InvalidArgumentException('Interaction metadata must be compact, scalar, and content-free.');
            }
            if (is_string($value)) {
                $this->ensureIdentifier($value, 'interaction metadata value');
            }
        }

        foreach ([
            'capability_id', 'reason_code', 'model_configuration_version', 'prompt_schema_version',
            'navigation_target_id', 'tool_id', 'tool_version', 'result_code',
        ] as $identifierField) {
            if (isset($fields[$identifierField])) {
                $this->ensureIdentifier($fields[$identifierField], $identifierField);
            }
        }

        if (isset($fields['pilot_grant_id']) && ! Str::isUuid((string) $fields['pilot_grant_id'])) {
            throw new InvalidArgumentException('Pilot grant evidence must use a UUID reference.');
        }

        if (isset($fields['knowledge_version_ids'])) {
            $knowledgeIds = $fields['knowledge_version_ids'];
            if (! is_array($knowledgeIds)
                || ! array_is_list($knowledgeIds)
                || count($knowledgeIds) > 50
                || collect($knowledgeIds)->contains(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1)) {
                throw new InvalidArgumentException('Knowledge evidence must contain only numeric version references.');
            }
        }

        foreach (['support_ticket_message_id', 'latency_ms', 'input_tokens', 'output_tokens', 'cost_microunits'] as $numericField) {
            if (isset($fields[$numericField])
                && (filter_var($fields[$numericField], FILTER_VALIDATE_INT) === false || (int) $fields[$numericField] < 0)) {
                throw new InvalidArgumentException("{$numericField} must be a non-negative integer.");
            }
        }

        $retentionStartedAt = $ticket->retention_started_at;

        return AiSupportInteractionEvent::query()->create([
            'id' => (string) Str::uuid(),
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            ...collect($fields)->except('safe_metadata')->all(),
            'safe_metadata' => $safeMetadata ?: null,
            'event_contract_version' => (string) config('ai_support.event_contract_version'),
            'retention_started_at' => $retentionStartedAt,
            'delete_after' => $retentionStartedAt?->copy()->addMonths(
                (int) config('ai_support.interaction_event_months', 24)
            ),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function ensureIdentifier(mixed $value, string $label): void
    {
        if (! is_string($value)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/', $value) !== 1) {
            throw new InvalidArgumentException("The {$label} must be a compact content-free identifier.");
        }
    }
}
