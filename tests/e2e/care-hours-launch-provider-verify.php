<?php

declare(strict_types=1);

// Real Stripe reads only. Optional replay posts the original test events to this isolated local app.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || ! in_array($mode, ['--verify-contribution', '--replay-contribution'], true)) {
    fwrite(STDERR, "Use --verify-contribution or --replay-contribution.\n");
    exit(2);
}
final class LaunchValidationFailure extends RuntimeException {}
$assert = static function (bool $condition, string $code): void {
    if (! $condition) {
        throw new LaunchValidationFailure($code);
    }
};
$root = dirname(__DIR__, 2).'/tmp/care-hours-launch-validation';
$proofFile = $root.'/contribution-provider-proof.json';
$stage = 'local_inputs';
try {
    $assert(is_dir($root) && ! is_link($root), 'isolated_root_missing');
    foreach (['provider.json', 'contribution-verification.json', 'contribution-provider-proof.json'] as $name) {
        $assert(! is_link($root.'/'.$name), 'linked_input_refused');
    }
    file_put_contents($proofFile, json_encode(['verified' => false, 'reason' => 'verification_in_progress']));
    $provider = json_decode(file_get_contents($root.'/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $bytes = file_get_contents($root.'/contribution-verification.json');
    $snapshot = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
    $assert(($provider['payment_mode'] ?? '') === 'stripe_test' && ($snapshot['payment_mode'] ?? '') === 'stripe_test', 'test_mode_required');
    $assert(preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '') === 1, 'test_key_required');
    $assert(($snapshot['webhook_signature_required'] ?? false) === true && count($snapshot['checks'] ?? []) >= 9, 'application_evidence_missing');
    foreach ($snapshot['checks'] as $check) {
        $assert($check === true, 'application_check_failed');
    }
    $purchase = $snapshot['purchase'];
    $assert($purchase['status'] === 'issued' && $purchase['amount_cents'] === 31000 && $purchase['total_minutes'] === 660
        && $purchase['buyer_user_id'] === null && is_int($purchase['care_hour_contribution_link_id']) && $purchase['livemode'] === false, 'contribution_mismatch');
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    \Stripe\ApiRequestor::setHttpClient(new class implements \Stripe\HttpClient\ClientInterface
    {
        private \Stripe\HttpClient\CurlClient $transport;

        public function __construct()
        {
            $this->transport = new \Stripe\HttpClient\CurlClient([CURLOPT_FOLLOWLOCATION => false]);
            $this->transport->setConnectTimeout(10);
            $this->transport->setTimeout(30);
        }

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $url = parse_url($absUrl);
            if (strtolower($method) !== 'get' || $hasFile || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'api.stripe.com'
                || isset($url['user']) || isset($url['pass']) || (isset($url['port']) && $url['port'] !== 443) || ! str_starts_with($url['path'] ?? '', '/v1/')) {
                throw new LaunchValidationFailure('provider_mutation_refused');
            }

            return $this->transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $stage = 'checkout';
    $session = $stripe->checkout->sessions->retrieve($purchase['stripe_checkout_session_id'])->toArray();
    $assert(($session['livemode'] ?? null) === false && $session['status'] === 'complete' && $session['payment_status'] === 'paid'
        && $session['amount_total'] === 31000 && $session['currency'] === 'usd' && $session['payment_intent'] === $purchase['stripe_payment_intent_id']
        && $session['client_reference_id'] === $purchase['public_id'] && $session['metadata']['care_hour_purchase_id'] === $purchase['public_id'], 'checkout_mismatch');
    $stage = 'intent';
    $intent = $stripe->paymentIntents->retrieve($purchase['stripe_payment_intent_id'])->toArray();
    $assert($intent['livemode'] === false && $intent['status'] === 'succeeded' && $intent['amount_received'] === 31000
        && $intent['latest_charge'] === $purchase['stripe_charge_id'] && $intent['metadata']['care_hour_purchase_id'] === $purchase['public_id'], 'intent_mismatch');
    $stage = 'charge';
    $charge = $stripe->charges->retrieve($purchase['stripe_charge_id'])->toArray();
    $assert($charge['livemode'] === false && $charge['paid'] === true && $charge['captured'] === true && $charge['amount'] === 31000
        && $charge['amount_refunded'] === 0 && $charge['disputed'] === false && $charge['payment_intent'] === $intent['id'], 'charge_mismatch');
    $transaction = $stripe->balanceTransactions->retrieve($charge['balance_transaction'])->toArray();
    $assert($transaction['source'] === $charge['id'] && $transaction['amount'] === 31000 && $transaction['currency'] === 'usd'
        && $transaction['fee'] === $purchase['processing_fee_cents'], 'processing_fee_mismatch');
    $stage = 'signed_webhooks';
    $events = [];
    foreach ($snapshot['webhook_events'] as $local) {
        if (! in_array($local['object_id'], [$session['id'], $intent['id'], $charge['id']], true)) {
            continue;
        }
        $assert($local['status'] === 'processed' && $local['livemode'] === false && ! $local['connected_account_id'], 'owned_webhook_not_processed');
        $event = $stripe->events->retrieve($local['stripe_event_id'])->toArray();
        $assert($event['livemode'] === false && empty($event['account']) && $event['type'] === $local['type']
            && $event['data']['object']['id'] === $local['object_id'], 'webhook_identity_mismatch');
        $events[] = $event;
    }
    $assert(count(array_filter($events, fn ($event) => $event['type'] === 'checkout.session.completed')) === 1, 'checkout_webhook_missing');
    $replays = [];
    if ($mode === '--replay-contribution') {
        $assert(preg_match('/^whsec_[A-Za-z0-9]+$/D', $provider['stripe_webhook_secret'] ?? '') === 1, 'signing_secret_missing');
        // Reverse delivery order, then repeat it. Every payload is an unmodified actual sandbox event.
        foreach (array_merge(array_reverse($events), array_reverse($events)) as $event) {
            $payload = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $provider['stripe_webhook_secret']);
            $curl = curl_init('http://127.0.0.1:8015/webhooks/stripe');
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Stripe-Signature: t='.$timestamp.',v1='.$signature]]);
            curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            $assert($status === 200, 'signed_replay_rejected');
            $replays[] = ['event_id' => $event['id'], 'type' => $event['type'], 'status' => $status];
        }
    }
    $proof = ['verified' => true, 'livemode' => false, 'run_id' => $snapshot['run_id'], 'verification_sha256' => hash('sha256', $bytes),
        'purchase_public_id' => $purchase['public_id'], 'stripe_checkout_session_id' => $session['id'], 'stripe_payment_intent_id' => $intent['id'],
        'stripe_charge_id' => $charge['id'], 'amount_cents' => 31000, 'currency' => 'usd', 'processing_fee_cents' => $transaction['fee'],
        'signed_webhook_ids' => array_column($events, 'id'), 'signed_duplicate_reverse_order_replays' => $replays, 'all_owned_webhooks_processed' => true,
        'external_email_delivery' => 'deferred_by_user_captured_codes_used'];
    file_put_contents($proofFile, json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $exception) {
    $code = $exception instanceof LaunchValidationFailure ? $exception->getMessage() : 'provider_or_input_error';
    file_put_contents($proofFile, json_encode(['verified' => false, 'stage' => $stage, 'reason' => $code], JSON_PRETTY_PRINT));
    fwrite(STDERR, 'Launch validation failed at '.$stage.': '.$code."\n");
    exit(1);
}
