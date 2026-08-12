<?php

return [
    'publishing' => [
        'require_independent_review' => filter_var(env('CONTENT_REQUIRE_INDEPENDENT_REVIEW', false), FILTER_VALIDATE_BOOL),
    ],

    'analytics' => [
        'retention_days' => (int) env('CONTENT_ANALYTICS_RETENTION_DAYS', 395),
        'identity_retention_days' => (int) env('CONTENT_ANALYTICS_IDENTITY_RETENTION_DAYS', 30),
    ],
];
