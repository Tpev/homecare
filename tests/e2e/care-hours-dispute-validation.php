<?php

declare(strict_types=1);

// Real sandbox backend proof; never opens or writes the workspace application database.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$project = dirname(__DIR__, 2);
$root = $project.'/tmp/care-hours-dispute-validation';
$stage = 'guard';
$result = ['verified' => false, 'livemode' => false, 'evidence_kind' => 'real Stripe sandbox payments/refunds/disputes with isolated backend fixtures',
    'limitations' => ['No browser Checkout or signed HTTP webhook delivery is exercised by this runner.', 'No live money or bank settlement is involved.']];
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
    $provider = json_decode(file_get_contents($project.'/tmp/care-hours-staging/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $check(($provider['payment_mode'] ?? '') === 'stripe_test' && preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '') === 1, 'sandbox_key_required');
    foreach (['storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
        if (! is_dir($root.'/'.$directory)) {
            mkdir($root.'/'.$directory, 0777, true);
        }
    }
    $database = str_replace('\\', '/', $root.'/disputes.sqlite');
    $check(! is_link($database) && ! file_exists($root.'/config.php'), 'isolated_database_and_uncached_config_required');
    if (! file_exists($database)) {
        touch($database);
    }
    $statePath = $root.'/state.private.json';
    $state = file_exists($statePath) ? json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR) : ['run_id' => bin2hex(random_bytes(12))];
    $persist = static function () use (&$state, $statePath): void {
        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    };
    $persist();
    $env = [
        'APP_ENV' => 'testing', 'APP_NAME' => 'LoLo sandbox dispute proof', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8017',
        'APP_KEY' => 'base64:'.base64_encode(hash('sha256', 'dispute-proof|'.$state['run_id'], true)),
        'APP_CONFIG_CACHE' => 'tmp/care-hours-dispute-validation/config.php', 'APP_SERVICES_CACHE' => 'tmp/care-hours-dispute-validation/services.php',
        'APP_PACKAGES_CACHE' => 'tmp/care-hours-dispute-validation/packages.php', 'APP_ROUTES_CACHE' => 'tmp/care-hours-dispute-validation/routes.php',
        'LARAVEL_STORAGE_PATH' => $root.'/storage', 'VIEW_COMPILED_PATH' => $root.'/storage/framework/views',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'DATABASE_URL' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'STRIPE_BYPASS' => 'false', 'STRIPE_SECRET' => $provider['stripe_secret'], 'STRIPE_CURRENCY' => 'usd',
        'CARE_HOURS_ENABLED' => 'true', 'CARE_HOURS_ENROLLMENT_ENABLED' => 'true', 'CARE_HOURS_SALES_ENABLED' => 'true',
        'CARE_HOURS_GIFTS_ENABLED' => 'true', 'CARE_HOURS_WELCOME_BONUS_ENABLED' => 'true',
        'TWILIO_BYPASS' => 'true', 'DIDIT_BYPASS' => 'true', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
        'AI_SUPPORT_SAFETY_IDENTIFIER_SECRET' => 'isolated-dispute-proof',
    ];
    foreach ($env as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    require $project.'/vendor/autoload.php';
    $transport = new class implements \Stripe\HttpClient\ClientInterface
    {
        public bool $writesEnabled = false;

        private \Stripe\HttpClient\CurlClient $transport;

        public function __construct()
        {
            $this->transport = new \Stripe\HttpClient\CurlClient([CURLOPT_FOLLOWLOCATION => false]);
            $this->transport->setConnectTimeout(10);
            $this->transport->setTimeout(25);
        }

        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '';
            if (($parsed['scheme'] ?? '') !== 'https' || ($parsed['host'] ?? '') !== 'api.stripe.com' || $hasFile
                || ! str_starts_with($path, '/v1/') || isset($parsed['user']) || isset($parsed['pass'])
                || (strtolower($method) !== 'get' && (! $this->writesEnabled || strtolower($method) !== 'post'
                    || ! preg_match('#^/v1/(payment_intents|refunds|disputes/(?:dp|du)_[A-Za-z0-9]+)$#D', $path)))) {
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
    $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, static function ($app) use ($database): void {
        $config = $app->make('config');
        if ($config->get('database.default') !== 'sqlite' || str_replace('\\', '/', $config->get('database.connections.sqlite.database')) !== $database
            || $config->get('database.connections.sqlite.url') || $config->get('mail.default') !== 'array'
            || ! str_starts_with((string) $config->get('services.stripe.secret'), 'sk_test_') || $config->get('services.stripe.bypass')) {
            throw new RuntimeException('resolved_runtime_not_isolated');
        }
    });
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \Illuminate\Support\Facades\Notification::fake();
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $check(\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]) === 0, 'isolated_migrations_failed');
    $owner = \App\Models\User::firstOrCreate(['email' => 'dispute-owner@example.test'], ['name' => 'Fictional Dispute Family', 'role' => 'family', 'password' => bcrypt('Sandbox-only-2026!'), 'email_verified_at' => now()]);
    $family = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($owner);
    $account = \App\Models\CareHourAccount::firstOrCreate(['family_account_id' => $family->id], ['display_name' => 'Fictional Dispute Recipient', 'currency' => 'usd', 'active' => true]);
    $purchases = app(\App\Services\CareHours\CareHourPurchaseService::class);
    $gifts = app(\App\Services\CareHours\CareHourGiftService::class);
    $gateway = app(\App\Services\CareHours\CareHourPaymentGateway::class);
    $webhooks = app(\App\Services\CareHours\CareHourWebhookService::class);
    $records = [];
    foreach (['refund', 'won', 'lost'] as $label) {
        $stage = 'create_'.$label.'_gift_payment';
        $key = hash('sha256', $state['run_id'].'|gift|'.$label);
        $purchase = \App\Models\CareHourPurchase::where('request_key', $key)->first();
        if (! $purchase) {
            $quote = $gifts->quote(10);
            $purchase = \App\Models\CareHourPurchase::create(['public_id' => (string) \Illuminate\Support\Str::uuid(), 'request_key' => $key,
                'care_hour_account_id' => null, 'family_account_id' => null, 'buyer_user_id' => $owner->id,
                'buyer_email' => $owner->email, 'buyer_name' => $owner->name, 'paid_minutes' => 600, 'bonus_minutes' => 0, 'total_minutes' => 600,
                'amount_cents' => 31000, 'currency' => 'usd', 'status' => 'pending', 'terms_version' => $quote['terms_version'], 'quote' => $quote,
                'terms_accepted_at' => now(), 'success_url' => 'http://127.0.0.1:8017', 'cancel_url' => 'http://127.0.0.1:8017']);
            $purchase->gift()->create(['welcome_bonus_minutes' => 60]);
        }
        if (! isset($state['intents'][$label])) {
            $intent = $stripe->paymentIntents->create(['amount' => 31000, 'currency' => 'usd',
                'payment_method' => $label === 'refund' ? 'pm_card_bypassPending' : 'pm_card_createDispute',
                'confirm' => true, 'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id, 'dispute_validation_run' => $state['run_id'], 'scenario' => $label]],
                ['idempotency_key' => 'dispute-proof:'.$state['run_id'].':payment:'.$label])->toArray();
            $check($intent['livemode'] === false && $intent['status'] === 'succeeded', 'test_payment_not_succeeded');
            $state['intents'][$label] = $intent['id'];
            $persist();
        }
        $intent = $stripe->paymentIntents->retrieve($state['intents'][$label])->toArray();
        $check($intent['livemode'] === false && ($intent['metadata']['dispute_validation_run'] ?? '') === $state['run_id']
            && ($intent['metadata']['care_hour_purchase_id'] ?? '') === $purchase->public_id, 'payment_not_owned_by_run');
        $purchase = $purchases->acceptPaid($purchase, $gateway->financials($intent['id']));
        if (($code = $gifts->codeForBuyer($purchase, $owner)) !== null) {
            $state['codes'][$label] = $code;
            $persist();
        }
        $records[$label] = $purchase;
        $result['payments'][$label] = ['payment_intent_id' => $intent['id'], 'charge_id' => $purchase->stripe_charge_id,
            'amount_cents' => 31000, 'currency' => 'usd', 'livemode' => false];
    }

    $stage = 'refund_unclaimed_gift';
    $refunded = $records['refund'];
    if (! isset($state['refund_id'])) {
        $refund = $stripe->refunds->create(['payment_intent' => $state['intents']['refund'], 'amount' => 31000,
            'metadata' => ['dispute_validation_run' => $state['run_id'], 'purpose' => 'unclaimed_gift_reversal_test']],
            ['idempotency_key' => 'dispute-proof:'.$state['run_id'].':refund'])->toArray();
        $state['refund_id'] = $refund['id'];
        $persist();
    }
    $refund = $stripe->refunds->retrieve($state['refund_id'])->toArray();
    $check($refund['status'] === 'succeeded' && $refund['payment_intent'] === $state['intents']['refund'], 'refund_not_succeeded');
    $charge = $stripe->charges->retrieve($refunded->stripe_charge_id)->toArray();
    $check($charge['livemode'] === false && $charge['amount_refunded'] === 31000, 'charge_refund_not_authoritative');
    foreach ([1, 2] as $replay) {
        $check($webhooks->handle('charge.refunded', $charge, false), 'refund_event_not_owned');
    }
    $refunded->refresh();
    $check($refunded->status === 'held' && $refunded->amount_reversed_cents === 31000 && $refunded->care_hour_account_id === null, 'refunded_gift_not_held');
    $check($gifts->codeForBuyer($refunded, $owner) === null, 'refunded_gift_exposes_code');
    $check(isset($state['codes']['refund']), 'refund_gift_never_had_original_code');
    try {
        $gifts->claim($state['codes']['refund'], $account, $owner, 'old-confirmation');
        throw new RuntimeException('refunded_gift_claim_succeeded');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $check(($exception->errors()['gift_code'][0] ?? null) === 'This gift code is unavailable, already claimed, or awaiting payment review.', 'refunded_claim_failed_for_unrelated_reason');
        // The real provider reversal invalidates an already issued bearer code.
    }
    $check(! \App\Models\CareHourLot::where('care_hour_purchase_id', $refunded->id)->exists(), 'refunded_gift_created_lot');
    $result['refund'] = ['refund_id' => $refund['id'], 'status' => $refund['status'], 'amount_cents' => $refund['amount'],
        'purchase_status' => $refunded->status, 'replayed_event_count' => 2, 'old_code_claim_rejected' => true, 'credit_lots' => 0];
    $save($result);

    foreach (['won' => 'winning_evidence', 'lost' => 'losing_evidence'] as $label => $evidence) {
        $stage = 'dispute_'.$label.'_hold';
        $purchase = $records[$label];
        $deadline = microtime(true) + 45;
        do {
            $rows = $stripe->disputes->all(['charge' => $purchase->stripe_charge_id, 'limit' => 1])->toArray();
            $dispute = $rows['data'][0] ?? null;
            if ($dispute !== null) {
                break;
            }
            usleep(1000000);
        } while (microtime(true) < $deadline);
        $check($dispute !== null && $dispute['livemode'] === false && $dispute['charge'] === $purchase->stripe_charge_id, 'test_dispute_not_created');
        if (! isset($state['hold_verified'][$label])) {
            $check(in_array($dispute['status'], ['needs_response', 'under_review'], true), 'dispute_not_in_review');
            // Read actual charge/dispute state through application reconciliation, before domain-event replay.
            $purchase = $purchases->acceptPaid($purchase, $gateway->financials($state['intents'][$label]));
            $check($purchase->status === 'held' && $purchase->care_hour_account_id === null && ! \App\Models\CareHourLot::where('care_hour_purchase_id', $purchase->id)->exists(), 'disputed_gift_not_held');
            $check($gifts->codeForBuyer($purchase, $owner) === null, 'disputed_gift_exposes_code');
            foreach ([1, 2] as $replay) {
                $check($webhooks->handle('charge.dispute.created', $dispute, false), 'dispute_event_not_owned');
            }
            if (isset($state['codes'][$label])) {
                try {
                    $gifts->claim($state['codes'][$label], $account, $owner, 'old-confirmation');
                    throw new RuntimeException('disputed_gift_claim_succeeded');
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $check(($exception->errors()['gift_code'][0] ?? null) === 'This gift code is unavailable, already claimed, or awaiting payment review.', 'disputed_claim_failed_for_unrelated_reason');
                }
            }
            $state['hold_verified'][$label] = ['dispute_id' => $dispute['id'], 'status' => $dispute['status'], 'credit_lots' => 0,
                'code_available' => false, 'old_code_claim_rejected' => isset($state['codes'][$label]), 'replayed_event_count' => 2];
            $persist();
        }
        $result['disputes'][$label]['hold'] = $state['hold_verified'][$label];
        $result['disputes'][$label]['hold']['claim_prevented'] = true;
        $result['disputes'][$label]['hold']['mechanism'] = isset($state['codes'][$label])
            ? 'Existing bearer code rejected after authoritative dispute hold.'
            : 'Stripe disputed the payment before initial reconciliation; no bearer code or care credits were issued.';
        $save($result);
        $stage = 'dispute_'.$label.'_resolution';
        if (! in_array($dispute['status'], ['won', 'lost'], true)) {
            $stripe->disputes->update($dispute['id'], ['evidence' => ['uncategorized_text' => $evidence], 'submit' => true],
                ['idempotency_key' => 'dispute-proof:'.$state['run_id'].':evidence:'.$label]);
        }
        $deadline = microtime(true) + 45;
        do {
            $dispute = $stripe->disputes->retrieve($dispute['id'])->toArray();
            if (in_array($dispute['status'], ['won', 'lost'], true)) {
                break;
            }
            usleep(1000000);
        } while (microtime(true) < $deadline);
        $check($dispute['status'] === $label, 'dispute_resolution_not_observed_'.$label);
        foreach ([1, 2] as $replay) {
            $check($webhooks->handle('charge.dispute.closed', $dispute, false), 'closed_dispute_event_not_owned');
        }
        $purchase = $purchases->acceptPaid($purchase->fresh(), $gateway->financials($state['intents'][$label]));
        if ($label === 'won') {
            if (! $purchase->gift->claimed_at) {
                $code = $gifts->codeForBuyer($purchase, $owner);
                $check($purchase->status === 'paid' && $code !== null, 'won_gift_not_released');
                $quote = $gifts->previewForClaim($code, $account, $owner);
                $purchase = $gifts->claim($code, $account, $owner, $quote['quote_key']);
            }
            $check($purchase->status === 'issued' && $account->balance()['available_minutes'] === 660, 'won_gift_not_credited_once');
        } else {
            $check($purchase->status === 'held' && $gifts->codeForBuyer($purchase, $owner) === null && ! \App\Models\CareHourLot::where('care_hour_purchase_id', $purchase->id)->exists(), 'lost_gift_became_claimable');
        }
        $result['disputes'][$label]['resolution'] = ['dispute_id' => $dispute['id'], 'provider_status' => $dispute['status'],
            'purchase_status' => $purchase->status, 'credit_lots' => \App\Models\CareHourLot::where('care_hour_purchase_id', $purchase->id)->count(), 'replayed_event_count' => 2];
        $save($result);
    }
    $check(\App\Models\CareBookingPayment::count() === 0, 'legacy_payment_record_created');
    $check(\App\Models\CareHourLot::count() === 1 && $account->balance()['available_minutes'] === 660, 'final_entitlements_incorrect');
    $result['final_balance'] = $account->balance();
    $result['legacy_card_records'] = 0;
    $result['verified'] = true;
    $result['completed_at'] = gmdate('c');
    $save($result);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    $result['failure_stage'] = $stage;
    $result['reason'] = $exception instanceof \Stripe\Exception\ApiErrorException ? 'stripe_api_error' : $exception->getMessage();
    $result['error_type'] = get_class($exception);
    if ($exception instanceof \Stripe\Exception\ApiErrorException) {
        $result['provider_code'] = $exception->getStripeCode();
        $result['provider_param'] = $exception->getError()?->param;
        $result['http_status'] = $exception->getHttpStatus();
    }
    if (is_dir($root)) {
        $save($result);
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(1);
}
