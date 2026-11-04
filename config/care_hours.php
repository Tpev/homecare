<?php

// Public by default in this reviewed release. Keep an existing pilot pause in
// force during the transition; malformed switches fail closed.
$publicEnabled = env('CARE_HOURS_PUBLIC_ENABLED', true) === true
    && env('CARE_HOURS_PRIVATE_PILOT_ENABLED', true) === true;

return [
    // Rollout switches affect discovery/new sales, never existing entitlements.
    // Legacy master/gift and pilot-list environment settings are superseded by
    // this reviewed public rollout. Dedicated pauses remain effective.
    'enabled' => $publicEnabled,
    'pilot_only' => false,
    'pilot_emails' => [],
    'wallet_scope' => 'family',
    'enrollment_enabled' => env('CARE_HOURS_ENROLLMENT_ENABLED', true) === true,
    'sales_enabled' => env('CARE_HOURS_SALES_ENABLED', true) === true,
    'contributions_enabled' => env('CARE_HOURS_CONTRIBUTIONS_ENABLED', true) === true,
    'gifts_enabled' => $publicEnabled,
    'welcome_enabled' => env('CARE_HOURS_WELCOME_BONUS_ENABLED', true) === true,
    'currency' => 'usd',
    'terms_version' => '2026-10-30-family-wallet',
    'packages' => [
        5 => ['paid_minutes' => 300, 'bonus_minutes' => 0, 'amount_cents' => 15500],
        10 => ['paid_minutes' => 600, 'bonus_minutes' => 60, 'amount_cents' => 31000],
        20 => ['paid_minutes' => 1200, 'bonus_minutes' => 120, 'amount_cents' => 62000],
        40 => ['paid_minutes' => 2400, 'bonus_minutes' => 240, 'amount_cents' => 124000],
    ],
    'verification_minutes' => 20,
];
