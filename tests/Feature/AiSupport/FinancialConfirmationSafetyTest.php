<?php

namespace Tests\Feature\AiSupport;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Support\ChatWidget;
use App\Models\AiSupportMessageAction;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareReview;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\FamilyCareFinancialQuoteService;
use App\Services\AiSupport\FamilyCareOperationsActionService;
use App\Services\AiSupport\FamilyLifecycleActionService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CareRequestHiringService;
use App\Services\Payments\BookingPaymentService;
use App\Support\MarketplacePricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialConfirmationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        config([
            'services.stripe.bypass' => true,
            'services.stripe.currency' => 'usd',
            'marketplace.pricing_v2.enabled' => true,
            'marketplace.pricing_v2.family_care_hourly_cents' => 3000,
            'marketplace.pricing_v2.family_processing_fee_hourly_cents' => 100,
            'marketplace.payments.authorization_buffer_percent' => 20,
        ]);
    }

    public function test_hire_recap_matches_real_default_payment_and_duplicate_confirmation_is_idempotent(): void
    {
        [$family, $request, $application] = $this->scenario();
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $quote = data_get($action->payload, 'renew_payload.financial_quote');
        $this->assertSame(6200, $quote['total_cents']);
        $this->assertSame(200, $quote['fee_cents']);
        $this->assertSame(7440, $quote['authorization_cents']);
        $this->assertStringContainsString('USD $62.00', json_encode($action->payload['fields']));
        $this->assertDatabaseCount('care_bookings', 0);
        $service = app(FamilyLifecycleActionService::class);
        $first = $service->confirm($family, $ticket, $action->id);
        $second = $service->confirm($family, $ticket, $action->id);
        $booking = CareBooking::query()->sole();
        $this->assertSame($first->id, $second->id);
        $this->assertSame(7440, (int) $booking->payment->amount_authorized_cents);
        $this->assertSame(6200, app(BookingPaymentService::class)->quoteForWorkedMinutes($booking, 120)['total_charge_cents']);
        $this->assertSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 1);
    }

    public function test_pair_agreement_change_requires_new_visible_quote_before_hiring(): void
    {
        [$family, $request, $application] = $this->scenario();
        $agreement = $this->agreement($family, $application->caregiver, 1750, 0);
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $this->assertSame(3500, data_get($action->payload, 'renew_payload.financial_quote.total_cents'));
        $agreement->update(['family_care_rate_cents' => 2000]);
        $this->assertConfirmationBlocked(fn () => app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id), 'quote changed');
        $this->assertNull($request->fresh()->booking);
        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);
        $renewed = app(FamilyLifecycleActionService::class)->renew($family, $ticket, $action->id);
        $this->assertStringContainsString('USD $40.00', json_encode($renewed->payload['fields']));
        $this->assertStringNotContainsString('USD $35.00', json_encode($renewed->payload['fields']));
        app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $renewed->id);
        $this->assertSame($agreement->id, $request->fresh()->booking->pricing_agreement_id);
        $this->assertSame(2000, $request->fresh()->booking->family_care_rate_cents);
    }

    public function test_legacy_hire_uses_payment_service_rate_fee_and_rounding(): void
    {
        config(['marketplace.pricing_v2.enabled' => false, 'marketplace.payments.platform_fee_percent' => 10]);
        [$family, $request, $application] = $this->scenario();
        $application->update(['proposed_rate' => 17.5]);
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $quote = data_get($action->payload, 'renew_payload.financial_quote');
        $this->assertSame(3500, $quote['care_cents']);
        $this->assertSame(350, $quote['fee_cents']);
        $this->assertSame(3850, $quote['total_cents']);
        app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $booking = $request->fresh()->booking;
        $this->assertSame(4620, (int) $booking->payment->amount_authorized_cents);
        $this->assertSame(3850, app(BookingPaymentService::class)->quoteForWorkedMinutes($booking, 120)['total_charge_cents']);
    }

    public function test_hours_quote_preserves_booking_snapshot_and_detects_rate_change_without_timestamp_change(): void
    {
        [$family, $request, $application] = $this->scenario();
        $booking = $this->completedBooking($request, $application);
        $booking->forceFill(array_merge(app(MarketplacePricing::class)->currentSnapshotAttributes(), [
            'family_care_rate_cents' => 1750, 'family_processing_fee_rate_cents' => 0,
        ]))->save();
        [$ticket, $action] = $this->recap($family, 'FAM-VISIT-020', 'Approve the submitted hours for request #'.$request->id.'.');
        $this->assertSame(3500, data_get($action->payload, 'renew_payload.financial_quote.total_cents'));
        config(['marketplace.pricing_v2.family_care_hourly_cents' => 4000]);
        $this->assertSame(3500, app(FamilyCareFinancialQuoteService::class)->hours($booking->fresh())['total_cents']);
        DB::table('care_bookings')->where('id', $booking->id)->update(['family_care_rate_cents' => 1800]);
        $this->assertConfirmationBlocked(fn () => app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id), 'quote changed');
        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_recurring_hire_quotes_actual_first_future_duration_and_rechecks_occurrence(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 12)->setTime(8, 50));
        [$family, $request] = $this->scenario();
        $request->update([
            'request_type' => CareRequest::TYPE_RECURRING, 'recurring_starts_on' => '2026-09-11',
            'recurring_days' => [5, 6], 'recurring_start_time' => '09:00', 'recurring_end_time' => '10:00',
            'recurring_schedule' => [
                ['day' => 5, 'start_time' => '09:00', 'end_time' => '10:00'],
                ['day' => 6, 'start_time' => '09:00', 'end_time' => '12:00'],
            ],
        ]);
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $quote = data_get($action->payload, 'renew_payload.financial_quote');
        $this->assertStringStartsWith('2026-09-12T09:00', $quote['start_at']);
        $this->assertSame(180, $quote['minutes']);
        $this->assertSame(9300, $quote['total_cents']);
        $this->assertDatabaseCount('care_plans', 0);
        $this->travelTo(now()->setDate(2026, 9, 12)->setTime(9, 1));
        $this->assertConfirmationBlocked(fn () => app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id), 'quote changed');
        // Renew keeps the same request but recalculates the actual next occurrence and duration.
        $renewed = app(FamilyLifecycleActionService::class)->renew($family, $ticket, $action->id);
        $this->assertSame(60, data_get($renewed->payload, 'renew_payload.financial_quote.minutes'));
        $this->assertStringContainsString('USD $31.00', json_encode($renewed->payload['fields']));
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_recurring_offer_quotes_proposed_future_visit_and_rechecks_pricing_before_offer(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(12, 0));
        [$family, $request, $application] = $this->scenario();
        $this->completedBooking($request, $application);
        [$ticket, $action] = $this->recap($family, 'FAM-REGULAR-003', 'Send recurring care offer for request #'.$request->id.' on Saturdays 09:00 to 12:00 starting 2026-09-12.');
        $quote = data_get($action->payload, 'renew_payload.financial_quote');
        $this->assertStringStartsWith('2026-09-12T09:00', $quote['start_at']);
        $this->assertSame(9300, $quote['total_cents']);
        $this->assertStringContainsString('No charge from sending this offer', json_encode($action->payload['fields']));
        config(['marketplace.pricing_v2.family_processing_fee_hourly_cents' => 200]);
        $this->assertConfirmationBlocked(fn () => app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id), 'quote changed');
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_bookings', 1);
    }

    public function test_far_future_legacy_recurring_offer_uses_the_offering_family_members_plan_rate(): void
    {
        config(['marketplace.pricing_v2.enabled' => false, 'marketplace.payments.platform_fee_percent' => 10]);
        [$family, $request, $application] = $this->scenario();
        $member = User::factory()->create(['role' => 'family']);
        config(['marketplace.family_pricing_overrides' => [$member->email => ['hourly_rate' => 17.5]]]);
        $start = now()->addWeeks(10);
        $quote = app(FamilyCareFinancialQuoteService::class)->recurringOffer($application, [
            'starts_on' => $start->toDateString(), 'schedule_days' => [$start->dayOfWeek],
            'schedule_slots' => [['day' => $start->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00']],
        ], $member);
        $this->assertStringStartsWith($start->toDateString().'T09:00', $quote['start_at']);
        $this->assertSame(1750, $quote['care_rate_cents']);
        $this->assertSame(5775, $quote['total_cents']);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_plans', 0);
    }

    public function test_review_requires_hours_approval_and_normal_approved_review_never_captures(): void
    {
        [$family, $request, $application] = $this->scenario();
        $booking = $this->completedBooking($request, $application);
        $ticket = $this->ticket($family, 'Leave a 5 star review for request #'.$request->id.'.');
        app(FamilyCareOperationsActionService::class)->respond($family, $ticket, ['intent_id' => 'FAM-VISIT-030'], $ticket->description);
        $this->assertSame(0, AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->count());
        $this->assertStringContainsString('First review and approve the submitted hours and payment', $ticket->publicMessages()->latest('id')->firstOrFail()->body);
        $this->assertDatabaseCount('care_reviews', 0);
        $this->assertNull($booking->fresh()->family_confirmed_at);
        $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $family->id]);
        $this->partialMock(BookingPaymentService::class)->shouldNotReceive('captureForBooking');
        app(FamilyCareOperationsActionService::class)->respond($family, $ticket, ['intent_id' => 'FAM-VISIT-030'], $ticket->description);
        $action = $this->latestRecap($ticket);
        $service = app(FamilyLifecycleActionService::class);
        $first = $service->confirm($family, $ticket, $action->id);
        $this->assertSame($first->id, $service->confirm($family, $ticket, $action->id)->id);
        $this->assertSame(5, (int) CareReview::query()->sole()->rating);
        $this->assertSame(CareBooking::STATUS_REVIEWED, $booking->fresh()->status);
    }

    public function test_approved_hours_capture_only_the_quoted_amount_and_retry_does_not_capture_again(): void
    {
        [$family, $request, $application] = $this->scenario();
        $booking = $this->completedBooking($request, $application);
        app(BookingPaymentService::class)->authorizeForBooking($booking, notify: false);
        [$ticket, $action] = $this->recap($family, 'FAM-VISIT-020', 'Approve the submitted hours for request #'.$request->id.'.');
        $this->assertSame(6200, data_get($action->payload, 'renew_payload.financial_quote.total_cents'));
        $service = app(FamilyLifecycleActionService::class);
        $evidence = $service->confirm($family, $ticket, $action->id);
        $this->assertSame(6200, (int) $booking->fresh()->payment->amount_captured_cents);
        $this->assertNotNull($booking->fresh()->family_confirmed_at);
        $operations = $booking->fresh()->payment->operations()->count();
        $this->assertSame($evidence->id, $service->confirm($family, $ticket, $action->id)->id);
        $this->assertSame($operations, $booking->fresh()->payment->operations()->count());
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 1);
    }

    public function test_legacy_review_recap_cannot_approve_hours_when_confirmation_is_missing(): void
    {
        [$family, $request, $application] = $this->scenario();
        $booking = $this->completedBooking($request, $application);
        $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $family->id]);
        [$ticket, $action] = $this->recap($family, 'FAM-VISIT-030', 'Leave a 5 star review for request #'.$request->id.'.');
        DB::table('care_bookings')->where('id', $booking->id)->update(['family_confirmed_at' => null, 'family_confirmed_by_user_id' => null]);
        $this->partialMock(BookingPaymentService::class)->shouldNotReceive('captureForBooking');
        $this->assertConfirmationBlocked(fn () => app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id), 'Approve the submitted hours');
        $this->assertDatabaseCount('care_reviews', 0);
        $this->assertNull($booking->fresh()->family_confirmed_at);
    }

    public function test_missing_authorization_recovers_in_chat_with_exact_visit_link_and_no_false_receipt(): void
    {
        [$family, $request, $application] = $this->scenario();
        $booking = $this->completedBooking($request, $application);
        [$ticket, $action] = $this->recap($family, 'FAM-VISIT-020', 'Approve the submitted hours for request #'.$request->id.'.');
        // Earlier recurring actions can contain both IDs; the exact visit must win.
        $payload = $action->payload;
        $payload['renew_payload']['care_plan_id'] = 999999;
        $action->update(['payload' => $payload]);
        $widget = Livewire::actingAs($family)->test(ChatWidget::class)
            ->assertSet('ticketId', $ticket->id)
            ->call('confirmDomainAction', $action->id)
            ->assertHasErrors(['confirmation'])
            ->assertDispatched('support-chat-confirmation-failed')
            ->assertSee('Payment authorization is missing for this booking.')
            ->assertSee('Review this visit’s payment')
            ->assertSee(route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'shift']), false);
        $widget->call('confirmDomainAction', $action->id)->assertHasErrors(['confirmation']);
        $this->assertSame(1, AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_NAVIGATE)->count());
        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->assertTrue($action->fresh()->isActive());
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_payment_recovery_exposes_user_message_only_and_never_another_accounts_link(): void
    {
        [$family, $request] = $this->scenario();
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $this->mock(CareRequestHiringService::class)->shouldReceive('hire')->once()
            ->andThrow(new PaymentException('Add a payment method to continue.', 'Secret provider diagnostic must stay private'));
        Livewire::actingAs($family)->test(ChatWidget::class)->call('confirmDomainAction', $action->id)
            ->assertHasErrors(['confirmation'])->assertSee('Add a payment method to continue.')
            ->assertDontSee('Secret provider diagnostic')
            ->assertSee(app(\App\Services\AiSupport\NavigationTargetRegistry::class)->urlFor($family, 'family.billing.payment_method'), false);
        $this->assertNull($request->fresh()->booking);
        $other = User::factory()->create(['role' => 'family']);
        $otherTicket = $this->ticket($other, 'Other account');
        // Unauthorized IDs must not reveal the original resource or create a recovery action.
        Livewire::actingAs($other)->test(ChatWidget::class)->assertSet('ticketId', $otherTicket->id)
            ->call('confirmDomainAction', $action->id)->assertHasErrors(['confirmation']);
        $this->assertSame(0, AiSupportMessageAction::query()->where('support_ticket_id', $otherTicket->id)->count());
    }

    public function test_expected_http_ownership_failure_recovers_without_revealing_domain_details(): void
    {
        [$family, $request] = $this->scenario();
        [$ticket, $action] = $this->recap($family, 'FAM-MATCH-020', 'Hire Taylor for request #'.$request->id.'.');
        $this->mock(CareRequestHiringService::class)->shouldReceive('hire')->once()
            ->andThrow(new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Private ownership diagnostic'));
        Livewire::actingAs($family)->test(ChatWidget::class)->call('confirmDomainAction', $action->id)
            ->assertHasErrors(['confirmation'])->assertDispatched('support-chat-confirmation-failed')
            ->assertSee('This confirmation is no longer available for your account.')
            ->assertDontSee('Private ownership diagnostic');
        $this->assertNull($request->fresh()->booking);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    private function scenario(): array
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family, 'Morning help', now()->addDays(2)->setTime(9, 0), now()->addDays(2)->setTime(11, 0));
        $application = $this->application($request, $this->caregiver('Taylor Care'));

        return [$family, $request, $application];
    }

    private function recap(User $family, string $intent, string $message): array
    {
        $ticket = $this->ticket($family, $message);
        $this->assertTrue(app(FamilyCareOperationsActionService::class)->respond($family, $ticket, ['intent_id' => $intent], $message));

        return [$ticket, $this->latestRecap($ticket)];
    }

    private function completedBooking(CareRequest $request, CareRequestApplication $application): CareBooking
    {
        $request->update(['status' => CareRequest::STATUS_FILLED]);
        $application->update(['status' => CareRequestApplication::STATUS_HIRED]);

        return CareBooking::query()->create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_account_id' => $request->family_account_id, 'family_user_id' => $request->family_user_id,
            'caregiver_user_id' => $application->caregiver_user_id, 'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subDay()->setTime(9, 0), 'scheduled_end_at' => now()->subDay()->setTime(11, 0),
            'started_at' => now()->subDay()->setTime(9, 0), 'completed_at' => now()->subDay()->setTime(11, 0),
            'worked_minutes' => 120, 'expected_minutes' => 120, 'timesheet_submitted_at' => now()->subHour(),
        ]);
    }

    private function agreement(User $family, User $caregiver, int $rate, int $fee): CarePricingAgreement
    {
        $source = $this->request($family, 'Earlier agreement');
        $booking = $this->completedBooking($source, $this->application($source, $caregiver));

        return CarePricingAgreement::query()->create([
            'family_account_id' => $source->family_account_id, 'caregiver_user_id' => $caregiver->id,
            'source_booking_id' => $booking->id, 'created_by_user_id' => $family->id,
            'family_care_rate_cents' => $rate, 'family_processing_fee_rate_cents' => $fee,
            'caregiver_gross_rate_cents' => $rate, 'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING,
            'reason' => 'Isolated financial confirmation test agreement.', 'active' => true,
        ]);
    }

    private function assertConfirmationBlocked(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Confirmation should require a fresh review.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, implode(' ', collect($exception->errors())->flatten()->all()));
        }
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

    private function caregiver(string $name): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $name]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'slug' => Str::slug($name).'-'.$caregiver->id,
            'status' => 'active', 'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 30, 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 15,
            'is_accepting_new_clients' => true, 'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
            'screening_initial_approved_at' => now(),
            'average_rating' => 4.8, 'reviews_count' => 8,
        ]);

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
}
