<?php

namespace Tests\Feature\AiSupport;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderProjectEvidenceCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('OPENAI_ADMIN_KEY=synthetic-admin-key');
        config([
            'services.openai.api_key' => 'sk-proj-synthetic-1234',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);
    }

    protected function tearDown(): void
    {
        putenv('OPENAI_ADMIN_KEY');
        putenv('AI_SUPPORT_SPEND_ALERT_RECIPIENTS');
        parent::tearDown();
    }

    public function test_read_only_verification_reports_content_free_project_retention_and_existing_alert_evidence(): void
    {
        $this->fakeProvider(alertExists: true);

        $this->artisan('ai-support:verify-provider-project', ['--project-id' => 'proj_intended'])
            ->expectsOutputToContain('PASS - unique redacted match and scoped request accepted')
            ->expectsOutputToContain('OBSERVED - organization_default')
            ->expectsOutputToContain('PASS - existing; 2 recipient(s)')
            ->expectsOutputToContain('NOT VERIFIED - no documented Admin API field')
            ->expectsOutputToContain('No credential or recipient address was printed')
            ->doesntExpectOutputToContain('synthetic-admin-key')
            ->doesntExpectOutputToContain('sk-proj-synthetic-1234')
            ->doesntExpectOutputToContain('admin-one@example.test')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://api.openai.com/v1/models') {
                return $request->hasHeader('Authorization', 'Bearer sk-proj-synthetic-1234')
                    && $request->hasHeader('OpenAI-Project', 'proj_intended');
            }

            if (str_contains($request->url(), '/api_keys')) {
                return $request->hasHeader('Authorization', 'Bearer synthetic-admin-key')
                    && str_contains($request->url(), 'owner_project_access=any');
            }

            return $request->hasHeader('Authorization', 'Bearer synthetic-admin-key');
        });
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_read_only_verification_fails_closed_when_the_alert_is_missing(): void
    {
        $this->fakeProvider(alertExists: false);

        $this->artisan('ai-support:verify-provider-project', ['--project-id' => 'proj_intended'])
            ->expectsOutputToContain('$25 monthly spend alert')
            ->expectsOutputToContain('MISSING')
            ->expectsOutputToContain('No provider state was changed')
            ->assertFailed();

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_alert_creation_requires_confirmation_before_any_provider_request(): void
    {
        Http::fake();

        $this->artisan('ai-support:verify-provider-project', [
            '--project-id' => 'proj_intended',
            '--create-spend-alert' => true,
        ])->expectsOutputToContain('Use --confirm=CREATE-25-MONTHLY-SPEND-ALERT')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_confirmed_creation_uses_2500_cents_and_never_prints_recipients(): void
    {
        putenv('AI_SUPPORT_SPEND_ALERT_RECIPIENTS=admin-one@example.test,admin-two@example.test');
        $alertExists = false;
        $this->fakeProvider(alertExists: $alertExists, mutableAlertState: true);

        $this->artisan('ai-support:verify-provider-project', [
            '--project-id' => 'proj_intended',
            '--create-spend-alert' => true,
            '--confirm' => 'CREATE-25-MONTHLY-SPEND-ALERT',
        ])->expectsOutputToContain('PASS - created; 2 recipient(s)')
            ->doesntExpectOutputToContain('admin-one@example.test')
            ->doesntExpectOutputToContain('admin-two@example.test')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.openai.com/v1/organization/projects/proj_intended/spend_alerts'
                && $request['threshold_amount'] === 2500
                && $request['currency'] === 'USD'
                && $request['interval'] === 'month'
                && $request['notification_channel']['recipients'] === [
                    'admin-one@example.test',
                    'admin-two@example.test',
                ]
                && $request->hasHeader('Idempotency-Key');
        });
    }

    public function test_alert_creation_rejects_any_invalid_or_duplicate_recipient_before_provider_access(): void
    {
        putenv('AI_SUPPORT_SPEND_ALERT_RECIPIENTS=admin-one@example.test,not-an-email');
        Http::fake();

        $this->artisan('ai-support:verify-provider-project', [
            '--project-id' => 'proj_intended',
            '--create-spend-alert' => true,
            '--confirm' => 'CREATE-25-MONTHLY-SPEND-ALERT',
        ])->expectsOutputToContain('one or more unique, valid comma-separated email addresses')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_verifier_refuses_to_send_an_admin_key_to_a_non_official_destination(): void
    {
        config(['services.openai.base_url' => 'https://example.test/v1']);
        Http::fake();

        $this->artisan('ai-support:verify-provider-project', ['--project-id' => 'proj_intended'])
            ->expectsOutputToContain('configured destination is not exactly https://api.openai.com/v1')
            ->assertFailed();

        Http::assertNothingSent();
    }

    private function fakeProvider(bool $alertExists, bool $mutableAlertState = false): void
    {
        $state = (object) ['alertExists' => $alertExists];
        Http::fake(function (Request $request) use ($state, $mutableAlertState) {
            $url = $request->url();
            if ($url === 'https://api.openai.com/v1/organization/projects/proj_intended') {
                return Http::response([
                    'id' => 'proj_intended',
                    'name' => 'Synthetic intended project',
                    'status' => 'active',
                ]);
            }
            if (str_starts_with($url, 'https://api.openai.com/v1/organization/projects/proj_intended/api_keys')) {
                return Http::response([
                    'data' => [[
                        'id' => 'key_synthetic',
                        'redacted_value' => 'sk-proj-...1234',
                        'owner_project_access' => 'active',
                    ]],
                    'has_more' => false,
                ]);
            }
            if ($url === 'https://api.openai.com/v1/models') {
                return Http::response(['object' => 'list', 'data' => []]);
            }
            if ($url === 'https://api.openai.com/v1/organization/projects/proj_intended/data_retention') {
                return Http::response(['object' => 'project.data_retention', 'type' => 'organization_default']);
            }
            if (str_starts_with($url, 'https://api.openai.com/v1/organization/projects/proj_intended/spend_alerts')) {
                if ($request->method() === 'POST') {
                    if ($mutableAlertState) {
                        $state->alertExists = true;
                    }

                    return Http::response($this->alertRecord(), 201);
                }

                return Http::response([
                    'data' => $state->alertExists ? [$this->alertRecord()] : [],
                    'has_more' => false,
                ]);
            }

            return Http::response([], 404);
        });
    }

    /** @return array<string,mixed> */
    private function alertRecord(): array
    {
        return [
            'id' => 'alert_synthetic',
            'object' => 'project.spend_alert',
            'threshold_amount' => 2500,
            'currency' => 'USD',
            'interval' => 'month',
            'notification_channel' => [
                'type' => 'email',
                'recipients' => ['admin-one@example.test', 'admin-two@example.test'],
            ],
        ];
    }
}
