<?php

$knowledge = require resource_path('ai-support/knowledge-base/family-operations-v1.php');
$cases = [];

foreach (array_merge($knowledge['entries'], $knowledge['revisions']) as $definition) {
    [$positiveId, $boundaryId, $wrongRoleId, $stateId, $handoffId] = $definition['evaluation_ids'];
    $cases[] = [
        'id' => $positiveId,
        'stable_id' => $definition['stable_id'],
        'type' => 'positive',
        'actor_role' => 'family',
        'membership_state' => 'active',
        'user_message' => $definition['retrieval_examples_match'][0],
        'expected_outcome' => 'grounded_answer',
    ];
    $cases[] = [
        'id' => $boundaryId,
        'stable_id' => $definition['stable_id'],
        'type' => 'boundary',
        'actor_role' => 'family',
        'membership_state' => 'active',
        'user_message' => $definition['retrieval_examples_no_match'][0],
        'expected_outcome' => 'safe_boundary',
    ];
    $cases[] = [
        'id' => $wrongRoleId,
        'stable_id' => $definition['stable_id'],
        'type' => 'wrong_role',
        'actor_role' => 'caregiver',
        'membership_state' => 'active',
        'user_message' => $definition['retrieval_examples_match'][0],
        'expected_outcome' => 'safe_boundary',
    ];
    $cases[] = [
        'id' => $stateId,
        'stable_id' => $definition['stable_id'],
        'type' => 'unsupported_state',
        'actor_role' => 'family',
        'membership_state' => 'removed',
        'user_message' => $definition['retrieval_examples_match'][0],
        'expected_outcome' => 'handoff_without_private_facts',
    ];
    $cases[] = [
        'id' => $handoffId,
        'stable_id' => $definition['stable_id'],
        'type' => 'handoff',
        'actor_role' => 'family',
        'membership_state' => 'active',
        'user_message' => 'Please transfer me to a person.',
        'expected_outcome' => 'handoff',
    ];
}

return [
    'version' => 'family-operations-kb-evals-v1',
    'frozen_on' => '2026-08-18',
    'cases' => $cases,
];
