<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || ($argv[1] ?? '') !== '--verify') {
    fwrite(STDERR, "Explicit --verify is required for read-only sandbox evidence.\n");
    exit(2);
}
final class GiftOnboardingProofFailure extends RuntimeException {}
$assert = static function (bool $condition, string $reason): void {
    if (! $condition) {
        throw new GiftOnboardingProofFailure($reason);
    }
};
$root = dirname(__DIR__, 2).'/tmp/care-hours-gift-onboarding';
$proofPath = $root.'/provider-proof.json';
$stage = 'inputs';
try {
    $assert(is_dir($root) && ! is_link($root), 'isolated_root_required');
    foreach (['provider.json', 'verification.json', 'unclaimed-verification.json', 'provider-proof.json'] as $file) {
        $assert(! is_link($root.'/'.$file), 'linked_input_refused');
    }
    file_put_contents($proofPath, json_encode(['verified' => false, 'reason' => 'verification_in_progress']));
    $provider = json_decode(file_get_contents($root.'/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $bytes = file_get_contents($root.'/verification.json');
    $state = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
    $unclaimedBytes = file_get_contents($root.'/unclaimed-verification.json');
    $unclaimed = json_decode($unclaimedBytes, true, flags: JSON_THROW_ON_ERROR);
    $assert(($provider['payment_mode'] ?? '') === 'stripe_test' && ($state['payment_mode'] ?? '') === 'stripe_test'
        && preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'] ?? '') === 1, 'sandbox_required');
    $assert(($state['webhook_signature_required'] ?? false) === true && count($state['checks'] ?? []) >= 12, 'application_checks_missing');
    foreach ($state['checks'] as $check) {
        $assert($check === true, 'application_check_failed');
    }
    $assert(count($state['purchases']) === 1 && count($unclaimed['purchases']) === 1, 'single_gift_required');
    $purchase = $state['purchases'][0];
    $assert($purchase['status'] === 'issued' && $purchase['paid_minutes'] === 600 && $purchase['bonus_minutes'] === 60 && $purchase['total_minutes'] === 660
        && $purchase['amount_cents'] === 31000 && $purchase['currency'] === 'usd' && $purchase['livemode'] === false && $purchase['buyer_user_id'] === null, 'issued_gift_mismatch');
    $assert($unclaimed['run_id'] === $state['run_id'] && $unclaimed['purchases'][0]['public_id'] === $purchase['public_id']
        && $unclaimed['purchases'][0]['status'] === 'paid' && $unclaimed['purchases'][0]['care_hour_account_id'] === null
        && $unclaimed['purchases'][0]['gift']['claimed_at'] === null && $unclaimed['users'] === [] && $unclaimed['profiles'] === []
        && $unclaimed['accounts'] === [] && $unclaimed['lots'] === [], 'recipient_did_not_start_empty');
    $assert(count($state['users']) === 1 && count($state['profiles']) === 1 && count($state['accounts']) === 1, 'recipient_identity_mismatch');
    $user = $state['users'][0];
    $profile = $state['profiles'][0];
    $account = $state['accounts'][0];
    $assert($user['name'] === 'Linda Bennett' && $user['role'] === 'family' && $user['stripe_default_payment_method_id'] === null
        && $profile['full_name'] === $user['name'] && $profile['status'] === 'draft'
        && $account['care_recipient_profile_id'] === $profile['id'] && $account['family_account_id'] === $profile['family_account_id']
        && $purchase['care_hour_account_id'] === $account['id'] && $purchase['gift']['claimed_by_user_id'] === $user['id']
        && $purchase['gift']['claimed_at'] !== null, 'self_claim_binding_mismatch');
    $assert($account['balance'] === ['available_minutes' => 660, 'reserved_minutes' => 0, 'used_minutes' => 0, 'held_minutes' => 0], 'hours_mismatch');
    $assert($state['care_request_count'] === 0 && $state['request_recipient_count'] === 0 && $state['booking_count'] === 0
        && $state['ordinary_card_payment_count'] === 0, 'unintended_booking_or_payment');
    foreach ($state['families'] as $family) {
        $assert($family['stripe_customer_id'] === null && $family['payment_method_summary'] === null, 'saved_card_changed');
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
                throw new GiftOnboardingProofFailure('provider_mutation_refused');
            }

            return $this->transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $stage = 'sandbox_account';
    $platform = $stripe->accounts->retrieve()->toArray();
    $assert($platform['id'] === 'acct_1UStLcKBu0xzVrza', 'wrong_sandbox');
    $stage = 'gift_payment';
    $session = $stripe->checkout->sessions->retrieve($purchase['stripe_checkout_session_id'])->toArray();
    $intent = $stripe->paymentIntents->retrieve($purchase['stripe_payment_intent_id'])->toArray();
    $charge = $stripe->charges->retrieve($purchase['stripe_charge_id'])->toArray();
    $assert($session['livemode'] === false && $session['status'] === 'complete' && $session['payment_status'] === 'paid'
        && $session['amount_total'] === 31000 && $session['currency'] === 'usd' && $session['client_reference_id'] === $purchase['public_id']
        && $session['metadata']['care_hour_purchase_id'] === $purchase['public_id'] && $session['payment_intent'] === $intent['id'], 'checkout_mismatch');
    $assert($intent['livemode'] === false && $intent['status'] === 'succeeded' && $intent['amount_received'] === 31000
        && $intent['currency'] === 'usd' && $intent['latest_charge'] === $charge['id'] && $intent['metadata']['care_hour_purchase_id'] === $purchase['public_id'], 'intent_mismatch');
    $assert($charge['livemode'] === false && $charge['paid'] === true && $charge['captured'] === true && $charge['amount_captured'] === 31000
        && $charge['amount_refunded'] === 0 && $charge['disputed'] === false && $charge['payment_intent'] === $intent['id'], 'charge_mismatch');
    $transaction = $stripe->balanceTransactions->retrieve($charge['balance_transaction'])->toArray();
    $assert($transaction['amount'] === 31000 && $transaction['currency'] === 'usd' && $transaction['source'] === $charge['id']
        && $transaction['fee'] === $purchase['processing_fee_cents'], 'actual_fee_mismatch');
    $stage = 'signed_webhooks';
    $events = [];
    foreach ($state['webhook_events'] as $local) {
        if (! in_array($local['object_id'], [$session['id'], $intent['id'], $charge['id']], true)) {
            continue;
        }
        $assert($local['status'] === 'processed' && $local['livemode'] === false && ! $local['connected_account_id'] && $local['processed_at'] !== null, 'owned_webhook_not_processed');
        $event = $stripe->events->retrieve($local['stripe_event_id'])->toArray();
        $assert($event['livemode'] === false && empty($event['account']) && $event['type'] === $local['type']
            && $event['data']['object']['id'] === $local['object_id'], 'event_identity_mismatch');
        $events[] = ['event_id' => $event['id'], 'type' => $event['type'], 'object_id' => $local['object_id']];
    }
    $assert(count(array_filter($events, fn ($event) => $event['type'] === 'checkout.session.completed')) === 1, 'signed_checkout_missing');
    $proof = ['verified' => true, 'livemode' => false, 'run_id' => $state['run_id'], 'sandbox_account_id' => $platform['id'],
        'verification_sha256' => hash('sha256', $bytes), 'unclaimed_sha256' => hash('sha256', $unclaimedBytes),
        'purchase_public_id' => $purchase['public_id'], 'stripe_checkout_session_id' => $session['id'], 'stripe_payment_intent_id' => $intent['id'],
        'stripe_charge_id' => $charge['id'], 'amount_cents' => 31000, 'processing_fee_cents' => $transaction['fee'], 'currency' => 'usd',
        'paid_minutes' => 600, 'bonus_minutes' => 60, 'available_minutes' => 660, 'care_hour_account_id' => $account['id'],
        'recipient_created_after_purchase' => true, 'signed_webhooks_processed' => true, 'all_owned_webhooks_processed' => true,
        'signed_events' => $events, 'external_email_delivery' => 'deferred_by_user_captured_codes_used'];
    file_put_contents($proofPath, json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $exception) {
    $reason = $exception instanceof GiftOnboardingProofFailure ? $exception->getMessage() : 'provider_or_input_error';
    file_put_contents($proofPath, json_encode(['verified' => false, 'stage' => $stage, 'reason' => $reason], JSON_PRETTY_PRINT));
    fwrite(STDERR, 'Gift-onboarding verification failed at '.$stage.': '.$reason."\n");
    exit(1);
}
