<?php

namespace Tests\Feature\Notifications;

use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\Skill;
use App\Models\User;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverOnboardingEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_open_and_click_tracking_endpoints_record_metrics(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $delivery = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_WELCOME,
            'channel' => 'email',
            'status' => 'sent',
            'payload' => [
                'tracking' => [
                    'token' => 'tok_abc123',
                    'target_url' => route('dashboard'),
                ],
            ],
            'sent_at' => now(),
        ]);

        $this->get(route('notifications.email.open', ['delivery' => $delivery->id, 'token' => 'tok_abc123']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'id' => $delivery->id,
            'open_count' => 1,
        ]);

        $this->get(route('notifications.email.click', ['delivery' => $delivery->id, 'token' => 'tok_abc123']))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'id' => $delivery->id,
            'click_count' => 1,
        ]);

        $this->get(route('notifications.email.open', ['delivery' => $delivery->id, 'token' => 'wrong']))
            ->assertNotFound();
    }

    public function test_onboarding_reminder_dispatches_after_24h_only_for_incomplete_profiles(): void
    {
        $incompleteCaregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $incompleteCaregiver->id,
            'status' => 'draft',
        ]);

        $completeCaregiver = User::factory()->create(['role' => 'caregiver']);
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);

        $completeProfile = CaregiverProfile::query()->create([
            'user_id' => $completeCaregiver->id,
            'status' => 'draft',
            'bio' => str_repeat('Reliable caregiver profile. ', 4),
            'years_experience' => 3,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $completeProfile->skills()->sync([$skill->id]);
        $completeProfile->languages()->sync([$language->id]);
        $completeProfile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        foreach ([$incompleteCaregiver, $completeCaregiver] as $user) {
            $welcomeDelivery = MarketplaceNotificationDelivery::query()->create([
                'user_id' => $user->id,
                'event_key' => MarketplaceEvent::CAREGIVER_WELCOME,
                'channel' => 'email',
                'status' => $user->is($incompleteCaregiver) ? 'queued' : 'sent',
                'dedupe_key' => 'caregiver-onboarding-welcome:user-'.$user->id.':email',
                'payload' => [
                    'tracking' => [
                        'token' => 'tok_'.$user->id,
                        'target_url' => route('caregiver.onboarding'),
                    ],
                ],
                'sent_at' => $user->is($incompleteCaregiver) ? null : now()->subHours(25),
            ]);
            if ($user->is($incompleteCaregiver)) {
                $welcomeDelivery->forceFill([
                    'created_at' => now()->subHours(25),
                    'updated_at' => now()->subHours(25),
                ])->save();
            }
        }

        $this->artisan('homecare:dispatch-notifications --type=onboarding')
            ->assertExitCode(0);

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $incompleteCaregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
            'channel' => 'email',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('marketplace_notification_deliveries', [
            'user_id' => $completeCaregiver->id,
            'event_key' => MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
            'channel' => 'email',
        ]);
    }
}
