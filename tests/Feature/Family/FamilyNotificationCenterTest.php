<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\NotificationsCenter;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_view_notification_center(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $this->createNotification($family, MarketplaceEvent::HIRE_CONFIRMED, 'Hire confirmed');
        $this->createNotification($family, MarketplaceEvent::NEW_APPLICANT, 'New caregiver application', read: true);

        $response = $this->actingAs($family)->get('/family/notifications');

        $response->assertOk();
        $response->assertSee('Family updates, clearly organized.');
        $response->assertSee('Hire confirmed');
        $response->assertSee('UNREAD');
    }

    public function test_family_user_can_save_notification_preferences(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(NotificationsCenter::class)
            ->set('preferences.'.MarketplaceEvent::INVITATION_SENT.'.'.NotificationChannels::EMAIL, false)
            ->set('preferences.'.MarketplaceEvent::INVITATION_SENT.'.'.NotificationChannels::IN_APP, true)
            ->set('preferences.'.MarketplaceEvent::INVITATION_SENT.'.'.NotificationChannels::SMS, true)
            ->call('savePreferences')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::INVITATION_SENT,
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'sms_enabled' => 1,
            'push_enabled' => 0,
        ]);

        $stored = UserNotificationPreference::query()
            ->where('user_id', $family->id)
            ->where('event_key', MarketplaceEvent::INVITATION_SENT)
            ->first();

        $this->assertNotNull($stored);
        $this->assertFalse((bool) $stored->email_enabled);
    }

    public function test_caregiver_cannot_access_family_notification_center(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($caregiver)->get('/family/notifications');

        $response->assertForbidden();
    }

    public function test_family_nav_shows_notifications_badge_count(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $this->createNotification($family, MarketplaceEvent::INVITATION_SENT, 'Invitation sent');

        $response = $this->actingAs($family)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Notifications (1)');
    }

    private function createNotification(User $user, string $eventKey, string $title, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MarketplaceEventNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'event_key' => $eventKey,
                'title' => $title,
                'body' => 'Notification body for '.$title,
                'url' => route('dashboard'),
                'payload' => [],
            ],
            'read_at' => $read ? now()->subMinute() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

