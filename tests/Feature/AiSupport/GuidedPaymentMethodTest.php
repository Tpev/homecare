<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Support\ChatWidget;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportGuidedTaskService;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_card_intent_reads_missing_state_and_offers_guidance_without_a_model_call(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'I need to add a credit card.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $task = AiSupportGuidedTask::query()->sole();
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();

        $this->assertSame(AiSupportGuidedTask::STATE_OFFERED, $task->state);
        $this->assertSame('family.billing.payment_method', $task->navigation_target_id);
        $this->assertSame('add', $task->payload['mode']);
        $this->assertSame('Add payment method', $action->payload['label']);
        $this->assertStringContainsString('no payment method on file', strtolower($message->body));
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'payment_status_read',
            'result_code' => 'missing',
        ]);
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_owner_existing_card_is_read_safely_and_gets_the_update_action(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $account->forceFill(['stripe_customer_id' => 'cus_existing_safe_summary'])->save();
        $ticket = $this->automatedTicket($family, 'Where do I change my payment method?');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $task = AiSupportGuidedTask::query()->sole();
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();

        $this->assertSame('update', $task->payload['mode']);
        $this->assertSame('Update payment method', $action->payload['label']);
        $this->assertStringContainsString('ends in 4242', $message->body);
        $this->assertStringNotContainsString('cus_existing_safe_summary', $message->body);
        $this->assertStringNotContainsString('pm_bypass', $message->body);
        $this->assertStringNotContainsString('4242', json_encode($task->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_visit_cancellation_question_does_not_cancel_an_unrelated_guided_task(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Update my payment method.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);

        $handled = app(AiSupportGuidedTaskService::class)->handleContextualReply(
            $family,
            $ticket,
            'What happens if I need to cancel a booked visit?',
        );

        $this->assertFalse($handled);
        $this->assertContains($task->fresh()->state, AiSupportGuidedTask::OPEN_STATES);
    }

    public function test_owner_wanting_to_use_another_credit_card_gets_the_update_action_without_a_model_call(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $account->forceFill(['stripe_customer_id' => 'cus_another_card_intent'])->save();
        $ticket = $this->automatedTicket($family, 'Hi, I want to use another credit card.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $this->assertSame(
            'Update payment method',
            AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole()->payload['label'],
        );
        $this->assertSame('family.billing.payment_method', AiSupportGuidedTask::query()->sole()->navigation_target_id);
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_owner_follow_up_recovers_a_recent_payment_request_into_guidance_without_a_model_call(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $account->forceFill(['stripe_customer_id' => 'cus_payment_follow_up'])->save();
        $ticket = $this->automatedTicket($family, 'Hi, I want to use another credit card.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, "I'm the account owner. Please take me there.");

        Http::assertNothingSent();
        $this->assertSame(
            'Update payment method',
            AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole()->payload['label'],
        );
        $this->assertSame('family.billing.payment_method', AiSupportGuidedTask::query()->sole()->navigation_target_id);
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_owner_can_ask_which_card_is_on_file_without_sending_account_state_to_the_model(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $account->forceFill(['stripe_customer_id' => 'cus_status_question'])->save();
        $ticket = $this->automatedTicket($family, 'Which card is currently on file?');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('ends in 4242', $message->body);
        $this->assertSame(
            'Update payment method',
            AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole()->payload['label'],
        );
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_family_member_gets_safe_card_details_and_the_update_destination(): void
    {
        [, $member] = $this->eligibleFamilyMember();
        $account = app(FamilyAccountContext::class)->account($member);
        $account->forceFill(['stripe_customer_id' => 'cus_member_shared_billing'])->save();
        $ticket = $this->automatedTicket($member, 'Please change the credit card on file.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($member, $ticket, $ticket->description);

        Http::assertNothingSent();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('ends in 4242', $message->body);
        $this->assertStringNotContainsString('cus_member_shared_billing', $message->body);
        $this->assertDatabaseCount('ai_support_guided_tasks', 1);
        $this->assertDatabaseCount('ai_support_message_actions', 1);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'payment_status_read',
            'result_code' => 'ready',
        ]);
        $this->assertTrue(app(NavigationTargetRegistry::class)->allowedFor($member, 'family.billing.payment_method'));
        $this->assertContains('family.billing.payment_method', app(NavigationTargetRegistry::class)->idsFor($member));
    }

    public function test_expired_guidance_cannot_navigate_or_create_a_foreground_task(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Help me change my card.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        $task->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);
            $this->fail('Expired guided task unexpectedly started.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                'This guided step is no longer available. Ask me to start again.',
                $exception->errors()['guidedTask'][0],
            );
        }

        $this->assertSame(AiSupportGuidedTask::STATE_OFFERED, $task->fresh()->state);
        $this->assertNull($task->fresh()->started_at);
        $this->assertNull(Session::get(AiSupportGuidedTaskService::SESSION_TASK_KEY));
    }

    public function test_chat_action_starts_exact_task_and_billing_page_renders_accessible_semantic_target(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Help me add a payment method.');
        app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();

        Livewire::actingAs($family)
            ->test(ChatWidget::class, ['originRoute' => 'dashboard', 'originPath' => '/dashboard'])
            ->call('startGuidedTask', $action->id)
            ->assertRedirect(route('family.billing.show'));

        $task = AiSupportGuidedTask::query()->sole();
        $this->assertSame(AiSupportGuidedTask::STATE_NAVIGATING, $task->state);
        $this->assertSame($task->id, Session::get(AiSupportGuidedTaskService::SESSION_TASK_KEY));
        $this->assertNotNull($action->fresh()->consumed_at);

        $this->actingAs($family)
            ->get(route('family.billing.show'))
            ->assertOk()
            ->assertSee('data-ai-target="family.billing.manage_payment_method"', false)
            ->assertSee('data-testid="ai-guided-task-strip"', false)
            ->assertSee('Use the highlighted Add card securely button.');
    }

    public function test_client_arrival_and_missing_target_results_are_server_authorized_and_recorded(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Help me update my card.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);

        Livewire::actingAs($family)
            ->test(ChatWidget::class, ['originRoute' => 'family.billing.show', 'originPath' => '/family/billing'])
            ->call('guidedTaskArrived', $task->id, 'arrived');

        $this->assertSame(AiSupportGuidedTask::STATE_ARRIVED, $task->fresh()->state);
        $this->assertNotNull($task->fresh()->arrived_at);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'guided_target_arrived',
            'result_code' => 'target_arrived',
        ]);

        $other = User::factory()->create(['role' => 'family']);
        $this->expectException(ModelNotFoundException::class);
        app(AiSupportGuidedTaskService::class)->reportArrival($other, $task->id, 'target_missing');
    }

    public function test_missing_target_never_guesses_and_opens_a_truthful_recovery_message(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Help me update my payment method.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);

        app(AiSupportGuidedTaskService::class)->reportArrival($family, $task->id, 'target_missing');

        $this->assertSame(AiSupportGuidedTask::STATE_FAILED, $task->fresh()->state);
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('could not safely find the exact payment-method button', $message->body);
        $this->assertStringContainsString('not changed anything', $message->body);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'guided_target_failed',
            'result_code' => 'target_missing',
        ]);
    }

    public function test_disabled_target_never_substitutes_another_control_or_claims_a_change(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Help me add a card.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);

        app(AiSupportGuidedTaskService::class)->reportArrival($family, $task->id, 'target_disabled');

        $this->assertSame(AiSupportGuidedTask::STATE_FAILED, $task->fresh()->state);
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('button is not available right now', $message->body);
        $this->assertStringContainsString('not changed anything', $message->body);
        $this->assertStringNotContainsString('now on file', $message->body);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'guided_target_failed',
            'result_code' => 'target_disabled',
        ]);
    }

    public function test_existing_stripe_checkout_flow_verifies_completion_before_success_message(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'I need to add a credit card.');
        app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);

        $start = $this->actingAs($family)->post(route('family.billing.checkout'));
        $start->assertRedirect();
        $this->assertSame(AiSupportGuidedTask::STATE_IN_PROGRESS, AiSupportGuidedTask::query()->sole()->state);
        $this->assertStringNotContainsString('now on file', $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body);

        $returnUrl = (string) $start->headers->get('Location');
        $this->actingAs($family)
            ->get($returnUrl)
            ->assertRedirect(route('family.billing.show'))
            ->assertSessionHas('status', 'Billing method updated successfully.');

        $task = AiSupportGuidedTask::query()->sole();
        $this->assertSame(AiSupportGuidedTask::STATE_COMPLETED, $task->state);
        $this->assertSame('payment_method_verified', $task->last_result_code);
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('ending in 4242 is now on file', $message->body);
        $this->assertStringContainsString('expires', $message->body);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'guided_task_completed',
            'result_code' => 'payment_method_verified',
        ]);
        $this->assertNull(Session::get(AiSupportGuidedTaskService::SESSION_TASK_KEY));

        $this->actingAs($family)
            ->get(route('family.billing.show'))
            ->assertOk()
            ->assertSee('Billing method updated successfully.')
            ->assertSee('ending in 4242 is now on file')
            ->assertSee('data-force-open="true"', false)
            ->assertDontSee('data-testid="ai-guided-task-strip"', false);
    }

    public function test_cancelled_checkout_keeps_recovery_guidance_and_never_claims_success(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Add a new payment method.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);
        app(AiSupportGuidedTaskService::class)->markPaymentSetupStarted($family);

        $this->actingAs($family)
            ->get(route('family.billing.show', ['checkout' => 'cancel']))
            ->assertRedirect(route('family.billing.show'))
            ->assertSessionHas('status', 'No payment-method changes were made.');

        $this->assertSame(AiSupportGuidedTask::STATE_ARRIVED, $task->fresh()->state);
        $this->assertSame('secure_checkout_cancelled', $task->fresh()->last_result_code);
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('was not changed', $message->body);
        $this->assertStringNotContainsString('now on file', $message->body);
    }

    public function test_unverifiable_checkout_result_stays_recoverable_and_never_claims_success(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Change my card.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);
        app(AiSupportGuidedTaskService::class)->markPaymentSetupStarted($family);

        app(AiSupportGuidedTaskService::class)->paymentSetupFailed($family, 'verification_unavailable');

        $this->assertSame(AiSupportGuidedTask::STATE_ARRIVED, $task->fresh()->state);
        $this->assertSame('verification_unavailable', $task->fresh()->last_result_code);
        $this->assertNull($task->fresh()->completed_at);
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString("couldn't verify", $message->body);
        $this->assertStringContainsString('not marked this as complete', $message->body);
        $this->assertStringNotContainsString('now on file', $message->body);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'guided_action_recovery',
            'result_code' => 'verification_unavailable',
        ]);
    }

    public function test_human_handoff_cancels_foreground_guidance_without_touching_billing(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family, 'Update my payment method.');
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket);
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);

        app(AiSupportHandoffService::class)->transfer($family, $ticket, 'user_requested');

        $this->assertSame(AiSupportGuidedTask::STATE_CANCELLED, $task->fresh()->state);
        $this->assertSame('human_handoff', $task->fresh()->last_result_code);
        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertNull(app(FamilyAccountContext::class)->account($family)->fresh()->stripe_customer_id);
    }

    /** @return array{User,User} */
    private function eligibleFamily(): array
    {
        [$admin] = $this->openFamilyControls();
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        $this->grant($admin, $family);

        return [$admin, $family];
    }

    /** @return array{User,User} */
    private function eligibleFamilyMember(): array
    {
        [$admin] = $this->openFamilyControls();
        $owner = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountContext::class)->account($owner);
        $member = User::factory()->create(['role' => 'family']);
        $account->memberships()->create([
            'user_id' => $member->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        $this->grant($admin, $member);

        return [$admin, $member];
    }

    /** @return array{User} */
    private function openFamilyControls(): array
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
            'services.stripe.bypass' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        foreach ([
            'master_enabled', 'user_visible_enabled', 'role.family',
            'capability.support_answers_v1', 'capability.semantic_navigation_v1',
            'capability.family_context_v1',
        ] as $key) {
            $controls->set($admin, $key, true, 'Open guided payment-method test capability');
        }
        $controls->set($admin, 'human_only', false, 'Permit guided payment-method test conversation');

        return [$admin];
    }

    private function grant(User $admin, User $family): void
    {
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Exact-user guided payment-method test',
            (string) Str::uuid(),
        );
    }

    private function automatedTicket(User $family, string $description): SupportTicket
    {
        $account = app(FamilyAccountContext::class)->account($family);

        return SupportTicket::query()->create([
            'family_account_id' => $account->id,
            'family_visibility' => 'opener_only',
            'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Guided payment-method test',
            'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }
}
