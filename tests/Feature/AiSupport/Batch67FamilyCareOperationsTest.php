<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPilotGrant;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\Language;
use App\Models\Skill;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\FamilyCareOperationsActionService;
use App\Services\AiSupport\FamilyIntentCatalog;
use App\Services\AiSupport\FamilyIntentResolver;
use App\Services\AiSupport\FamilyLifecycleActionService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Batch67FamilyCareOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_six_seven_routes_representative_natural_language_without_overriding_existing_routes(): void
    {
        $resolver = app(FamilyIntentResolver::class);
        foreach ([
            'Invite Taylor to request #12.' => 'FAM-MATCH-008',
            'Compare the caregivers who applied.' => 'FAM-MATCH-014',
            'Hire Taylor for this request.' => 'FAM-MATCH-020',
            'What is the current visit status?' => 'FAM-VISIT-003',
            'Help me accept the caregiver change request.' => 'FAM-VISIT-010',
            'The caregiver did not show.' => 'FAM-VISIT-014',
            'What happens if I need to cancel a booked visit?' => 'FAM-VISIT-008',
            'What is the cancellation policy for care visits?' => 'FAM-VISIT-008',
            'Approve the submitted hours.' => 'FAM-VISIT-020',
            'Leave a five star review for the caregiver.' => 'FAM-VISIT-030',
            'When is my next regular care visit?' => 'FAM-REGULAR-009',
            'Pause regular care on 2026-09-01.' => 'FAM-REGULAR-020',
            'End regular care and cancel the next visit.' => 'FAM-REGULAR-023',
        ] as $message => $intentId) {
            $result = $resolver->resolve($message);
            $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $result['status'], $message);
            $this->assertSame($intentId, $result['intent_id'], $message);
        }
    }

    public function test_general_visit_cancellation_question_explains_the_rule_without_requesting_a_reason_or_handoff(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'What happens if I need to cancel a booked visit?');

        $handled = $this->respond($family, $ticket, 'FAM-VISIT-008', $ticket->description);

        $this->assertTrue($handled);
        $body = $ticket->publicMessages()->latest()->firstOrFail()->body;
        $this->assertStringContainsString('24-hour late-cancellation window', $body);
        $this->assertStringNotContainsString('Tell me which scheduled visit and the cancellation reason', $body);
        $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $ticket->fresh()->responder_mode);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_all_eighty_six_batch_six_seven_catalog_intents_resolve_from_every_registered_phrase(): void
    {
        $resolver = app(FamilyIntentResolver::class);
        $records = collect(app(FamilyIntentCatalog::class)->records())
            ->filter(fn (array $record): bool => preg_match('/^FAM-(MATCH|VISIT|REGULAR)-/', $record['intent_id']) === 1);

        $this->assertCount(86, $records);
        foreach ($records as $record) {
            $phrases = [...(array) data_get($record, 'phrases.ordinary', []), ...(array) data_get($record, 'phrases.imperfect', [])];
            foreach ($phrases as $phrase) {
                $result = $resolver->resolve((string) $phrase);
                $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $result['status'], $record['intent_id'].': '.$phrase);
                $this->assertSame($record['intent_id'], $result['intent_id'], $record['intent_id'].': '.$phrase);
            }
        }
    }

    public function test_every_declared_batch_six_seven_write_contract_is_registered_under_the_pilot_capability(): void
    {
        $records = collect(app(FamilyIntentCatalog::class)->records())
            ->filter(fn (array $record): bool => preg_match('/^FAM-(MATCH|VISIT|REGULAR)-/', $record['intent_id']) === 1);
        $tools = $records->pluck('contracts.tool')->filter()->unique()->values();

        $this->assertNotEmpty($tools);
        $configuredTools = (array) config('ai_support.tools');
        foreach ($tools as $toolContract) {
            $tool = (string) Str::before((string) $toolContract, ':');
            $this->assertSame(
                FamilyCareOperationsActionService::CAPABILITY,
                $configuredTools[$tool]['capability_id'] ?? null,
                (string) $toolContract,
            );
        }
        $controls = (array) config('ai_support.controls');
        $this->assertFalse((bool) data_get($controls['general_release_enabled'] ?? [], 'default'));
        $this->assertFalse((bool) data_get($controls['capability.'.FamilyCareOperationsActionService::CAPABILITY] ?? [], 'default'));
    }

    public function test_applicant_read_uses_only_the_signed_in_family_account(): void
    {
        [, $family] = $this->eligibleFamily();
        [, $other] = $this->eligibleFamily();
        $request = $this->request($family, 'Family request');
        $foreign = $this->request($other, 'Foreign request');
        $caregiver = $this->caregiver('Taylor Care');
        $foreignCaregiver = $this->caregiver('Private Person');
        $this->application($request, $caregiver);
        $this->application($foreign, $foreignCaregiver);
        $ticket = $this->ticket($family, 'Who applied to request #'.$request->id.'?');

        $handled = $this->respond($family, $ticket, 'FAM-MATCH-013', $ticket->description);

        $this->assertTrue($handled);
        $body = $ticket->publicMessages()->latest()->firstOrFail()->body;
        $this->assertStringContainsString('Taylor Care', $body);
        $this->assertStringNotContainsString('Private Person', $body);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_visit_reads_ignore_past_records_and_hours_approval_selects_an_unconfirmed_record(): void
    {
        [, $family] = $this->eligibleFamily();
        $oldCaregiver = $this->caregiver('Old Caregiver');
        $nextCaregiver = $this->caregiver('Next Caregiver');
        $oldRequest = $this->request($family, 'Past care', now()->subMonths(5), now()->subMonths(5)->addHours(2));
        $oldBooking = $this->booking(
            $family,
            $oldRequest,
            $oldCaregiver,
            CareBooking::STATUS_REVIEWED,
            now()->subMonths(5),
            now()->subMonths(5)->addHours(2),
        );
        $oldBooking->forceFill([
            'timesheet_submitted_at' => now(),
            'family_confirmed_at' => now(),
            'worked_minutes' => 120,
        ])->save();
        $nextRequest = $this->request($family, 'Upcoming care');
        $nextBooking = $this->booking(
            $family,
            $nextRequest,
            $nextCaregiver,
            CareBooking::STATUS_SCHEDULED,
            now()->addDays(2),
            now()->addDays(2)->addHours(2),
        );

        $visitTicket = $this->ticket($family, 'When is my next scheduled visit and who is the caregiver?');
        $this->respond($family, $visitTicket, 'FAM-VISIT-001', $visitTicket->description);
        $visitBody = $visitTicket->publicMessages()->latest()->firstOrFail()->body;
        $this->assertStringContainsString('Next Caregiver', $visitBody);
        $this->assertStringNotContainsString('Old Caregiver', $visitBody);

        $nextBooking->delete();
        $emptyTicket = $this->ticket($family, 'When is my next scheduled visit?');
        $this->respond($family, $emptyTicket, 'FAM-VISIT-001', $emptyTicket->description);
        $this->assertStringContainsString(
            'did not find a current or upcoming visit',
            $emptyTicket->publicMessages()->latest()->firstOrFail()->body,
        );

        $hoursRequest = $this->request($family, 'Hours needing review', now()->subDay(), now()->subDay()->addHours(2));
        $hoursBooking = $this->booking(
            $family,
            $hoursRequest,
            $nextCaregiver,
            CareBooking::STATUS_COMPLETED,
            now()->subDay(),
            now()->subDay()->addHours(2),
        );
        $hoursBooking->forceFill([
            'timesheet_submitted_at' => now()->subHour(),
            'worked_minutes' => 90,
            'family_confirmed_at' => null,
        ])->save();
        $hoursTicket = $this->ticket($family, 'Approve the submitted hours.');
        $this->respond($family, $hoursTicket, 'FAM-VISIT-020', $hoursTicket->description);
        $this->assertSame($hoursBooking->id, (int) data_get($this->latestRecap($hoursTicket)->payload, 'renew_payload.care_booking_id'));
    }

    public function test_invitation_is_not_sent_until_the_family_confirms_the_exact_request_and_caregiver(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = $this->caregiver('Taylor Care', true);
        $ticket = $this->ticket($family, 'Invite Taylor to request #'.$request->id.' saying Please review this request.');
        Http::fake();

        $this->respond($family, $ticket, 'FAM-MATCH-008', $ticket->description);
        $action = $this->latestRecap($ticket);
        $this->assertSame('caregiver.invite', data_get($action->payload, 'tool_id'));
        $this->assertSame($request->id, data_get($action->payload, 'renew_payload.care_request_id'));
        $this->assertSame($caregiver->id, data_get($action->payload, 'renew_payload.caregiver_user_id'));
        $this->assertDatabaseCount('care_request_invitations', 0);

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame('invitation_sent_verified', $evidence->outcome_code);
        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => 'pending',
            'message' => 'Please review this request.',
        ]);
    }

    public function test_shortlist_requires_recap_is_idempotent_and_rejects_stale_state(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $application = $this->application($request, $this->caregiver('Taylor Care'));
        $ticket = $this->ticket($family, 'Save Taylor for later on request #'.$request->id.'.');

        $this->respond($family, $ticket, 'FAM-MATCH-015', $ticket->description);
        $action = $this->latestRecap($ticket);
        $this->assertSame('applicant.shortlist', data_get($action->payload, 'tool_id'));
        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);

        $first = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $second = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('applicant_shortlisted_verified', $first->outcome_code);
        $this->assertSame(CareRequestApplication::STATUS_SHORTLISTED, $application->fresh()->status);
        $this->assertSame(1, AiSupportConfirmedActionEvidence::query()->count());

        $stale = $this->application($request, $this->caregiver('Morgan Care'));
        $staleTicket = $this->ticket($family, 'Decline Morgan on request #'.$request->id.'.');
        $this->respond($family, $staleTicket, 'FAM-MATCH-016', $staleTicket->description);
        $staleAction = $this->latestRecap($staleTicket);
        $stale->update(['status' => CareRequestApplication::STATUS_WITHDRAWN]);
        $this->expectException(ValidationException::class);
        app(FamilyLifecycleActionService::class)->confirm($family, $staleTicket, $staleAction->id);
    }

    public function test_exact_caregiver_message_is_sent_only_after_confirmation(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = $this->caregiver('Taylor Care');
        $application = $this->application($request, $caregiver, CareRequestApplication::STATUS_SHORTLISTED);
        $conversation = CareRequestConversation::findOrCreateForApplication($application->load('careRequest'), $family->id);
        $ticket = $this->ticket($family, 'Send Taylor a message saying I will be home at nine.');
        Http::fake();

        $this->respond($family, $ticket, 'FAM-MATCH-018', $ticket->description);
        $action = $this->latestRecap($ticket);
        $this->assertSame('I will be home at nine.', data_get($action->payload, 'renew_payload.message'));
        $this->assertSame(0, CareRequestMessage::query()->count());

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame('message_sent_verified', $evidence->outcome_code);
        $this->assertDatabaseHas('care_request_messages', [
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $family->id,
            'body' => 'I will be home at nine.',
        ]);
        $this->assertStringContainsString('sent', mb_strtolower($ticket->publicMessages()->reorder()->orderByDesc('id')->firstOrFail()->body));
    }

    public function test_hiring_uses_the_existing_booking_and_payment_workflow_after_material_confirmation(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = $this->caregiver('Taylor Care');
        $application = $this->application($request, $caregiver);
        $ticket = $this->ticket($family, 'Hire Taylor for request #'.$request->id.'.');

        $this->respond($family, $ticket, 'FAM-MATCH-020', $ticket->description);
        $action = $this->latestRecap($ticket);
        $this->assertSame('caregiver.hire', data_get($action->payload, 'tool_id'));
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $booking = CareBooking::query()->where('care_request_id', $request->id)->sole();
        $this->assertSame('caregiver_hired_verified', $evidence->outcome_code);
        $this->assertSame(CareRequest::STATUS_FILLED, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertSame($caregiver->id, $booking->caregiver_user_id);
        $this->assertNotNull($booking->payment);
    }

    public function test_no_show_requires_eligibility_confirmation_and_creates_verified_receipt(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family, requestedStart: now()->subHour(), requestedEnd: now()->subMinutes(30));
        $caregiver = $this->caregiver('Taylor Care');
        $booking = $this->booking($family, $request, $caregiver, CareBooking::STATUS_SCHEDULED, now()->subMinutes(40), now()->addHour());
        $ticket = $this->ticket($family, 'Taylor did not show for booking #'.$booking->id.'.');

        $this->respond($family, $ticket, 'FAM-VISIT-014', $ticket->description);
        $action = $this->latestRecap($ticket);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);

        $this->assertSame('caregiver_no_show_verified', $evidence->outcome_code);
        $this->assertSame(CareBooking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertTrue($booking->fresh()->no_show_flag);
        $this->assertDatabaseHas('ai_support_message_actions', ['action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECEIPT]);
    }

    public function test_visit_reschedule_is_a_pending_request_and_does_not_silently_change_the_booking(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = $this->caregiver('Taylor Care');
        $booking = $this->booking($family, $request, $caregiver, CareBooking::STATUS_SCHEDULED, now()->addDays(2), now()->addDays(2)->addHours(2));
        $newStart = now()->addDays(4)->setTime(10, 0);
        $newEnd = $newStart->copy()->addHours(3);
        $message = 'Reschedule booking #'.$booking->id.' to '.$newStart->format('Y-m-d H:i').' to '.$newEnd->format('Y-m-d H:i').' because a family appointment changed.';
        $ticket = $this->ticket($family, $message);

        $this->respond($family, $ticket, 'FAM-VISIT-005', $message);
        $originalStart = $booking->scheduled_start_at->toIso8601String();
        $action = $this->latestRecap($ticket);
        $this->assertSame('visit.change-request', data_get($action->payload, 'tool_id'));
        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);

        $change = CareBookingChangeRequest::query()->sole();
        $this->assertSame('visit_change_requested_verified', $evidence->outcome_code);
        $this->assertSame(CareBookingChangeRequest::STATUS_PENDING, $change->status);
        $this->assertSame(CareBookingChangeRequest::TYPE_RESCHEDULE, $change->type);
        $this->assertSame($newStart->format('Y-m-d H:i'), $change->proposed_start_at->format('Y-m-d H:i'));
        $this->assertSame($originalStart, $booking->fresh()->scheduled_start_at->toIso8601String());
    }

    public function test_regular_care_pause_and_resume_each_require_a_fresh_confirmation(): void
    {
        [, $family] = $this->eligibleFamily();
        $caregiver = $this->caregiver('Taylor Care');
        $plan = $this->plan($family, $caregiver);
        $pauseDate = now()->toDateString();
        $pauseTicket = $this->ticket($family, 'Pause regular care plan #'.$plan->id.' on '.$pauseDate.'.');

        $this->respond($family, $pauseTicket, 'FAM-REGULAR-020', $pauseTicket->description);
        $pause = app(FamilyLifecycleActionService::class)->confirm($family, $pauseTicket, $this->latestRecap($pauseTicket)->id);
        $this->assertSame('regular_care_paused_verified', $pause->outcome_code);
        $this->assertSame(CarePlan::STATUS_PAUSED, $plan->fresh()->status);

        $resumeTicket = $this->ticket($family, 'Resume regular care plan #'.$plan->id.'.');
        $this->respond($family, $resumeTicket, 'FAM-REGULAR-021', $resumeTicket->description);
        $this->assertSame(CarePlan::STATUS_PAUSED, $plan->fresh()->status);
        $resume = app(FamilyLifecycleActionService::class)->confirm($family, $resumeTicket, $this->latestRecap($resumeTicket)->id);
        $this->assertSame('regular_care_resumed_verified', $resume->outcome_code);
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
    }

    public function test_exceptional_match_visit_and_regular_care_cases_transfer_without_domain_mutation(): void
    {
        [, $family] = $this->eligibleFamily();
        foreach (['FAM-MATCH-024', 'FAM-VISIT-028', 'FAM-REGULAR-026'] as $intentId) {
            $ticket = $this->ticket($family, 'I need human help with this case.');
            $this->assertTrue($this->respond($family, $ticket, $intentId, $ticket->description));
            $this->assertTrue($ticket->fresh()->isHumanOnly());
        }
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_batch_seven_activation_changes_only_the_exact_two_pilot_grants_and_keeps_everyone_off(): void
    {
        [$admin, $first] = $this->eligibleFamily();
        [, $second] = $this->eligibleFamily($admin);
        config(['ai_support.initial_pilot.approved_user_ids' => [$first->id, $second->id]]);
        AiSupportPilotGrant::query()->get()->each(function (AiSupportPilotGrant $grant): void {
            $grant->forceFill(['capability_ids' => collect($grant->capability_ids)
                ->reject(fn (string $id): bool => $id === FamilyCareOperationsActionService::CAPABILITY)->values()->all()])->save();
        });
        $controls = app(AiSupportControlService::class);
        if ($controls->enabled('capability.'.FamilyCareOperationsActionService::CAPABILITY)) {
            $controls->set($admin, 'capability.'.FamilyCareOperationsActionService::CAPABILITY, false, 'Activation test reset');
        }

        $this->artisan('ai-support:activate-batch7-pilot', ['--actor-email' => $admin->email])
            ->expectsOutputToContain('Batches 6 and 7 are active for the existing two-user pilot only')
            ->assertSuccessful();

        $this->assertTrue(AiSupportPilotGrant::query()->get()->every(
            fn (AiSupportPilotGrant $grant): bool => in_array(FamilyCareOperationsActionService::CAPABILITY, $grant->capability_ids, true),
        ));
        $this->assertTrue($controls->enabled('capability.'.FamilyCareOperationsActionService::CAPABILITY));
        $this->assertTrue($controls->enabled('tool.caregiver.hire'));
        $this->assertTrue($controls->enabled('tool.regular-care.end'));
        $this->assertFalse($controls->enabled('general_release_enabled'));
    }

    private function respond(User $family, SupportTicket $ticket, string $intentId, string $message): bool
    {
        return app(FamilyCareOperationsActionService::class)->respond(
            $family,
            $ticket,
            app(FamilyIntentCatalog::class)->find($intentId) ?? ['intent_id' => $intentId],
            $message,
        );
    }

    private function latestRecap(SupportTicket $ticket): AiSupportMessageAction
    {
        return AiSupportMessageAction::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->latest()->firstOrFail();
    }

    /** @return array{User,User} */
    private function eligibleFamily(?User $admin = null): array
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
            'services.stripe.bypass' => true,
        ]);
        $admin ??= User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        $required = [
            'master_enabled', 'user_visible_enabled', 'role.family',
            'capability.support_answers_v1', 'capability.semantic_navigation_v1',
            'capability.family_context_v1', 'capability.'.FamilyCareOperationsActionService::CAPABILITY,
        ];
        foreach ((array) config('ai_support.tools', []) as $toolId => $definition) {
            if (($definition['capability_id'] ?? null) === FamilyCareOperationsActionService::CAPABILITY) {
                $required[] = 'tool.'.$toolId;
            }
        }
        foreach ($required as $key) {
            if (! $controls->enabled($key)) {
                $controls->set($admin, $key, true, 'Batches 6 and 7 Family operations test');
            }
        }
        if ($controls->enabled('human_only')) {
            $controls->set($admin, 'human_only', false, 'Batches 6 and 7 Family operations test');
        }
        if ($controls->enabled('general_release_enabled')) {
            $controls->set($admin, 'general_release_enabled', false, 'Keep exact pilot only');
        }
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        app(AiSupportPilotGrantService::class)->grant(
            $admin, $family, 'family_support_v1', CarbonImmutable::now(), CarbonImmutable::now()->addDays(14),
            'Exact-user Batches 6 and 7 test', (string) Str::uuid(),
        );

        return [$admin, $family];
    }

    private function ticket(User $family, string $description): SupportTicket
    {
        return SupportTicket::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_visibility' => 'opener_only', 'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => 'Batches 6 and 7 test', 'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function request(
        User $family,
        string $title = 'Morning help',
        mixed $requestedStart = null,
        mixed $requestedEnd = null,
    ): CareRequest {
        $request = CareRequest::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id, 'created_by_user_id' => $family->id,
            'is_system_generated' => false, 'title' => $title, 'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $requestedStart ?? now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => $requestedEnd ?? now()->addDays(2)->setTime(12, 0),
            'preferred_response_hours' => 12,
            'address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $request->recipient()->create([
            'recipient_is_requester' => false, 'full_name' => 'Maria Example', 'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }

    private function caregiver(string $name, bool $marketplaceReady = false): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $name]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'slug' => Str::slug($name).'-'.$caregiver->id,
            'status' => 'active', 'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 30, 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 15,
            'is_accepting_new_clients' => true, 'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
            'average_rating' => 4.8, 'reviews_count' => 8,
        ]);
        if ($marketplaceReady) {
            $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
            $language = Language::query()->create(['name' => 'English '.$caregiver->id]);
            $profile->skills()->sync([$skill->id]);
            $profile->languages()->sync([$language->id]);
            $profile->availabilities()->create([
                'day_of_week' => 1,
                'start_time' => '08:00',
                'end_time' => '18:00',
            ]);
        }

        return $caregiver->fresh('caregiverProfile');
    }

    private function application(CareRequest $request, User $caregiver, string $status = CareRequestApplication::STATUS_APPLIED): CareRequestApplication
    {
        return CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => $status,
            'proposed_rate' => 30,
            'cover_note' => 'Available and interested.',
        ]);
    }

    private function booking(User $family, CareRequest $request, User $caregiver, string $status, mixed $start, mixed $end): CareBooking
    {
        return CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => $status,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
        ]);
    }

    private function plan(User $family, User $caregiver): CarePlan
    {
        return CarePlan::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Regular morning care',
            'recipient_snapshot' => ['full_name' => 'Maria Example'],
            'address_snapshot' => ['address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
            'task_snapshot' => [],
            'schedule_days' => [1],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'schedule_slots' => [['day' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
            'starts_on' => now()->subWeek()->toDateString(),
            'timezone' => 'America/New_York',
            'schedule_version' => 1,
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
    }
}
