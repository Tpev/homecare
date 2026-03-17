<?php

return [
    'family_estimate_hourly_rate' => 30.00,
    'caregiver_prelaunch_mode' => filter_var(env('MARKETPLACE_CAREGIVER_PRELAUNCH_MODE', false), FILTER_VALIDATE_BOOL),

    'payments' => [
        'platform_fee_percent' => (float) env('MARKETPLACE_PLATFORM_FEE_PERCENT', 10),
        'authorization_buffer_percent' => (float) env('MARKETPLACE_AUTH_BUFFER_PERCENT', 20),
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
            'rate' => 24.00,
        ],
        'standard' => [
            'label' => 'Standard',
            'rate' => 27.00,
        ],
        'premium' => [
            'label' => 'Premium',
            'rate' => 31.00,
        ],
    ],
];
