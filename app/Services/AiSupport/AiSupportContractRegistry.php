<?php

namespace App\Services\AiSupport;

class AiSupportContractRegistry
{
    /** @return array<string, mixed> */
    public function versions(): array
    {
        return [
            'context' => (string) config('ai_support.context_contract_version'),
            'event' => (string) config('ai_support.event_contract_version'),
            'navigation' => (string) config('ai_support.navigation_contract_version'),
            'confirmation' => (string) config('ai_support.confirmation_contract_version'),
            'eligibility' => (string) config('ai_support.policy_version'),
            'retention' => (string) config('ai_support.retention_policy_version'),
            'kb_validation' => 'kb-validation-v1',
            'kb_governance' => 'kb-governance-v1',
        ];
    }

    /** @return array<string, mixed> */
    public function confirmationSchema(): array
    {
        return [
            'preview_bound_to' => ['actor', 'conversation', 'capability', 'tool_version', 'material_hash', 'expiration'],
            'confirmation_storage' => 'reference_hash_only',
            'commit_requires' => ['registered_tool_version', 'current_exact_user_eligibility', 'exact_reference', 'exact_actor', 'automated_ownership', 'unexpired_preview', 'idempotency_key'],
            'evidence_after' => 'authoritative_commit_only',
            'preview_content_max_hours' => (int) config('ai_support.preview_content_max_hours', 24),
        ];
    }
}
