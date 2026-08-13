<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\CareRequest;
use App\Models\DataRetentionHold;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AiSupport\AiSupportEventRecorder;
use App\Services\AiSupport\AiSupportPilotGrantService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetentionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_resolution_starts_authoritative_clocks_and_reopening_restarts_them(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($family);
        $event = app(AiSupportEventRecorder::class)->record($ticket, 'answer_delivered', [
            'capability_id' => 'support_answers_v1',
            'result_code' => 'delivered',
        ]);
        $this->assertNull($event->delete_after);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        $ticket->forceFill(['status' => SupportTicket::STATUS_RESOLVED, 'resolved_at' => now()])->save();
        $ticket->refresh();
        $firstStart = $ticket->retention_started_at;
        $this->assertTrue($firstStart->equalTo(now()));
        $this->assertTrue($ticket->transcript_delete_after->equalTo(now()->addMonths(12)));
        $this->assertTrue($event->fresh()->delete_after->equalTo(now()->addMonths(24)));

        CarbonImmutable::setTestNow(now()->addDay());
        $ticket->forceFill(['status' => SupportTicket::STATUS_CLOSED])->save();
        $this->assertTrue($ticket->fresh()->retention_started_at->equalTo($firstStart));

        CarbonImmutable::setTestNow(now()->addDay());
        $ticket->forceFill(['status' => SupportTicket::STATUS_OPEN, 'resolved_at' => null])->save();
        $this->assertNull($ticket->fresh()->retention_started_at);
        $this->assertNull($ticket->fresh()->transcript_delete_after);
        $this->assertNull($event->fresh()->delete_after);

        CarbonImmutable::setTestNow(now()->addDays(4));
        $ticket->forceFill(['status' => SupportTicket::STATUS_RESOLVED, 'resolved_at' => now()])->save();
        $this->assertTrue($ticket->fresh()->retention_started_at->equalTo(now()));
        $this->assertTrue($event->fresh()->delete_after->equalTo(now()->addMonths(24)));
    }

    public function test_retention_command_is_dry_run_then_deletes_only_due_unheld_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $request = CareRequest::withoutEvents(fn () => CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Authoritative care request survives',
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '1 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-01 09:00:00'));
        $due = $this->createTicket($family, [
            'care_request_id' => $request->id,
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'subject' => 'Sensitive due subject',
            'description' => 'Sensitive due description',
            'admin_note' => 'Sensitive private note',
        ]);
        $this->message($due, $family, 'Sensitive due message');
        $held = $this->createTicket($family, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'subject' => 'Held subject',
            'description' => 'Held description',
        ]);
        $this->message($held, $family, 'Held message');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        DataRetentionHold::query()->create([
            'id' => (string) Str::uuid(),
            'scope_type' => SupportTicket::class,
            'scope_id' => (string) $held->id,
            'reason_category' => 'legal',
            'authority_reference' => 'LEGAL-2026-001',
            'owner_user_id' => $admin->id,
            'starts_at' => now()->subDay(),
            'review_at' => now()->addMonth(),
            'expires_at' => null,
            'created_at' => now(),
        ]);
        $duePreview = $this->expiredPreview($due, $family, 'Due preview content');
        $heldPreview = $this->expiredPreview($held, $family, 'Held preview content');

        $this->artisan('ai-support:apply-retention')
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();
        $this->assertDatabaseHas('support_ticket_messages', ['body' => 'Sensitive due message']);
        $this->assertDatabaseCount('data_deletion_evidence', 0);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'title' => 'Authoritative care request survives',
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $due->id,
            'subject' => 'Support conversation content deleted',
            'description' => 'Content deleted under the approved support retention policy.',
            'admin_note' => null,
        ]);
        $this->assertDatabaseMissing('support_ticket_messages', ['support_ticket_id' => $due->id]);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $held->id,
            'body' => 'Held message',
        ]);
        $this->assertDatabaseMissing('ai_support_action_previews', ['id' => $duePreview->id]);
        $this->assertDatabaseHas('ai_support_action_previews', ['id' => $heldPreview->id]);
        $this->assertDatabaseHas('data_deletion_evidence', [
            'data_class' => 'support_transcript_content',
            'retention_policy_version' => 'ai-support-retention-v1',
            'result' => 'passed',
        ]);
        $this->assertDatabaseHas('data_deletion_evidence', [
            'data_class' => 'action_preview_records',
            'record_count' => 1,
            'result' => 'passed',
        ]);

        $evidence = json_encode(DB::table('data_deletion_evidence')->get(), JSON_THROW_ON_ERROR);
        foreach (['Sensitive due subject', 'Sensitive due description', 'Sensitive due message', 'Sensitive private note'] as $content) {
            $this->assertStringNotContainsString($content, $evidence);
        }

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        $this->assertSame(1, DB::table('data_deletion_evidence')->where('data_class', 'support_transcript_content')->count());
    }

    public function test_expired_hold_no_longer_suspends_deletion(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-01 09:00:00'));
        $ticket = $this->createTicket($family, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'description' => 'Expired hold content',
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        DataRetentionHold::query()->create([
            'id' => (string) Str::uuid(),
            'scope_type' => SupportTicket::class,
            'scope_id' => (string) $ticket->id,
            'reason_category' => 'security',
            'authority_reference' => 'SEC-EXPIRED',
            'starts_at' => now()->subMonths(2),
            'review_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'created_at' => now()->subMonths(2),
        ]);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();

        $this->assertNotNull($ticket->fresh()->transcript_deleted_at);
    }

    public function test_overdue_review_does_not_release_an_open_ended_hold(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-01 09:00:00'));
        $ticket = $this->createTicket($family, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'description' => 'Open ended held content',
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        DataRetentionHold::query()->create([
            'id' => (string) Str::uuid(),
            'scope_type' => SupportTicket::class,
            'scope_id' => (string) $ticket->id,
            'reason_category' => 'legal',
            'authority_reference' => 'LEGAL-REVIEW-OVERDUE',
            'starts_at' => now()->subMonths(2),
            'review_at' => now()->subDay(),
            'expires_at' => null,
            'created_at' => now()->subMonths(2),
        ]);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();

        $this->assertNull($ticket->fresh()->transcript_deleted_at);
        $this->assertSame('Open ended held content', $ticket->fresh()->description);
    }

    public function test_deleted_user_cannot_leave_a_no_expiry_grant_unbounded(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $grant = app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            now(),
            null,
            'Explicit no expiry pilot for deletion test',
            (string) Str::uuid(),
            true,
        );
        $family->delete();
        $this->assertNull($grant->fresh()->user_id);
        $this->assertNull($grant->fresh()->retain_until);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();

        $grant->refresh();
        $this->assertSame('target_user_deleted', $grant->revocation_reason);
        $this->assertTrue($grant->revoked_at->equalTo(now()));
        $this->assertTrue($grant->retain_until->equalTo(now()->addMonths(24)));
        $this->assertDatabaseHas('ai_support_admin_audit_events', [
            'action' => 'pilot_grant_retired_after_user_deletion',
            'subject_id' => $grant->id,
            'reason_code' => 'target_user_deleted',
        ]);
    }

    private function createTicket(User $opener, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'opener_user_id' => $opener->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Need help',
            'description' => 'Please help me use LoLo.',
        ], $overrides));
    }

    private function message(SupportTicket $ticket, User $sender, string $body): SupportTicketMessage
    {
        return SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => $sender->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'responder_type' => SupportTicketMessage::RESPONDER_HUMAN,
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function expiredPreview(SupportTicket $ticket, User $actor, string $content): AiSupportActionPreview
    {
        return AiSupportActionPreview::query()->create([
            'id' => (string) Str::uuid(),
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor->id,
            'capability_id' => 'test_preview',
            'tool_id' => 'test.preview',
            'tool_version' => 'v1',
            'preview_payload' => ['content' => $content],
            'material_hash' => hash('sha256', $content),
            'confirmation_reference_hash' => hash('sha256', (string) Str::uuid()),
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
        ]);
    }
}
