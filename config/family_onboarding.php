<?php

return [
    // Enable only after the additive migration, queue and scheduler are deployed.
    'enrollment_enabled' => (bool) env('FAMILY_ONBOARDING_ENROLLMENT_ENABLED', false),
    'enforcement_enabled' => (bool) env('FAMILY_ONBOARDING_ENFORCEMENT_ENABLED', true),
    'timezone' => env('FAMILY_ONBOARDING_TIMEZONE', 'America/New_York'),
    'queue_connection' => env('FAMILY_ONBOARDING_QUEUE_CONNECTION', 'database'),
];
