<?php

return [
    // Reuse the existing provider/model configuration. No extra deployment secrets.
    'batch_size' => 12,
    'maximum_candidates' => 240,
    'shortlist_size' => 12,
    'history_limit' => 100,
    'text_limit' => 4000,
    'draft_hours' => 24,
    'review_minutes' => 15,
    'conflict_days' => 90,
];
