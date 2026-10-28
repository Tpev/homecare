<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--verify' || count($argv) !== 2) {
    exit(2);
}
final class MixedCaptureProofFailure extends RuntimeException {}
$assert = static function (bool $condition, string $code): void {
    if (! $condition) {
        throw new MixedCaptureProofFailure($code);
    }
};
$root = dirname(__DIR__, 2).'/tmp/care-hours-launch-validation';
$proofPath = $root.'/mixed-capture-provider-proof.json';
$stage = 'inputs';
try {
    $assert(is_dir($root) && ! is_link($root), 'isolated_root_required');
    foreach (['provider.json', 'mixed-capture-verification.json', 'mixed-capture-provider-proof.json'] as $file) {
        $assert(! is_link($root.'/'.$file), 'linked_file_refused');
    }
    file_put_contents($proofPath, json_encode(['verified' => false, 'reason' => 'in_progress']));
    $provider = json_decode(file_get_contents($root.'/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $bytes = file_get_contents($root.'/mixed-capture-verification.json');
    $state = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
    $assert($provider['payment_mode'] === 'stripe_test' && $state['payment_mode'] === 'stripe_test' && preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret']) === 1, 'sandbox_required');
    $assert($state['webhook_signature_required'] === true && count($state['checks']) >= 9, 'application_evidence_missing');
    foreach ($state['checks'] as $check) {
        $assert($check === true, 'application_check_failed');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    \Stripe\ApiRequestor::setHttpClient(new class implements \Stripe\HttpClient\ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $url = parse_url($absUrl);
            if (strtolower($method) !== 'get' || $hasFile || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'api.stripe.com'
                || isset($url['user']) || isset($url['pass']) || (isset($url['port']) && $url['port'] !== 443) || ! str_starts_with($url['path'] ?? '', '/v1/')) {
                throw new MixedCaptureProofFailure('provider_write_refused');
            }
            $transport = new \Stripe\HttpClient\CurlClient([CURLOPT_FOLLOWLOCATION => false]);
            $transport->setConnectTimeout(10);
            $transport->setTimeout(30);

            return $transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $fundingId = $state['funding_id'];
    $settlements = array_values(array_filter($state['settlements'], fn ($row) => $row['care_hour_booking_funding_id'] === $fundingId));
    $assert(count($settlements) === 1, 'one_settlement_required');
    $settlement = $settlements[0];
    $assert($settlement['status'] === 'paid' && $settlement['worked_minutes'] === 720 && $settlement['prepaid_minutes'] === 660 && $settlement['amount_due_cents'] === 0 && $settlement['caregiver_gross_cents'] === 32400, 'settlement_mismatch');
    $operations = array_values(array_filter($state['operations'], fn ($row) => $row['care_hour_booking_funding_id'] === $fundingId));
    $shortfalls = array_values(array_filter($operations, fn ($row) => $row['kind'] === 'shortfall'));
    $assert(count($shortfalls) === 1, 'one_shortfall_required');
    $shortfall = $shortfalls[0];
    $stage = 'captured_shortfall';
    $intent = $stripe->paymentIntents->retrieve($shortfall['stripe_payment_intent_id'])->toArray();
    $charge = $stripe->charges->retrieve($intent['latest_charge'])->toArray();
    $assert($intent['livemode'] === false && $intent['status'] === 'succeeded' && $intent['capture_method'] === 'manual' && $intent['amount_received'] === 3100 && $intent['amount_capturable'] === 0
        && $intent['metadata']['care_hour_operation_id'] === $shortfall['public_id'], 'capture_intent_mismatch');
    $assert($charge['livemode'] === false && $charge['captured'] === true && $charge['amount_captured'] === 3100 && $charge['amount_refunded'] === 0 && ! $charge['disputed'], 'capture_charge_mismatch');
    $cardTransaction = $stripe->balanceTransactions->retrieve($charge['balance_transaction'])->toArray();
    $package = $stripe->charges->retrieve($state['purchase']['stripe_charge_id'])->toArray();
    $packageTransaction = $stripe->balanceTransactions->retrieve($package['balance_transaction'])->toArray();
    $assert($cardTransaction['source'] === $charge['id'] && $cardTransaction['amount'] === 3100 && $cardTransaction['fee'] === $settlement['card_fee_cents'], 'card_fee_mismatch');
    $assert($package['livemode'] === false && $package['captured'] && $package['amount_refunded'] === 0 && $packageTransaction['source'] === $package['id']
        && $packageTransaction['amount'] === 31000 && $packageTransaction['fee'] === $settlement['package_fee_cents'], 'package_fee_mismatch');
    $expectedNet = 32400 - $cardTransaction['fee'] - $packageTransaction['fee'];
    $assert($settlement['caregiver_net_cents'] === $expectedNet && $settlement['paid_cents'] === $expectedNet, 'net_earnings_mismatch');
    $stage = 'source_transfers';
    $transfers = [];
    foreach (array_filter($operations, fn ($row) => $row['kind'] === 'transfer') as $operation) {
        $assert($operation['status'] === 'succeeded' && $operation['currency'] === 'usd', 'transfer_not_succeeded');
        $transfer = $stripe->transfers->retrieve($operation['stripe_transfer_id'])->toArray();
        $assert($transfer['livemode'] === false && $transfer['amount'] === $operation['amount_cents'] && $transfer['currency'] === 'usd' && $transfer['amount_reversed'] === 0
            && $transfer['destination'] === $provider['connect_account_id'] && $transfer['destination'] === $operation['destination_account_id']
            && $transfer['source_transaction'] === $operation['source_charge_id'] && in_array($transfer['source_transaction'], [$package['id'], $charge['id']], true), 'source_transfer_mismatch');
        $transfers[] = ['id' => $transfer['id'], 'source_charge_id' => $transfer['source_transaction'], 'amount_cents' => $transfer['amount']];
    }
    $assert(count($transfers) === 2 && count(array_unique(array_column($transfers, 'source_charge_id'))) === 2 && array_sum(array_column($transfers, 'amount_cents')) === $expectedNet, 'transfer_total_mismatch');
    $stage = 'signed_webhooks';
    $ownedIds = [$intent['id'], $charge['id'], $shortfall['stripe_checkout_session_id'], ...array_column($transfers, 'id')];
    $events = [];
    foreach ($state['webhook_events'] as $local) {
        if (! in_array($local['object_id'], $ownedIds, true)) {
            continue;
        }
        $assert($local['status'] === 'processed' && $local['livemode'] === false && ! $local['connected_account_id'], 'owned_webhook_not_processed');
        $event = $stripe->events->retrieve($local['stripe_event_id'])->toArray();
        $assert($event['livemode'] === false && empty($event['account']) && $event['type'] === $local['type'] && $event['data']['object']['id'] === $local['object_id'], 'webhook_identity_mismatch');
        $events[] = ['id' => $event['id'], 'type' => $event['type'], 'object_id' => $local['object_id']];
    }
    foreach (['checkout.session.completed', 'payment_intent.succeeded', 'charge.captured'] as $type) {
        $assert(count(array_filter($events, fn ($event) => $event['type'] === $type)) === 1, 'capture_webhook_missing');
    }
    foreach ($transfers as $transfer) {
        $assert(count(array_filter($events, fn ($event) => $event['type'] === 'transfer.created' && $event['object_id'] === $transfer['id'])) === 1, 'transfer_webhook_missing');
    }
    $proof = ['verified' => true, 'livemode' => false, 'run_id' => $state['run_id'], 'verification_sha256' => hash('sha256', $bytes), 'funding_id' => $fundingId,
        'payment_intent_id' => $intent['id'], 'charge_id' => $charge['id'], 'captured_cents' => 3100, 'used_prepaid_minutes' => 660, 'worked_minutes' => 720,
        'caregiver_gross_cents' => 32400, 'package_fee_cents' => $packageTransaction['fee'], 'card_fee_cents' => $cardTransaction['fee'], 'caregiver_net_cents' => $expectedNet,
        'transfers' => $transfers, 'signed_events' => $events, 'all_owned_webhooks_processed' => true];
    file_put_contents($proofPath, json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode($proof, JSON_PRETTY_PRINT)."\n";
} catch (Throwable $exception) {
    $reason = $exception instanceof MixedCaptureProofFailure ? $exception->getMessage() : 'provider_or_input_error';
    file_put_contents($proofPath, json_encode(['verified' => false, 'stage' => $stage, 'reason' => $reason]));
    fwrite(STDERR, 'Mixed capture proof failed at '.$stage.': '.$reason."\n");
    exit(1);
}
