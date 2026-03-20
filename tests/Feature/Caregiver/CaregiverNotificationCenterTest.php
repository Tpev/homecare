<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\NotificationsCenter;
use App\Models\CaregiverProfile;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_view_notification_center(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $this->createNotification($caregiver, MarketplaceEvent::CAREGIVER_HIRED, 'You were hired');
        $this->createNotification($caregiver, MarketplaceEvent::MESSAGE_RECEIVED, 'New message', read: true);

        $response = $this->actingAs($caregiver)->get('/caregiver/notifications');

        $response->assertOk();
        $response->assertSee('Your caregiver updates, in one place.');
        $response->assertSee('You were hired');
        $response->assertSee('UNREAD');
    }

    public function test_caregiver_can_mark_notifications_read(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $first = $this->createNotification($caregiver, MarketplaceEvent::CAREGIVER_HIRED, 'You were hired');
        $second = $this->createNotification($caregiver, MarketplaceEvent::SHIFT_STARTING_SOON, 'Shift starts soon');

        Livewire::actingAs($caregiver)
            ->test(NotificationsCenter::class)
            ->call('markRead', $first->id);

        $this->assertNotNull(DatabaseNotification::query()->find($first->id)?->read_at);
        $this->assertNull(DatabaseNotification::query()->find($second->id)?->read_at);

        Livewire::actingAs($caregiver)
            ->test(NotificationsCenter::class)
            ->call('markAllRead');

        $this->assertNotNull(DatabaseNotification::query()->find($first->id)?->read_at);
        $this->assertNotNull(DatabaseNotification::query()->find($second->id)?->read_at);
    }

    public function test_caregiver_can_save_notification_preferences(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(NotificationsCenter::class)
            ->set('preferences.'.MarketplaceEvent::REVIEW_RECEIVED.'.'.NotificationChannels::EMAIL, false)
            ->set('preferences.'.MarketplaceEvent::REVIEW_RECEIVED.'.'.NotificationChannels::IN_APP, true)
            ->set('preferences.'.MarketplaceEvent::REVIEW_RECEIVED.'.'.NotificationChannels::SMS, true)
            ->call('savePreferences')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::REVIEW_RECEIVED,
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'sms_enabled' => 1,
            'push_enabled' => 0,
        ]);

        $stored = UserNotificationPreference::query()
            ->where('user_id', $caregiver->id)
            ->where('event_key', MarketplaceEvent::REVIEW_RECEIVED)
            ->first();

        $this->assertNotNull($stored);
        $this->assertFalse((bool) $stored->email_enabled);
    }

    public function test_family_user_cannot_access_caregiver_notification_center(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/caregiver/notifications');
        $response->assertForbidden();
    }

    public function test_caregiver_nav_shows_notifications_badge_count(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        $this->createNotification($caregiver, MarketplaceEvent::CAREGIVER_HIRED, 'You were hired');

        $response = $this->actingAs($caregiver)->get('/dashboard');
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
