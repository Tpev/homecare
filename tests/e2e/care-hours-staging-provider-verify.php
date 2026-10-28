<?php

declare(strict_types=1);

// Run only after the authorized Stripe sandbox journey has produced verification.json:
// php tests/e2e/care-hours-staging-provider-verify.php --verify
// This script never boots Laravel, loads .env, opens a database, or writes to Stripe.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--verify' || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php tests/e2e/care-hours-staging-provider-verify.php --verify\n");
    exit(2);
}

final class CareHoursProviderVerificationFailure extends RuntimeException {}

$assert = static function (bool $condition, string $code): void {
    if (! $condition) {
        throw new CareHoursProviderVerificationFailure($code);
    }
};
$normalizePath = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
$objectId = static fn (mixed $value): string => is_array($value) ? (string) ($value['id'] ?? '') : (is_string($value) ? $value : '');
$realId = static function (mixed $value, string $prefix) use ($assert): string {
    $assert(is_string($value) && preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9]+$/D', $value) === 1, 'invalid_provider_object_id');
    $assert(! preg_match('/bypass|simulat|fixture|mock|fake/i', $value), 'simulated_provider_object_id');

    return $value;
};
$project = dirname(__DIR__, 2);
$root = $project.'/tmp/care-hours-staging';
$proofPath = $root.'/provider-verification.json';
$proofWritable = false;
$stage = 'local_inputs';
$writeProof = static function (array $proof) use ($proofPath): void {
    $contents = json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($proofPath, $contents, LOCK_EX) === false) {
        throw new CareHoursProviderVerificationFailure('proof_write_failed');
    }
};

try {
    $assert(is_dir($root) && $normalizePath((string) realpath($root)) === $normalizePath($root), 'staging_directory_not_isolated');
    foreach (['provider.json', 'verification.json', 'provider-verification.json'] as $name) {
        $path = $root.'/'.$name;
        $assert(! is_link($path), 'staging_file_link_refused');
        if (file_exists($path)) {
            $assert(is_file($path) && $normalizePath((string) realpath($path)) === $normalizePath($path), 'staging_file_not_isolated');
        }
    }
    $proofWritable = true;
    // Invalidate the preceding result before any input validation or provider read can fail.
    $writeProof(['verified' => false, 'reason' => 'verification_in_progress']);
    $assert(is_file($root.'/provider.json') && is_file($root.'/verification.json'), 'staging_inputs_missing');
    $providerBytes = file_get_contents($root.'/provider.json');
    $verificationBytes = file_get_contents($root.'/verification.json');
    $assert(is_string($providerBytes) && is_string($verificationBytes), 'staging_inputs_unreadable');
    $provider = json_decode($providerBytes, true, 512, JSON_THROW_ON_ERROR);
    $verification = json_decode($verificationBytes, true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($provider) && is_array($verification), 'staging_inputs_invalid');
    $assert(($provider['payment_mode'] ?? '') === 'stripe_test' && ($verification['payment_mode'] ?? '') === 'stripe_test', 'real_test_mode_required');
    $secret = $provider['stripe_secret'] ?? '';
    $assert(is_string($secret) && preg_match('/^(sk|rk|rkcs)_test_[A-Za-z0-9]+$/D', $secret) === 1, 'test_credentials_required');
    $destination = $realId($provider['connect_account_id'] ?? null, 'acct_');
    $assert(is_string($verification['run_id'] ?? null) && $verification['run_id'] !== '', 'run_binding_missing');
    $checks = $verification['checks'] ?? null;
    $assert(is_array($checks) && count($checks) >= 7 && ! in_array(false, $checks, true), 'local_verification_incomplete');
    foreach ($checks as $check) {
        $assert($check === true, 'local_verification_not_successful');
    }
    $purchase = $verification['purchase'] ?? [];
    $booking = $verification['booking'] ?? [];
    $settlement = $verification['settlement'] ?? [];
    $purchaseId = $purchase['public_id'] ?? '';
    $assert(is_string($purchaseId) && preg_match('/^[a-f0-9-]{36}$/iD', $purchaseId) === 1, 'purchase_binding_missing');
    $assert(($purchase['status'] ?? '') === 'issued' && ($purchase['livemode'] ?? null) === false
        && ($purchase['amount_cents'] ?? null) === 31000 && ($purchase['paid_minutes'] ?? null) === 600
        && ($purchase['bonus_minutes'] ?? null) === 60 && ($purchase['total_minutes'] ?? null) === 660, 'unexpected_package');
    $assert(($booking['worked_minutes'] ?? null) === 120 && ! empty($booking['family_confirmed_at'])
        && ($settlement['status'] ?? '') === 'paid' && ($settlement['worked_minutes'] ?? null) === 120
        && ($settlement['prepaid_minutes'] ?? null) === 120 && ($settlement['amount_due_cents'] ?? null) === 0
        && ($settlement['caregiver_gross_cents'] ?? null) === 5400, 'unexpected_visit_settlement');
    $assert(($verification['ordinary_card_booking_payment_count'] ?? null) === 0, 'ordinary_card_payment_present');
    $assert(($verification['balance']['available_minutes'] ?? null) === 540
        && ($verification['balance']['reserved_minutes'] ?? null) === 0
        && ($verification['balance']['used_minutes'] ?? null) === 120, 'unexpected_minutes_balance');
    $sessionId = $realId($purchase['stripe_checkout_session_id'] ?? null, 'cs_test_');
    $intentId = $realId($purchase['stripe_payment_intent_id'] ?? null, 'pi_');
    $chargeId = $realId($purchase['stripe_charge_id'] ?? null, 'ch_');
    $operations = $verification['operations'] ?? null;
    $assert(is_array($operations), 'transfer_operations_missing');
    $transfers = array_values(array_filter($operations, static fn ($operation) => ($operation['kind'] ?? '') === 'transfer'));
    $assert(count($transfers) > 0, 'successful_transfer_missing');
    $localTransferIds = [];
    foreach ($transfers as $transfer) {
        $assert(($transfer['status'] ?? '') === 'succeeded' && is_int($transfer['amount_cents'] ?? null)
            && $transfer['amount_cents'] > 0 && ($transfer['source_charge_id'] ?? '') === $chargeId
            && ($transfer['destination_account_id'] ?? '') === $destination && ($transfer['currency'] ?? '') === 'usd', 'local_transfer_not_final');
        $assert(is_string($transfer['public_id'] ?? null) && $transfer['public_id'] !== '', 'operation_binding_missing');
        $localTransferIds[] = $realId($transfer['stripe_transfer_id'] ?? null, 'tr_');
    }
    $assert(count(array_unique($localTransferIds)) === count($localTransferIds), 'duplicate_local_transfer');
    // Inbox rows are created by the existing controller only after signature verification.
    // Require a non-bypass runtime and corroborate their event identities with Stripe below.
    $assert(($verification['webhook_signature_required'] ?? null) === true, 'signed_webhook_runtime_required');
    $webhookEvents = $verification['webhook_events'] ?? null;
    $assert(is_array($webhookEvents), 'processed_webhook_evidence_missing');
    $processedEvents = array_values(array_filter($webhookEvents, static fn ($event) => is_array($event)
        && ($event['status'] ?? '') === 'processed' && ($event['livemode'] ?? null) === false
        && empty($event['connected_account_id']) && is_int($event['attempts'] ?? null) && $event['attempts'] >= 1
        && is_string($event['processed_at'] ?? null) && $event['processed_at'] !== ''));
    $purchaseEvents = array_values(array_filter($processedEvents, static fn ($event) => (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($event['object_id'] ?? '') === $sessionId)
        || (($event['type'] ?? '') === 'payment_intent.succeeded' && ($event['object_id'] ?? '') === $intentId)));
    $assert(count($purchaseEvents) > 0, 'processed_purchase_webhook_missing');
    $selectedEvents = [['local' => $purchaseEvents[0], 'purpose' => 'purchase', 'operation' => null]];
    foreach ($transfers as $transfer) {
        $transferEvents = array_values(array_filter($processedEvents, static fn ($event) => in_array($event['type'] ?? '', ['transfer.created', 'transfer.paid'], true)
            && ($event['object_id'] ?? '') === $transfer['stripe_transfer_id']));
        $assert(count($transferEvents) > 0, 'processed_transfer_webhook_missing');
        $selectedEvents[] = ['local' => $transferEvents[0], 'purpose' => 'transfer', 'operation' => $transfer];
    }
    foreach ($selectedEvents as $selected) {
        $realId($selected['local']['stripe_event_id'] ?? null, 'evt_');
    }
    $verificationHash = hash('sha256', $verificationBytes);
    $providerHash = hash('sha256', $providerBytes);

    require $project.'/vendor/autoload.php';
    // Enforce read-only transport even if a future edit accidentally invokes an SDK mutation.
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
            if (strtolower($method) !== 'get' || $hasFile || ($url['scheme'] ?? '') !== 'https'
                || ($url['host'] ?? '') !== 'api.stripe.com' || isset($url['user']) || isset($url['pass'])
                || (isset($url['port']) && $url['port'] !== 443) || ! str_starts_with($url['path'] ?? '', '/v1/')) {
                throw new CareHoursProviderVerificationFailure('non_readonly_provider_request_refused');
            }

            return $this->transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($secret);

    $stage = 'checkout';
    $session = $stripe->checkout->sessions->retrieve($sessionId, [])->toArray();
    $assert(($session['id'] ?? '') === $sessionId && ($session['livemode'] ?? null) === false
        && ($session['mode'] ?? '') === 'payment' && ($session['status'] ?? '') === 'complete'
        && ($session['payment_status'] ?? '') === 'paid' && ($session['amount_total'] ?? null) === 31000
        && ($session['currency'] ?? '') === 'usd' && $objectId($session['payment_intent'] ?? null) === $intentId
        && ($session['client_reference_id'] ?? '') === $purchaseId
        && ($session['metadata']['care_hour_domain'] ?? '') === 'purchase'
        && ($session['metadata']['care_hour_purchase_id'] ?? '') === $purchaseId, 'checkout_mismatch');

    $stage = 'payment_intent';
    $intent = $stripe->paymentIntents->retrieve($intentId, [])->toArray();
    $assert(($intent['id'] ?? '') === $intentId && ($intent['livemode'] ?? null) === false
        && ($intent['status'] ?? '') === 'succeeded' && ($intent['amount'] ?? null) === 31000
        && ($intent['amount_received'] ?? null) === 31000 && ($intent['currency'] ?? '') === 'usd'
        && $objectId($intent['latest_charge'] ?? null) === $chargeId
        && ($intent['metadata']['care_hour_domain'] ?? '') === 'purchase'
        && ($intent['metadata']['care_hour_purchase_id'] ?? '') === $purchaseId, 'payment_intent_mismatch');

    $stage = 'charge';
    $charge = $stripe->charges->retrieve($chargeId, [])->toArray();
    $assert(($charge['id'] ?? '') === $chargeId && ($charge['livemode'] ?? null) === false
        && ($charge['paid'] ?? null) === true && ($charge['captured'] ?? null) === true
        && ($charge['status'] ?? '') === 'succeeded' && ($charge['amount'] ?? null) === 31000
        && ($charge['amount_captured'] ?? null) === 31000 && ($charge['currency'] ?? '') === 'usd'
        && ($charge['amount_refunded'] ?? null) === 0 && ($charge['refunded'] ?? null) === false
        && ($charge['disputed'] ?? null) === false && $objectId($charge['payment_intent'] ?? null) === $intentId
        && ($charge['metadata']['care_hour_domain'] ?? '') === 'purchase'
        && ($charge['metadata']['care_hour_purchase_id'] ?? '') === $purchaseId, 'charge_mismatch');

    $stage = 'balance_transaction';
    $balanceTransactionId = $realId($objectId($charge['balance_transaction'] ?? null), 'txn_');
    $balanceTransaction = $stripe->balanceTransactions->retrieve($balanceTransactionId, [])->toArray();
    $fee = $balanceTransaction['fee'] ?? null;
    $assert(($balanceTransaction['id'] ?? '') === $balanceTransactionId
        && $objectId($balanceTransaction['source'] ?? null) === $chargeId
        && ($balanceTransaction['amount'] ?? null) === 31000 && ($balanceTransaction['currency'] ?? '') === 'usd'
        && is_int($fee) && $fee >= 0 && ($balanceTransaction['net'] ?? null) === 31000 - $fee
        && ($purchase['processing_fee_cents'] ?? null) === $fee, 'balance_transaction_fee_mismatch');
    $allocatedFee = intdiv($fee * 120, 660);
    $expectedNet = 5400 - $allocatedFee;
    $assert(($settlement['package_fee_cents'] ?? null) === $allocatedFee
        && ($settlement['caregiver_net_cents'] ?? null) === $expectedNet
        && ($settlement['paid_cents'] ?? null) === $expectedNet && $expectedNet > 0, 'settlement_fee_allocation_mismatch');

    $stage = 'connected_recipient';
    $account = $stripe->accounts->retrieve($destination, [])->toArray();
    $assert(($account['id'] ?? '') === $destination && ! ($account['deleted'] ?? false), 'connected_recipient_mismatch');
    $assert(($account['charges_enabled'] ?? false) === true && ($account['payouts_enabled'] ?? false) === true
        && ($account['details_submitted'] ?? false) === true
        && ($account['capabilities']['card_payments'] ?? '') === 'active'
        && ($account['capabilities']['transfers'] ?? '') === 'active', 'connected_recipient_not_ready');

    $stage = 'transfers';
    $transferEvidence = [];
    $transferredCents = 0;
    foreach ($transfers as $operation) {
        $transferId = $operation['stripe_transfer_id'];
        $transfer = $stripe->transfers->retrieve($transferId, [])->toArray();
        $assert(($transfer['id'] ?? '') === $transferId && ($transfer['livemode'] ?? null) === false
            && ($transfer['amount'] ?? null) === $operation['amount_cents'] && ($transfer['currency'] ?? '') === 'usd'
            && $objectId($transfer['destination'] ?? null) === $destination
            && $objectId($transfer['source_transaction'] ?? null) === $operation['source_charge_id']
            && ($transfer['amount_reversed'] ?? null) === 0 && ($transfer['reversed'] ?? null) === false
            && ($transfer['metadata']['care_hour_domain'] ?? '') === 'transfer'
            && ($transfer['metadata']['care_hour_operation_id'] ?? '') === $operation['public_id']
            && (string) ($transfer['metadata']['care_booking_id'] ?? '') === (string) ($booking['id'] ?? ''), 'transfer_mismatch');
        $transferredCents += $transfer['amount'];
        $transferEvidence[] = ['id' => $transferId, 'operation_public_id' => $operation['public_id'],
            'amount_cents' => $transfer['amount'], 'currency' => 'usd', 'destination' => $destination,
            'source_charge_id' => $operation['source_charge_id'], 'amount_reversed_cents' => 0, 'livemode' => false];
    }
    $assert($transferredCents === $expectedNet && $transferredCents <= 31000, 'transfer_total_mismatch');

    $stage = 'processed_signed_webhooks';
    $webhookEvidence = [];
    foreach ($selectedEvents as $selected) {
        $local = $selected['local'];
        $event = $stripe->events->retrieve($local['stripe_event_id'], [])->toArray();
        $eventObject = $event['data']['object'] ?? [];
        $assert(($event['id'] ?? '') === $local['stripe_event_id'] && ($event['type'] ?? '') === $local['type']
            && ($event['livemode'] ?? null) === false && empty($event['account'])
            && ($eventObject['id'] ?? '') === $local['object_id'] && ($eventObject['livemode'] ?? null) === false, 'processed_webhook_identity_mismatch');
        if ($selected['purpose'] === 'purchase') {
            $assert(($eventObject['metadata']['care_hour_domain'] ?? '') === 'purchase'
                && ($eventObject['metadata']['care_hour_purchase_id'] ?? '') === $purchaseId
                && ($eventObject['currency'] ?? '') === 'usd', 'purchase_webhook_metadata_mismatch');
            if ($local['type'] === 'payment_intent.succeeded') {
                $assert(($eventObject['status'] ?? '') === 'succeeded' && ($eventObject['amount'] ?? null) === 31000
                    && ($eventObject['amount_received'] ?? null) === 31000
                    && $objectId($eventObject['latest_charge'] ?? null) === $chargeId, 'purchase_webhook_payment_mismatch');
            } else {
                $assert(($eventObject['mode'] ?? '') === 'payment' && ($eventObject['payment_status'] ?? '') === 'paid'
                    && ($eventObject['amount_total'] ?? null) === 31000
                    && $objectId($eventObject['payment_intent'] ?? null) === $intentId, 'purchase_webhook_checkout_mismatch');
            }
        } else {
            $operation = $selected['operation'];
            $assert(($eventObject['metadata']['care_hour_domain'] ?? '') === 'transfer'
                && ($eventObject['metadata']['care_hour_operation_id'] ?? '') === $operation['public_id']
                && (string) ($eventObject['metadata']['care_booking_id'] ?? '') === (string) $booking['id']
                && ($eventObject['amount'] ?? null) === $operation['amount_cents'] && ($eventObject['currency'] ?? '') === $operation['currency']
                && $objectId($eventObject['destination'] ?? null) === $operation['destination_account_id']
                && $objectId($eventObject['source_transaction'] ?? null) === $operation['source_charge_id']
                && ($eventObject['amount_reversed'] ?? null) === 0, 'transfer_webhook_payment_mismatch');
        }
        $webhookEvidence[] = ['id' => $local['stripe_event_id'], 'type' => $local['type'],
            'object_id' => $local['object_id'], 'livemode' => false, 'processed_at' => $local['processed_at']];
    }

    $stage = 'proof_binding';
    $assert(hash_file('sha256', $root.'/verification.json') === $verificationHash
        && hash_file('sha256', $root.'/provider.json') === $providerHash, 'staging_inputs_changed_during_verification');
    $writeProof([
        'verified' => true, 'livemode' => false, 'verified_at' => gmdate('c'),
        'run_id' => $verification['run_id'], 'verification_sha256' => $verificationHash,
        'purchase_public_id' => $purchaseId, 'stripe_charge_id' => $chargeId, 'transfer_ids' => $localTransferIds,
        'signed_webhooks_processed' => true, 'webhook_event_ids' => array_column($webhookEvidence, 'id'),
        'webhook_events' => $webhookEvidence,
        'stripe_checkout_session_id' => $sessionId, 'stripe_payment_intent_id' => $intentId,
        'connected_account_id' => $destination, 'balance_transaction_id' => $balanceTransactionId,
        'purchase_amount_cents' => 31000, 'currency' => 'usd', 'purchase_processing_fee_cents' => $fee,
        'allocated_processing_fee_cents' => $allocatedFee, 'caregiver_gross_cents' => 5400,
        'caregiver_net_cents' => $expectedNet, 'transferred_cents' => $transferredCents, 'transfers' => $transferEvidence,
        'scope' => 'Read-only Stripe test Checkout, payment, charge, fee, recipient, Connect transfer and processed signed webhook verification. Bank payout is not asserted.',
    ]);
    fwrite(STDOUT, "Stripe test-provider proof verified and saved for the current staging snapshot.\n");
} catch (Throwable $error) {
    // Provider exception messages can contain key fragments; never print or persist them.
    $code = $error instanceof CareHoursProviderVerificationFailure ? $error->getMessage() : 'verification_read_failed';
    if ($proofWritable) {
        try {
            $writeProof(['verified' => false, 'stage' => $stage, 'reason' => $code, 'checked_at' => gmdate('c')]);
        } catch (Throwable) {
            // The preceding in-progress marker remains unverified if final output cannot be written.
        }
    }
    fwrite(STDERR, 'Stripe test-provider verification failed at '.$stage.' ('.$code."). No verified proof was retained.\n");
    exit(1);
}
