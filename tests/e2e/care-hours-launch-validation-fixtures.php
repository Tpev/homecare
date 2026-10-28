<?php

require __DIR__.'/care-hours-launch-validation-guard.php';
$stagingCommand = $argv[1] ?? '';
if ($stagingCommand === 'prepare') {
    foreach (['', '/screenshots', '/mail', '/storage/framework/sessions', '/storage/framework/views', '/storage/framework/cache/data', '/storage/logs'] as $directory) {
        if (! is_dir($stagingRoot.$directory)) {
            mkdir($stagingRoot.$directory, 0777, true);
        }
    }
    if (! file_exists($stagingExpectedDatabase)) {
        touch($stagingExpectedDatabase);
    }
    file_put_contents($stagingRoot.'/mail/index.jsonl', '');
    file_put_contents($stagingRoot.'/verification.json', json_encode(['status' => 'running', 'payment_mode' => $stagingMode], JSON_PRETTY_PRINT));
    exit(0);
}

require $stagingProject.'/vendor/autoload.php';
$app = require $stagingProject.'/bootstrap/app.php';
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, $stagingAssertConfiguration);
try {
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $exception) {
    fwrite(STDERR, "Staging bootstrap failed; see the isolated storage/logs/laravel.log.\n");
    report($exception);
    exit(1);
}
\Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class, $stagingCaptureMail);
if ($stagingCommand === 'migrate') {
    exit(\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]));
}
if ($stagingCommand === 'migrate-pending') {
    exit(\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]));
}
if ($stagingCommand === 'deliver-notices') {
    $clock = json_decode(file_get_contents($stagingRoot.'/clock.json'), true, flags: JSON_THROW_ON_ERROR);
    \Carbon\Carbon::setTestNow($clock['now']);
    \Carbon\CarbonImmutable::setTestNow($clock['now']);
    $code = \Illuminate\Support\Facades\Artisan::call('homecare:reconcile-care-hour-purchases', ['--limit' => 100]);
    echo \Illuminate\Support\Facades\Artisan::output();
    exit($code);
}
if ($stagingCommand === 'seed') {
    if (\App\Models\User::query()->exists()) {
        throw new RuntimeException('Seed requires a freshly migrated isolated staging database.');
    }
    $purchaseAt = \Illuminate\Support\Carbon::now(config('app.timezone'))->startOfMinute();
    $visitStart = $purchaseAt->copy()->addDay()->setTime(9, 0);
    $visitEnd = $visitStart->copy()->addHours(2);
    $password = \Illuminate\Support\Facades\Hash::make('Staging-only-2026!');
    $family = \App\Models\User::factory()->create(['name' => 'Emma Bennett', 'email' => 'emma.bennett@example.test', 'role' => 'family', 'password' => $password, 'email_verified_at' => $purchaseAt]);
    $caregiver = \App\Models\User::factory()->create(['name' => 'Claire Martin', 'email' => 'claire.martin@example.test', 'role' => 'caregiver', 'password' => $password, 'email_verified_at' => $purchaseAt]);
    \App\Models\User::factory()->create(['name' => 'LoLo Staging Admin', 'email' => 'care-hours.admin@example.test', 'role' => 'admin', 'password' => $password, 'email_verified_at' => $purchaseAt]);
    \App\Models\CaregiverProfile::create([
        'user_id' => $caregiver->id, 'status' => 'active', 'years_experience' => 8,
        'bio' => 'I help older adults feel comfortable at home through companionship, meal preparation, and everyday support.',
        'service_area_zip' => '27601', 'service_radius_miles' => 15, 'is_accepting_new_clients' => true,
        'screening_initial_approved_at' => $purchaseAt, 'identity_verified_at' => $purchaseAt,
        'identity_verification_status' => 'approved', 'background_check_verified_at' => $purchaseAt,
        'stripe_connect_account_id' => getenv('CARE_HOURS_LAUNCH_VALIDATION_CONNECT_ACCOUNT'),
        'stripe_connect_onboarding_completed_at' => $purchaseAt, 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
    ]);
    $familyAccount = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($family);
    $request = \App\Models\CareRequest::create([
        'family_user_id' => $family->id, 'family_account_id' => $familyAccount->id,
        'title' => 'Morning companionship for Robert', 'request_type' => 'one_time', 'status' => 'open',
        'address_line1' => '124 Oakwood Avenue', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        'scope_of_work' => 'Keep Robert company, prepare a light lunch, and take a short walk if he feels up to it.',
        'requested_start_at' => $visitStart, 'requested_end_at' => $visitEnd,
    ]);
    $recipient = $request->recipient()->create(['full_name' => 'Robert Bennett', 'relationship_to_family' => 'Father']);
    $account = app(\App\Services\CareHours\CareHourIdentityService::class)->enroll($recipient, $family);
    $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 30,
        'cover_note' => 'I would be happy to spend the morning with Robert and help with lunch.']);
    $fixture = ['run_id' => (string) \Illuminate\Support\Str::uuid(), 'payment_mode' => $stagingMode, 'request_id' => $request->id, 'account_id' => $account->id,
        'purchase_at' => $purchaseAt->copy()->utc()->toIso8601String(), 'visit_start' => $visitStart->copy()->utc()->toIso8601String(), 'visit_end' => $visitEnd->copy()->utc()->toIso8601String(),
        'fixture_note' => 'Existing family, recipient, screened caregiver, care request and application only. No purchase, hours, booking, worked time or payment has been inserted.'];
    file_put_contents($stagingRoot.'/fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($stagingRoot.'/clock.json', json_encode(['now' => $fixture['purchase_at'], 'stage' => 'purchase'], JSON_PRETTY_PRINT));
    echo "Isolated care-hours staging fixture ready.\n";
    exit(0);
}

if ($stagingCommand === 'seed-gift-recipient') {
    $fixture = json_decode(file_get_contents($stagingRoot.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! isset($fixture['gift_account_id'])) {
        $family = \App\Models\User::where('email', 'emma.bennett@example.test')->firstOrFail();
        $request = \App\Models\CareRequest::findOrFail($fixture['request_id'])->replicate();
        $request->title = 'Companionship for Alice';
        $request->save();
        $recipient = $request->recipient()->create(['full_name' => 'Alice Bennett', 'relationship_to_family' => 'Mother']);
        $account = app(\App\Services\CareHours\CareHourIdentityService::class)->enroll($recipient, $family);
        $fixture['gift_account_id'] = $account->id;
        file_put_contents($stagingRoot.'/fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    echo json_encode(['account_id' => $fixture['gift_account_id']]);
    exit(0);
}
if (in_array($stagingCommand, ['seed-mixed', 'seed-mixed-capture'], true)) {
    $fixture = json_decode(file_get_contents($stagingRoot.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    $fixtureKey = $stagingCommand === 'seed-mixed' ? 'mixed_request_id' : 'mixed_capture_request_id';
    if (! isset($fixture[$fixtureKey])) {
        $family = \App\Models\User::where('email', 'emma.bennett@example.test')->firstOrFail();
        $original = \App\Models\CareRequest::findOrFail($fixture['request_id']);
        $request = $original->replicate();
        $request->title = 'Daytime companionship for Robert';
        $request->requested_start_at = $original->requested_start_at->copy()->addDays($stagingCommand === 'seed-mixed' ? 2 : 3);
        $request->requested_end_at = $request->requested_start_at->copy()->addHours(12);
        $request->save();
        $recipient = $request->recipient()->create(['full_name' => 'Robert Bennett', 'relationship_to_family' => 'Father']);
        app(\App\Services\CareHours\CareHourIdentityService::class)->linkRecipient(\App\Models\CareHourAccount::findOrFail($fixture['account_id']), $recipient, $family);
        $request->applications()->create(['caregiver_user_id' => \App\Models\User::where('email', 'claire.martin@example.test')->value('id'), 'status' => 'applied', 'proposed_rate' => 30, 'cover_note' => 'I can help Robert throughout the day.']);
        $fixture[$fixtureKey] = $request->id;
        file_put_contents($stagingRoot.'/fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $request = \App\Models\CareRequest::findOrFail($fixture[$fixtureKey]);
    echo json_encode(['request_id' => $request->id, 'visit_start' => $request->requested_start_at->copy()->utc()->toIso8601String(), 'visit_end' => $request->requested_end_at->copy()->utc()->toIso8601String()]);
    exit(0);
}
if ($stagingCommand === 'webhook-diagnostics') {
    echo json_encode(\App\Models\StripeWebhookEvent::where('status', 'failed')->get(['stripe_event_id', 'type', 'object_id', 'last_error'])->toArray(), JSON_PRETTY_PRINT);
    exit(0);
}
if (! in_array($stagingCommand, ['snapshot', 'verify'], true)) {
    throw new RuntimeException('Supported commands: prepare, migrate, seed, deliver-notices, snapshot, verify.');
}
$account = \App\Models\CareHourAccount::firstOrFail();
$purchase = \App\Models\CareHourPurchase::first();
$booking = \App\Models\CareBooking::first();
$settlement = \App\Models\CareHourSettlement::first();
$balance = app(\App\Services\CareHours\CareHourLedgerService::class)->balance($account);
$fixtureMetadata = json_decode(file_get_contents($stagingRoot.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$snapshot = [
    'run_id' => $fixtureMetadata['run_id'] ?? $fixtureMetadata['purchase_at'], 'payment_mode' => $stagingMode, 'balance' => $balance,
    'purchase' => $purchase?->only(['public_id', 'status', 'buyer_user_id', 'care_hour_account_id', 'care_hour_contribution_link_id', 'amount_cents', 'paid_minutes', 'bonus_minutes', 'total_minutes', 'processing_fee_cents', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_charge_id', 'livemode']),
    'family_payment_details' => \App\Models\FamilyAccount::first()?->only(['stripe_customer_id', 'payment_method_summary', 'payment_method_checked_at', 'payment_method_snapshot_version']),
    'family_membership_count' => \App\Models\FamilyAccountMember::count(),
    'contributor_user_count' => \App\Models\User::where('email', 'peverelli.t@gmail.com')->count(),
    'purchase_count' => \App\Models\CareHourPurchase::count(),
    'lot_count' => \App\Models\CareHourLot::count(),
    'ledger_entry_count' => \App\Models\CareHourLedgerEntry::count(),
    'booking' => $booking?->only(['id', 'status', 'expected_minutes', 'worked_minutes', 'started_at', 'completed_at', 'family_confirmed_at', 'caregiver_gross_rate_cents']),
    'settlement' => $settlement?->only(['status', 'worked_minutes', 'prepaid_minutes', 'amount_due_cents', 'caregiver_gross_cents', 'package_fee_cents', 'caregiver_net_cents', 'paid_cents', 'last_error']),
    'bookings' => \App\Models\CareBooking::get()->map->only(['id', 'care_request_id', 'status', 'started_at', 'completed_at', 'worked_minutes'])->all(),
    'settlements' => \App\Models\CareHourSettlement::get()->map->only(['care_booking_id', 'care_hour_booking_funding_id', 'status', 'worked_minutes', 'prepaid_minutes', 'amount_due_cents', 'caregiver_gross_cents', 'package_fee_cents', 'card_fee_cents', 'caregiver_net_cents', 'paid_cents', 'last_error'])->all(),
    'operations' => \App\Models\CareHourPaymentOperation::get()->map->only(['public_id', 'kind', 'status', 'amount_cents', 'currency', 'destination_account_id', 'source_charge_id', 'stripe_transfer_id', 'stripe_payment_intent_id', 'stripe_checkout_session_id', 'stripe_charge_id', 'processing_fee_cents', 'captured_cents', 'refunded_cents', 'care_hour_booking_funding_id', 'last_error'])->all(),
    'fundings' => \App\Models\CareHourBookingFunding::get()->map->only(['id', 'care_booking_id', 'care_hour_account_id', 'status', 'planned_minutes', 'reserved_minutes', 'shortfall_cents'])->all(),
    'accounts' => \App\Models\CareHourAccount::get()->map(fn ($account) => ['id' => $account->id, 'display_name' => $account->display_name, 'balance' => app(\App\Services\CareHours\CareHourLedgerService::class)->balance($account)])->all(),
    'gift_purchases' => \Illuminate\Support\Facades\Schema::hasTable('care_hour_gifts') ? \App\Models\CareHourPurchase::whereHas('gift')->with('gift')->get()->map(fn ($purchase) => $purchase->only(['public_id', 'status', 'care_hour_account_id', 'paid_minutes', 'bonus_minutes', 'total_minutes', 'amount_cents', 'processing_fee_cents', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_charge_id', 'livemode']) + ['gift' => $purchase->gift->only(['id', 'ready_at', 'claimed_at', 'claimed_by_user_id'])])->all() : [],
    'events' => $booking?->events()->pluck('event_type')->all() ?? [],
    'ordinary_card_booking_payment_count' => \App\Models\CareBookingPayment::count(),
    'purchase_notices' => \Illuminate\Support\Facades\DB::table('care_hour_purchase_notices')->get(['kind', 'recipient', 'sent_at', 'attempts', 'last_error'])->all(),
    'notifications' => \Illuminate\Support\Facades\DB::table('notifications')->get(['notifiable_id', 'type', 'data', 'created_at'])->map(function ($row) {
        $data = json_decode($row->data, true);

        return ['user_id' => $row->notifiable_id, 'type' => $row->type, 'event_key' => $data['event_key'] ?? $data['type'] ?? null,
            'title' => $data['title'] ?? null, 'message' => $data['message'] ?? $data['body'] ?? null, 'created_at' => $row->created_at];
    })->all(),
    'notification_deliveries' => \App\Models\MarketplaceNotificationDelivery::get(['user_id', 'event_key', 'channel', 'status', 'sent_at'])->toArray(),
    'webhook_signature_required' => config('services.stripe.bypass') === false && trim((string) config('services.stripe.webhook_secret')) !== '',
    'webhook_events' => \App\Models\StripeWebhookEvent::get(['stripe_event_id', 'type', 'object_id', 'livemode', 'connected_account_id', 'status', 'attempts', 'processed_at'])->toArray(),
    'captured_mail' => file_exists($stagingRoot.'/mail/index.jsonl') ? array_map(fn ($line) => json_decode($line, true), file($stagingRoot.'/mail/index.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [],
];
$processedWebhooks = collect($snapshot['webhook_events'])->filter(fn ($event) => $event['status'] === 'processed'
    && $event['livemode'] === false && empty($event['connected_account_id']) && $event['processed_at'] !== null);
$purchaseWebhookProcessed = $processedWebhooks->contains(fn ($event) => ($event['type'] === 'checkout.session.completed' && $event['object_id'] === $purchase?->stripe_checkout_session_id)
    || ($event['type'] === 'payment_intent.succeeded' && $event['object_id'] === $purchase?->stripe_payment_intent_id));
$successfulTransfers = collect($snapshot['operations'])->filter(fn ($operation) => $operation['kind'] === 'transfer' && $operation['status'] === 'succeeded');
$transferWebhooksProcessed = $successfulTransfers->isNotEmpty() && $successfulTransfers->every(fn ($operation) => $processedWebhooks->contains(fn ($event) => in_array($event['type'], ['transfer.created', 'transfer.paid'], true) && $event['object_id'] === $operation['stripe_transfer_id']));
$snapshot['webhook_coverage'] = ['purchase_processed' => $purchaseWebhookProcessed, 'transfers_processed' => $transferWebhooksProcessed,
    'ready' => $snapshot['webhook_signature_required'] && $purchaseWebhookProcessed && $transferWebhooksProcessed];
if ($stagingCommand === 'verify') {
    $checks = [
        'package_paid_and_issued' => $purchase?->status === 'issued' && $purchase->amount_cents === 31000 && $purchase->total_minutes === 660 && $purchase->bonus_minutes === 60,
        'actual_two_hour_visit' => $booking?->worked_minutes === 120 && $booking->family_confirmed_at !== null,
        'remaining_hours_and_usage' => $balance['available_minutes'] === 540 && $balance['reserved_minutes'] === 0 && $balance['used_minutes'] === 120,
        'settlement_and_caregiver_transfer' => $settlement?->status === 'paid' && $settlement->paid_cents === $settlement->caregiver_net_cents && $settlement->paid_cents > 0,
        'proportional_processing_fee' => $purchase?->processing_fee_cents !== null && $settlement?->package_fee_cents === intdiv($purchase->processing_fee_cents * 120, 660),
        'existing_card_flow_untouched' => $snapshot['ordinary_card_booking_payment_count'] === 0,
        'test_provider_only' => $stagingMode === 'bypass' || ($purchase?->livemode === false && ! str_contains((string) $purchase->stripe_charge_id, 'bypass')),
        'purchase_notice_delivery' => count($snapshot['purchase_notices']) >= 2 && collect($snapshot['purchase_notices'])->every(fn ($notice) => $notice->sent_at !== null),
        'local_receipt_email_captured' => collect($snapshot['captured_mail'])->contains(fn ($mail) => $mail['subject'] === 'Your LoLo care-hours receipt' && in_array('emma.bennett@example.test', $mail['to'], true)),
        'customer_notifications_stored' => collect(['care_hours_added', 'caregiver_hired', 'hire_confirmed', 'shift_started', 'shift_completed'])->diff(collect($snapshot['notifications'])->pluck('event_key'))->isEmpty(),
        'visit_notifications_delivered' => collect(['caregiver_hired', 'hire_confirmed', 'shift_started', 'shift_completed'])->every(fn ($event) => collect($snapshot['notification_deliveries'])->contains(fn ($delivery) => $delivery['event_key'] === $event && $delivery['channel'] === 'in_app' && $delivery['status'] === 'sent' && $delivery['sent_at'] !== null)),
    ];
    if ($stagingMode === 'stripe_test') {
        $checks['signed_payment_and_transfer_webhooks_processed'] = $snapshot['webhook_coverage']['ready'];
    }
    $snapshot['checks'] = $checks;
    file_put_contents($stagingRoot.'/verification.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (in_array(false, $checks, true)) {
        fwrite(STDERR, 'Staging verification failed: '.implode(', ', array_keys(array_filter($checks, fn ($passed) => ! $passed)))."\n");
        exit(1);
    }
}
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
