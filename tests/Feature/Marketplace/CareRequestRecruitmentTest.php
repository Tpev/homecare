<?php

namespace Tests\Feature\Marketplace;

use App\Jobs\SendCareRequestRecruitment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\CareRequestRecruitment;
use App\Models\Language;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Marketplace\CareRequestRecruitmentService;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareRequestRecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        Notification::fake();
        Queue::fake();
    }

    public function test_admin_queues_once_without_applying_or_sending_in_the_web_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);

        $this->assertSame(['queued' => 1, 'skipped' => 0], $service->enqueue($admin, $request, [$caregiver->id, $caregiver->id]));
        $this->assertSame(['queued' => 0, 'skipped' => 1], $service->enqueue($admin, $request, [$caregiver->id]));
        Queue::assertPushed(SendCareRequestRecruitment::class, 1);
        Queue::assertPushed(SendCareRequestRecruitment::class, fn ($job) => $job->connection === 'database');
        Notification::assertNothingSent();
        $this->assertDatabaseCount('care_request_recruitments', 1);
        $this->assertDatabaseHas('care_request_recruitments', [
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'invited_by_user_id' => $admin->id, 'status' => 'queued',
        ]);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_stale_admin_role_is_reauthorized_at_enqueue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::query()->whereKey($admin->id)->update(['role' => 'family']);
        $request = $this->request();
        $caregiver = $this->caregiver();

        $this->expectException(AuthorizationException::class);
        app(CareRequestRecruitmentService::class)->enqueue($admin, $request, [$caregiver->id]);
    }

    public function test_queue_connection_failure_can_be_requeued_on_the_same_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        Queue::swap(\Mockery::mock()->shouldReceive('connection')->with('database')->once()
            ->andThrow(new \RuntimeException('Queue unavailable'))->getMock());

        $this->assertSame(['queued' => 0, 'skipped' => 1], app(CareRequestRecruitmentService::class)
            ->enqueue($admin, $request, [$caregiver->id]));
        $recruitment = CareRequestRecruitment::query()->sole();
        $this->assertSame('queue_failed', $recruitment->status);
        Notification::assertNothingSent();

        Queue::fake();
        $service = app(CareRequestRecruitmentService::class);
        $this->assertSame([$caregiver->id], $service->candidates($request)->pluck('id')->all());
        $nextAdmin = User::factory()->create(['role' => 'admin']);
        $this->assertSame(['queued' => 1, 'skipped' => 0], $service->enqueue($nextAdmin, $request, [$caregiver->id]));
        $this->assertSame($recruitment->id, CareRequestRecruitment::query()->sole()->id);
        $this->assertSame($nextAdmin->id, $recruitment->fresh()->invited_by_user_id);
        $this->assertNull($recruitment->fresh()->status_reason);
        Queue::assertPushed(SendCareRequestRecruitment::class, fn ($job) => $job->recruitmentId === $recruitment->id);
        $service->send($recruitment);
        $service->send($recruitment);
        Notification::assertSentToTimes($caregiver, MarketplaceEventNotification::class, 1);
        $this->assertSame('sent', $recruitment->fresh()->status);
        $this->assertDatabaseCount('marketplace_notification_deliveries', 1);
    }

    public function test_queue_error_does_not_overwrite_a_message_already_sent_by_a_worker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        Queue::swap(\Mockery::mock()->shouldReceive('connection')->with('database')->once()
            ->andReturnUsing(function () use ($service): void {
                $service->send(CareRequestRecruitment::query()->sole());
                throw new \RuntimeException('Queue acknowledgement lost');
            })->getMock());

        $this->assertSame(['queued' => 1, 'skipped' => 0], $service->enqueue($admin, $request, [$caregiver->id]));
        $this->assertSame('sent', CareRequestRecruitment::query()->sole()->status);
        Notification::assertSentToTimes($caregiver, MarketplaceEventNotification::class, 1);
        $this->assertSame(0, $service->candidates($request)->count());
    }

    public function test_delivery_reservation_prevents_retry_even_when_outreach_is_marked_queue_failed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
        $recruitment = CareRequestRecruitment::query()->sole();
        MarketplaceNotificationDelivery::query()->create([
            'user_id' => $caregiver->id, 'event_key' => MarketplaceEvent::CAREGIVER_RECRUITMENT,
            'channel' => 'email', 'status' => 'queued',
            'notifiable_type' => $request->getMorphClass(), 'notifiable_id' => $request->id,
            'dedupe_key' => 'care-request-recruitment-'.$recruitment->id.':email',
        ]);
        $recruitment->update(['status' => 'queue_failed']);

        $this->assertSame(0, $service->candidates($request)->count());
        $this->assertSame(['queued' => 0, 'skipped' => 1], $service->enqueue($admin, $request, [$caregiver->id]));
        Queue::assertPushed(SendCareRequestRecruitment::class, 1);
        Notification::assertNothingSent();
    }

    public function test_worker_failure_without_a_delivery_is_not_automatically_retryable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
        $recruitment = CareRequestRecruitment::query()->sole();
        (new SendCareRequestRecruitment($recruitment->id))->failed(new \RuntimeException('Worker stopped'));

        $this->assertSame('failed', $recruitment->fresh()->status);
        $this->assertSame(0, $service->candidates($request)->count());
        $this->assertSame(['queued' => 0, 'skipped' => 1], $service->enqueue($admin, $request, [$caregiver->id]));
        Queue::assertPushed(SendCareRequestRecruitment::class, 1);
        $this->assertDatabaseCount('marketplace_notification_deliveries', 0);
    }

    public function test_batch_size_is_bounded_on_the_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $this->expectException(ValidationException::class);
        app(CareRequestRecruitmentService::class)->enqueue($admin, $request, range(1, 101));
    }

    #[DataProvider('unavailableJobs')]
    public function test_unavailable_jobs_cannot_be_recruited(string $state): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $this->changeState($state, $request, $caregiver, $admin);
        $service = app(CareRequestRecruitmentService::class);
        $this->assertNotNull($service->unavailableReason($request->fresh()));
        $this->assertSame(0, $service->candidates($request->fresh())->count());
        $this->expectException(ValidationException::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
    }

    public static function unavailableJobs(): array
    {
        return array_map(fn (string $state) => [$state], ['private', 'closed', 'expired', 'system', 'hired', 'prelaunch', 'recurring_ended']);
    }

    public function test_candidates_are_ready_opted_in_and_ranked_by_location_and_searchable(): void
    {
        $request = $this->request();
        $far = $this->caregiver(['name' => 'Aaron Far', 'city' => 'Austin', 'state' => 'TX'], ['service_area_zip' => '73301']);
        $near = $this->caregiver(['name' => 'Zoe Near']);
        $city = $this->caregiver(['name' => 'Beth City'], ['service_area_zip' => '27602']);
        $state = $this->caregiver(['name' => 'Cara State', 'city' => 'Durham'], ['service_area_zip' => '27701']);
        $unready = $this->caregiver();
        $unready->caregiverProfile->availabilities()->delete();
        $suspended = $this->caregiver([], ['screening_suspended_at' => now()]);
        $unavailable = $this->caregiver([], ['is_accepting_new_clients' => false]);
        $optedOut = $this->caregiver();
        $this->optOut($optedOut, MarketplaceEvent::INVITATION_RECEIVED);
        $recruitmentOptOut = $this->caregiver();
        $this->optOut($recruitmentOptOut, MarketplaceEvent::CAREGIVER_RECRUITMENT);
        $invalidEmail = $this->caregiver(['email' => 'not-an-email']);
        $applicant = $this->caregiver();
        $this->apply($request, $applicant);
        $invited = $this->caregiver();
        $this->invite($request, $invited);

        $service = app(CareRequestRecruitmentService::class);
        $this->assertSame([$near->id, $city->id, $state->id, $far->id], $service->candidates($request)->pluck('id')->all());
        $this->assertSame([$near->id], $service->candidates($request, 'Zoe')->pluck('id')->all());
        $this->assertSame([$far->id], $service->candidates($request, $far->email)->pluck('id')->all());
        $this->assertSame([$far->id], $service->candidates($request, 'Austin')->pluck('id')->all());
        $this->assertSame([$state->id], $service->candidates($request, '27701')->pluck('id')->all());
        $this->assertTrue($service->candidates($request)->first()->relationLoaded('caregiverProfile'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSame(['queued' => 0, 'skipped' => 8], $service->enqueue($admin, $request, [
            $unready->id, $suspended->id, $unavailable->id, $optedOut->id,
            $recruitmentOptOut->id, $invalidEmail->id, $applicant->id, $invited->id,
        ]));
        Queue::assertNothingPushed();
    }

    public function test_worker_sends_one_email_with_direct_application_link_and_never_notifies_family(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
        $recruitment = CareRequestRecruitment::query()->sole();

        (new SendCareRequestRecruitment($recruitment->id))->handle($service);
        (new SendCareRequestRecruitment($recruitment->id))->handle($service);

        Notification::assertSentToTimes($caregiver, MarketplaceEventNotification::class, 1);
        Notification::assertSentTo($caregiver, MarketplaceEventNotification::class, fn ($notification, $channels) => $channels === ['mail']
            && $notification->toArray($caregiver)['event_key'] === MarketplaceEvent::CAREGIVER_RECRUITMENT
            && $notification->toArray($caregiver)['url'] === route('care-requests.apply', $request->id));
        Notification::assertNotSentTo($request->family, MarketplaceEventNotification::class);
        $this->assertSame('sent', $recruitment->fresh()->status);
        $this->assertNotNull($recruitment->fresh()->sent_at);
        $this->assertDatabaseCount('marketplace_notification_deliveries', 1);
        $delivery = MarketplaceNotificationDelivery::query()->sole();
        $this->assertSame('email', $delivery->channel);
        $this->assertSame($admin->id, $delivery->payload['invited_by_user_id']);
        $this->assertSame($recruitment->id, $delivery->payload['recruitment_id']);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    #[DataProvider('workerChanges')]
    public function test_worker_revalidates_changes_after_selection(string $state): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
        $recruitment = CareRequestRecruitment::query()->sole();
        $this->changeState($state, $request, $caregiver, $admin);
        $service->send($recruitment);

        $this->assertSame('skipped', $recruitment->fresh()->status);
        $this->assertNotEmpty($recruitment->fresh()->status_reason);
        $this->assertNull($recruitment->fresh()->sent_at);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('marketplace_notification_deliveries', 0);
    }

    public static function workerChanges(): array
    {
        return array_map(fn (string $state) => [$state], [
            'private', 'closed', 'expired', 'system', 'hired', 'prelaunch', 'recurring_ended',
            'admin_revoked', 'suspended', 'not_accepting', 'unready', 'applied', 'invited', 'opted_out', 'recruitment_opted_out',
        ]);
    }

    public function test_transport_failure_is_recorded_without_being_reported_as_sent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $caregiver = $this->caregiver();
        $service = app(CareRequestRecruitmentService::class);
        $service->enqueue($admin, $request, [$caregiver->id]);
        Notification::swap(\Mockery::mock()->shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Transport rejected'))->getMock());
        $recruitment = CareRequestRecruitment::query()->sole();
        $service->send($recruitment);

        $this->assertSame('failed', $recruitment->fresh()->status);
        $this->assertNull($recruitment->fresh()->sent_at);
        $this->assertSame('failed', MarketplaceNotificationDelivery::query()->sole()->status);
        $this->assertSame(0, $service->candidates($request)->count());
        $this->assertSame(['queued' => 0, 'skipped' => 1], $service->enqueue($admin, $request, [$caregiver->id]));
        Queue::assertPushed(SendCareRequestRecruitment::class, 1);
    }

    private function request(): CareRequest
    {
        return CareRequest::query()->create([
            'family_user_id' => User::factory()->create(['role' => 'family'])->id,
            'title' => 'Morning companionship', 'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME, 'is_private' => false,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '500 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
    }

    private function caregiver(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC'], $userAttributes));
        $profile = CaregiverProfile::query()->create(array_merge([
            'user_id' => $user->id, 'slug' => 'recruitment-caregiver-'.$user->id,
            'status' => 'active', 'screening_initial_approved_at' => now(),
            'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'is_accepting_new_clients' => true, 'identity_verified_at' => now(),
        ], $profileAttributes));
        $profile->skills()->sync([Skill::query()->firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::query()->firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }

    private function optOut(User $user, string $event): void
    {
        UserNotificationPreference::query()->create(['user_id' => $user->id, 'event_key' => $event, 'email_enabled' => false]);
    }

    private function apply(CareRequest $request, User $caregiver, string $status = 'applied'): void
    {
        CareRequestApplication::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => $status, 'proposed_rate' => 28,
        ]);
    }

    private function invite(CareRequest $request, User $caregiver): void
    {
        CareRequestInvitation::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'family_user_id' => $request->family_user_id, 'status' => 'declined',
        ]);
    }

    private function changeState(string $state, CareRequest $request, User $caregiver, User $admin): void
    {
        match ($state) {
            'private' => $request->update(['is_private' => true]),
            'closed' => $request->update(['status' => CareRequest::STATUS_CANCELLED]),
            'expired' => $request->update(['requested_start_at' => now()->subDay(), 'requested_end_at' => now()->subHour()]),
            'system' => $request->update(['is_system_generated' => true]),
            'hired' => $this->apply($request, $this->caregiver(), CareRequestApplication::STATUS_HIRED),
            'prelaunch' => config(['marketplace.caregiver_prelaunch_mode' => true]),
            'recurring_ended' => $request->update(['request_type' => CareRequest::TYPE_RECURRING, 'recurring_ends_on' => now()->subDay()]),
            'admin_revoked' => $admin->update(['role' => 'family']),
            'suspended' => $caregiver->caregiverProfile->update(['screening_suspended_at' => now()]),
            'not_accepting' => $caregiver->caregiverProfile->update(['is_accepting_new_clients' => false]),
            'unready' => $caregiver->caregiverProfile->availabilities()->delete(),
            'applied' => $this->apply($request, $caregiver),
            'invited' => $this->invite($request, $caregiver),
            'opted_out' => $this->optOut($caregiver, MarketplaceEvent::INVITATION_RECEIVED),
            'recruitment_opted_out' => $this->optOut($caregiver, MarketplaceEvent::CAREGIVER_RECRUITMENT),
        };
    }
}
