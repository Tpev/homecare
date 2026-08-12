<?php

return [
    'analytics' => [
        'retention_days' => (int) env('CONTENT_ANALYTICS_RETENTION_DAYS', 395),
        'identity_retention_days' => (int) env('CONTENT_ANALYTICS_IDENTITY_RETENTION_DAYS', 30),
    ],
];
