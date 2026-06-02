<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient as StripeSdk;
use Stripe\Webhook;
use Throwable;

class DiagnoseStripeConfig extends Command
{
    protected $signature = 'stripe:diagnose
        {--live : Require live-mode Stripe keys and account}
        {--expected-url= : Public webhook endpoint URL. Defaults to the app webhook route}
        {--skip-api : Skip Stripe API calls and dashboard webhook checks}
        {--require-connected-endpoint : Fail unless a connected-account webhook endpoint exists for account.updated}';

    protected $description = 'Validate Stripe production configuration without creating charges or customers.';

    private const PLATFORM_EVENTS = [
        'payment_intent.amount_capturable_updated',
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'payment_intent.requires_action',
        'checkout.session.completed',
        'transfer.created',
        'transfer.paid',
        'transfer.reversed',
        'transfer.reversal.created',
        'charge.refunded',
    ];

    private const CONNECT_EVENTS = [
        'account.updated',
    ];

    /**
     * @var list<array{check:string,result:string,details:string}>
     */
    private array $rows = [];

    /**
     * @var list<string>
     */
    private array $failures = [];

    public function handle(): int
    {
        $publishableKey = trim((string) config('services.stripe.publishable_key', ''));
        $secretKey = trim((string) config('services.stripe.secret', ''));
        $webhookSecrets = $this->webhookSecrets();
        $currency = strtolower(trim((string) config('services.stripe.currency', 'usd')));
        $bypass = (bool) config('services.stripe.bypass', false);
        $requireLive = (bool) $this->option('live');
        $skipApi = (bool) $this->option('skip-api');
        $expectedUrl = $this->expectedWebhookUrl();

        $this->line('Stripe diagnostic checks are read-only. No customer, payment, transfer, or refund is created.');
        $this->newLine();

        $this->checkBypass($bypass, $requireLive);
        $publishableMode = $this->checkPublishableKey($publishableKey, $requireLive);
        $secretMode = $this->checkSecretKey($secretKey, $requireLive);
        $this->checkKeyModeMatch($publishableMode, $secretMode);
        $this->checkWebhookSecrets($webhookSecrets);
        $this->checkCurrency($currency);
        $this->checkWebhookRoute($expectedUrl, $requireLive);
        $this->checkWebhookSignatureLocally($webhookSecrets);

        if (! $skipApi && $secretKey !== '') {
            $client = new StripeSdk($secretKey);
            $this->checkStripeAccount($client, $secretMode, $requireLive);
            $this->checkStripeBalance($client);
            $this->checkWebhookEndpoints($client, $expectedUrl, count($webhookSecrets));
            $this->checkConnectAccess($client);
        } elseif ($skipApi) {
            $this->warnRow('Stripe API', 'Skipped by --skip-api.');
        }

        $this->table(['Check', 'Result', 'Details'], $this->rows);

        if ($this->failures !== []) {
            $this->newLine();
            $this->error('Stripe diagnostic failed:');
            foreach ($this->failures as $failure) {
                $this->line('- '.$failure);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Stripe diagnostic passed.');

        return self::SUCCESS;
    }

    private function checkBypass(bool $bypass, bool $requireLive): void
    {
        if ($bypass && $requireLive) {
            $this->failRow('STRIPE_BYPASS', 'STRIPE_BYPASS must be false for live production.');
            return;
        }

        $this->passRow('STRIPE_BYPASS', $bypass ? 'Enabled' : 'Disabled');
    }

    private function checkPublishableKey(string $key, bool $requireLive): ?string
    {
        if ($key === '') {
            $this->failRow('STRIPE_KEY', 'Missing publishable key.');
            return null;
        }

        $mode = $this->publishableKeyMode($key);
        if ($mode === null) {
            $this->failRow('STRIPE_KEY', 'Expected a key beginning with pk_test_ or pk_live_.');
            return null;
        }

        if ($requireLive && $mode !== 'live') {
            $this->failRow('STRIPE_KEY', 'Expected pk_live_... but found '.$this->mask($key).'.');
            return $mode;
        }

        $this->passRow('STRIPE_KEY', strtoupper($mode).' publishable key configured: '.$this->mask($key));

        return $mode;
    }

    private function checkSecretKey(string $key, bool $requireLive): ?string
    {
        if ($key === '') {
            $this->failRow('STRIPE_SECRET', 'Missing secret key.');
            return null;
        }

        $mode = $this->secretKeyMode($key);
        if ($mode === null) {
            $this->failRow('STRIPE_SECRET', 'Expected a key beginning with sk_test_, sk_live_, rk_test_, or rk_live_.');
            return null;
        }

        if ($requireLive && $mode !== 'live') {
            $this->failRow('STRIPE_SECRET', 'Expected live secret key but found '.$this->mask($key).'.');
            return $mode;
        }

        $this->passRow('STRIPE_SECRET', strtoupper($mode).' secret key configured: '.$this->mask($key));

        return $mode;
    }

    private function checkKeyModeMatch(?string $publishableMode, ?string $secretMode): void
    {
        if (! $publishableMode || ! $secretMode) {
            return;
        }

        if ($publishableMode !== $secretMode) {
            $this->failRow('Stripe key mode', 'Publishable key is '.$publishableMode.' but secret key is '.$secretMode.'.');
            return;
        }

        $this->passRow('Stripe key mode', 'Publishable and secret keys are both '.$publishableMode.'.');
    }

    /**
     * @param list<string> $secrets
     */
    private function checkWebhookSecrets(array $secrets): void
    {
        if ($secrets === []) {
            $this->failRow('STRIPE_WEBHOOK_SECRET', 'Missing webhook signing secret.');
            return;
        }

        foreach ($secrets as $secret) {
            if (! str_starts_with($secret, 'whsec_')) {
                $this->failRow('STRIPE_WEBHOOK_SECRET', 'Expected each signing secret to begin with whsec_. Found '.$this->mask($secret).'.');
                return;
            }
        }

        $details = count($secrets) === 1
            ? 'One webhook signing secret configured: '.$this->mask($secrets[0])
            : count($secrets).' webhook signing secrets configured for multiple Stripe destinations.';

        $this->passRow('STRIPE_WEBHOOK_SECRET', $details);
    }

    private function checkCurrency(string $currency): void
    {
        if (! preg_match('/^[a-z]{3}$/', $currency)) {
            $this->failRow('STRIPE_CURRENCY', 'Expected a three-letter currency code, for example usd.');
            return;
        }

        $this->passRow('STRIPE_CURRENCY', $currency);
    }

    private function checkWebhookRoute(string $expectedUrl, bool $requireLive): void
    {
        if (! Route::has('webhooks.stripe')) {
            $this->failRow('Webhook route', 'Route webhooks.stripe is missing.');
            return;
        }

        if ($expectedUrl === '') {
            $this->failRow('Webhook URL', 'Unable to resolve expected webhook URL.');
            return;
        }

        if ($requireLive && ! str_starts_with($expectedUrl, 'https://')) {
            $this->failRow('Webhook URL', 'Live webhook URL must be HTTPS. Resolved: '.$expectedUrl);
            return;
        }

        $this->passRow('Webhook URL', $expectedUrl);
    }

    /**
     * @param list<string> $secrets
     */
    private function checkWebhookSignatureLocally(array $secrets): void
    {
        if ($secrets === []) {
            return;
        }

        $payload = json_encode([
            'id' => 'evt_homecare_diagnostic',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_homecare_diagnostic']],
        ]);

        if (! is_string($payload)) {
            $this->failRow('Webhook signature', 'Unable to generate local test payload.');
            return;
        }

        foreach ($secrets as $secret) {
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
            $header = 't='.$timestamp.',v1='.$signature;

            try {
                Webhook::constructEvent($payload, $header, $secret);
            } catch (Throwable $e) {
                $this->failRow('Webhook signature', 'Local signature verification failed for '.$this->mask($secret).': '.$e->getMessage());
                return;
            }
        }

        $this->passRow('Webhook signature', 'Local whsec signature verification works.');
    }

    private function checkStripeAccount(StripeSdk $client, ?string $secretMode, bool $requireLive): void
    {
        try {
            $account = $client->accounts->retrieve();
        } catch (Throwable $e) {
            $this->failRow('Stripe API account', 'Unable to retrieve platform account: '.$this->stripeError($e));
            return;
        }

        $apiMode = (bool) ($account->livemode ?? false) ? 'live' : 'test';
        $details = 'Account '.($account->id ?? '(unknown)').' returned '.$apiMode.' mode.';

        if ($secretMode && $apiMode !== $secretMode) {
            $this->failRow('Stripe API account', $details.' This does not match STRIPE_SECRET mode.');
            return;
        }

        if ($requireLive && $apiMode !== 'live') {
            $this->failRow('Stripe API account', $details.' Expected live mode.');
            return;
        }

        $this->passRow('Stripe API account', $details);
    }

    private function checkStripeBalance(StripeSdk $client): void
    {
        try {
            $balance = $client->balance->retrieve();
        } catch (Throwable $e) {
            $this->failRow('Stripe balance API', 'Unable to retrieve balance: '.$this->stripeError($e));
            return;
        }

        $available = is_countable($balance->available ?? null) ? count($balance->available) : 0;
        $this->passRow('Stripe balance API', 'Balance endpoint reachable; '.$available.' available balance bucket(s).');
    }

    private function checkWebhookEndpoints(StripeSdk $client, string $expectedUrl, int $configuredSecretCount): void
    {
        if ($expectedUrl === '') {
            return;
        }

        try {
            $endpoints = $client->webhookEndpoints->all(['limit' => 100]);
        } catch (Throwable $e) {
            $this->failRow('Stripe webhooks API', 'Unable to list webhook endpoints: '.$this->stripeError($e));
            return;
        }

        $matches = [];
        foreach ($endpoints->data ?? [] as $endpoint) {
            if ($this->normalizeUrl((string) ($endpoint->url ?? '')) === $this->normalizeUrl($expectedUrl)) {
                $matches[] = $endpoint;
            }
        }

        if ($matches === []) {
            $this->failRow('Stripe webhook endpoint', 'No Stripe webhook endpoint found for '.$expectedUrl.'.');
            return;
        }

        $enabledMatches = array_filter($matches, fn ($endpoint) => (string) ($endpoint->status ?? '') === 'enabled');
        if ($enabledMatches === []) {
            $this->failRow('Stripe webhook endpoint', 'Matching endpoint exists but is not enabled.');
            return;
        }

        $allEvents = [];
        $hasConnectedEndpoint = false;
        foreach ($enabledMatches as $endpoint) {
            $hasConnectedEndpoint = $hasConnectedEndpoint || (bool) ($endpoint->connect ?? false);
            foreach ((array) ($endpoint->enabled_events ?? []) as $event) {
                $allEvents[$event] = true;
            }
        }

        $missing = array_values(array_filter(
            array_merge(self::PLATFORM_EVENTS, self::CONNECT_EVENTS),
            fn (string $event): bool => ! isset($allEvents[$event]) && ! isset($allEvents['*'])
        ));

        if ($missing !== []) {
            $this->failRow('Stripe webhook events', 'Missing event(s): '.implode(', ', $missing).'.');
        } else {
            $this->passRow('Stripe webhook events', 'Required platform and Connect events are selected.');
        }

        $details = count($enabledMatches).' enabled endpoint(s) match '.$expectedUrl.'.';
        if ($configuredSecretCount < count($enabledMatches)) {
            $details .= ' Configure all signing secrets comma-separated in STRIPE_WEBHOOK_SECRET.';
            $this->warnRow('Webhook signing secrets', $details);
        } else {
            $this->passRow('Stripe webhook endpoint', $details);
        }

        if ($hasConnectedEndpoint) {
            $this->passRow('Connected-account webhook', 'At least one matching endpoint is scoped to connected accounts.');
        } elseif ((bool) $this->option('require-connected-endpoint')) {
            $this->failRow('Connected-account webhook', 'No matching connected-account endpoint found for account.updated.');
        } else {
            $this->warnRow('Connected-account webhook', 'No matching connected-account endpoint found. If caregiver account.updated events do not arrive, add a connected-account destination and place both whsec values in STRIPE_WEBHOOK_SECRET separated by commas.');
        }
    }

    private function checkConnectAccess(StripeSdk $client): void
    {
        try {
            $accounts = $client->accounts->all(['limit' => 1]);
        } catch (Throwable $e) {
            $this->failRow('Stripe Connect API', 'Unable to list connected accounts: '.$this->stripeError($e));
            return;
        }

        $count = is_countable($accounts->data ?? null) ? count($accounts->data) : 0;
        $this->passRow('Stripe Connect API', 'Connected accounts endpoint reachable; sampled '.$count.' account(s).');
    }

    private function expectedWebhookUrl(): string
    {
        $explicit = trim((string) $this->option('expected-url'));
        if ($explicit !== '') {
            return $explicit;
        }

        if (! Route::has('webhooks.stripe')) {
            return '';
        }

        return route('webhooks.stripe');
    }

    /**
     * @return list<string>
     */
    private function webhookSecrets(): array
    {
        $raw = (string) config('services.stripe.webhook_secret', '');

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $secret): bool => $secret !== ''
        ));
    }

    private function publishableKeyMode(string $key): ?string
    {
        return match (true) {
            str_starts_with($key, 'pk_live_') => 'live',
            str_starts_with($key, 'pk_test_') => 'test',
            default => null,
        };
    }

    private function secretKeyMode(string $key): ?string
    {
        return match (true) {
            str_starts_with($key, 'sk_live_'), str_starts_with($key, 'rk_live_') => 'live',
            str_starts_with($key, 'sk_test_'), str_starts_with($key, 'rk_test_') => 'test',
            default => null,
        };
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '(missing)';
        }

        if (strlen($value) <= 12) {
            return substr($value, 0, 4).'...';
        }

        return substr($value, 0, 7).'...'.substr($value, -4);
    }

    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    private function stripeError(Throwable $e): string
    {
        if ($e instanceof ApiErrorException) {
            $code = $e->getStripeCode();
            $requestId = $e->getRequestId();

            return trim($e->getMessage()
                .($code ? ' Stripe code: '.$code.'.' : '')
                .($requestId ? ' Request id: '.$requestId.'.' : ''));
        }

        return $e->getMessage();
    }

    private function passRow(string $check, string $details): void
    {
        $this->rows[] = ['check' => $check, 'result' => 'PASS', 'details' => $details];
    }

    private function warnRow(string $check, string $details): void
    {
        $this->rows[] = ['check' => $check, 'result' => 'WARN', 'details' => $details];
    }

    private function failRow(string $check, string $details): void
    {
        $this->rows[] = ['check' => $check, 'result' => 'FAIL', 'details' => $details];
        $this->failures[] = $check.': '.$details;
    }
}
