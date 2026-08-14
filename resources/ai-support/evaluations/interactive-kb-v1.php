<?php

$knowledge = require resource_path('ai-support/knowledge-base/interactive-v1.php');
$cases = [];
foreach ($knowledge['entries'] as $entry) {
    [$positiveId, $boundaryId, $wrongRoleId, $stateId, $handoffId] = $entry['evaluation_ids'];
    $primaryRole = $entry['roles'][0];
    $wrongRole = $primaryRole === 'family' ? 'caregiver' : 'family';
    $cases[] = [
        'id' => $positiveId,
        'stable_id' => $entry['stable_id'],
        'type' => 'positive',
        'actor_role' => $primaryRole,
        'membership_state' => 'active',
        'user_message' => $entry['retrieval_examples_match'][0],
        'expected_outcome' => 'grounded_answer',
    ];
    $cases[] = [
        'id' => $boundaryId,
        'stable_id' => $entry['stable_id'],
        'type' => 'boundary',
        'actor_role' => $primaryRole,
        'membership_state' => 'active',
        'user_message' => $entry['retrieval_examples_no_match'][0],
        'expected_outcome' => 'safe_boundary',
    ];
    $cases[] = [
        'id' => $wrongRoleId,
        'stable_id' => $entry['stable_id'],
        'type' => 'wrong_role',
        'actor_role' => $wrongRole,
        'membership_state' => 'active',
        'user_message' => $entry['retrieval_examples_match'][0],
        'expected_outcome' => in_array($wrongRole, $entry['roles'], true) ? 'grounded_answer' : 'safe_boundary',
    ];
    $cases[] = [
        'id' => $stateId,
        'stable_id' => $entry['stable_id'],
        'type' => 'unsupported_state',
        'actor_role' => $primaryRole,
        'membership_state' => 'removed',
        'user_message' => $entry['retrieval_examples_match'][0],
        'expected_outcome' => 'handoff_without_private_facts',
    ];
    $cases[] = [
        'id' => $handoffId,
        'stable_id' => $entry['stable_id'],
        'type' => 'handoff',
        'actor_role' => $primaryRole,
        'membership_state' => 'active',
        'user_message' => 'Please transfer me to a person.',
        'expected_outcome' => 'handoff',
    ];
}

return [
    'version' => 'interactive-kb-evals-v1',
    'frozen_on' => '2026-08-14',
    'cases' => $cases,
];
