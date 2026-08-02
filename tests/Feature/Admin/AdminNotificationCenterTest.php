<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\NotificationsCenter;
use App\Models\User;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_updates_and_save_real_channel_preferences(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MarketplaceEventNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => [
                'event_key' => MarketplaceEvent::SUPPORT_TICKET_CREATED,
                'title' => 'New support request',
                'body' => 'A family opened support request #42.',
                'url' => route('admin.support.tickets'),
                'payload' => ['email_details' => [['label' => 'Priority', 'value' => 'High']]],
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Operations notifications')
            ->assertSee('New support request')
            ->assertSee('Priority')
            ->assertSee('SMS and push are not offered yet.');

        Livewire::actingAs($admin)
            ->test(NotificationsCenter::class)
            ->set('preferences.'.MarketplaceEvent::SUPPORT_TICKET_CREATED.'.'.NotificationChannels::EMAIL, false)
            ->set('preferences.'.MarketplaceEvent::SUPPORT_TICKET_CREATED.'.'.NotificationChannels::IN_APP, true)
            ->call('savePreferences')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $admin->id,
            'event_key' => MarketplaceEvent::SUPPORT_TICKET_CREATED,
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'sms_enabled' => 0,
            'push_enabled' => 0,
        ]);
    }

    public function test_non_admin_cannot_access_admin_notifications(): void
    {
        $family = User::factory()->create(['role' => 'family', 'email_verified_at' => now()]);

        $this->actingAs($family)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }
}
