<?php

// This reviewed release enables only the named private pilot. A dedicated pause
// survives deployments; malformed values fail closed instead of becoming truthy.
$privatePilotEnabled = env('CARE_HOURS_PRIVATE_PILOT_ENABLED', true) === true;

return [
    // Rollout switches affect discovery/new sales, never existing entitlements.
    // Legacy master/gift and pilot-list environment settings intentionally cannot
    // disable the approved default or expand this release to other purchasers.
    'enabled' => $privatePilotEnabled,
    'pilot_only' => true,
    'pilot_emails' => ['peverelli.t@gmail.com'],
    'wallet_scope' => 'family',
    'enrollment_enabled' => (bool) env('CARE_HOURS_ENROLLMENT_ENABLED', true),
    'sales_enabled' => (bool) env('CARE_HOURS_SALES_ENABLED', true),
    'contributions_enabled' => (bool) env('CARE_HOURS_CONTRIBUTIONS_ENABLED', true),
    'gifts_enabled' => $privatePilotEnabled,
    'welcome_enabled' => (bool) env('CARE_HOURS_WELCOME_BONUS_ENABLED', true),
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
