<?php

return [
    /*
    | This deployment guard is deliberately separate from database controls.
    | A missing environment value must never make customer AI available.
    */
    'runtime_available' => (bool) env('AI_SUPPORT_RUNTIME_AVAILABLE', false),
    'retention_execution_enabled' => (bool) env('AI_SUPPORT_RETENTION_EXECUTION_ENABLED', false),
    'shadow_mutations_allowed' => false,

    /*
    | This switch controls a local, synthetic-only evaluation adapter. It is
    | intentionally independent from the customer runtime and defaults off.
    */
    'offline_evaluation_enabled' => filter_var(
        env('AI_SUPPORT_OFFLINE_EVALUATION_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    | Customer-runtime provider access is an additional deployment guard. It is
    | intentionally independent from eligibility and every database control.
    */
    'provider_enabled' => filter_var(env('AI_SUPPORT_PROVIDER_ENABLED', false), FILTER_VALIDATE_BOOL),
    'model' => env('AI_SUPPORT_MODEL', 'gpt-5.6-luna'),
    'reasoning_effort' => env('AI_SUPPORT_REASONING_EFFORT', 'low'),
    'max_output_tokens' => (int) env('AI_SUPPORT_MAX_OUTPUT_TOKENS', 900),
    'safety_identifier_secret' => env('AI_SUPPORT_SAFETY_IDENTIFIER_SECRET'),
    'model_configuration_version' => 'luna-low-v2',
    'prompt_schema_version' => 'interactive-support-v3',
    'provider_retry_attempts' => 2,
    'provider_price_version' => 'openai-gpt-5.6-luna-2026-08-14',
    'provider_price_source' => 'https://developers.openai.com/api/docs/models/gpt-5.6-luna',
    'provider_price_effective_on' => '2026-08-14',
    'provider_input_usd_per_million' => 0.20,
    'provider_cached_input_usd_per_million' => 0.02,
    'provider_output_usd_per_million' => 1.20,
    'conversation_cost_alert_microunits' => 30_000,
    'conversation_cost_stop_microunits' => 50_000,
    'rehearsal_daily_cost_stop_microunits' => 2_000_000,
    'pilot_daily_cost_stop_microunits' => 5_000_000,
    'pilot_daily_model_turn_limit' => 50,
    'provider_monthly_budget_alert_usd' => 25,
    'conversation_p95_target_ms' => 5_000,
    'tool_p95_target_ms' => 8_000,
    'performance_minimum_sample' => 20,
    'draft_retention_days' => 7,
    'confirmation_validity_minutes' => 30,

    'policy_version' => 'ai-support-eligibility-v1',
    'context_contract_version' => 'support-context-v1',
    'event_contract_version' => 'support-event-v1',
    'navigation_contract_version' => 'support-navigation-v1',
    'confirmation_contract_version' => 'support-confirmation-v1',

    'default_grant_days' => 14,
    'grant_history_months' => 24,
    'control_history_months' => 24,
    'kb_full_history_months' => 36,
    'kb_tombstone_months' => 24,
    'support_transcript_months' => 12,
    'interaction_event_months' => 24,
    'confirmed_action_months' => 24,
    'deletion_evidence_months' => 36,
    'readiness_evidence_months' => 24,
    'incident_evidence_months' => 24,
    'preview_content_max_hours' => 24,
    'retention_policy_version' => 'ai-support-retention-v1',
    'reason_max_length' => 500,

    'supported_roles' => ['family', 'caregiver'],

    'bundles' => [
        'family_support_v1' => [
            'label' => 'Family support pilot v1',
            'roles' => ['family'],
            'capabilities' => [
                'support_answers_v1',
                'semantic_navigation_v1',
                'family_context_v1',
                'care_intake_v1',
                'care_request_draft_v1',
                'care_request_recap_v1',
                'care_request_publish_v1',
                'care_24h_handoff_v1',
            ],
        ],
        'caregiver_support_v1' => [
            'label' => 'Caregiver support pilot v1',
            'roles' => ['caregiver'],
            'capabilities' => ['support_answers_v1', 'semantic_navigation_v1'],
        ],
    ],

    'controls' => [
        'master_enabled' => false,
        'user_visible_enabled' => false,
        'shadow_enabled' => false,
        'human_only' => true,
        'role.family' => false,
        'role.caregiver' => false,
        'capability.support_answers_v1' => false,
        'capability.semantic_navigation_v1' => false,
        'capability.family_context_v1' => false,
        'capability.care_intake_v1' => false,
        'capability.care_request_draft_v1' => false,
        'capability.care_request_recap_v1' => false,
        'capability.care_request_publish_v1' => false,
        'capability.care_24h_handoff_v1' => false,
        'commit.one_time' => false,
        'commit.recurring' => false,
        'tool.care-request.publish.one-time' => false,
        'tool.care-request.publish.recurring' => false,
    ],

    /*
    | Material-action tools are registered here but remain disabled by default.
    | Each release still requires its exact capability/version entry plus both
    | the capability and tool controls to be explicitly enabled.
    */
    'tools' => [
        'care-request.publish.one-time' => [
            'capability_id' => 'care_request_publish_v1',
            'versions' => ['v1'],
            'preview_validity_minutes' => 30,
        ],
        'care-request.publish.recurring' => [
            'capability_id' => 'care_request_publish_v1',
            'versions' => ['v1'],
            'preview_validity_minutes' => 30,
        ],
    ],

    'navigation_targets' => [
        'support.center' => ['route' => 'support.index', 'roles' => ['family', 'caregiver']],
        'family.dashboard' => ['route' => 'dashboard', 'roles' => ['family']],
        'family.care_requests' => ['route' => 'family.requests.index', 'roles' => ['family']],
        'family.new_care_request' => ['route' => 'family.requests.create', 'roles' => ['family']],
        'family.access' => ['route' => 'family.access', 'roles' => ['family']],
        'caregiver.dashboard' => ['route' => 'dashboard', 'roles' => ['caregiver']],
        'caregiver.work_inbox' => ['route' => 'caregiver.work-inbox.index', 'roles' => ['caregiver']],
        'caregiver.shifts' => ['route' => 'caregiver.shifts.index', 'roles' => ['caregiver']],
        'account.profile' => ['route' => 'profile', 'roles' => ['family', 'caregiver']],
    ],
];
