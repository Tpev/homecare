<?php

declare(strict_types=1);

// Explicit sandbox-only provider shape check; no Laravel database or legacy payment code.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$project = dirname(__DIR__, 2);
$root = $project.'/tmp/care-hours-financial-validation';
$stage = 'guard';
try {
    if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--run' || ! is_dir($root) || is_link($root)) {
        throw new RuntimeException('explicit_isolated_cli_required');
    }
    $provider = json_decode(file_get_contents($project.'/tmp/care-hours-staging/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($provider['payment_mode'] ?? '') !== 'stripe_test' || ! preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '')) {
        throw new RuntimeException('sandbox_key_required');
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
                || isset($parsed['user']) || isset($parsed['pass']) || ! str_starts_with($path, '/v1/')
                || (strtolower($method) !== 'get' && (! $this->writesEnabled || strtolower($method) !== 'post'
                    || ! preg_match('#^/v1/(payment_intents(/[A-Za-z0-9_]+/capture)?|refunds)$#D', $path)))) {
                throw new RuntimeException('provider_request_outside_authorized_sandbox_proof');
            }
            return $this->transport->request($method, $url, $headers, $params, $hasFile, $apiMode);
        }
    };
    \Stripe\ApiRequestor::setHttpClient($transport);
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $platform = $stripe->accounts->retrieve();
    if ($platform->id !== 'acct_1UStLcKBu0xzVrza') {
        throw new RuntimeException('wrong_sandbox_platform');
    }
    $transport->writesEnabled = true;
    $path = $root.'/partial-capture-state.json';
    $state = file_exists($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : ['run_id' => bin2hex(random_bytes(12))];
    $persist = static function () use (&$state, $path): void { file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); };
    $persist();
    $stage = 'authorize';
    if (! isset($state['intent'])) {
        $intent = $stripe->paymentIntents->create(['amount' => 3100, 'currency' => 'usd', 'capture_method' => 'manual',
            'payment_method' => 'pm_card_visa', 'payment_method_types' => ['card'], 'confirm' => true,
            'metadata' => ['financial_validation_run' => $state['run_id'], 'purpose' => 'partial_capture_shape']],
            ['idempotency_key' => 'partial-capture-proof:'.$state['run_id'].':authorize']);
        if ($intent->livemode || $intent->status !== 'requires_capture') {
            throw new RuntimeException('unexpected_authorization');
        }
        $state['intent'] = $intent->id; $persist();
    }
    $stage = 'capture';
    if (! isset($state['captured'])) {
        $stripe->paymentIntents->capture($state['intent'], ['amount_to_capture' => 1550],
            ['idempotency_key' => 'partial-capture-proof:'.$state['run_id'].':capture']);
        $state['captured'] = true; $persist();
    }
    $snapshot = static function () use ($stripe, &$state): array {
        $intent = $stripe->paymentIntents->retrieve($state['intent'], ['expand' => ['latest_charge.balance_transaction', 'latest_charge.refunds.data.balance_transaction']])->toArray();
        if ($intent['livemode'] || $intent['status'] !== 'succeeded' || $intent['amount_received'] !== 1550) {
            throw new RuntimeException('unexpected_capture');
        }
        $charge = $intent['latest_charge'];
        $refunds = [];
        foreach ($stripe->refunds->all(['charge' => $charge['id'], 'limit' => 100, 'expand' => ['data.balance_transaction']])->autoPagingIterator() as $refund) {
            $refund = $refund->toArray();
            $transaction = is_array($refund['balance_transaction'] ?? null) ? $refund['balance_transaction'] : [];
            $refunds[] = ['id' => $refund['id'], 'amount' => $refund['amount'], 'status' => $refund['status'],
                'balance_transaction' => array_intersect_key($transaction, array_flip(['id', 'amount', 'fee', 'net', 'reporting_category', 'type']))];
        }
        return ['intent' => array_intersect_key($intent, array_flip(['id', 'amount', 'amount_received', 'amount_capturable', 'status', 'livemode'])),
            'charge' => array_intersect_key($charge, array_flip(['id', 'amount', 'amount_captured', 'amount_refunded', 'captured', 'refunded', 'status'])),
            'refunds' => $refunds];
    };
    $stage = 'capture_snapshot';
    if (! isset($state['after_capture'])) {
        $state['after_capture'] = $snapshot(); $persist();
    }
    $stage = 'cash_refund';
    if (! isset($state['refund'])) {
        $refund = $stripe->refunds->create(['payment_intent' => $state['intent'], 'amount' => 100,
            'metadata' => ['financial_validation_run' => $state['run_id'], 'purpose' => 'actual_cash_refund_shape']],
            ['idempotency_key' => 'partial-capture-proof:'.$state['run_id'].':refund']);
        $state['refund'] = $refund->id; $persist();
    }
    $proof = ['verified' => true, 'sandbox_account_id' => $platform->id, 'livemode' => false, 'bank_money_moved' => false,
        'run_id' => $state['run_id'], 'after_capture' => $state['after_capture'], 'after_100_cent_cash_refund' => $snapshot()];
    // Exercise the actual additive financial reader with an in-memory service container only.
    // No application environment, database, scheduler, mail, or legacy payment flow is loaded.
    $stage = 'application_cash_classification';
    $container = new \Illuminate\Container\Container;
    \Illuminate\Container\Container::setInstance($container);
    $container->instance('config', new \Illuminate\Config\Repository(['services' => ['stripe' => [
        'bypass' => false, 'secret' => $provider['stripe_secret'], 'currency' => 'usd',
    ]]]));
    $financials = (new \App\Services\Payments\StripeClient)->retrieveCareHourPaymentFinancials($state['intent']);
    if (! ($financials['refund_financials_finalized'] ?? false) || ($financials['cash_refunded_cents'] ?? null) !== 100
        || ($financials['authorization_released_cents'] ?? null) !== 1550) {
        throw new RuntimeException('application_cash_classification_mismatch');
    }
    $proof['application_financials'] = array_intersect_key($financials, array_flip([
        'id', 'status', 'amount_received', 'processing_fee_cents', 'fee_finalized', 'cash_refunded_cents',
        'authorization_released_cents', 'refund_financials_finalized',
    ]));
    file_put_contents($root.'/partial-capture-proof.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['verified' => false, 'stage' => $stage, 'error_class' => get_class($error)]).PHP_EOL;
    exit(1);
}
