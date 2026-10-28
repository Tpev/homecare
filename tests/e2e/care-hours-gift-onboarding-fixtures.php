<?php

require __DIR__.'/care-hours-gift-onboarding-guard.php';
$stagingCommand = $argv[1] ?? '';
if ($stagingCommand === 'prepare') {
    if (file_exists($stagingRoot.'/checkpoint.private.json') || (file_exists($stagingExpectedDatabase) && filesize($stagingExpectedDatabase) > 0)) {
        throw new RuntimeException('An existing gift-onboarding database cannot be reset. Resume with CARE_HOURS_GIFT_ONBOARDING_REUSE=1.');
    }
    foreach (['', '/screenshots', '/mail', '/storage/framework/sessions', '/storage/framework/views', '/storage/framework/cache/data', '/storage/logs'] as $directory) {
        if (! is_dir($stagingRoot.$directory)) {
            mkdir($stagingRoot.$directory, 0777, true);
        }
    }
    touch($stagingExpectedDatabase);
    file_put_contents($stagingRoot.'/mail/index.jsonl', '');
    file_put_contents($stagingRoot.'/provider-proof.json', json_encode(['verified' => false, 'reason' => 'journey_not_complete']));
    exit(0);
}

require $stagingProject.'/vendor/autoload.php';
$app = require $stagingProject.'/bootstrap/app.php';
$app->useStoragePath($stagingRoot.'/storage');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, $stagingAssertConfiguration);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, $stagingCaptureMail);
if ($stagingCommand === 'diagnose-entry') {
    $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (Throwable $exception) use ($stagingRoot): void {
        file_put_contents($stagingRoot.'/entry-diagnostic.private.txt', get_class($exception).': '.$exception->getMessage());
    });
    $response = $app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(\Illuminate\Http\Request::create('http://127.0.0.1:8016/care-hours/claim'));
    echo json_encode(['status' => $response->getStatusCode(), 'storage' => storage_path(), 'views' => config('view.compiled')]);
    exit($response->getStatusCode() >= 400 ? 1 : 0);
}
if ($stagingCommand === 'migrate') {
    if (file_exists($stagingRoot.'/checkpoint.private.json') || filesize($stagingExpectedDatabase) > 0) {
        throw new RuntimeException('Only an empty isolated gift-onboarding database may be initialized.');
    }
    exit(\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]));
}
if ($stagingCommand === 'migrate-pending') {
    exit(\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]));
}
if ($stagingCommand === 'seed') {
    if (\App\Models\User::exists() || \App\Models\CareHourPurchase::exists()) {
        throw new RuntimeException('The gift-onboarding journey must begin with no users or purchases.');
    }
    file_put_contents($stagingRoot.'/fixture.json', json_encode([
        'run_id' => (string) \Illuminate\Support\Str::uuid(), 'payment_mode' => $stagingMode,
        'started_at' => now()->toIso8601String(), 'seeded_users' => 0, 'seeded_recipients' => 0,
        'fixture_note' => 'No users, recipients, care requests, purchases, bookings or hours are seeded. All customer records are created through the real UI. Time is not simulated. Email delivery is captured locally as requested.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Empty isolated gift-onboarding fixture ready.\n";
    exit(0);
}
if ($stagingCommand === 'deliver-notices') {
    $status = \Illuminate\Support\Facades\Artisan::call('homecare:reconcile-care-hour-purchases', ['--limit' => 100]);
    echo \Illuminate\Support\Facades\Artisan::output();
    exit($status);
}
if ($stagingCommand !== 'snapshot') {
    throw new RuntimeException('Supported commands: prepare, migrate, migrate-pending, seed, deliver-notices, snapshot.');
}
$fixture = json_decode(file_get_contents($stagingRoot.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$snapshot = [
    'run_id' => $fixture['run_id'], 'payment_mode' => $stagingMode, 'recorded_at' => now()->toIso8601String(),
    'users' => \App\Models\User::get()->map->only(['id', 'name', 'email', 'role', 'email_verified_at', 'stripe_default_payment_method_id'])->all(),
    'families' => \App\Models\FamilyAccount::get()->map->only(['id', 'owner_user_id', 'stripe_customer_id', 'payment_method_summary'])->all(),
    'memberships' => \App\Models\FamilyAccountMember::get()->map->only(['family_account_id', 'user_id', 'role'])->all(),
    'profiles' => \App\Models\CareRecipientProfile::get()->map->only(['id', 'family_account_id', 'full_name', 'status', 'relationship_to_family'])->all(),
    'accounts' => \App\Models\CareHourAccount::get()->map(fn ($account) => $account->only(['id', 'family_account_id', 'care_recipient_profile_id', 'display_name']) + ['balance' => $account->balance()])->all(),
    'purchases' => \App\Models\CareHourPurchase::with('gift')->get()->map(fn ($purchase) => $purchase->only(['id', 'public_id', 'status', 'buyer_user_id', 'buyer_name', 'buyer_email', 'care_hour_account_id', 'paid_minutes', 'bonus_minutes', 'total_minutes', 'amount_cents', 'currency', 'processing_fee_cents', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_charge_id', 'livemode', 'paid_at', 'issued_at']) + ['gift' => $purchase->gift?->only(['id', 'ready_at', 'claimed_at', 'claimed_by_user_id'])])->all(),
    'lots' => \App\Models\CareHourLot::get()->map->only(['id', 'care_hour_account_id', 'care_hour_purchase_id', 'total_minutes', 'available_minutes', 'reserved_minutes', 'used_minutes', 'held_minutes', 'processing_fee_cents'])->all(),
    'ledger' => \App\Models\CareHourLedgerEntry::get()->map->only(['id', 'care_hour_account_id', 'care_hour_purchase_id', 'type', 'minutes', 'available_delta', 'reserved_delta', 'used_delta', 'held_delta'])->all(),
    'care_request_count' => \App\Models\CareRequest::count(), 'request_recipient_count' => \App\Models\CareRecipient::count(),
    'booking_count' => \App\Models\CareBooking::count(), 'ordinary_card_payment_count' => \App\Models\CareBookingPayment::count(),
    'purchase_notices' => \Illuminate\Support\Facades\DB::table('care_hour_purchase_notices')->get(['kind', 'recipient', 'sent_at', 'attempts'])->all(),
    'webhook_signature_required' => config('services.stripe.bypass') === false && trim((string) config('services.stripe.webhook_secret')) !== '',
    'webhook_events' => \App\Models\StripeWebhookEvent::get(['stripe_event_id', 'type', 'object_id', 'livemode', 'connected_account_id', 'status', 'attempts', 'processed_at'])->toArray(),
    'captured_mail' => array_map(fn ($line) => json_decode($line, true), file($stagingRoot.'/mail/index.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
];
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
