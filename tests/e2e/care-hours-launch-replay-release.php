<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || ! in_array($mode, ['--inspect', '--replay'], true)) {
    exit(2);
}
$root = dirname(__DIR__, 2).'/tmp/care-hours-launch-validation';
try {
    if (is_link($root) || is_link($root.'/provider.json')) {
        throw new RuntimeException('Unsafe path');
    }
    $provider = json_decode(file_get_contents($root.'/provider.json'), true, flags: JSON_THROW_ON_ERROR);
    $state = json_decode(file_get_contents($root.'/mixed-cancellation-verification.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($provider['payment_mode'] !== 'stripe_test' || $state['payment_mode'] !== 'stripe_test'
        || ! preg_match('/^sk_test_[A-Za-z0-9]+$/D', $provider['stripe_secret'])) {
        throw new RuntimeException('Sandbox required');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    \Stripe\ApiRequestor::setHttpClient(new class implements \Stripe\HttpClient\ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            if (strtolower($method) !== 'get' || $hasFile || ! str_starts_with($absUrl, 'https://api.stripe.com/v1/')) {
                throw new RuntimeException('Provider writes prohibited');
            }
            $transport = new \Stripe\HttpClient\CurlClient([CURLOPT_FOLLOWLOCATION => false]);
            $transport->setConnectTimeout(10);
            $transport->setTimeout(30);

            return $transport->request($method, $absUrl, $headers, $params, $hasFile, $apiMode);
        }
    });
    $stripe = new \Stripe\StripeClient($provider['stripe_secret']);
    $operations = array_values(array_filter($state['authorization_snapshot']['operations'], fn ($op) => $op['kind'] === 'shortfall' && $op['status'] === 'authorized'));
    if (count($operations) !== 1) {
        throw new RuntimeException('One authorization required');
    }
    $operation = $operations[0];
    $intent = $stripe->paymentIntents->retrieve($operation['stripe_payment_intent_id'])->toArray();
    $charge = $stripe->charges->retrieve($intent['latest_charge'])->toArray();
    if ($intent['livemode'] || $charge['livemode'] || $intent['status'] !== 'canceled' || $charge['captured'] || $charge['amount_captured'] !== 0
        || $intent['metadata']['care_hour_operation_id'] !== $operation['public_id']) {
        throw new RuntimeException('Unexpected authorization');
    }
    $events = array_values(array_filter($state['webhook_events'], fn ($event) => $event['object_id'] === $charge['id'] && $event['type'] === 'charge.refunded'));
    if (count($events) !== 1) {
        throw new RuntimeException('One actual release event required');
    }
    $event = $stripe->events->retrieve($events[0]['stripe_event_id'])->toArray();
    if ($event['livemode'] || ! empty($event['account']) || $event['type'] !== 'charge.refunded' || $event['data']['object']['id'] !== $charge['id']) {
        throw new RuntimeException('Unexpected event');
    }
    $result = ['run_id' => $state['run_id'], 'event_id' => $event['id'], 'intent_id' => $intent['id'], 'charge_id' => $charge['id'],
        'intent_status' => $intent['status'], 'captured' => $charge['captured'], 'amount' => $charge['amount'], 'amount_captured' => $charge['amount_captured'], 'amount_refunded' => $charge['amount_refunded']];
    if ($mode === '--replay') {
        if (! preg_match('/^whsec_[A-Za-z0-9]+$/D', $provider['stripe_webhook_secret'])) {
            throw new RuntimeException('Signing required');
        }
        $result['replays'] = [];
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $payload = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $provider['stripe_webhook_secret']);
            $curl = curl_init('http://127.0.0.1:8015/webhooks/stripe');
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Stripe-Signature: t='.$timestamp.',v1='.$signature]]);
            curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if ($status !== 200) {
                throw new RuntimeException('Release replay rejected');
            }
            $result['replays'][] = ['event_id' => $event['id'], 'status' => $status];
        }
        file_put_contents($root.'/mixed-release-replay.json', json_encode($result, JSON_PRETTY_PRINT));
    }
    echo json_encode($result, JSON_PRETTY_PRINT)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Sandbox authorization release inspection/replay failed.\n");
    exit(1);
}
