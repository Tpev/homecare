<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class VerifyAiSupportProviderProject extends Command
{
    private const ALERT_CONFIRMATION = 'CREATE-25-MONTHLY-SPEND-ALERT';

    private const ALERT_THRESHOLD_CENTS = 2500;

    protected $signature = 'ai-support:verify-provider-project
        {--current-key-only : Authenticate the currently configured API key without Admin API access}
        {--project-id= : Intended non-secret OpenAI project ID}
        {--list-projects : List active non-secret project IDs and names without verifying or changing one}
        {--create-spend-alert : Create the missing $25 monthly email alert}
        {--confirm= : Must equal CREATE-25-MONTHLY-SPEND-ALERT for the provider write}';

    protected $description = 'Verify content-free OpenAI project, key, retention, and spend-alert evidence through the Admin API.';

    public function handle(): int
    {
        $projectId = trim((string) $this->option('project-id'));
        $currentKeyOnly = (bool) $this->option('current-key-only');
        $listProjects = (bool) $this->option('list-projects');
        $createAlert = (bool) $this->option('create-spend-alert');
        if ($currentKeyOnly && ($projectId !== '' || $listProjects || $createAlert || trim((string) $this->option('confirm')) !== '')) {
            $this->error('Use --current-key-only by itself; it does not use Admin API project or alert operations.');

            return self::FAILURE;
        }
        if ($listProjects && ($projectId !== '' || $createAlert || trim((string) $this->option('confirm')) !== '')) {
            $this->error('Use --list-projects by itself; it cannot verify a project or create an alert.');

            return self::FAILURE;
        }
        if (! $currentKeyOnly && ! $listProjects && ! preg_match('/^proj_[A-Za-z0-9_-]+$/', $projectId)) {
            $this->error('Provide --project-id=proj_..., use --list-projects, or use --current-key-only.');

            return self::FAILURE;
        }
        if (! $this->officialDestinationIsConfigured()) {
            $this->error('Provider verification refused: the configured destination is not exactly https://api.openai.com/v1.');

            return self::FAILURE;
        }
        if ($currentKeyOnly) {
            return $this->verifyCurrentKey();
        }
        if ($createAlert && (string) $this->option('confirm') !== self::ALERT_CONFIRMATION) {
            $this->error('Use --confirm='.self::ALERT_CONFIRMATION.' to create the provider alert.');

            return self::FAILURE;
        }

        $adminKey = trim((string) getenv('OPENAI_ADMIN_KEY'));
        if ($adminKey === '') {
            $this->error('An ephemeral OPENAI_ADMIN_KEY is required.');

            return self::FAILURE;
        }
        if ($listProjects) {
            return $this->listProjects($adminKey);
        }

        $projectKey = trim((string) config('services.openai.api_key'));
        if ($projectKey === '') {
            $this->error('The configured production project key is required.');

            return self::FAILURE;
        }

        $recipients = [];
        if ($createAlert) {
            $recipients = $this->spendAlertRecipients();
            if ($recipients === []) {
                $this->error('Set ephemeral AI_SUPPORT_SPEND_ALERT_RECIPIENTS to one or more unique, valid comma-separated email addresses.');

                return self::FAILURE;
            }
        }

        try {
            $project = $this->getAdminJson($adminKey, 'organization/projects/'.$projectId, 'project lookup');
            if (($project['id'] ?? null) !== $projectId || ($project['status'] ?? null) !== 'active') {
                throw new RuntimeException('The intended project was not returned as active.');
            }

            $keyRecords = $this->listAdminCollection(
                $adminKey,
                'organization/projects/'.$projectId.'/api_keys',
                ['owner_project_access' => 'any'],
            );
            $matches = collect($keyRecords)
                ->filter(fn (array $record): bool => $this->redactedValueMatches(
                    $projectKey,
                    trim((string) ($record['redacted_value'] ?? '')),
                ))
                ->values();
            if ($matches->count() !== 1) {
                throw new RuntimeException($matches->isEmpty()
                    ? 'The configured project key did not match a redacted key record in the intended project.'
                    : 'The configured project key match was ambiguous within the intended project.');
            }
            if (($matches->first()['owner_project_access'] ?? 'active') !== 'active') {
                throw new RuntimeException('The matching key owner does not have active project access.');
            }

            $projectRequest = $this->request($projectKey)
                ->withHeaders(['OpenAI-Project' => $projectId])
                ->get('models');
            $this->assertSuccessful($projectRequest, 'project-scoped content-free request');

            $retention = $this->getAdminJson(
                $adminKey,
                'organization/projects/'.$projectId.'/data_retention',
                'project data-retention lookup',
            );
            $retentionType = $this->retentionType($retention, 'project.data_retention', [
                'organization_default',
                'none',
                'zero_data_retention',
                'modified_abuse_monitoring',
                'enhanced_zero_data_retention',
                'enhanced_modified_abuse_monitoring',
            ]);
            $retentionEvidence = $retentionType;
            if ($retentionType === 'organization_default') {
                $organizationRetention = $this->getAdminJson(
                    $adminKey,
                    'organization/data_retention',
                    'organization data-retention lookup',
                );
                $effectiveType = $this->retentionType($organizationRetention, 'organization.data_retention', [
                    'zero_data_retention',
                    'modified_abuse_monitoring',
                    'enhanced_zero_data_retention',
                    'enhanced_modified_abuse_monitoring',
                ]);
                $retentionEvidence .= ' -> '.$effectiveType;
            }

            $alerts = $this->listAdminCollection($adminKey, 'organization/projects/'.$projectId.'/spend_alerts');
            $alert = $this->qualifyingAlert($alerts);
            $alertCreated = false;
            if ($alert === null && $createAlert) {
                $this->createSpendAlert($adminKey, $projectId, $recipients);
                $alerts = $this->listAdminCollection($adminKey, 'organization/projects/'.$projectId.'/spend_alerts');
                $alert = $this->qualifyingAlert($alerts);
                $alertCreated = true;
            }
            if ($alert === null) {
                $this->table(['Check', 'Result'], [
                    ['Official destination', 'PASS'],
                    ['Intended project', 'PASS - active'],
                    ['Configured project key', 'PASS - unique redacted match and scoped request accepted'],
                    ['Project data retention', 'OBSERVED - '.$retentionEvidence],
                    ['$25 monthly spend alert', 'OPTIONAL - missing'],
                    ['Model-improvement sharing', 'NOT VERIFIED - no documented Admin API field'],
                ]);
                $this->warn('Optional $25 monthly email spend alert was not found for the intended project.');
                $this->warn('Read-only verification completed. No provider state was changed.');

                return self::SUCCESS;
            }

            $recipientCount = count((array) data_get($alert, 'notification_channel.recipients', []));
            $this->table(['Check', 'Result'], [
                ['Official destination', 'PASS'],
                ['Intended project', 'PASS - active'],
                ['Configured project key', 'PASS - unique redacted match and scoped request accepted'],
                ['Project data retention', 'OBSERVED - '.$retentionEvidence],
                ['$25 monthly spend alert', 'PASS - '.($alertCreated ? 'created' : 'existing').'; '.$recipientCount.' recipient(s)'],
                ['Model-improvement sharing', 'NOT VERIFIED - no documented Admin API field'],
            ]);
            $this->info('Content-free provider verification completed. No credential or recipient address was printed.');
            $this->warn('Keep model-improvement sharing Pending until separate account-owned evidence is supplied.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Provider verification failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function officialDestinationIsConfigured(): bool
    {
        $parts = parse_url(rtrim((string) config('services.openai.base_url'), '/'));

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'api.openai.com'
            && rtrim((string) ($parts['path'] ?? ''), '/') === '/v1'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    private function verifyCurrentKey(): int
    {
        $projectKey = trim((string) config('services.openai.api_key'));
        if ($projectKey === '') {
            $this->error('The currently configured OpenAI API key is required.');

            return self::FAILURE;
        }

        try {
            $response = $this->request($projectKey)->get('models');
            $this->assertSuccessful($response, 'current-key content-free request');
            $keyForm = str_starts_with($projectKey, 'sk-proj-')
                ? 'OBSERVED - project-scoped key form'
                : 'OBSERVED - standard user/service key form';
            $this->table(['Check', 'Result'], [
                ['Official destination', 'PASS'],
                ['Configured API credential', 'PASS - content-free request accepted'],
                ['Credential form', $keyForm],
                ['Exact provider project identity', 'DEFERRED - Admin API evidence not required for this pilot'],
                ['Data-sharing and retention settings', 'NOT VERIFIED - standard API keys cannot read these controls'],
                ['$25 monthly provider alert', 'OPTIONAL - not checked or required for this pilot'],
            ]);
            $this->info('Current-key verification completed without printing the credential or changing provider state.');
            $this->warn('Do not mark data-sharing or retention settings verified from this result.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Current-key verification failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function listProjects(string $adminKey): int
    {
        try {
            $projects = collect($this->listAdminCollection($adminKey, 'organization/projects'))
                ->filter(fn (array $project): bool => ($project['status'] ?? null) === 'active')
                ->map(fn (array $project): array => [
                    trim((string) ($project['id'] ?? '')),
                    trim((string) ($project['name'] ?? '')) ?: '(unnamed)',
                ])
                ->filter(fn (array $project): bool => preg_match('/^proj_[A-Za-z0-9_-]+$/', $project[0]) === 1)
                ->values()
                ->all();
            if ($projects === []) {
                throw new RuntimeException('No active project record was returned.');
            }

            $this->table(['Active project ID', 'Project name'], $projects);
            $this->info('Read-only project discovery completed. No credential was printed and no provider state was changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Provider project discovery failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return list<string> */
    private function spendAlertRecipients(): array
    {
        $value = trim((string) getenv('AI_SUPPORT_SPEND_ALERT_RECIPIENTS'));
        if ($value === '') {
            return [];
        }

        $recipients = collect(explode(',', $value))
            ->map(fn (string $recipient): string => strtolower(trim($recipient)))
            ->values();
        if ($recipients->contains(fn (string $recipient): bool => $recipient === ''
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false)
            || $recipients->unique()->count() !== $recipients->count()) {
            return [];
        }

        return $recipients->all();
    }

    private function request(string $token): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30);
        $caBundle = trim((string) config('services.openai.ca_bundle'));

        return $caBundle === '' ? $request : $request->withOptions(['verify' => $caBundle]);
    }

    /** @return array<string,mixed> */
    private function getAdminJson(string $adminKey, string $path, string $label): array
    {
        $response = $this->request($adminKey)->get($path);
        $this->assertSuccessful($response, $label);
        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException($label.' returned an invalid body.');
        }

        return $body;
    }

    /** @return list<array<string,mixed>> */
    private function listAdminCollection(string $adminKey, string $path, array $query = []): array
    {
        $records = [];
        $after = null;
        for ($page = 1; $page <= 20; $page++) {
            $response = $this->request($adminKey)->get($path, array_filter(
                array_merge($query, [
                    'limit' => 100,
                    'after' => $after,
                ]),
                fn (mixed $value): bool => $value !== null && $value !== '',
            ));
            $this->assertSuccessful($response, 'Admin collection lookup');
            $body = $response->json();
            if (! is_array($body) || ! is_array($body['data'] ?? null)) {
                throw new RuntimeException('Admin collection lookup returned an invalid body.');
            }
            foreach ($body['data'] as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
            if (! ($body['has_more'] ?? false)) {
                return $records;
            }
            $after = $body['last_id'] ?? $body['next'] ?? null;
            if (! is_string($after) || $after === '') {
                throw new RuntimeException('Admin collection pagination did not provide a cursor.');
            }
        }

        throw new RuntimeException('Admin collection pagination exceeded the bounded page limit.');
    }

    private function redactedValueMatches(string $actual, string $redacted): bool
    {
        if ($actual === '' || $redacted === '') {
            return false;
        }
        if (! str_contains($redacted, '*') && ! str_contains($redacted, '...')) {
            return hash_equals($actual, $redacted);
        }

        $segments = array_values(array_filter(
            preg_split('/(?:\*+|\.{3,})/', $redacted) ?: [],
            fn (string $segment): bool => $segment !== '',
        ));
        if ($segments === [] || strlen((string) end($segments)) < 4) {
            return false;
        }
        if (! preg_match('/^(?:\*+|\.{3,})/', $redacted) && ! str_starts_with($actual, $segments[0])) {
            return false;
        }
        if (! preg_match('/(?:\*+|\.{3,})$/', $redacted) && ! str_ends_with($actual, (string) end($segments))) {
            return false;
        }

        $offset = 0;
        foreach ($segments as $segment) {
            $position = strpos($actual, $segment, $offset);
            if ($position === false) {
                return false;
            }
            $offset = $position + strlen($segment);
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $retention
     * @param  list<string>  $allowedTypes
     */
    private function retentionType(array $retention, string $expectedObject, array $allowedTypes): string
    {
        $type = trim((string) ($retention['type'] ?? ''));
        if (($retention['object'] ?? null) !== $expectedObject || ! in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('The data-retention response was not recognized.');
        }

        return $type;
    }

    /** @param list<array<string,mixed>> $alerts */
    private function qualifyingAlert(array $alerts): ?array
    {
        foreach ($alerts as $alert) {
            if ((int) ($alert['threshold_amount'] ?? -1) === self::ALERT_THRESHOLD_CENTS
                && strtoupper((string) ($alert['currency'] ?? '')) === 'USD'
                && ($alert['interval'] ?? null) === 'month'
                && data_get($alert, 'notification_channel.type') === 'email'
                && count((array) data_get($alert, 'notification_channel.recipients', [])) > 0) {
                return $alert;
            }
        }

        return null;
    }

    /** @param list<string> $recipients */
    private function createSpendAlert(string $adminKey, string $projectId, array $recipients): void
    {
        $idempotencyKey = 'ai-support-25-alert-'.hash('sha256', $projectId.'|'.implode('|', $recipients));
        try {
            $response = $this->request($adminKey)
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('organization/projects/'.$projectId.'/spend_alerts', [
                    'threshold_amount' => self::ALERT_THRESHOLD_CENTS,
                    'currency' => 'USD',
                    'interval' => 'month',
                    'notification_channel' => [
                        'type' => 'email',
                        'recipients' => $recipients,
                        'subject_prefix' => 'LoLo AI Support spend alert',
                    ],
                ]);
            $this->assertSuccessful($response, 'spend-alert creation');
        } catch (ConnectionException) {
            // The caller always re-lists alerts after this method. A provider-side
            // success followed by a lost response therefore cannot cause a retry
            // that creates a duplicate alert.
        }
    }

    private function assertSuccessful(Response $response, string $label): void
    {
        if (! $response->successful()) {
            throw new RuntimeException($label.' failed with HTTP '.$response->status().'.');
        }
    }
}
