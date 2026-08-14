<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InteractiveRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_private_draft_and_recap_content_are_extinguished_but_fresh_draft_remains(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $dueTicket = $this->ticket($family, 'Due draft');
        $freshTicket = $this->ticket($family, 'Fresh draft');
        $due = $this->draft($family, $dueTicket, now()->subMinute(), 'Sensitive due recipient');
        $fresh = $this->draft($family, $freshTicket, now()->addDay(), 'Sensitive fresh recipient');
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $dueTicket->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
            'body' => 'Review your private draft.',
            'client_message_id' => (string) Str::uuid(),
        ]);
        $action = AiSupportMessageAction::query()->create([
            'id' => (string) Str::uuid(),
            'support_ticket_message_id' => $message->id,
            'support_ticket_id' => $dueTicket->id,
            'actor_user_id' => $family->id,
            'action_type' => AiSupportMessageAction::TYPE_RECAP,
            'payload' => ['recap' => ['recipient' => 'Sensitive due recipient']],
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('ai-support:apply-retention')->assertSuccessful();
        $this->assertNotNull($due->fresh()->payload);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        $this->assertNull($due->fresh()->payload);
        $this->assertSame(AiSupportRequestDraft::STATE_EXPIRED, $due->fresh()->state);
        $this->assertNull($action->fresh()->payload);
        $this->assertSame('draft_expired', $action->fresh()->invalidation_reason);
        $this->assertSame('Sensitive fresh recipient', $fresh->fresh()->payload['recipient_full_name']);
        $this->assertDatabaseHas('data_deletion_evidence', [
            'data_class' => 'private_request_draft_content',
            'record_count' => 1,
        ]);
    }

    private function ticket(User $family, string $subject): SupportTicket
    {
        return SupportTicket::query()->create([
            'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => $subject, 'description' => $subject,
        ]);
    }

    private function draft(User $family, SupportTicket $ticket, $expiresAt, string $recipient): AiSupportRequestDraft
    {
        return AiSupportRequestDraft::query()->create([
            'id' => (string) Str::uuid(),
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $family->id,
            'request_type' => 'one_time',
            'state' => AiSupportRequestDraft::STATE_COLLECTING,
            'payload' => ['recipient_full_name' => $recipient],
            'material_hash' => hash('sha256', $recipient),
            'version' => 1,
            'expires_at' => $expiresAt,
        ]);
    }
}
