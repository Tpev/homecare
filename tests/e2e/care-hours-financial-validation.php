<?php

declare(strict_types=1);

// Explicit, isolated sandbox integration proof. Never runs against the workspace database.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$project = dirname(__DIR__, 2);
$root = $project.'/tmp/care-hours-financial-validation';
$stage = 'guard';
$result = ['verified' => false, 'evidence_kind' => 'real Stripe sandbox payments and backend domain fixtures; not a browser journey'];
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$save = static function (array $data) use ($root): void {
    file_put_contents($root.'/proof.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
try {
    $check(PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--run' && ! is_link($root), 'explicit_isolated_cli_required');
    if (! is_dir($root)) {
        mkdir($root, 0777, true);
    }
    $providerPath = $project.'/tmp/care-hours-staging/provider.json';
    $provider = json_decode(file_get_contents($providerPath), true, flags: JSON_THROW_ON_ERROR);
    $check(($provider['payment_mode'] ?? '') === 'stripe_test' && preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '') === 1, 'sandbox_key_required');
    foreach (['storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
        if (! is_dir($root.'/'.$directory)) {
            mkdir($root.'/'.$directory, 0777, true);
        }
    }
    $database = str_replace('\\', '/', $root.'/financial.sqlite');
    $check(! is_link($database) && ! file_exists($root.'/config.php'), 'isolated_database_and_uncached_config_required');
    if (! file_exists($database)) {
        touch($database);
    }
    $statePath = $root.'/state.json';
    $state = file_exists($statePath) ? json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR) : ['run_id' => bin2hex(random_bytes(12))];
    $persist = static function () use (&$state, $statePath): void {
        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    };
    $persist();
    $env = [
        'APP_ENV' => 'testing', 'APP_NAME' => 'LoLo sandbox financial proof', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8016',
        'APP_KEY' => 'base64:'.base64_encode(hash('sha256', 'financial-proof|'.$state['run_id'], true)),
        'APP_CONFIG_CACHE' => 'tmp/care-hours-financial-validation/config.php',
        'APP_SERVICES_CACHE' => 'tmp/care-hours-financial-validation/services.php',
        'APP_PACKAGES_CACHE' => 'tmp/care-hours-financial-validation/packages.php',
        'APP_ROUTES_CACHE' => 'tmp/care-hours-financial-validation/routes.php',
        'LARAVEL_STORAGE_PATH' => $root.'/storage', 'VIEW_COMPILED_PATH' => $root.'/storage/framework/views',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'STRIPE_BYPASS' => 'false', 'STRIPE_SECRET' => $provider['stripe_secret'], 'STRIPE_CURRENCY' => 'usd',
        'CARE_HOURS_ENABLED' => 'true', 'CARE_HOURS_ENROLLMENT_ENABLED' => 'true', 'CARE_HOURS_SALES_ENABLED' => 'true',
        'CARE_HOURS_PILOT_ONLY' => 'false',
        'CARE_HOURS_WELCOME_BONUS_ENABLED' => 'true', 'MARKETPLACE_PRICING_V2_ENABLED' => 'true',
        'MARKETPLACE_FAMILY_CARE_HOURLY_CENTS' => '3000', 'MARKETPLACE_FAMILY_PROCESSING_FEE_HOURLY_CENTS' => '100',
        'MARKETPLACE_CAREGIVER_GROSS_HOURLY_CENTS' => '2700', 'TWILIO_BYPASS' => 'true', 'DIDIT_BYPASS' => 'true',
        'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
        'AI_SUPPORT_SAFETY_IDENTIFIER_SECRET' => 'isolated-financial-proof',
    ];
    foreach ($env as $key => $value) {
        putenv($key.'='.$value); $_ENV[$key] = $value; $_SERVER[$key] = $value;
    }
    require $project.'/vendor/autoload.php';
    $transport = new class implements \Stripe\HttpClient\ClientInterface {
        public bool $writesEnabled = false;
        private \Stripe\HttpClient\CurlClient $transport;
        public function __construct() {
            $this->transport = new \Stripe\HttpClient\CurlClient([CURLOPT_FOLLOWLOCATION => false]);
            $this->transport->setConnectTimeout(10); $this->transport->setTimeout(30);
        }
        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1') {
            $parsed = parse_url($url); $path = $parsed['path'] ?? '';
            if (($parsed['scheme'] ?? '') !== 'https' || ($parsed['host'] ?? '') !== 'api.stripe.com' || $hasFile
                || ! str_starts_with($path, '/v1/') || isset($parsed['user']) || isset($parsed['pass'])
                || (strtolower($method) !== 'get' && (! $this->writesEnabled || strtolower($method) !== 'post'
                    || ! preg_match('#^/v1/(accounts|payment_intents|transfers|payouts)(/[A-Za-z0-9_]+/reversals)?$#D', $path)))) {
                throw new RuntimeException('provider_request_outside_authorized_sandbox_proof');
            }
            return $this->transport->request($method, $url, $headers, $params, $hasFile, $apiMode);
        }
    };
    \Stripe\ApiRequestor::setHttpClient($transport);
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $stage = 'sandbox_identity';
    $platform = $stripe->accounts->retrieve()->toArray();
    $check(($platform['id'] ?? '') === 'acct_1UStLcKBu0xzVrza', 'wrong_sandbox_platform');
    $transport->writesEnabled = true;
    $result['sandbox_account_id'] = $platform['id'];
    $result['run_id'] = $state['run_id'];
    $app = require $project.'/bootstrap/app.php';
    $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, static function ($app) use ($database, $root): void {
        $config = $app->make('config');
        if ($config->get('database.default') !== 'sqlite' || str_replace('\\', '/', $config->get('database.connections.sqlite.database')) !== $database
            || $config->get('database.connections.sqlite.url') || $config->get('mail.default') !== 'array'
            || ! str_starts_with((string) $config->get('services.stripe.secret'), 'sk_test_') || $config->get('services.stripe.bypass')) {
            throw new RuntimeException('resolved_runtime_not_isolated');
        }
    });
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $stage = 'fresh_connect_accounts';
    foreach (['A', 'B'] as $label) {
        if (! isset($state['connect'][$label])) {
            $account = $stripe->accounts->create([
                'type' => 'custom', 'country' => 'US', 'business_type' => 'individual',
                'email' => 'financial-proof-'.strtolower($label).'-'.$state['run_id'].'@example.invalid',
                'capabilities' => ['transfers' => ['requested' => true], 'card_payments' => ['requested' => true]],
                'individual' => ['first_name' => 'Fictional', 'last_name' => 'Caregiver'.$label, 'phone' => '+12025550123',
                    'email' => 'financial-proof-'.strtolower($label).'-'.$state['run_id'].'@example.invalid',
                    'dob' => ['day' => 1, 'month' => 1, 'year' => 1902], 'id_number' => '222222222',
                    'address' => ['line1' => 'address_full_match', 'city' => 'Raleigh', 'state' => 'NC', 'postal_code' => '27601', 'country' => 'US']],
                'business_profile' => ['mcc' => '8099', 'url' => 'https://accessible.stripe.com', 'product_description' => 'Fictional sandbox-only care test'],
                'external_account' => ['object' => 'bank_account', 'country' => 'US', 'currency' => 'usd', 'routing_number' => '110000000', 'account_number' => '000123456789'],
                'tos_acceptance' => ['date' => time(), 'ip' => '127.0.0.1'],
                'settings' => ['payouts' => ['schedule' => ['interval' => 'manual']]],
                'metadata' => ['financial_validation_run' => $state['run_id'], 'fictional_person' => 'true'],
            ], ['idempotency_key' => 'financial-proof:'.$state['run_id'].':account:'.$label])->toArray();
            $state['connect'][$label] = $account['id']; $persist();
        }
        $account = $stripe->accounts->retrieve($state['connect'][$label])->toArray();
        $check(($account['capabilities']['transfers'] ?? '') === 'active', 'connect_transfers_not_ready_'.$label);
        $result['connect'][$label] = ['id' => $account['id'], 'payouts_enabled' => $account['payouts_enabled'], 'transfers' => $account['capabilities']['transfers'],
            'currently_due' => $account['requirements']['currently_due'] ?? [], 'pending_verification' => $account['requirements']['pending_verification'] ?? []];
    }
    $stage = 'test_liquidity';
    if (! isset($state['liquidity_intent'])) {
        $liquidity = $stripe->paymentIntents->create(['amount' => 100000, 'currency' => 'usd', 'payment_method' => 'pm_card_bypassPending',
            'confirm' => true, 'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            'metadata' => ['financial_validation_run' => $state['run_id'], 'purpose' => 'sandbox_platform_liquidity']],
            ['idempotency_key' => 'financial-proof:'.$state['run_id'].':liquidity'])->toArray();
        $check($liquidity['livemode'] === false && $liquidity['status'] === 'succeeded', 'liquidity_not_succeeded');
        $state['liquidity_intent'] = $liquidity['id']; $persist();
    }
    $stage = 'domain_fixtures';
    $owner = \App\Models\User::firstOrCreate(['email' => 'financial-owner@example.test'], ['name' => 'Fictional Family', 'role' => 'family', 'password' => bcrypt('Sandbox-only-2026!'), 'email_verified_at' => now()]);
    $family = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($owner);
    $careAccount = \App\Models\CareHourAccount::firstOrCreate(['family_account_id' => $family->id], ['display_name' => 'Fictional Recipient', 'currency' => 'usd', 'active' => true]);
    $caregivers = [];
    foreach (['A', 'B'] as $label) {
        $caregivers[$label] = \App\Models\User::firstOrCreate(['email' => 'financial-caregiver-'.$label.'@example.test'], ['name' => 'Fictional Caregiver '.$label, 'role' => 'caregiver', 'password' => bcrypt('Sandbox-only-2026!'), 'email_verified_at' => now()]);
        \App\Models\CaregiverProfile::firstOrCreate(['user_id' => $caregivers[$label]->id], ['status' => 'active', 'stripe_connect_account_id' => $state['connect'][$label], 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
    }
    $stage = 'actual_package_payments';
    $purchases = [];
    foreach (['A' => 10, 'B' => 5] as $label => $hours) {
        $key = hash('sha256', $state['run_id'].'|purchase|'.$label);
        $purchase = \App\Models\CareHourPurchase::where('request_key', $key)->first();
        if (! $purchase) {
            $quote = app(\App\Services\CareHours\CareHourPurchaseService::class)->quote($careAccount, $hours);
            $purchase = \App\Models\CareHourPurchase::create(['public_id' => (string) \Illuminate\Support\Str::uuid(), 'request_key' => $key,
                'care_hour_account_id' => $careAccount->id, 'family_account_id' => $family->id, 'buyer_user_id' => $owner->id,
                'buyer_email' => $owner->email, 'buyer_name' => $owner->name, 'paid_minutes' => $quote['paid_minutes'], 'bonus_minutes' => $quote['bonus_minutes'],
                'total_minutes' => $quote['total_minutes'], 'amount_cents' => $quote['amount_cents'], 'currency' => 'usd', 'status' => 'pending',
                'terms_version' => $quote['terms_version'], 'quote' => $quote, 'terms_accepted_at' => now(), 'success_url' => 'http://127.0.0.1:8016', 'cancel_url' => 'http://127.0.0.1:8016']);
            if ($purchase->bonus_minutes) {
                \Illuminate\Support\Facades\DB::table('care_hour_welcome_claims')->insert(['care_hour_account_id' => $careAccount->id, 'care_hour_purchase_id' => $purchase->id, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (! isset($state['intents'][$label])) {
            $intent = $stripe->paymentIntents->create(['amount' => $purchase->amount_cents, 'currency' => 'usd', 'payment_method' => 'pm_card_bypassPending',
                'confirm' => true, 'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id, 'financial_validation_run' => $state['run_id']]],
                ['idempotency_key' => 'financial-proof:'.$state['run_id'].':purchase:'.$label])->toArray();
            $state['intents'][$label] = $intent['id']; $persist();
        }
        $financials = app(\App\Services\CareHours\CareHourPaymentGateway::class)->financials($state['intents'][$label]);
        $check($financials['livemode'] === false && $financials['fee_finalized'] === true && is_int($financials['processing_fee_cents']), 'actual_package_fee_not_finalized');
        $purchases[$label] = app(\App\Services\CareHours\CareHourPurchaseService::class)->acceptPaid($purchase, $financials);
        $result['purchases'][$label] = $purchases[$label]->only(['public_id', 'amount_cents', 'paid_minutes', 'bonus_minutes', 'total_minutes', 'processing_fee_cents', 'stripe_payment_intent_id', 'stripe_charge_id', 'livemode']);
    }
    $bookingFor = function (string $label, int $minutes, string $caregiverLabel, int $rate = 2700) use ($owner, $family, $careAccount, $caregivers): \App\Models\CareBooking {
        $request = \App\Models\CareRequest::firstOrCreate(['title' => 'Financial proof '.$label], ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addMinutes($minutes)]);
        $recipient = $request->recipient()->firstOrCreate([], ['full_name' => 'Fictional Recipient', 'relationship_to_family' => 'Parent']);
        app(\App\Services\CareHours\CareHourIdentityService::class)->linkRecipient($careAccount, $recipient, $owner);
        $application = $request->applications()->firstOrCreate(['caregiver_user_id' => $caregivers[$caregiverLabel]->id], ['status' => 'hired', 'proposed_rate' => $rate / 100]);
        $booking = \App\Models\CareBooking::firstOrCreate(['care_request_id' => $request->id], ['care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'family_account_id' => $family->id, 'caregiver_user_id' => $caregivers[$caregiverLabel]->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => $minutes]);
        if (! app(\App\Services\CareHours\PrepaidBookingFundingService::class)->forBooking($booking)) {
            app(\App\Services\CareHours\PrepaidBookingFundingService::class)->selectForBooking($booking, $careAccount, $owner);
            app(\App\Services\CareHours\PrepaidBookingFundingService::class)->prepare($booking->fresh());
            $booking->forceFill(['status' => 'completed', 'worked_minutes' => $minutes, 'completed_at' => now(), 'family_confirmed_at' => now(), 'caregiver_gross_rate_cents' => $rate])->save();
        }

        return $booking->fresh();
    };
    $stage = 'multiple_lots_and_caregivers';
    // Preserve the observed provider validation rejection from the first run. Only this isolated proof
    // may retire that attempt after an authoritative lookup confirms no transfer exists for its key.
    foreach (\App\Models\CareHourPaymentOperation::where('kind', 'transfer')->where('status', 'unknown')
        ->where('last_error', 'You cannot use `transfer_group` if the `source_transaction` already has one set.')->get() as $rejected) {
        $found = app(\App\Services\Payments\StripeClient::class)->findPrepaidTransfer(null, $rejected->public_id, $rejected->destination_account_id, $rejected->created_at->timestamp - 60);
        $check($found === null, 'rejected_operation_unexpectedly_exists');
        $state['observed_provider_rejection'] = ['operation_id' => $rejected->public_id, 'amount_cents' => $rejected->amount_cents,
            'source_charge_id' => $rejected->source_charge_id, 'reason' => $rejected->last_error, 'provider_lookup_absent' => true,
            'fixture_only_resolution' => 'Marked confirmed provider-rejected request failed; new operation uses inherited source group.'];
        $persist();
        $rejected->update(['status' => 'failed']);
    }
    $result['observed_provider_rejection'] = $state['observed_provider_rejection'] ?? null;
    $first = $bookingFor('first', 720, 'A');
    $second = $bookingFor('second', 240, 'B');
    $settlements = app(\App\Services\CareHours\PrepaidBookingSettlementService::class);
    $firstSettlement = $settlements->settle($first);
    $secondSettlement = $settlements->settle($second);
    $check($firstSettlement->status === 'paid' && $secondSettlement->status === 'paid', 'initial_transfers_not_paid');
    if (! isset($state['initial'])) {
        $state['initial'] = ['first' => $firstSettlement->only(['caregiver_gross_cents', 'package_fee_cents', 'caregiver_net_cents', 'paid_cents']),
            'second' => $secondSettlement->only(['caregiver_gross_cents', 'package_fee_cents', 'caregiver_net_cents', 'paid_cents'])]; $persist();
    }
    $result['initial'] = $state['initial'];
    $stage = 'transfer_response_loss_recovery';
    if (! isset($state['transfer_recovered'])) {
        $operation = $firstSettlement->operations()->where('kind', 'transfer')->firstOrFail();
        $original = $operation->stripe_transfer_id;
        $count = $firstSettlement->operations()->where('kind', 'transfer')->count();
        $operation->forceFill(['status' => 'unknown', 'stripe_transfer_id' => null, 'created_at' => now()->subHours(25)])->save();
        app(\App\Services\CareHours\PrepaidTransferPlanner::class)->pay($firstSettlement->fresh());
        $check($operation->fresh()->stripe_transfer_id === $original && $operation->fresh()->status === 'succeeded'
            && $count === $firstSettlement->operations()->where('kind', 'transfer')->count(), 'transfer_response_loss_recovery_failed');
        $state['transfer_recovered'] = ['transfer_id' => $original, 'operation_public_id' => $operation->public_id, 'new_operation_count' => 0]; $persist();
    }
    $result['transfer_response_loss_recovery'] = $state['transfer_recovered'];
    $stage = 'downward_correction_and_real_reversal';
    $first->forceFill(['worked_minutes' => 600])->save();
    $firstSettlement = $settlements->settle($first->fresh());
    $check($firstSettlement->status === 'paid' && $firstSettlement->prepaid_minutes === 600, 'corrected_settlement_not_paid');
    $check($firstSettlement->operations()->where('kind', 'reversal')->where('status', 'succeeded')->count() > 0, 'real_reversal_missing');
    $stage = 'reversal_response_loss_recovery';
    if (! isset($state['reversal_recovered'])) {
        $operation = $firstSettlement->operations()->where('kind', 'reversal')->firstOrFail();
        $original = $operation->metadata['provider_reversal_id'];
        $metadata = $operation->metadata; unset($metadata['provider_reversal_id']);
        $count = $firstSettlement->operations()->where('kind', 'reversal')->count();
        $operation->forceFill(['status' => 'unknown', 'metadata' => $metadata, 'created_at' => now()->subHours(25)])->save();
        app(\App\Services\CareHours\PrepaidTransferPlanner::class)->pay($firstSettlement->fresh());
        $check(($operation->fresh()->metadata['provider_reversal_id'] ?? '') === $original && $operation->fresh()->status === 'succeeded'
            && $count === $firstSettlement->operations()->where('kind', 'reversal')->count(), 'reversal_response_loss_recovery_failed');
        $state['reversal_recovered'] = ['reversal_id' => $original, 'operation_public_id' => $operation->public_id, 'new_operation_count' => 0]; $persist();
    }
    $result['reversal_response_loss_recovery'] = $state['reversal_recovered'];
    $stage = 'source_capacity_and_platform_funding';
    $third = $bookingFor('price-protected-reused-hours', 120, 'B', 6000);
    $thirdSettlement = $settlements->settle($third);
    $check($thirdSettlement->status === 'paid', 'platform_funding_not_paid');
    $topup = (int) $thirdSettlement->operations()->where('kind', 'transfer')->whereNull('source_charge_id')->where('status', 'succeeded')->sum('amount_cents');
    $check($topup > 0, 'platform_funding_not_exercised');
    $result['platform_funding_cents'] = $topup;
    $result['settlements'] = \App\Models\CareHourSettlement::get()->map->only(['id', 'worked_minutes', 'prepaid_minutes', 'caregiver_gross_cents', 'package_fee_cents', 'caregiver_net_cents', 'paid_cents', 'status'])->all();
    $feeTotal = (int) \App\Models\CareHourSettlement::sum('package_fee_cents');
    $purchaseFeeTotal = $purchases['A']->processing_fee_cents + $purchases['B']->processing_fee_cents;
    $check($feeTotal === $purchaseFeeTotal, 'actual_processing_fee_not_conserved');
    $result['fee_conservation'] = ['actual_provider_fees_cents' => $purchaseFeeTotal, 'settled_fee_cents' => $feeTotal];
    $result['balance'] = $careAccount->balance();
    $check($result['balance']['used_minutes'] === 960 && $result['balance']['available_minutes'] === 0, 'minute_conservation_failed');
    $stage = 'provider_transfer_verification';
    $result['transfers'] = [];
    foreach (\App\Models\CareHourPaymentOperation::where('kind', 'transfer')->where('status', 'succeeded')->get() as $operation) {
        $transfer = $stripe->transfers->retrieve($operation->stripe_transfer_id)->toArray();
        $check($transfer['livemode'] === false && $transfer['amount'] === $operation->amount_cents && $transfer['destination'] === $operation->destination_account_id
            && ($transfer['source_transaction'] ?? null) === $operation->source_charge_id && $transfer['metadata']['care_hour_operation_id'] === $operation->public_id, 'provider_transfer_mismatch');
        $duplicates = 0;
        foreach ($stripe->transfers->all(['destination' => $operation->destination_account_id, 'limit' => 100])->autoPagingIterator() as $candidate) {
            if (($candidate->metadata['care_hour_operation_id'] ?? '') === $operation->public_id) $duplicates++;
        }
        $check($duplicates === 1, 'duplicate_provider_transfer');
        $result['transfers'][] = ['id' => $transfer['id'], 'amount_cents' => $transfer['amount'], 'reversed_cents' => $transfer['amount_reversed'],
            'destination' => $transfer['destination'], 'source_charge_id' => $transfer['source_transaction'], 'unique_operation_confirmed' => true];
    }
    foreach ($purchases as $label => $purchase) {
        $committed = (int) \App\Models\CareHourPaymentOperation::where('kind', 'transfer')->where('source_purchase_id', $purchase->id)
            ->whereNotIn('status', ['failed', 'cancelled'])->sum('amount_cents');
        $check($committed <= $purchase->amount_cents, 'source_capacity_exceeded');
        $result['source_capacity'][$label] = ['original_charge_cents' => $purchase->amount_cents, 'gross_transferred_cents' => $committed, 'reversals_restore_capacity' => false];
    }
    $stage = 'connected_account_bank_payout';
    foreach (['A', 'B'] as $label) {
        $options = ['stripe_account' => $state['connect'][$label]];
        $balance = $stripe->balance->retrieve([], $options)->toArray();
        $available = array_sum(array_map(fn ($row) => $row['currency'] === 'usd' ? $row['amount'] : 0, $balance['available']));
        if ($available >= 100 && ! isset($state['bank_payouts'][$label])) {
            $payout = $stripe->payouts->create(['amount' => 100, 'currency' => 'usd', 'metadata' => ['financial_validation_run' => $state['run_id']]],
                $options + ['idempotency_key' => 'financial-proof:'.$state['run_id'].':bank-payout:'.$label])->toArray();
            $check($payout['livemode'] === false, 'live_payout_prohibited');
            $state['bank_payouts'][$label] = $payout['id']; $persist();
        }
        $payout = isset($state['bank_payouts'][$label]) ? $stripe->payouts->retrieve($state['bank_payouts'][$label], [], $options)->toArray() : null;
        $result['bank_payouts'][$label] = ['connected_account_id' => $state['connect'][$label], 'available_balance_cents_before_payout' => $available,
            'payout_id' => $payout['id'] ?? null, 'amount_cents' => $payout['amount'] ?? null, 'status' => $payout['status'] ?? 'not_created_balance_pending',
            'arrival_date' => $payout['arrival_date'] ?? null, 'bank_money_moved' => false, 'note' => 'Stripe test payout only; application paid means connected-account transfer, not bank payout.'];
    }
    $result['legacy_card_records'] = \App\Models\CareBookingPayment::count();
    $check($result['legacy_card_records'] === 0, 'unexpected_legacy_payment_mutation');
    $result['verified'] = true;
    $result['completed_at'] = gmdate('c');
    $save($result);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $exception) {
    $result['failure_stage'] = $stage;
    $result['reason'] = $exception instanceof \Stripe\Exception\ApiErrorException ? 'stripe_api_error' : $exception->getMessage();
    $result['error_type'] = get_class($exception);
    if ($exception instanceof \Stripe\Exception\ApiErrorException) {
        $result['provider_code'] = $exception->getStripeCode();
        $result['provider_param'] = $exception->getError()?->param;
        $result['http_status'] = $exception->getHttpStatus();
    }
    $save($result);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    exit(1);
}
