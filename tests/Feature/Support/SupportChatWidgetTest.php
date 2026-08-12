<?php

namespace Tests\Feature\Support;

use App\Livewire\Admin\SupportTicketShow;
use App\Livewire\Admin\SupportTicketsQueue;
use App\Livewire\Support\ChatWidget;
use App\Mail\Ops\SupportTicketCreatedOpsAlertMail;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\FamilyAccounts\FamilyAccountAccessService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use App\Services\Support\SupportChatService;
use App\Services\Support\SupportMessageRateLimiter;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupportChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_is_rendered_only_for_signed_in_family_and_caregiver_users(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($family)->get(route('profile'))
            ->assertOk()
            ->assertSee('data-testid="support-chat-widget"', false)
            ->assertSee('Chat with LoLo Support');

        $this->actingAs($caregiver)->get(route('profile'))
            ->assertOk()
            ->assertSee('data-testid="support-chat-widget"', false);

        $this->actingAs($admin)->get(route('profile'))
            ->assertOk()
            ->assertDontSee('data-testid="support-chat-widget"', false);

        $this->actingAs($sales)->get(route('profile'))
            ->assertOk()
            ->assertDontSee('data-testid="support-chat-widget"', false);

        auth()->logout();
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-testid="support-chat-widget"', false);
    }

    public function test_first_message_creates_one_chat_ticket_with_safe_context_and_notifies_operations(): void
    {
        Notification::fake();
        Mail::fake();
        config(['marketplace.ops_alert_recipients' => ['ops@example.com']]);

        $family = User::factory()->create(['role' => 'family', 'name' => 'Maya Family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $clientMessageId = (string) Str::uuid();
        $body = "  I need help understanding tomorrow's visit.  ";

        Livewire::actingAs($family)
            ->test(ChatWidget::class, [
                'originRoute' => 'family.requests.show',
                'originPath' => '/family/requests/184?token=must-not-be-stored',
            ])
            ->call('sendMessage', $body, $clientMessageId)
            ->assertDispatched('support-chat-message-sent');

        $ticket = SupportTicket::query()->sole();
        $this->assertSame(SupportTicket::SOURCE_CHAT_WIDGET, $ticket->source);
        $this->assertSame('general', $ticket->category);
        $this->assertSame('normal', $ticket->priority);
        $this->assertSame('Chat: I need help understanding tomorrow\'s visit.', $ticket->subject);
        $this->assertSame("I need help understanding tomorrow's visit.", $ticket->description);
        $this->assertSame($clientMessageId, $ticket->initial_client_message_id);
        $this->assertSame('family.requests.show', $ticket->origin_route);
        $this->assertSame('/family/requests/184', $ticket->origin_path);
        $this->assertSame($family->id, $ticket->opener_user_id);
        $this->assertNotNull($ticket->family_account_id);
        $this->assertSame('shared_care', $ticket->family_visibility);
        $this->assertSame($family->id, $ticket->last_public_message_sender_id);
        $this->assertDatabaseCount('support_ticket_messages', 0);

        Notification::assertSentToTimes($admin, MarketplaceEventNotification::class, 1);
        Mail::assertSent(SupportTicketCreatedOpsAlertMail::class, 1);
    }

    public function test_retried_initial_request_is_globally_idempotent(): void
    {
        Notification::fake();
        Mail::fake();

        $family = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $clientMessageId = (string) Str::uuid();
        $chat = app(SupportChatService::class);

        $first = $chat->startConversation($family, 'Please help with my schedule.', $clientMessageId, 'dashboard', '/dashboard');
        $second = $chat->startConversation($family, 'Please help with my schedule.', $clientMessageId, 'dashboard', '/dashboard');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_ticket_messages', 0);
        Notification::assertSentToTimes($admin, MarketplaceEventNotification::class, 1);

        Livewire::actingAs($family)
            ->test(ChatWidget::class, ['originRoute' => 'dashboard', 'originPath' => '/dashboard'])
            ->call('sendMessage', 'Please help with my schedule.', $clientMessageId)
            ->assertDispatched('support-chat-message-sent');

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    public function test_origin_sanitizer_strips_queries_and_redacts_sensitive_path_parameters(): void
    {
        $chat = app(SupportChatService::class);

        $this->assertSame(
            ['family.requests.show', '/family/requests/42'],
            $chat->sanitizeOrigin('family.requests.show', '/family/requests/42?secret=hidden'),
        );
        $this->assertSame(
            ['family.invitations.show', '/family/invitations/{token}'],
            $chat->sanitizeOrigin('family.invitations.show', '/family/invitations/very-secret-token'),
        );
        $this->assertSame([null, null], $chat->sanitizeOrigin('not.a.route', '//evil.example/path'));
    }

    public function test_active_chat_selection_prefers_open_then_resolved_and_ignores_structured_tickets(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $chat = app(SupportChatService::class);
        $resolved = $this->ticket($family, [
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'status' => SupportTicket::STATUS_RESOLVED,
            'subject' => 'Resolved chat',
            'last_public_message_at' => now(),
        ]);
        $structured = $this->ticket($family, [
            'source' => SupportTicket::SOURCE_SUPPORT_CENTER,
            'subject' => 'Newer structured request',
            'last_public_message_at' => now()->addMinute(),
        ]);
        $open = $this->ticket($family, [
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'subject' => 'Open chat',
            'last_public_message_at' => now()->subDay(),
        ]);

        $this->assertSame($open->id, $chat->conversationFor($family)?->id);
        $open->forceFill(['status' => SupportTicket::STATUS_CLOSED])->save();
        $this->assertSame($resolved->id, $chat->conversationFor($family)?->id);
        $this->assertNotSame($structured->id, $chat->conversationFor($family)?->id);
    }

    public function test_shared_family_member_can_continue_chat_and_other_family_cannot_access_it(): void
    {
        Notification::fake();
        Mail::fake();

        [$owner, $member] = $this->ownerAndMember();
        $otherFamily = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $chat = app(SupportChatService::class);
        $ticket = $chat->startConversation(
            $owner,
            'Our family needs help with a shared visit.',
            (string) Str::uuid(),
            'dashboard',
            '/dashboard',
        );
        Notification::fake();

        $this->assertSame($ticket->id, $chat->conversationFor($member)?->id);
        $this->assertFalse($otherFamily->can('view', $ticket));
        $this->assertNull($chat->conversationFor($otherFamily));

        app(SupportTicketMessagingService::class)->sendUserReply(
            $ticket,
            $member,
            'I am the family member following up.',
            (string) Str::uuid(),
        );

        $ticket->refresh();
        $this->assertTrue($ticket->isUnreadForAdmin());
        Notification::assertSentToTimes($admin, MarketplaceEventNotification::class, 1);

        $this->actingAs($otherFamily)
            ->get(route('support.tickets.show', $ticket))
            ->assertNotFound();
    }

    public function test_unassigned_follow_up_notifies_all_admins_in_app_only(): void
    {
        Notification::fake();
        Mail::fake();

        $user = User::factory()->create(['role' => 'caregiver']);
        $firstAdmin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->ticket($user, ['source' => SupportTicket::SOURCE_CHAT_WIDGET]);
        Notification::fake();
        Mail::fake();

        app(SupportTicketMessagingService::class)->sendUserReply(
            $ticket,
            $user,
            'Is anyone able to help with this?',
            (string) Str::uuid(),
        );

        Notification::assertSentToTimes($firstAdmin, MarketplaceEventNotification::class, 1);
        Notification::assertSentToTimes($secondAdmin, MarketplaceEventNotification::class, 1);
        Mail::assertNothingSent();
    }

    public function test_removed_family_member_immediately_loses_shared_chat_but_can_start_private_support(): void
    {
        Notification::fake();
        Mail::fake();

        [$owner, $member] = $this->ownerAndMember();
        $chat = app(SupportChatService::class);
        $shared = $chat->startConversation(
            $owner,
            'Shared family support before access ends.',
            (string) Str::uuid(),
            'dashboard',
            '/dashboard',
        );
        $account = app(FamilyAccountContext::class)->account($owner);
        $membership = $account->activeMemberships()->where('user_id', $member->id)->firstOrFail();
        app(FamilyAccountAccessService::class)->remove($owner, $membership);

        $this->assertFalse($member->can('view', $shared));
        $this->assertNull($chat->conversationFor($member));

        $private = $chat->startConversation(
            $member,
            'I need help after my family access ended.',
            (string) Str::uuid(),
            'family.access.ended',
            '/family/access-ended',
        );
        $this->assertNull($private->family_account_id);
        $this->assertSame($member->id, $private->opener_user_id);
        $this->assertTrue($member->can('view', $private));
    }

    public function test_claim_is_atomic_and_second_admin_cannot_overwrite_owner(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $firstAdmin = User::factory()->create(['role' => 'admin', 'name' => 'First Admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin', 'name' => 'Second Admin']);
        $ticket = $this->ticket($user, ['source' => SupportTicket::SOURCE_CHAT_WIDGET]);
        $chat = app(SupportChatService::class);
        $this->actingAs($firstAdmin);

        $claimed = $chat->claim($ticket, $firstAdmin);
        $this->assertSame($firstAdmin->id, $claimed->assigned_admin_id);
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $claimed->status);
        $this->assertNotNull($claimed->claimed_at);
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $firstAdmin->id,
            'action' => 'conversation_claimed',
        ]);

        try {
            $chat->claim($ticket->fresh(), $secondAdmin);
            $this->fail('A second administrator overwrote the conversation owner.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('First Admin', collect($exception->errors())->flatten()->first());
        }

        $this->assertSame($firstAdmin->id, $ticket->fresh()->assigned_admin_id);

        $claimed->forceFill([
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ])->save();
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $firstAdmin->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_admin_chat_surfaces_show_source_context_and_claim_action(): void
    {
        $user = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Jordan Caregiver',
            'phone' => '+19195550123',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->ticket($user, [
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'origin_route' => 'caregiver.shifts.index',
            'origin_path' => '/caregiver/shifts',
        ]);

        Livewire::actingAs($admin)
            ->test(SupportTicketsQueue::class)
            ->assertSee('Chat')
            ->assertSee('Jordan Caregiver')
            ->assertSee('Claim conversation');

        Livewire::actingAs($admin)
            ->test(SupportTicketShow::class, ['ticket' => $ticket])
            ->assertSee('Support chat context')
            ->assertSee('Jordan Caregiver')
            ->assertSee('+19195550123')
            ->assertSee('/caregiver/shifts')
            ->assertSee('Open user profile')
            ->assertSee('Claim conversation');
    }

    public function test_closed_chat_is_read_only_and_can_be_reset_to_a_new_conversation(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $ticket = $this->ticket($family, [
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'status' => SupportTicket::STATUS_CLOSED,
        ]);

        Livewire::actingAs($family)
            ->test(ChatWidget::class, ['originRoute' => 'dashboard', 'originPath' => '/dashboard'])
            ->assertSet('ticketId', $ticket->id)
            ->assertSee('closed and read-only')
            ->assertSee('Start a new conversation')
            ->assertDontSee('Ask us a question')
            ->call('startNewConversation')
            ->assertSet('ticketId', null)
            ->assertDispatched('support-chat-conversation-reset')
            ->assertSee('How can we help?');
    }

    public function test_message_rate_limit_rejects_excess_without_creating_a_message(): void
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        $ticket = $this->ticket($user, ['source' => SupportTicket::SOURCE_CHAT_WIDGET]);
        $limiter = app(SupportMessageRateLimiter::class);

        RateLimiter::clear($limiter->keyFor($user));
        foreach (range(1, SupportMessageRateLimiter::MAX_ATTEMPTS) as $attempt) {
            RateLimiter::hit($limiter->keyFor($user), SupportMessageRateLimiter::DECAY_SECONDS);
        }

        $this->expectException(ValidationException::class);
        try {
            app(SupportTicketMessagingService::class)->sendUserReply(
                $ticket,
                $user,
                'One message beyond the limit.',
                (string) Str::uuid(),
            );
        } finally {
            $this->assertDatabaseCount('support_ticket_messages', 0);
        }
    }

    public function test_chat_message_html_is_rendered_as_text_not_executable_markup(): void
    {
        Notification::fake();
        Mail::fake();

        $user = User::factory()->create(['role' => 'caregiver']);
        $body = '<script>alert("unsafe")</script><b>Help me</b>';
        app(SupportChatService::class)->startConversation(
            $user,
            $body,
            (string) Str::uuid(),
            'profile',
            '/profile',
        );

        Livewire::actingAs($user)
            ->test(ChatWidget::class, ['originRoute' => 'profile', 'originPath' => '/profile'])
            ->assertSee($body)
            ->assertDontSee($body, false);
    }

    private function ticket(User $opener, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'opener_user_id' => $opener->id,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Chat: Need help with my account',
            'description' => 'I need help understanding something in my account.',
        ], $overrides));
    }

    /** @return array{User, User} */
    private function ownerAndMember(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'chat-member@example.com']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $member->email, '127.0.0.1');
        app(FamilyAccountInvitationService::class)->accept($member, $issued['token']);

        return [$owner, $member];
    }
}
