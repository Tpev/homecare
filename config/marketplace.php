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
        explode(',', (string) env('MARKETPLACE_OPS_ALERT_RECIPIENTS', 'peverelli.t@gmail.com,hello@carelolo.com'))
    ))),
    // Redirect legacy values that may still exist in a deployed environment's cached configuration.
    'ops_alert_recipient_replacements' => [
        'cpetrinipoli@hub.healthcare' => 'hello@carelolo.com',
    ],

    'payments' => [
        'platform_fee_percent' => (float) env('MARKETPLACE_PLATFORM_FEE_PERCENT', 10),
        'authorization_buffer_percent' => (float) env('MARKETPLACE_AUTH_BUFFER_PERCENT', 20),
    ],

    'pricing_v2' => [
        'enabled' => filter_var(env('MARKETPLACE_PRICING_V2_ENABLED', true), FILTER_VALIDATE_BOOL),
        'version' => (string) env('MARKETPLACE_PRICING_VERSION', '2026-08-v2'),
        'family_care_hourly_cents' => (int) env('MARKETPLACE_FAMILY_CARE_HOURLY_CENTS', 3000),
        'family_processing_fee_hourly_cents' => (int) env('MARKETPLACE_FAMILY_PROCESSING_FEE_HOURLY_CENTS', 100),
        'caregiver_gross_hourly_cents' => (int) env('MARKETPLACE_CAREGIVER_GROSS_HOURLY_CENTS', 2700),
        'caregiver_fee_policy' => 'successful_charge_balance_transaction',
    ],

    'time_corrections' => [
        'enabled' => filter_var(env('MARKETPLACE_TIME_CORRECTIONS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'self_service_window_hours' => (int) env('MARKETPLACE_TIME_CORRECTION_WINDOW_HOURS', 72),
        'max_duration_minutes' => (int) env('MARKETPLACE_TIME_CORRECTION_MAX_DURATION_MINUTES', 960),
        'future_clock_skew_minutes' => (int) env('MARKETPLACE_TIME_CORRECTION_FUTURE_SKEW_MINUTES', 5),
        'first_reminder_hours' => (int) env('MARKETPLACE_TIME_CORRECTION_FIRST_REMINDER_HOURS', 12),
        'second_reminder_hours' => (int) env('MARKETPLACE_TIME_CORRECTION_SECOND_REMINDER_HOURS', 24),
        'escalation_hours' => (int) env('MARKETPLACE_TIME_CORRECTION_ESCALATION_HOURS', 48),
    ],

    'completed_extra_visits' => [
        'enabled' => filter_var(env('MARKETPLACE_COMPLETED_EXTRA_VISITS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'history_window_days' => (int) env('MARKETPLACE_COMPLETED_EXTRA_VISIT_HISTORY_DAYS', 30),
        'ended_plan_grace_days' => (int) env('MARKETPLACE_COMPLETED_EXTRA_VISIT_ENDED_GRACE_DAYS', 30),
        'minimum_duration_minutes' => (int) env('MARKETPLACE_COMPLETED_EXTRA_VISIT_MIN_MINUTES', 15),
        'maximum_duration_minutes' => (int) env('MARKETPLACE_COMPLETED_EXTRA_VISIT_MAX_MINUTES', 960),
        'future_clock_skew_minutes' => (int) env('MARKETPLACE_COMPLETED_EXTRA_VISIT_FUTURE_SKEW_MINUTES', 5),
    ],

    'continuous_coverage' => [
        'enabled' => filter_var(env('CONTINUOUS_COVERAGE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'pilot_emails' => array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) env('CONTINUOUS_COVERAGE_PILOT_EMAILS', ''))
        ))),
        'generation_weeks' => max(1, (int) env('CONTINUOUS_COVERAGE_GENERATION_WEEKS', 6)),
        'booking_horizon_hours' => max(1, (int) env('CONTINUOUS_COVERAGE_BOOKING_HORIZON_HOURS', 48)),
        'offer_expires_hours' => max(1, (int) env('CONTINUOUS_COVERAGE_OFFER_EXPIRES_HOURS', 12)),
        'lane_offer_expires_hours' => max(1, (int) env('CONTINUOUS_COVERAGE_LANE_OFFER_EXPIRES_HOURS', 72)),
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
        'responsive_widths' => [480, 768],
        'responsive_quality' => (int) env('CAREGIVER_PROFILE_PHOTO_RESPONSIVE_QUALITY', 78),
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
