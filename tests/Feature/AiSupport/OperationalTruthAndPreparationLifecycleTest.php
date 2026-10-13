<?php

namespace Tests\Feature\AiSupport;

use App\Listeners\InvalidateAiSupportConfirmationsOnLogout;
use App\Livewire\Family\CareProfileEditor;
use App\Models\AiSupportIncident;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPreparation;
use App\Models\FamilyAccountMember;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportHealthMonitorService;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalTruthAndPreparationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Notification::fake();
        config([
            'ai_support.runtime_available' => true,
            'ai_support.controls' => [
                ...config('ai_support.controls'),
                'master_enabled' => true, 'user_visible_enabled' => true,
                'general_release_enabled' => true, 'human_only' => false,
                'role.family' => true, 'capability.support_answers_v1' => true,
                'capability.semantic_navigation_v1' => true,
            ],
        ]);
    }

    public function test_critical_incident_does_not_claim_a_stop_when_controls_remain_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $incident = app(AiSupportIncidentService::class)->open('synthetic_delivery_failed', 'Synthetic notification failure.');

        $this->assertSame('no_confirmed_stop', data_get($incident->safe_metadata, 'containment.state'));
        $this->assertTrue(app(AiSupportControlService::class)->enabled('capability.support_answers_v1'));
        $this->assertDatabaseCount('ai_support_control_versions', 0);
        Notification::assertSentTo($admin, MarketplaceEventNotification::class, fn ($notification): bool => str_contains($notification->toArray($admin)['body'], 'No control stop was confirmed.'));
    }

    public function test_stop_notification_identifies_the_persisted_control_version_and_deduplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        $stopped = $controls->systemStop('capability.support_answers_v1', 'synthetic_cost_stop', 'Synthetic cost ceiling reached.');
        $controls->systemStop('capability.support_answers_v1', 'synthetic_cost_stop', 'Synthetic cost ceiling reached.');
        $incident = AiSupportIncident::query()->sole();

        $this->assertSame('control_stopped', data_get($incident->safe_metadata, 'containment.state'));
        $this->assertSame($stopped->id, data_get($incident->safe_metadata, 'containment.control_version_id'));
        $this->assertFalse(data_get($incident->safe_metadata, 'containment.control_enabled'));
        $this->assertDatabaseCount('marketplace_notification_deliveries', 2);
        Notification::assertSentTo($admin, MarketplaceEventNotification::class, fn ($notification): bool => str_contains($notification->toArray($admin)['body'], 'A stopped control was confirmed'));
    }

    public function test_handoff_invalidates_an_applied_preparation_before_form_consumption(): void
    {
        [$family, $ticket, $preparation] = $this->appliedPreparation();
        app(AiSupportHandoffService::class)->transfer($family, $ticket);

        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertFalse(session()->has(AiSupportPreparationService::SESSION_KEY));
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        Http::assertNothingSent();
    }

    public function test_control_stop_cancels_applied_preparation_and_it_cannot_return_after_resume(): void
    {
        [$family, , $preparation] = $this->appliedPreparation();
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        $controls->stopAllAutomation($admin);
        $controls->resumeAutomation($admin);

        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertSame([], $preparation->fresh()->payload);
    }

    public function test_direct_admin_reply_cleans_up_the_applied_preparation(): void
    {
        [$family, $ticket, $preparation] = $this->appliedPreparation();
        $admin = User::factory()->create(['role' => 'admin']);
        app(SupportTicketMessagingService::class)->sendAdminReply($ticket, $admin, 'A person is helping you now.', (string) Str::uuid());

        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
    }

    public function test_consumption_rechecks_membership_after_account_access_is_removed(): void
    {
        [$family, , $preparation] = $this->appliedPreparation();
        FamilyAccountMember::query()->where('user_id', $family->id)->update(['status' => 'removed']);

        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertFalse(session()->has(AiSupportPreparationService::SESSION_KEY));
        $this->assertDatabaseCount('family_accounts', 1);
    }

    public function test_expiry_removes_the_session_pointer_and_the_prepared_payload(): void
    {
        [$family, , $preparation] = $this->appliedPreparation();
        $this->travel(25)->hours();

        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertFalse(session()->has(AiSupportPreparationService::SESSION_KEY));
    }

    public function test_logout_cancels_preparation_for_that_actor_and_clears_the_pointer(): void
    {
        [$family, , $preparation] = $this->appliedPreparation();
        app(InvalidateAiSupportConfirmationsOnLogout::class)->handle(new Logout('web', $family));

        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertFalse(session()->has(AiSupportPreparationService::SESSION_KEY));
        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
    }

    public function test_consumption_is_one_time_and_handoff_preserves_manual_form_edits(): void
    {
        [$family, $ticket, $preparation] = $this->appliedPreparation();
        $form = Livewire::actingAs($family)->test(CareProfileEditor::class)
            ->assertSet('preferredName', 'Prepared name')
            ->set('preferredName', 'My manually edited name');
        app(AiSupportHandoffService::class)->transfer($family, $ticket);

        $form->call('$refresh')->assertSet('preferredName', 'My manually edited name');
        $this->assertSame(AiSupportPreparation::STATE_CONSUMED, $preparation->fresh()->state);
        $this->assertSame([], $preparation->fresh()->payload);
        $this->assertSame([], app(AiSupportPreparationService::class)->consume($family, 'care_profile_v1'));
        $this->assertDatabaseCount('care_recipient_profiles', 0);
    }

    public function test_health_snapshot_is_read_only_and_monitor_reports_delayed_delivery_without_claiming_a_stop(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $delivery = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $admin->id, 'event_key' => 'support_ticket_reply', 'channel' => 'email',
            'status' => 'queued', 'dedupe_key' => 'support-handoff-synthetic', 'payload' => [],
        ]);
        $delivery->forceFill(['created_at' => now()->subMinutes(11)])->save();
        $monitor = app(AiSupportHealthMonitorService::class);
        $snapshot = $monitor->snapshot();
        $this->assertSame(1, $snapshot['metrics']['queued_notifications_over_ten_minutes']);
        $this->assertFalse($snapshot['metrics']['conversation_sample_sufficient']);
        $this->assertDatabaseCount('ai_support_incidents', 0);
        $this->assertSame([], $snapshot['last_run']);

        $monitor->run();
        $this->assertSame('succeeded', $monitor->snapshot()['last_run']['status']);
        $this->assertSame('no_confirmed_stop', data_get(AiSupportIncident::query()->sole()->safe_metadata, 'containment.state'));
        $this->assertTrue(app(AiSupportControlService::class)->enabled('capability.support_answers_v1'));
    }

    private function appliedPreparation(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountContext::class)->account($family);
        $ticket = SupportTicket::query()->create([
            'family_account_id' => $account->id, 'family_visibility' => 'opener_only',
            'opener_user_id' => $family->id, 'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => 'Synthetic preparation', 'description' => 'Prepare a profile',
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
        $this->actingAs($family);
        $service = app(AiSupportPreparationService::class);
        $preparation = $service->prepare($family, $ticket, 'care_profile_v1', ['preferred_name' => 'Prepared name']);
        $action = AiSupportMessageAction::query()->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_PREPARATION)->sole();
        $service->applyFromAction($family, $ticket, $action->id);

        return [$family, $ticket, $preparation];
    }
}
