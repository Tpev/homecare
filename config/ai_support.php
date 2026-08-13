<?php

return [
    /*
    | This deployment guard is deliberately separate from database controls.
    | A missing environment value must never make customer AI available.
    */
    'runtime_available' => (bool) env('AI_SUPPORT_RUNTIME_AVAILABLE', false),
    'shadow_mutations_allowed' => false,

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
    'preview_content_max_hours' => 24,
    'retention_policy_version' => 'ai-support-retention-v1',
    'reason_max_length' => 500,

    'supported_roles' => ['family', 'caregiver'],

    'bundles' => [
        'family_support_v1' => [
            'label' => 'Family support pilot v1',
            'roles' => ['family'],
            'capabilities' => ['support_answers_v1'],
        ],
        'caregiver_support_v1' => [
            'label' => 'Caregiver support pilot v1',
            'roles' => ['caregiver'],
            'capabilities' => ['support_answers_v1'],
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
    ],

    /*
    | Material-action tools remain unregistered in this foundation release.
    | A later capability release must add an exact tool/capability/version entry
    | and its deny-by-default capability and tool controls before use.
    */
    'tools' => [],

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
