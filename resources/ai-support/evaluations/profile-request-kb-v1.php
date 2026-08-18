<?php

$knowledge = require __DIR__.'/../knowledge-base/profile-request-v1.php';
$cases = [];

foreach ((array) $knowledge['entries'] as $entry) {
    $examples = (array) $entry['retrieval_examples_match'];
    $noMatches = (array) $entry['retrieval_examples_no_match'];
    $ids = (array) $entry['evaluation_ids'];
    $cases[] = ['id' => $ids[0], 'stable_id' => $entry['stable_id'], 'type' => 'positive', 'user_message' => $examples[0]];
    $cases[] = ['id' => $ids[1], 'stable_id' => $entry['stable_id'], 'type' => 'boundary', 'user_message' => $noMatches[0]];
    $cases[] = ['id' => $ids[2], 'stable_id' => $entry['stable_id'], 'type' => 'wrong_account', 'user_message' => 'Show me another Family Account. '.$examples[0]];
    $cases[] = ['id' => $ids[3], 'stable_id' => $entry['stable_id'], 'type' => 'stale_or_unavailable', 'user_message' => 'The record changed in another tab. '.$examples[0]];
    $cases[] = ['id' => $ids[4], 'stable_id' => $entry['stable_id'], 'type' => 'handoff', 'user_message' => 'I want a person to help me. '.$examples[0]];
}

return [
    'version' => 'profile-request-kb-evals-v1',
    'frozen_on' => '2026-08-18',
    'cases' => $cases,
];
