<?php

// This fixture refuses every database except the dedicated disposable browser-test database.
$expected = str_replace('\\', '/', dirname(__DIR__, 2).'/database/care-hours-e2e.sqlite');
$actual = str_replace('\\', '/', (string) getenv('DB_DATABASE'));
if (getenv('APP_ENV') !== 'playwright' || $actual !== $expected || getenv('STRIPE_BYPASS') !== 'true' || getenv('MAIL_MAILER') !== 'array') {
    fwrite(STDERR, "Care-hour browser fixtures require the isolated test environment.\n");
    exit(1);
}
if (($argv[1] ?? '') === 'prepare') {
    if (! file_exists($expected)) {
        touch($expected);
    }
    exit(0);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$owner = \App\Models\User::factory()->create(['email' => 'family.e2e@example.com', 'name' => 'E2E Family', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'family', 'email_verified_at' => now()]);
\App\Models\User::factory()->create(['email' => 'test@test.com', 'name' => 'E2E Admin', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'admin', 'email_verified_at' => now()]);
$family = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($owner);
$account = \App\Models\CareHourAccount::firstOrCreate(['family_account_id' => $family->id, 'display_name' => 'Margaret E2E']);
$caregiver = \App\Models\User::factory()->create(['email' => 'caregiver.care-hours.e2e@example.com', 'name' => 'Casey Carehours', 'role' => 'caregiver', 'email_verified_at' => now()]);
\App\Models\CaregiverProfile::create([
    'user_id' => $caregiver->id, 'status' => 'active', 'years_experience' => 6,
    'bio' => 'Experienced caregiver supporting older adults with companionship, meals, and everyday routines.',
    'platform_hourly_rate' => 27, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
    'insurance_status' => \App\Models\CaregiverProfile::INSURANCE_NO,
    'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
    'background_check_verified_at' => now(), 'screening_initial_approved_at' => now(),
    'stripe_connect_account_id' => 'acct_care_hours_e2e', 'stripe_connect_onboarding_completed_at' => now(),
    'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
]);
$makeRequest = function (\App\Models\User $actor, \App\Models\CareHourAccount $balance, string $title, $start, int $minutes, bool $completed = false) use ($caregiver): array {
    $request = \App\Models\CareRequest::create([
        'family_user_id' => $actor->id, 'family_account_id' => $balance->family_account_id,
        'title' => $title, 'request_type' => 'one_time', 'status' => $completed ? 'filled' : 'open',
        'address_line1' => '123 Local Demo Lane', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        'scope_of_work' => 'Companionship and meal preparation.',
        'requested_start_at' => $start, 'requested_end_at' => $start->copy()->addMinutes($minutes),
    ]);
    $recipient = $request->recipient()->create(['full_name' => $balance->display_name, 'relationship_to_family' => 'Mother']);
    app(\App\Services\CareHours\CareHourIdentityService::class)->linkRecipient($balance, $recipient, $actor);
    $application = $request->applications()->create([
        'caregiver_user_id' => $caregiver->id, 'status' => $completed ? 'hired' : 'applied',
        'proposed_rate' => 30, 'cover_note' => 'I can help with this visit.',
    ]);

    return [$request, $application];
};
[$request] = $makeRequest($owner, $account, 'Care hours browser visit', now()->addDays(2)->setTime(9, 0), 120);
if ($request->id !== 1) {
    throw new RuntimeException('Fresh isolated fixture expected primary request #1.');
}

// A separate family has 90 minutes left from a real five-hour test package,
// after a previous 210-minute approved visit. This keeps the main purchase demo independent.
$shortfallOwner = \App\Models\User::factory()->create(['email' => 'family.eligible.e2e@example.com', 'name' => 'E2E Eligible Relative', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'family', 'email_verified_at' => now()]);
$shortfallFamily = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($shortfallOwner);
$shortfallAccount = \App\Models\CareHourAccount::create(['family_account_id' => $shortfallFamily->id, 'display_name' => 'Nora Shortfall']);
$purchases = app(\App\Services\CareHours\CareHourPurchaseService::class);
$quote = $purchases->quote($shortfallAccount, 5);
$purchases->refresh($purchases->start($shortfallAccount, 5, $shortfallOwner, $quote['quote_key'], (string) \Illuminate\Support\Str::uuid(), 'http://127.0.0.1:8012/family/care-hours/purchases/{PURCHASE_ID}', 'http://127.0.0.1:8012/family/care-hours/purchases/{PURCHASE_ID}'));
[$pastRequest, $pastApplication] = $makeRequest($shortfallOwner, $shortfallAccount, 'Previously approved care', now()->subDays(2)->setTime(9, 0), 210, true);
$pastBooking = \App\Models\CareBooking::create([
    'family_user_id' => $shortfallOwner->id, 'family_account_id' => $shortfallFamily->id,
    'care_request_id' => $pastRequest->id, 'care_request_application_id' => $pastApplication->id,
    'caregiver_user_id' => $caregiver->id, 'status' => 'completed',
    'scheduled_start_at' => $pastRequest->requested_start_at, 'scheduled_end_at' => $pastRequest->requested_end_at,
    'expected_minutes' => 210, 'worked_minutes' => 210, 'completed_at' => $pastRequest->requested_end_at,
    'family_confirmed_at' => $pastRequest->requested_end_at,
]);
$ledger = app(\App\Services\CareHours\CareHourLedgerService::class);
$ledger->reserve($shortfallAccount, $pastBooking, 210);
$ledger->settle($pastBooking, 210);
[$shortfallRequest] = $makeRequest($shortfallOwner, $shortfallAccount, 'Care hours shortfall visit', now()->addDays(3)->setTime(9, 0), 120);
if ($shortfallRequest->id !== 3) {
    throw new RuntimeException('Fresh isolated fixture expected shortfall request #3.');
}

fwrite(STDOUT, "Care-hour browser fixtures ready.\n");
