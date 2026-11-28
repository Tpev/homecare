<?php

return [
    // Dedicated review quality; reuse services.openai for credentials and transport.
    // Deliberately independent of the fast customer-support model. No new env setup.
    'model' => 'gpt-6.2-astra',
    'reasoning_effort' => 'high',
    'request_timeout_seconds' => 120,
    'max_output_tokens' => 16000,
    'batch_size' => 4,
    'maximum_candidates' => 240,
    'shortlist_size' => 12,
    'history_limit' => 100,
    'text_limit' => 4000,
    'draft_hours' => 24,
    'review_minutes' => 15,
    'conflict_days' => 90,
];
