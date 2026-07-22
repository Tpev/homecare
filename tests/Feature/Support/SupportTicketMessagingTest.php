<?php

namespace Tests\Feature\Support;

use App\Livewire\Admin\SupportTicketShow as AdminSupportTicketShow;
use App\Livewire\Admin\SupportTicketsQueue;
use App\Livewire\Support\TicketConversation;
use App\Livewire\Support\TicketsCenter;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupportTicketMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_ticket_without_messages_keeps_description_and_legacy_admin_response(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($user, [
            'description' => 'This description predates support chat.',
            'admin_note' => 'This response was already visible to the user.',
        ]);
        $ticket->forceFill([
            'last_public_message_at' => null,
            'last_public_message_sender_id' => null,
            'opener_last_read_at' => null,
            'admin_last_read_at' => null,
        ])->saveQuietly();

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->assertSee('This description predates support chat.')
            ->assertSee('Previous admin response')
            ->assertSee('This response was already visible to the user.');

        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    public function test_admin_public_reply_assigns_ticket_moves_it_in_progress_and_notifies_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user);

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket])
            ->set('messageBody', 'We are reviewing this with our operations team now.')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSet('messageBody', '')
            ->assertSet('status', SupportTicket::STATUS_IN_PROGRESS);

        $ticket->refresh();

        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_admin_id);
        $this->assertTrue($ticket->isUnreadForOpener());
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => $admin->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'body' => 'We are reviewing this with our operations team now.',
        ]);

        Notification::assertSentTo(
            $user,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification): bool => data_get($notification->toArray($user), 'payload.support_ticket_id') === $ticket->id
                && data_get($notification->toArray($user), 'url') === route('support.tickets.show', $ticket->id)
        );
    }

    public function test_user_reply_reopens_resolved_ticket_and_notifies_assigned_admin(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'caregiver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now()->subHour(),
            'assigned_admin_id' => $admin->id,
        ]);

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->assertSee('Sending a reply will reopen it.')
            ->set('messageBody', 'The same issue happened again this morning.')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertTrue($ticket->isUnreadForAdmin());
        Notification::assertSentTo(
            $admin,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification): bool => data_get($notification->toArray($admin), 'payload.support_ticket_id') === $ticket->id
                && data_get($notification->toArray($admin), 'url') === route('admin.support.tickets.show', $ticket->id)
        );
    }

    public function test_closed_ticket_is_read_only_for_user_and_service_enforces_it(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($user, ['status' => SupportTicket::STATUS_CLOSED]);

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->assertSee('This ticket is closed and read-only.')
            ->assertDontSee('Write a reply to support...');

        $this->assertFalse($user->can('reply', $ticket));

        $this->expectException(ValidationException::class);
        app(SupportTicketMessagingService::class)->sendUserReply(
            $ticket,
            $user,
            'Trying to reply to a closed ticket.',
            (string) Str::uuid(),
        );
    }

    public function test_internal_note_is_admin_only_and_does_not_notify_or_change_public_unread_state(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user);

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket])
            ->set('messageKind', SupportTicketMessage::KIND_INTERNAL_NOTE)
            ->set('messageBody', 'Private escalation note for the operations team.')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSee('Private escalation note for the operations team.');

        $ticket->refresh();

        $this->assertFalse($ticket->isUnreadForOpener());
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertNull($ticket->assigned_admin_id);
        $internalNote = SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->sole();
        $this->assertFalse($user->can('view', $internalNote));
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'kind' => SupportTicketMessage::KIND_INTERNAL_NOTE,
            'body' => 'Private escalation note for the operations team.',
        ]);
        Notification::assertNothingSent();

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->assertDontSee('Private escalation note for the operations team.');
    }

    public function test_other_user_cannot_view_ticket_or_messages(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $otherUser = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($owner);
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => $owner->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'body' => 'Sensitive support details.',
            'client_message_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('support.tickets.show', $ticket))
            ->assertNotFound()
            ->assertDontSee('Sensitive support details.');

        $this->actingAs($otherUser)
            ->get(route('admin.support.tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_unread_state_is_visible_and_clears_when_each_side_opens_ticket(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user);

        $this->assertTrue($ticket->fresh()->isUnreadForAdmin());
        $this->assertNull($ticket->assigned_admin_id);
        $this->actingAs($admin)
            ->get(route('admin.support.tickets'))
            ->assertOk()
            ->assertSee('Admin Support (1)')
            ->assertSee('1 unread support tickets')
            ->assertSee('Support activity')
            ->assertSee('#'.$ticket->id.' '.$ticket->subject)
            ->assertSee('Unassigned');

        Livewire::actingAs($admin)
            ->test(SupportTicketsQueue::class)
            ->assertSee('Unread');

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket]);

        $this->assertFalse($ticket->fresh()->isUnreadForAdmin());

        app(SupportTicketMessagingService::class)->sendAdminReply(
            $ticket->fresh(),
            $admin,
            'Here is the update you requested.',
            (string) Str::uuid(),
        );

        $this->assertTrue($ticket->fresh()->isUnreadForOpener());
        $this->actingAs($user)
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('Support Center (1)');

        Livewire::actingAs($user)
            ->test(TicketsCenter::class)
            ->assertSee('New reply');

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket]);

        $this->assertFalse($ticket->fresh()->isUnreadForOpener());
    }

    public function test_repeated_client_message_id_does_not_duplicate_message_or_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user);
        $clientMessageId = (string) Str::uuid();
        $messaging = app(SupportTicketMessagingService::class);

        $first = $messaging->sendAdminReply($ticket, $admin, 'A single idempotent reply.', $clientMessageId);
        $second = $messaging->sendAdminReply($ticket->fresh(), $admin, 'A single idempotent reply.', $clientMessageId);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('support_ticket_messages', 1);
        Notification::assertSentToTimes($user, MarketplaceEventNotification::class, 1);
    }

    public function test_user_can_load_older_public_messages(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket($user);

        foreach (range(1, 41) as $index) {
            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $admin->id,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'body' => $index === 1 ? 'Oldest retained reply' : 'Support reply '.$index,
                'client_message_id' => (string) Str::uuid(),
                'created_at' => now()->subMinutes(42 - $index),
                'updated_at' => now()->subMinutes(42 - $index),
            ]);
        }

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->assertSee('Load older messages')
            ->assertDontSee('Oldest retained reply')
            ->call('loadMore')
            ->assertSee('Oldest retained reply');
    }

    public function test_admin_can_reassign_ticket_to_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($user);

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket])
            ->set('assignedAdminId', (string) $otherAdmin->id)
            ->call('updateAssignment')
            ->assertHasNoErrors();

        $this->assertSame($otherAdmin->id, $ticket->fresh()->assigned_admin_id);
    }

    public function test_whitespace_only_message_is_rejected_without_creating_a_record(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($user);

        Livewire::actingAs($user)
            ->test(TicketConversation::class, ['ticket' => $ticket])
            ->set('messageBody', '   ')
            ->call('sendMessage')
            ->assertHasErrors(['messageBody']);

        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    public function test_closing_resolved_ticket_preserves_original_resolution_timestamp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'family']);
        $resolvedAt = now()->subDay()->startOfSecond();
        $ticket = $this->createTicket($user, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => $resolvedAt,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket])
            ->set('status', SupportTicket::STATUS_CLOSED)
            ->call('updateStatus')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->status);
        $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt));
    }

    private function createTicket(User $opener, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'opener_user_id' => $opener->id,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Need help with my account',
            'description' => 'I need help understanding something in my account.',
        ], $overrides));
    }
}
