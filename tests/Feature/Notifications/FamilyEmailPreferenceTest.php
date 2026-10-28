<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Family\NotificationsCenter;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\FamilyAccountMember;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationPreferenceResolver;
use App\Services\Notifications\UnreadMessageEmailService;
use App\Support\MarketplaceEvent;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyEmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'marketplace.ops_alert_recipients' => [],
        ]);
    }

    public function test_saved_family_email_opt_out_survives_reload_and_blocks_every_listed_event(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $events = MarketplaceNotificationPresentation::eventsForRole('family');

        $this->disableEmail($family, $events);

        $reloaded = Livewire::actingAs($family)->test(NotificationsCenter::class);
        $notifications = app(MarketplaceNotificationService::class);

        foreach ($events as $event) {
            $reloaded->assertSet('preferences.'.$event.'.email', false)
                ->assertSet('preferences.'.$event.'.in_app', true);

            $this->assertDatabaseHas('user_notification_preferences', [
                'user_id' => $family->id,
                'event_key' => $event,
                'email_enabled' => false,
                'in_app_enabled' => true,
            ]);

            $notifications->notify($family, $event, 'Family update', 'An update is available.');
        }

        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame(count($events), $family->notifications()->count());
        $this->assertSame(0, MarketplaceNotificationDelivery::query()->where('channel', 'email')->count());
        $this->assertSame(count($events), MarketplaceNotificationDelivery::query()
            ->where('channel', 'in_app')->where('status', 'sent')->count());
    }

    public function test_reenabling_one_email_event_keeps_other_email_events_disabled(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->disableEmail($family, MarketplaceNotificationPresentation::eventsForRole('family'));

        Livewire::actingAs($family)->test(NotificationsCenter::class)
            ->set('preferences.'.MarketplaceEvent::NEW_APPLICANT.'.email', true)
            ->call('savePreferences')
            ->assertHasNoErrors();

        $notifications = app(MarketplaceNotificationService::class);
        $notifications->notify($family, MarketplaceEvent::NEW_APPLICANT, 'New caregiver reply', 'Review the reply.');
        $notifications->notify($family, MarketplaceEvent::SHIFT_STARTED, 'Visit started', 'The caregiver has arrived.');

        $this->assertCount(1, $this->transport()->messages());
        $this->assertStringContainsString('New caregiver reply', $this->transport()->messages()->first()->getOriginalMessage()->getSubject());
        $this->assertSame(2, $family->notifications()->count());
        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::SHIFT_STARTED,
            'email_enabled' => false,
        ]);
    }

    public function test_shared_family_recipients_each_keep_their_own_email_preference(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family']);
        $request = $this->careRequest($owner);
        FamilyAccountMember::query()->create([
            'family_account_id' => $request->family_account_id,
            'user_id' => $member->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        $this->disableEmail($owner, [MarketplaceEvent::NEW_APPLICANT]);

        app(MarketplaceNotificationService::class)->notify(
            $owner,
            MarketplaceEvent::NEW_APPLICANT,
            'New caregiver reply',
            'Review the reply.',
            subject: $request,
        );

        $this->assertCount(1, $this->transport()->messages());
        $this->assertSame($member->email, $this->transport()->messages()->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $member->notifications()->count());
        $this->assertFalse(UserNotificationPreference::query()->where('user_id', $member->id)->exists());
    }

    public function test_saving_email_opt_out_suppresses_already_pending_chat_and_support_emails(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = $this->careRequest($family);
        $conversation = CareRequestConversation::findOrCreateForRequest($request, $caregiver->id, $family->id);
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $family->id,
            'category' => 'general',
            'subject' => 'Help with care',
            'description' => 'A question about care.',
            'status' => 'open',
        ]);
        $notifications = app(MarketplaceNotificationService::class);
        $notifications->notify($family, MarketplaceEvent::MESSAGE_RECEIVED, 'New message', 'A caregiver replied.', subject: $conversation);
        $notifications->notify($family, MarketplaceEvent::SUPPORT_TICKET_REPLY, 'Support reply', 'Support replied.', subject: $ticket);

        $this->assertSame(2, MarketplaceNotificationDelivery::query()->where('channel', 'email')->where('status', 'pending')->count());
        $this->disableEmail($family, [MarketplaceEvent::MESSAGE_RECEIVED, MarketplaceEvent::SUPPORT_TICKET_REPLY]);
        $this->travel(6)->minutes();

        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame(2, MarketplaceNotificationDelivery::query()->where('channel', 'email')->where('status', 'suppressed')->count());
        $this->assertSame(2, $family->notifications()->count());
    }

    public function test_email_channel_override_cannot_bypass_a_familys_saved_opt_out(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->disableEmail($family, [MarketplaceEvent::NEW_APPLICANT]);

        app(MarketplaceNotificationService::class)->notify(
            $family,
            MarketplaceEvent::NEW_APPLICANT,
            'New caregiver reply',
            'Review the reply.',
            channelOverrides: ['email' => true],
        );

        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame(1, $family->notifications()->count());
        $this->assertSame(0, MarketplaceNotificationDelivery::query()->where('channel', 'email')->count());
    }

    public function test_reserved_email_is_suppressed_if_the_family_opts_out_before_sending(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $delivery = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::NEW_APPLICANT,
            'channel' => 'email',
            'status' => 'queued',
            'payload' => ['email' => ['title' => 'New caregiver reply', 'body' => 'Review the reply.', 'url' => null]],
        ]);
        $this->disableEmail($family, [MarketplaceEvent::NEW_APPLICANT]);

        app(MarketplaceNotificationService::class)->sendReservedEmail($family, $delivery);

        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame('suppressed', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->sent_at);
    }

    public function test_administrator_operations_email_override_remains_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        UserNotificationPreference::query()->create([
            'user_id' => $admin->id,
            'event_key' => MarketplaceEvent::SUPPORT_TICKET_REPLY,
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);

        app(MarketplaceNotificationService::class)->notify(
            $admin,
            MarketplaceEvent::SUPPORT_TICKET_REPLY,
            'Operations attention',
            'Human attention is needed.',
            channelOverrides: ['email' => true],
        );

        $this->assertCount(1, $this->transport()->messages());
        $this->assertSame($admin->email, $this->transport()->messages()->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_opt_out_at_laravels_final_send_check_keeps_the_delivery_suppressed(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $delivery = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::NEW_APPLICANT,
            'channel' => 'email',
            'status' => 'queued',
            'payload' => ['email' => ['title' => 'New caregiver reply', 'body' => 'Review the reply.', 'url' => null]],
        ]);
        $preferences = $this->mock(NotificationPreferenceResolver::class);
        $preferences->shouldReceive('resolve')
            ->with($family, MarketplaceEvent::NEW_APPLICANT)
            ->once()->ordered()->andReturn(['email' => true]);
        $preferences->shouldReceive('resolve')
            ->with($family, MarketplaceEvent::NEW_APPLICANT)
            ->once()->ordered()->andReturn(['email' => false]);

        app(MarketplaceNotificationService::class)->sendReservedEmail($family, $delivery);

        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame('suppressed', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->sent_at);
    }

    public function test_queued_notification_rechecks_email_opt_out_without_dropping_in_app_delivery(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
        $family = User::factory()->create(['role' => 'family']);
        $family->notify(new MarketplaceEventNotification(
            eventKey: MarketplaceEvent::NEW_APPLICANT,
            title: 'New caregiver reply',
            body: 'Review the reply.',
            url: null,
            channels: ['mail', 'database'],
        ));
        $queued = DB::table('jobs')->orderBy('id')->get();
        $this->assertCount(2, $queued);
        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame(0, $family->notifications()->count());

        $this->disableEmail($family, [MarketplaceEvent::NEW_APPLICANT]);

        foreach ($queued as $row) {
            $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
            $job = unserialize($payload['data']['command']);
            $this->assertInstanceOf(SendQueuedNotifications::class, $job);
            $job->handle(app(ChannelManager::class));
        }

        $this->assertCount(0, $this->transport()->messages());
        $this->assertSame(1, $family->notifications()->count());
        $this->assertSame(MarketplaceEvent::NEW_APPLICANT, $family->notifications()->first()->data['event_key']);
    }

    /** @param list<string> $events */
    private function disableEmail(User $family, array $events): void
    {
        $center = Livewire::actingAs($family)->test(NotificationsCenter::class);
        $preferences = $center->get('preferences');
        foreach ($events as $event) {
            $preferences[$event]['email'] = false;
            $preferences[$event]['in_app'] = true;
        }

        $center->set('preferences', $preferences)->call('savePreferences')->assertHasNoErrors();
    }

    private function careRequest(User $family): CareRequest
    {
        return CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Care',
            'status' => 'open',
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
    }

    private function transport(): ArrayTransport
    {
        return Mail::mailer()->getSymfonyTransport();
    }
}
