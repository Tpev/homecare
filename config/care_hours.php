<?php

return [
    // Rollout switches affect discovery/new sales, never existing entitlements.
    'enabled' => (bool) env('CARE_HOURS_ENABLED', false),
    'enrollment_enabled' => (bool) env('CARE_HOURS_ENROLLMENT_ENABLED', true),
    'sales_enabled' => (bool) env('CARE_HOURS_SALES_ENABLED', true),
    'contributions_enabled' => (bool) env('CARE_HOURS_CONTRIBUTIONS_ENABLED', true),
    'gifts_enabled' => (bool) env('CARE_HOURS_GIFTS_ENABLED', false),
    'welcome_enabled' => (bool) env('CARE_HOURS_WELCOME_BONUS_ENABLED', true),
    'currency' => 'usd',
    'terms_version' => '2026-10-16',
    'packages' => [
        5 => ['paid_minutes' => 300, 'bonus_minutes' => 0, 'amount_cents' => 15500],
        10 => ['paid_minutes' => 600, 'bonus_minutes' => 60, 'amount_cents' => 31000],
        20 => ['paid_minutes' => 1200, 'bonus_minutes' => 120, 'amount_cents' => 62000],
        40 => ['paid_minutes' => 2400, 'bonus_minutes' => 240, 'amount_cents' => 124000],
    ],
    'verification_minutes' => 20,
];
