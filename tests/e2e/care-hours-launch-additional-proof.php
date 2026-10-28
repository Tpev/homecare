<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || ! in_array($mode, ['--mixed', '--gift'], true)) {
    exit(2);
}
final class AdditionalProofFailure extends RuntimeException {}
$assert = static function (bool $condition, string $code): void {
    if (! $condition) {
        throw new AdditionalProofFailure($code);
    }
};
$root = dirname(__DIR__, 2).'/tmp/care-hours-launch-validation';
$name = $mode === '--mixed' ? 'mixed-cancellation' : 'gift-claim';
$proofPath = $root.'/'.$name.'-provider-proof.json';
$stage = 'inputs';
try {
    $assert(is_dir($root) && ! is_link($root), 'isolated_root_required');
    foreach (['provider.json', $name.'-verification.json', $name.'-provider-proof.json'] as $file) {
        $assert(! is_link($root.'/'.$file), 'linked_file_refused');
    }
    file_put_contents($proofPath, json_encode(['verified' => false, 'reason' => 'in_progress']));
    $provider = json_decode(file_get_contents($root.'/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $bytes = file_get_contents($root.'/'.$name.'-verification.json');
    $state = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
    $assert(($provider['payment_mode'] ?? '') === 'stripe_test' && ($state['payment_mode'] ?? '') === 'stripe_test'
        && preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '') === 1, 'sandbox_required');
    $assert(($state['webhook_signature_required'] ?? false) === true && count($state['checks'] ?? []) >= 7, 'application_proof_missing');
    foreach ($state['checks'] as $check) {
        $assert($check === true, 'application_check_failed');
    }
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
                throw new AdditionalProofFailure('provider_mutation_refused');
            }

            return $this->transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $proof = ['verified' => false, 'livemode' => false, 'run_id' => $state['run_id'], 'verification_sha256' => hash('sha256', $bytes)];
    if ($mode === '--mixed') {
        $stage = 'authorization_cancellation';
        $authorized = array_values(array_filter($state['authorization_snapshot']['operations'], fn ($op) => $op['kind'] === 'shortfall' && $op['status'] === 'authorized'));
        $assert(count($authorized) === 1, 'single_authorization_required');
        $operation = $authorized[0];
        $intent = $stripe->paymentIntents->retrieve($operation['stripe_payment_intent_id'])->toArray();
        $assert($intent['livemode'] === false && $intent['status'] === 'canceled' && $intent['amount'] === 3100
            && $intent['amount_capturable'] === 0 && $intent['amount_received'] === 0 && $intent['capture_method'] === 'manual'
            && $intent['metadata']['care_hour_domain'] === 'shortfall' && $intent['metadata']['care_hour_operation_id'] === $operation['public_id'], 'canceled_intent_mismatch');
        $charge = $stripe->charges->retrieve($intent['latest_charge'])->toArray();
        $assert($charge['livemode'] === false && $charge['captured'] === false && $charge['amount_captured'] === 0
            && $charge['payment_intent'] === $intent['id'] && ($charge['payment_method_details']['card']['three_d_secure']['result'] ?? '') === 'authenticated', 'three_ds_or_capture_mismatch');
        $ownedEvents = array_values(array_filter($state['webhook_events'], fn ($event) => in_array($event['object_id'], [$intent['id'], $charge['id'], $operation['stripe_checkout_session_id']], true)));
        $assert(count($ownedEvents) > 0, 'owned_webhook_evidence_missing');
        foreach ($ownedEvents as $local) {
            $assert($local['status'] === 'processed' && $local['livemode'] === false && ! $local['connected_account_id'], 'owned_webhook_not_processed');
        }
        $releaseEvents = array_values(array_filter($ownedEvents, fn ($event) => $event['type'] === 'charge.refunded' && $event['object_id'] === $charge['id']));
        $assert(count($releaseEvents) === 1, 'authorization_release_webhook_missing');
        $releaseEvent = $stripe->events->retrieve($releaseEvents[0]['stripe_event_id'])->toArray();
        $releaseCharge = $releaseEvent['data']['object'];
        $assert($releaseEvent['livemode'] === false && empty($releaseEvent['account']) && $releaseEvent['type'] === 'charge.refunded'
            && $releaseCharge['id'] === $charge['id'] && $releaseCharge['captured'] === false && $releaseCharge['amount_captured'] === 0,
            'authorization_release_event_mismatch');
        $stage = 'failure_and_cancellation_webhooks';
        $eventProof = [];
        $operationIds = array_column(array_filter($state['operations'], fn ($op) => $op['kind'] === 'shortfall'), 'public_id');
        foreach ($state['webhook_events'] as $local) {
            if ($local['status'] !== 'processed' || $local['livemode'] !== false || $local['connected_account_id'] || ! in_array($local['type'], ['payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
                continue;
            }
            $event = $stripe->events->retrieve($local['stripe_event_id'])->toArray();
            $object = $event['data']['object'];
            if (! in_array($object['metadata']['care_hour_operation_id'] ?? '', $operationIds, true)) {
                continue;
            }
            $assert($event['livemode'] === false && $object['livemode'] === false && empty($event['account']) && $object['id'] === $local['object_id'], 'webhook_mismatch');
            $eventProof[] = ['id' => $event['id'], 'type' => $event['type'], 'intent_id' => $object['id'], 'decline_code' => $object['last_payment_error']['decline_code'] ?? null];
        }
        $assert(count(array_filter($eventProof, fn ($event) => $event['decline_code'] === 'insufficient_funds')) > 0, 'real_decline_event_missing');
        $assert(count(array_filter($eventProof, fn ($event) => $event['type'] === 'payment_intent.canceled' && $event['intent_id'] === $intent['id'])) === 1, 'signed_cancellation_missing');
        $assert($state['balance']['available_minutes'] === 660 && $state['balance']['reserved_minutes'] === 0 && $state['balance']['used_minutes'] === 0, 'hours_not_restored');
        $proof += ['payment_intent_id' => $intent['id'], 'charge_id' => $charge['id'], 'authorized_cents' => 3100, 'captured_cents' => 0,
            'status' => 'canceled', 'three_ds_result' => 'authenticated', 'restored_minutes' => 660, 'signed_events' => $eventProof,
            'authorization_release_webhook_id' => $releaseEvent['id'], 'all_owned_webhooks_processed' => true];
    } else {
        $stage = 'gift_payment';
        $purchase = $state['gift_purchases'][0];
        $assert($purchase['status'] === 'issued' && $purchase['total_minutes'] === 660 && $purchase['bonus_minutes'] === 60
            && $purchase['care_hour_account_id'] !== null && $purchase['gift']['claimed_at'] !== null, 'gift_claim_mismatch');
        $unclaimedBytes = file_get_contents($root.'/gift-unclaimed-verification.json');
        $unclaimed = json_decode($unclaimedBytes, true, flags: JSON_THROW_ON_ERROR);
        $assert($unclaimed['run_id'] === $state['run_id'] && $unclaimed['gift_purchases'][0]['public_id'] === $purchase['public_id']
            && $unclaimed['gift_purchases'][0]['care_hour_account_id'] === null && $unclaimed['gift_purchases'][0]['status'] === 'paid', 'unclaimed_gift_evidence_missing');
        $session = $stripe->checkout->sessions->retrieve($purchase['stripe_checkout_session_id'])->toArray();
        $intent = $stripe->paymentIntents->retrieve($purchase['stripe_payment_intent_id'])->toArray();
        $charge = $stripe->charges->retrieve($purchase['stripe_charge_id'])->toArray();
        $assert($session['livemode'] === false && $session['payment_status'] === 'paid' && $session['amount_total'] === 31000 && $session['currency'] === 'usd'
            && $session['metadata']['care_hour_purchase_id'] === $purchase['public_id'] && $session['payment_intent'] === $intent['id'], 'gift_checkout_mismatch');
        $assert($intent['livemode'] === false && $intent['status'] === 'succeeded' && $intent['amount_received'] === 31000 && $intent['latest_charge'] === $charge['id'], 'gift_intent_mismatch');
        $assert($charge['livemode'] === false && $charge['paid'] === true && $charge['captured'] === true && $charge['amount_captured'] === 31000 && $charge['amount_refunded'] === 0, 'gift_charge_mismatch');
        foreach ($state['webhook_events'] as $local) {
            if (! in_array($local['object_id'], [$session['id'], $intent['id'], $charge['id']], true)) {
                continue;
            }
            $assert($local['status'] === 'processed' && $local['livemode'] === false && ! $local['connected_account_id'], 'owned_webhook_not_processed');
        }
        $transaction = $stripe->balanceTransactions->retrieve($charge['balance_transaction'])->toArray();
        $assert($transaction['fee'] === $purchase['processing_fee_cents'] && $transaction['amount'] === 31000 && $transaction['source'] === $charge['id'], 'gift_fee_mismatch');
        $eventIds = [];
        foreach ($state['webhook_events'] as $local) {
            if ($local['status'] !== 'processed' || $local['type'] !== 'checkout.session.completed' || $local['object_id'] !== $session['id'] || $local['livemode'] !== false || $local['connected_account_id']) {
                continue;
            }
            $event = $stripe->events->retrieve($local['stripe_event_id'])->toArray();
            $assert($event['livemode'] === false && empty($event['account']) && $event['data']['object']['id'] === $session['id'], 'gift_webhook_mismatch');
            $eventIds[] = $event['id'];
        }
        $assert(count($eventIds) === 1, 'signed_gift_webhook_missing');
        $proof += ['purchase_public_id' => $purchase['public_id'], 'stripe_charge_id' => $charge['id'], 'amount_cents' => 31000,
            'processing_fee_cents' => $transaction['fee'], 'care_hour_account_id' => $purchase['care_hour_account_id'], 'claimed_minutes' => 660,
            'bonus_minutes' => 60, 'unclaimed_sha256' => hash('sha256', $unclaimedBytes), 'signed_webhook_ids' => $eventIds, 'all_owned_webhooks_processed' => true];
    }
    $proof['verified'] = true;
    file_put_contents($proofPath, json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $exception) {
    $reason = $exception instanceof AdditionalProofFailure ? $exception->getMessage() : 'provider_or_input_error';
    file_put_contents($proofPath, json_encode(['verified' => false, 'stage' => $stage, 'reason' => $reason]));
    fwrite(STDERR, 'Additional provider proof failed at '.$stage.': '.$reason."\n");
    exit(1);
}
