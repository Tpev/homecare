<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendSlackOpsNotification;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SlackOpsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://hooks.slack.com/services/T000/B000/testing-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('services.slack.ops.enabled', false);
        config()->set('services.slack.ops.webhook_url', null);
    }

    public function test_open_family_request_queues_slack_notification_when_enabled(): void
    {
        Queue::fake();
        $this->enableSlack();

        $request = $this->createRequest();

        Queue::assertPushed(SendSlackOpsNotification::class, function (SendSlackOpsNotification $job) use ($request): bool {
            return $job->event === SlackOpsNotificationService::EVENT_CARE_REQUEST_CREATED
                && $job->careRequestId === $request->id
                && $job->applicationId === null;
        });
    }

    public function test_system_generated_and_invalid_webhook_requests_do_not_queue_notifications(): void
    {
        Queue::fake();
        $this->enableSlack();

        $this->createRequest(['is_system_generated' => true]);

        config()->set('services.slack.ops.webhook_url', 'https://example.com/services/T000/B000/secret');
        $this->createRequest();

        Queue::assertNothingPushed();
    }

    public function test_hired_status_transition_queues_once_and_excludes_system_requests(): void
    {
        Queue::fake();
        $request = $this->createRequest();
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        $this->enableSlack();
        $application->update(['status' => CareRequestApplication::STATUS_HIRED]);
        $application->update(['proposed_rate' => 31]);

        $systemRequest = $this->createRequest(['is_system_generated' => true]);
        $systemApplication = CareRequestApplication::query()->create([
            'care_request_id' => $systemRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $systemApplication->update(['status' => CareRequestApplication::STATUS_HIRED]);

        Queue::assertPushed(SendSlackOpsNotification::class, 1);
        Queue::assertPushed(SendSlackOpsNotification::class, function (SendSlackOpsNotification $job) use ($request, $application): bool {
            return $job->event === SlackOpsNotificationService::EVENT_CAREGIVER_HIRED
                && $job->careRequestId === $request->id
                && $job->applicationId === $application->id;
        });
    }

    public function test_request_payload_contains_requested_ops_details_but_not_sensitive_care_data(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'name' => 'Family & Partners',
            'email' => 'family@example.com',
        ]);
        $request = $this->createRequest([
            'family_user_id' => $family->id,
            'address_line1' => '123 Private Street',
            'additional_info' => 'PRIVATE MEDICAL DETAIL',
            'home_access_notes' => 'DOOR CODE 1234',
        ]);
        $request->recipient()->create([
            'full_name' => 'PRIVATE RECIPIENT NAME',
            'relationship_to_family' => 'Parent',
            'care_notes' => 'PRIVATE CARE NOTE',
        ]);
        $this->enableSlack();
        Http::fake([self::WEBHOOK_URL => Http::response('ok')]);

        app(SlackOpsNotificationService::class)->deliver(
            SlackOpsNotificationService::EVENT_CARE_REQUEST_CREATED,
            $request->id,
        );

        Http::assertSent(function ($outgoing) use ($request): bool {
            $payload = json_encode($outgoing->data(), JSON_UNESCAPED_UNICODE);

            return $outgoing->url() === self::WEBHOOK_URL
                && str_contains($payload, 'Family &amp; Partners')
                && str_contains($payload, 'family@example.com')
                && str_contains($payload, 'Raleigh, NC, 27601')
                && str_contains($payload, '4 hours')
                && str_contains($payload, 'Open care request #'.$request->id)
                && ! str_contains($payload, '123 Private Street')
                && ! str_contains($payload, 'PRIVATE MEDICAL DETAIL')
                && ! str_contains($payload, 'DOOR CODE 1234')
                && ! str_contains($payload, 'PRIVATE RECIPIENT NAME')
                && ! str_contains($payload, 'PRIVATE CARE NOTE');
        });
    }

    public function test_hire_payload_identifies_family_caregiver_schedule_duration_and_area(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'name' => 'Jamie Family',
            'email' => 'jamie@example.com',
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Taylor Caregiver',
            'email' => 'taylor@example.com',
        ]);
        $request = $this->createRequest(['family_user_id' => $family->id]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->enableSlack();
        Http::fake([self::WEBHOOK_URL => Http::response('ok')]);

        app(SlackOpsNotificationService::class)->deliver(
            SlackOpsNotificationService::EVENT_CAREGIVER_HIRED,
            $request->id,
            $application->id,
        );

        Http::assertSent(function ($outgoing): bool {
            $payload = json_encode($outgoing->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($payload, 'Family hired a caregiver')
                && str_contains($payload, 'Jamie Family')
                && str_contains($payload, 'jamie@example.com')
                && str_contains($payload, 'Taylor Caregiver')
                && str_contains($payload, 'taylor@example.com')
                && str_contains($payload, '4 hours')
                && str_contains($payload, 'Raleigh, NC, 27601');
        });
    }

    public function test_safe_artisan_command_tests_the_configured_webhook(): void
    {
        $this->enableSlack();
        Http::fake([self::WEBHOOK_URL => Http::response('ok')]);

        $this->artisan('ops:slack:test')
            ->expectsOutput('Slack operations test notification delivered.')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_slack_failure_never_prevents_family_request_creation(): void
    {
        $this->enableSlack();
        Http::fake([self::WEBHOOK_URL => Http::response('temporary failure', 503)]);

        $request = $this->createRequest();

        $this->assertDatabaseHas('care_requests', ['id' => $request->id]);
        Http::assertSentCount(1);
    }

    public function test_recurring_payload_reports_each_week_schedule_and_duration(): void
    {
        $request = $this->createRequest([
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_days' => [1, 3],
            'recurring_start_time' => '09:00',
            'recurring_end_time' => '13:00',
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'end_time' => '13:00'],
                ['day' => 3, 'start_time' => '09:00', 'end_time' => '13:00'],
            ],
            'recurring_starts_on' => '2026-08-24',
            'recurring_ends_on' => null,
        ]);
        $this->enableSlack();
        Http::fake([self::WEBHOOK_URL => Http::response('ok')]);

        app(SlackOpsNotificationService::class)->deliver(
            SlackOpsNotificationService::EVENT_CARE_REQUEST_CREATED,
            $request->id,
        );

        Http::assertSent(function ($outgoing): bool {
            $payload = json_encode($outgoing->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($payload, 'Mon, Wed · 9:00 AM–1:00 PM')
                && str_contains($payload, 'starts Aug 24, 2026')
                && str_contains($payload, 'ongoing')
                && str_contains($payload, '8 hours per week');
        });
    }

    private function enableSlack(): void
    {
        config()->set('services.slack.ops.enabled', true);
        config()->set('services.slack.ops.webhook_url', self::WEBHOOK_URL);
    }

    private function createRequest(array $overrides = []): CareRequest
    {
        $familyId = $overrides['family_user_id'] ?? User::factory()->create(['role' => 'family'])->id;

        return CareRequest::query()->create(array_merge([
            'family_user_id' => $familyId,
            'created_by_user_id' => $familyId,
            'is_system_generated' => false,
            'title' => 'Companion care request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => Carbon::parse('2026-08-24 09:00:00', 'America/New_York'),
            'requested_end_at' => Carbon::parse('2026-08-24 13:00:00', 'America/New_York'),
            'address_line1' => '100 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ], $overrides));
    }
}
