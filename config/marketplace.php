<?php

return [
    'family_estimate_hourly_rate' => 30.00,
    'caregiver_prelaunch_mode' => filter_var(env('MARKETPLACE_CAREGIVER_PRELAUNCH_MODE', false), FILTER_VALIDATE_BOOL),
    'family_prelaunch_auto_applicants' => [
        'enabled' => filter_var(env('MARKETPLACE_FAMILY_PRELAUNCH_AUTO_APPLICANTS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'emails' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(
                ',',
                (string) env(
                    'MARKETPLACE_FAMILY_PRELAUNCH_AUTO_APPLICANT_EMAILS',
                    ''
                )
            )
        ))),
        'delays_minutes' => array_values(array_filter(array_map(
            static fn (string $minutes): int => max(0, (int) trim($minutes)),
            explode(',', (string) env('MARKETPLACE_FAMILY_PRELAUNCH_AUTO_APPLICANT_DELAYS_MINUTES', '10,15'))
        ), static fn (int $minutes): bool => $minutes >= 0)),
        'cover_note' => (string) env(
            'MARKETPLACE_FAMILY_PRELAUNCH_AUTO_APPLICANT_COVER_NOTE',
            'I am available to support this request and can align timing details in chat.'
        ),
    ],
    'ops_alert_recipients' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', (string) env('MARKETPLACE_OPS_ALERT_RECIPIENTS', 'peverelli.t@gmail.com,cpetrinipoli@hub.healthcare'))
    ))),

    'payments' => [
        'platform_fee_percent' => (float) env('MARKETPLACE_PLATFORM_FEE_PERCENT', 10),
        'authorization_buffer_percent' => (float) env('MARKETPLACE_AUTH_BUFFER_PERCENT', 20),
    ],

    'regular_care' => [
        'authorization_window_hours' => (int) env('REGULAR_CARE_AUTHORIZATION_WINDOW_HOURS', 48),
        'visit_window_weeks' => (int) env('REGULAR_CARE_VISIT_WINDOW_WEEKS', 6),
        'check_in_opens_minutes_before' => (int) env('REGULAR_CARE_CHECK_IN_OPENS_MINUTES_BEFORE', 30),
        'check_in_closes_minutes_after' => (int) env('REGULAR_CARE_CHECK_IN_CLOSES_MINUTES_AFTER', 120),
    ],

    'family_pricing_overrides' => [
        'donrjohn22@yahoo.com' => [
            'hourly_rate' => 15.75,
            'platform_fee_percent' => 0,
        ],
    ],

    'caregiver_profile_photo' => [
        'max_upload_kb' => (int) env('CAREGIVER_PROFILE_PHOTO_MAX_UPLOAD_KB', 65536),
        'max_dimension' => (int) env('CAREGIVER_PROFILE_PHOTO_MAX_DIMENSION', 1600),
        'quality' => (int) env('CAREGIVER_PROFILE_PHOTO_QUALITY', 86),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Pricing Tiers
    |--------------------------------------------------------------------------
    |
    | Caregivers no longer set individual hourly pricing. The platform assigns
    | one of these tiers. Rates are in USD/hour.
    |
    */
    'default_pricing_tier' => 'standard',

    'pricing_tiers' => [
        'starter' => [
            'label' => 'Starter',
            'rate' => 30.00,
        ],
        'standard' => [
            'label' => 'Standard',
            'rate' => 30.00,
        ],
        'premium' => [
            'label' => 'Premium',
            'rate' => 30.00,
        ],
    ],
];
