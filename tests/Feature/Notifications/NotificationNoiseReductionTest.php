<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Messaging\Inbox;
use App\Mail\Ops\NewCareRequestOpsAlertMail;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\FamilyAccountMember;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationReadService;
use App\Services\Notifications\UnreadMessageEmailService;
use App\Services\Ops\OpsAlertService;
use App\Services\Support\SupportTicketMessagingService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationNoiseReductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.ops_alert_recipients' => []]);
    }

    public function test_unread_chat_messages_are_delayed_and_batched_into_one_email(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        Notification::fake();
        $this->message($family, $thread, 'first');
        $this->travel(1)->minutes();
        $this->message($family, $thread, 'second');
        $this->assertSame(0, $this->emailCount($family));
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->travel(4)->minutes();
        $this->artisan('homecare:dispatch-unread-message-emails')->assertSuccessful();

        $this->assertSame(1, $this->emailCount($family));
        Notification::assertSentTo($family, MarketplaceEventNotification::class, function ($notification, $channels) use ($family) {
            $mail = $notification->toMail($family);

            return $channels === ['mail'] && str_contains($mail->viewData['body'], '2 new messages')
                && str_contains($mail->viewData['url'], '/notifications/email/click/')
                && $mail->viewData['ctaLabel'] === 'Read message';
        });
        $this->assertSame(1, MarketplaceNotificationDelivery::where('status', 'batched')->count());
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
    }

    public function test_reading_chat_cancels_pending_email_and_clears_only_its_alerts(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        $this->message($family, $thread, 'read-me');
        $unrelated = $this->alert($family, ['conversation_id' => $thread->id + 100]);
        $this->assertSame(2, $family->unreadNotifications()->count());
        $this->travel(1)->minutes();

        Livewire::actingAs($family)->test(Inbox::class, ['conversation' => $thread->id])
            ->assertDispatched('notifications-read');

        $this->assertSame(1, $family->unreadNotifications()->count());
        $this->assertNull($unrelated->fresh()->read_at);
        $this->assertSame(1, MarketplaceNotificationDelivery::where('status', 'suppressed')->count());
        $this->travel(5)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
    }

    public function test_caregiver_reading_chat_also_clears_notifications(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        $this->message($caregiver, $thread, 'caregiver-read');
        $this->travel(1)->minutes();
        Livewire::actingAs($caregiver)->test(Inbox::class, ['conversation' => $thread->id]);
        $this->assertSame(0, $caregiver->unreadNotifications()->count());
        $this->assertSame(1, MarketplaceNotificationDelivery::where('status', 'suppressed')->count());
    }

    public function test_existing_read_conversation_alerts_are_reconciled_without_clearing_newer_alerts(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        $old = $this->alert($family, ['conversation_id' => $thread->id]);
        $this->travel(1)->minutes();
        $thread->familyReads()->create(['user_id' => $family->id, 'last_read_at' => now()]);
        $this->travel(1)->minutes();
        $new = $this->alert($family, ['conversation_id' => $thread->id]);

        app(NotificationReadService::class)->synchronize($family);

        $this->assertNotNull($old->fresh()->read_at);
        $this->assertNull($new->fresh()->read_at);
    }

    public function test_repeated_chat_emails_have_a_per_conversation_cooldown(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        Notification::fake();
        $this->message($family, $thread, 'first');
        $this->travel(5)->minutes();
        $this->assertSame(1, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->travel(1)->minutes();
        $this->message($family, $thread, 'second');
        $this->travel(5)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->travel(24)->minutes();
        $this->assertSame(1, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(2, $this->emailCount($family));
    }

    public function test_preferences_are_rechecked_before_delayed_email(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        Notification::fake();
        $this->message($family, $thread, 'opt-out');
        UserNotificationPreference::create(['user_id' => $family->id, 'event_key' => MarketplaceEvent::MESSAGE_RECEIVED, 'email_enabled' => false]);
        $this->travel(5)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(0, $this->emailCount($family));
    }

    public function test_removed_participant_does_not_receive_queued_message_email(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        Notification::fake();
        $this->message($caregiver, $thread, 'revoked');
        $thread->update(['caregiver_user_id' => User::factory()->create(['role' => 'caregiver'])->id]);
        $this->travel(5)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(0, $this->emailCount($caregiver));
    }

    public function test_human_support_reply_is_suppressed_when_read_in_the_app(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = SupportTicket::create(['opener_user_id' => $user->id, 'category' => 'general', 'subject' => 'Help', 'description' => 'Question', 'status' => 'open']);
        app(SupportTicketMessagingService::class)->sendAdminReply($ticket, $admin, 'Here is the answer.', (string) Str::uuid());
        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->travel(1)->minutes();
        $ticket->fresh()->markReadFor($user);
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->travel(5)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
    }

    public function test_unread_support_replies_are_grouped_and_delivered(): void
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = SupportTicket::create(['opener_user_id' => $user->id, 'category' => 'general', 'subject' => 'Help', 'description' => 'Question', 'status' => 'open']);
        Notification::fake();
        app(SupportTicketMessagingService::class)->sendAdminReply($ticket, $admin, 'First answer.', (string) Str::uuid());
        app(SupportTicketMessagingService::class)->sendAdminReply($ticket, $admin, 'Another detail.', (string) Str::uuid());
        $this->assertSame(0, $this->emailCount($user));
        $this->travel(5)->minutes();
        $this->assertSame(1, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(1, $this->emailCount($user));
    }

    public function test_own_action_confirmations_never_email_even_with_old_enabled_preferences(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        Notification::fake();
        foreach ([MarketplaceEvent::INVITATION_SENT, MarketplaceEvent::APPLICATION_SUBMITTED, MarketplaceEvent::HIRE_CONFIRMED] as $event) {
            UserNotificationPreference::create(['user_id' => $user->id, 'event_key' => $event, 'email_enabled' => true, 'in_app_enabled' => true]);
            app(MarketplaceNotificationService::class)->notify($user, $event, 'Done', 'Your action succeeded.', channelOverrides: ['email' => true]);
        }
        $this->assertSame(0, $this->emailCount($user));
        $this->assertSame(3, MarketplaceNotificationDelivery::where('channel', 'in_app')->count());
        app(MarketplaceNotificationService::class)->notify($user, MarketplaceEvent::PAYMENT_ACTION_REQUIRED, 'Payment needed', 'Please update your card.');
        $this->assertSame(1, $this->emailCount($user));
    }

    public function test_generated_visits_do_not_send_operations_email_but_real_requests_do(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        config(['marketplace.ops_alert_recipients' => ['operations@example.test']]);
        $request = $thread->careRequest;
        $request->update(['is_system_generated' => true]);
        app(OpsAlertService::class)->notifyCareRequestCreated($request);
        Mail::assertNotSent(NewCareRequestOpsAlertMail::class);
        $request->update(['is_system_generated' => false]);
        app(OpsAlertService::class)->notifyCareRequestCreated($request);
        Mail::assertSent(NewCareRequestOpsAlertMail::class, 1);
        $generated = $request->replicate();
        $generated->is_system_generated = true;
        $generated->save();
        Mail::assertSent(NewCareRequestOpsAlertMail::class, 1);
    }

    public function test_dashboard_notification_link_marks_only_the_owners_selected_notification_read(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $other = User::factory()->create(['role' => 'family']);
        $first = $this->alert($user, []);
        $second = $this->alert($user, []);
        $this->actingAs($other)->get(route('notifications.open', $first->id))->assertNotFound();
        $this->assertNull($first->fresh()->read_at);
        $this->actingAs($user)->get(route('notifications.open', $first->id))->assertRedirect(route('messages.index'));
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at);
    }

    public function test_shared_family_read_state_does_not_suppress_another_members_email(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::create(['family_account_id' => $thread->family_account_id, 'user_id' => $member->id, 'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $this->message($family, $thread, 'shared-message');
        $this->travel(1)->minutes();
        $thread->markRead($family);
        $this->assertSame(0, $family->unreadNotifications()->count());
        $this->assertSame(1, $member->unreadNotifications()->count());
        Notification::fake();
        $this->travel(4)->minutes();
        $this->assertSame(1, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(0, $this->emailCount($family));
        $this->assertSame(1, $this->emailCount($member));
    }

    public function test_admin_operations_alerts_using_support_event_remain_immediate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'family']);
        $ticket = SupportTicket::create(['opener_user_id' => $user->id, 'category' => 'general', 'subject' => 'Help', 'description' => 'Question', 'status' => 'open']);
        Notification::fake();
        foreach ([null, $ticket] as $index => $subject) {
            app(MarketplaceNotificationService::class)->notify($admin, MarketplaceEvent::SUPPORT_TICKET_REPLY, 'Operations attention', 'Human attention is needed.', subject: $subject, dedupeKey: 'ops-'.$index, channelOverrides: ['email' => true]);
        }
        $this->assertSame(2, $this->emailCount($admin));
        $this->assertSame(0, MarketplaceNotificationDelivery::where('status', 'pending')->count());
    }

    public function test_in_app_delivery_is_immediate_even_with_a_database_queue(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        config(['queue.default' => 'database']);
        $this->message($family, $thread, 'no-queue-race');
        $this->assertSame(1, $family->unreadNotifications()->count());
        $this->assertDatabaseCount('jobs', 0);
        $thread->markRead($family);
        $this->assertSame(0, $family->unreadNotifications()->count());
    }

    public function test_interrupted_email_is_not_resent_and_does_not_block_later_messages_forever(): void
    {
        [$family, $caregiver, $thread] = $this->conversation();
        Notification::fake();
        $this->message($family, $thread, 'interrupted');
        MarketplaceNotificationDelivery::where('channel', 'email')->update(['status' => 'sending']);
        $this->travel(16)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(1, MarketplaceNotificationDelivery::where('status', 'unconfirmed')->count());
        $this->message($family, $thread, 'later');
        $this->travel(31)->minutes();
        $this->assertSame(1, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(1, $this->emailCount($family));
    }

    public function test_notification_open_link_rejects_external_redirects(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $item = $this->alert($user, []);
        foreach (['https://example.org/outside', '/\\example.org/outside'] as $url) {
            $item->update(['data' => ['url' => $url]]);
            $this->actingAs($user)->get(route('notifications.open', $item->id))->assertRedirect(route('dashboard'));
        }
    }

    private function conversation(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'onboarding_completed_at' => now()]);
        $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Care', 'status' => 'open', 'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'shortlisted', 'proposed_rate' => 28]);
        $thread = CareRequestConversation::findOrCreateForApplication($application, $family->id);
        $thread->update(['family_last_read_at' => null, 'caregiver_last_read_at' => null]);

        return [$family, $caregiver, $thread];
    }

    private function message(User $recipient, CareRequestConversation $thread, string $key): void
    {
        $senderId = $recipient->role === 'family' ? $thread->caregiver_user_id : $thread->family_user_id;
        $thread->messages()->create(['sender_user_id' => $senderId, 'body' => $key]);
        $thread->update(['last_message_at' => now(), 'last_message_sender_id' => $senderId]);
        app(MarketplaceNotificationService::class)->notify($recipient, MarketplaceEvent::MESSAGE_RECEIVED, 'New message', 'You have a new message.', route('messages.show', $thread->id), ['conversation_id' => $thread->id], $thread, $key);
    }

    private function emailCount(User $user): int
    {
        return Notification::sent($user, MarketplaceEventNotification::class, fn ($notification, $channels) => $channels === ['mail'])->count();
    }

    private function alert(User $user, array $payload)
    {
        return $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => MarketplaceEventNotification::class, 'data' => ['event_key' => MarketplaceEvent::MESSAGE_RECEIVED, 'title' => 'Message', 'body' => 'New message', 'url' => route('messages.index'), 'payload' => $payload]]);
    }
}
