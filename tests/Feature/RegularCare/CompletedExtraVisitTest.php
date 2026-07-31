<?php

namespace Tests\Feature\RegularCare;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Caregiver\RegularClients;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CompletedExtraVisitRequest;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Family\FamilyCareHistoryService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CompletedExtraVisitService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Mockery;
use Tests\TestCase;

class CompletedExtraVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.bypass', true);
        config()->set('marketplace.completed_extra_visits.enabled', true);
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-31 16:00:00', 'America/New_York'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_established_caregiver_can_submit_without_creating_a_booking_or_payment(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();

        $report = $this->submit($plan, $caregiver);

        $this->assertSame(CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, $report->status);
        $this->assertNull($report->care_booking_id);
        $this->assertSame(0, CareBooking::query()->where('care_plan_id', $plan->id)->count());
        $this->assertSame(0, CareBookingPayment::query()->count());
        $this->assertDatabaseHas('care_plan_events', [
            'care_plan_id' => $plan->id,
            'event_type' => 'completed_extra_visit_submitted',
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::COMPLETED_EXTRA_VISIT_SUBMITTED,
            'channel' => 'email',
            'status' => 'queued',
        ]);
        Notification::assertSentTo($family, MarketplaceEventNotification::class, function ($notification, array $channels) use ($family): bool {
            if (! in_array('mail', $channels, true)) {
                return false;
            }
            $mail = $notification->toMail($family);

            return ($mail->viewData['eventLabel'] ?? null) === 'Extra visit reported'
                && ($mail->viewData['ctaLabel'] ?? null) === 'Review extra visit'
                && collect($mail->viewData['emailDetails'] ?? [])->contains(fn ($detail) => $detail['label'] === 'Worked time');
        });
    }

    public function test_unrelated_or_unaccepted_caregivers_cannot_report_visits(): void
    {
        [, $caregiver, $plan] = $this->establishedPlan();
        $stranger = $this->caregiver('stranger@example.test');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompletedExtraVisitService::class)->preview($plan, $stranger, $this->input());
    }

    public function test_pending_plan_is_not_an_established_relationship(): void
    {
        [, $caregiver, $plan] = $this->establishedPlan();
        $plan->forceFill(['status' => CarePlan::STATUS_PENDING_CAREGIVER, 'activated_at' => null])->save();

        $this->assertFalse(app(CompletedExtraVisitService::class)->canReport($plan->fresh(), $caregiver));
    }

    public function test_recently_ended_plan_has_a_grace_period_but_old_plan_does_not(): void
    {
        [, $caregiver, $plan] = $this->establishedPlan();
        $plan->forceFill(['status' => CarePlan::STATUS_ENDED, 'ended_at' => now()->subDays(29)])->save();
        $this->assertTrue(app(CompletedExtraVisitService::class)->canReport($plan->fresh(), $caregiver));

        $plan->forceFill(['ended_at' => now()->subDays(31)])->save();
        $this->assertFalse(app(CompletedExtraVisitService::class)->canReport($plan->fresh(), $caregiver));
    }

    public function test_future_and_overlapping_reports_are_rejected(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $future = $this->input(['date' => now($plan->timezone)->addDay()->toDateString()]);

        try {
            app(CompletedExtraVisitService::class)->preview($plan, $caregiver, $future);
            $this->fail('Future visit should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reportEndTime', $exception->errors());
        }

        $existingRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Existing Friday visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => Carbon::parse('2026-07-24 10:00', $plan->timezone),
            'requested_end_at' => Carbon::parse('2026-07-24 12:00', $plan->timezone),
            'address_line1' => '123 Main St', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27703',
        ]);
        CareBooking::query()->create([
            'care_request_id' => $existingRequest->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => Carbon::parse('2026-07-24 10:00', $plan->timezone),
            'scheduled_end_at' => Carbon::parse('2026-07-24 12:00', $plan->timezone),
        ]);

        $this->expectException(ValidationException::class);
        $this->submit($plan, $caregiver);
    }

    public function test_regular_schedule_slots_are_rejected_even_when_no_booking_was_materialized(): void
    {
        [, $caregiver, $plan] = $this->establishedPlan();
        $reportDay = Carbon::parse('2026-07-24', $plan->timezone);
        $plan->forceFill([
            'schedule_days' => [$reportDay->dayOfWeek],
            'schedule_start_time' => '10:00',
            'schedule_end_time' => '12:00',
        ])->save();

        try {
            app(CompletedExtraVisitService::class)->preview($plan->fresh(), $caregiver, $this->input());
            $this->fail('A regular schedule slot cannot be reported as an extra visit.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'These hours belong to the regular schedule. Open that visit if its recorded time needs correction.',
                $exception->errors()['reportDate'][0]
            );
        }

        $this->assertDatabaseCount('completed_extra_visit_requests', 0);
    }

    public function test_duplicate_submit_is_idempotent_and_does_not_repeat_notifications(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $clientRequestId = (string) Str::uuid();
        $service = app(CompletedExtraVisitService::class);

        $first = $service->submit($plan, $caregiver, $this->input(), $clientRequestId);
        $second = $service->submit($plan, $caregiver, $this->input(), $clientRequestId);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('completed_extra_visit_requests', 1);
        $this->assertSame(2, $family->notificationDeliveries()->where('notifiable_id', $first->id)->count());
        Notification::assertSentToTimes($family, MarketplaceEventNotification::class, 2);
    }

    public function test_family_can_request_changes_and_caregiver_resubmits_an_immutable_new_version(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $first = $this->submit($plan, $caregiver);
        app(CompletedExtraVisitService::class)->requestChanges($first, $family, 'Please change the end time to 11:30 AM.');

        $second = app(CompletedExtraVisitService::class)->submit(
            $plan,
            $caregiver,
            $this->input(['end_time' => '11:30', 'explanation' => 'Barbara asked me to stay until 11:30 that Friday.']),
            (string) Str::uuid(),
            $first->id,
        );

        $this->assertSame(CompletedExtraVisitRequest::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, $second->status);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertGreaterThan($first->version, $second->version);

        $this->expectException(LogicException::class);
        $first->fresh()->forceFill(['explanation' => 'Attempted overwrite'])->save();
    }

    public function test_family_approval_creates_exactly_one_manual_completed_booking_and_processes_payment(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $schedule = $plan->schedule_days;
        $report = $this->submit($plan, $caregiver);

        $applied = app(CompletedExtraVisitService::class)->approve($report, $family);
        $again = app(CompletedExtraVisitService::class)->approve($applied->fresh(), $family);

        $this->assertSame(CompletedExtraVisitRequest::STATUS_APPLIED, $applied->status);
        $this->assertSame($applied->id, $again->id);
        $this->assertSame(1, CareBooking::query()->where('care_plan_id', $plan->id)->count());
        $booking = $applied->booking()->with('payment')->firstOrFail();
        $this->assertSame('completed_extra', $booking->plan_visit_kind);
        $this->assertSame(CareBooking::STATUS_REVIEWED, $booking->status);
        $this->assertSame('manual_family_approved_extra', $booking->check_in_source);
        $this->assertNull($booking->check_in_lat);
        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertSame(90, $booking->worked_minutes);
        $this->assertContains($booking->payment->status, [CareBookingPayment::STATUS_CAPTURED, CareBookingPayment::STATUS_TRANSFERRED]);
        $this->assertSame($schedule, $plan->fresh()->schedule_days);
        $this->assertSame('Family-approved extra visit', app(FamilyCareHistoryService::class)->present($booking->fresh(['careRequest.recipient', 'caregiver.caregiverProfile', 'carePlan', 'payment', 'corrections', 'timeCorrections', 'taskChecks']))['care_type_label']);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'completed_extra_visit_reported',
        ]);
    }

    public function test_approval_revalidates_caregiver_eligibility_and_rejects_stale_withdrawn_reports(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $report = $this->submit($plan, $caregiver);
        $caregiver->caregiverProfile()->update(['status' => 'suspended']);

        try {
            app(CompletedExtraVisitService::class)->approve($report, $family);
            $this->fail('Approval must revalidate caregiver eligibility.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($report->fresh()->approved_at);
        $this->assertDatabaseCount('care_booking_payments', 0);

        $caregiver->caregiverProfile()->update(['status' => 'active']);
        $withdrawn = app(CompletedExtraVisitService::class)->withdraw($report->fresh(), $caregiver);
        $this->expectException(ValidationException::class);
        app(CompletedExtraVisitService::class)->approve($withdrawn, $family);
    }

    public function test_family_sees_a_changed_price_and_approval_captures_the_authoritative_quote(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $report = $this->submit($plan, $caregiver);
        $submittedCharge = (int) data_get($report->financial_preview, 'total_charge_cents');
        config()->set('marketplace.family_pricing_overrides', [
            $family->email => ['hourly_rate' => 40, 'platform_fee_percent' => 10],
        ]);

        Livewire::actingAs($family)
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('The price changed since this report was submitted.')
            ->assertSee('$66.00');

        $applied = app(CompletedExtraVisitService::class)->approve($report->fresh(), $family);

        $this->assertSame(CompletedExtraVisitRequest::STATUS_APPLIED, $applied->status);
        $this->assertNotSame($submittedCharge, (int) data_get($applied->final_financial_preview, 'total_charge_cents'));
        $this->assertSame(6600, (int) data_get($applied->final_financial_preview, 'amount_captured_cents'));
    }

    public function test_payment_failure_preserves_approved_booking_and_enters_recoverable_state(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $report = $this->submit($plan, $caregiver);
        $payments = Mockery::mock(BookingPaymentService::class);
        $payments->shouldReceive('authorizeForBooking')->once()->andThrow(new PaymentException('Confirm your card to continue.'));
        $this->app->instance(BookingPaymentService::class, $payments);

        $result = app(CompletedExtraVisitService::class)->approve($report, $family);

        $this->assertSame(CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED, $result->status);
        $this->assertNotNull($result->care_booking_id);
        $this->assertSame(CareBooking::STATUS_REVIEWED, $result->booking->status);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::COMPLETED_EXTRA_VISIT_PAYMENT_ACTION_REQUIRED,
        ]);
    }

    public function test_retry_command_finishes_a_safely_failed_approved_report_without_duplicates(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $report = $this->submit($plan, $caregiver);
        $payments = Mockery::mock(BookingPaymentService::class);
        $payments->shouldReceive('authorizeForBooking')->once()->andThrow(new \RuntimeException('Temporary local test failure.'));
        $this->app->instance(BookingPaymentService::class, $payments);

        $failed = app(CompletedExtraVisitService::class)->approve($report, $family);
        $this->assertSame(CompletedExtraVisitRequest::STATUS_FAILED, $failed->status);
        $failed->forceFill(['processing_started_at' => now()->subMinutes(11)])->save();

        $this->app->forgetInstance(BookingPaymentService::class);
        $this->app->forgetInstance(CompletedExtraVisitService::class);
        $this->artisan('homecare:process-completed-extra-visits')->assertExitCode(0);

        $this->assertSame(CompletedExtraVisitRequest::STATUS_APPLIED, $failed->fresh()->status);
        $this->assertSame(1, CareBooking::query()->where('care_plan_id', $plan->id)->count());
        $this->assertDatabaseCount('care_booking_payments', 1);
    }

    public function test_overnight_visit_uses_plan_timezone_and_break_for_worked_time(): void
    {
        [, $caregiver, $plan] = $this->establishedPlan();
        $preview = app(CompletedExtraVisitService::class)->preview($plan, $caregiver, $this->input([
            'start_time' => '23:30',
            'end_time' => '01:00',
            'break_minutes' => 15,
        ]));

        $this->assertSame('America/New_York', $preview['timezone']);
        $this->assertSame(75, $preview['worked_minutes']);
        $this->assertSame('2026-07-25', $preview['end']->copy()->setTimezone($preview['timezone'])->toDateString());
    }

    public function test_dispute_and_withdrawal_preserve_audit_without_payment(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        $disputed = $this->submit($plan, $caregiver);
        $disputed = app(CompletedExtraVisitService::class)->dispute($disputed, $family, 'Charles did not provide care at our home that Friday.');
        $this->assertSame(CompletedExtraVisitRequest::STATUS_DISPUTED, $disputed->status);
        $this->assertNotNull($disputed->support_ticket_id);
        $this->assertNull($disputed->care_booking_id);

        $secondInput = $this->input(['date' => '2026-07-23', 'start_time' => '13:00', 'end_time' => '14:00']);
        $second = app(CompletedExtraVisitService::class)->submit($plan, $caregiver, $secondInput, (string) Str::uuid());
        $withdrawn = app(CompletedExtraVisitService::class)->withdraw($second, $caregiver);
        $this->assertSame(CompletedExtraVisitRequest::STATUS_WITHDRAWN, $withdrawn->status);
        $this->assertSame(0, CareBookingPayment::query()->count());
    }

    public function test_channel_dedupe_does_not_prevent_email_when_only_in_app_was_previously_enabled(): void
    {
        [$family, , $plan] = $this->establishedPlan();
        $event = MarketplaceEvent::COMPLETED_EXTRA_VISIT_SUBMITTED;
        UserNotificationPreference::query()->create([
            'user_id' => $family->id, 'event_key' => $event,
            'in_app_enabled' => true, 'email_enabled' => false, 'sms_enabled' => false, 'push_enabled' => false,
        ]);
        $service = app(MarketplaceNotificationService::class);
        $key = 'channel-aware-completed-extra-'.$family->id;
        $service->notify($family, $event, 'Extra visit reported', 'Review the visit.', route('family.care.show', $plan), [], $plan, $key);

        $preference = UserNotificationPreference::query()->where('user_id', $family->id)->where('event_key', $event)->firstOrFail();
        $preference->forceFill(['email_enabled' => true])->save();
        $service->notify($family, $event, 'Extra visit reported', 'Review the visit.', route('family.care.show', $plan), [], $plan, $key);

        $this->assertSame(1, $family->notificationDeliveries()->where('dedupe_key', $key.':in_app')->count());
        $this->assertSame(1, $family->notificationDeliveries()->where('dedupe_key', $key.':email')->count());
    }

    public function test_caregiver_and_family_interfaces_expose_the_workflow_on_the_regular_plan(): void
    {
        [$family, $caregiver, $plan] = $this->establishedPlan();
        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->assertSee('Report a completed extra visit')
            ->call('openCompletedExtraVisit', $plan->id)
            ->assertSet('reportPlanId', $plan->id)
            ->assertSee('Care already provided');

        $report = $this->submit($plan, $caregiver);
        Livewire::actingAs($family)
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee($caregiver->name.' reported an extra visit')
            ->assertSee('Approve visit and payment')
            ->assertSee('This visit did not happen');
    }

    /** @return array{User,User,CarePlan} */
    private function establishedPlan(): array
    {
        $family = User::factory()->create(['role' => 'family', 'name' => 'Barbara Pearl', 'email' => Str::uuid().'@family.test']);
        $caregiver = $this->caregiver(Str::uuid().'@caregiver.test');
        $source = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Established care for Barbara',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subDays(60)->setTime(9, 0),
            'requested_end_at' => now()->subDays(60)->setTime(11, 0),
            'address_line1' => '1238 White Flint Circle', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27703',
        ]);
        $source->recipient()->create(['full_name' => 'Barbara Pearl', 'relationship_to_family' => 'Self', 'recipient_is_requester' => true]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $source->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 30,
        ]);
        $sourceBooking = CareBooking::query()->create([
            'care_request_id' => $source->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subDays(60)->setTime(9, 0),
            'scheduled_end_at' => now()->subDays(60)->setTime(11, 0),
            'started_at' => now()->subDays(60)->setTime(9, 0),
            'completed_at' => now()->subDays(60)->setTime(11, 0),
            'family_confirmed_at' => now()->subDays(60)->setTime(12, 0),
        ]);
        $relationship = CareRelationship::query()->create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'last_care_request_id' => $source->id,
            'last_care_booking_id' => $sourceBooking->id, 'recipient_name' => 'Barbara Pearl',
            'status' => CareRelationship::STATUS_ACTIVE, 'last_visit_at' => $sourceBooking->completed_at,
        ]);
        $reportDay = Carbon::parse('2026-07-24', 'America/New_York');
        $plan = CarePlan::query()->create([
            'care_relationship_id' => $relationship->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'source_care_booking_id' => $sourceBooking->id,
            'status' => CarePlan::STATUS_ACTIVE, 'title' => 'Regular care for Barbara',
            'recipient_snapshot' => ['full_name' => 'Barbara Pearl', 'relationship_to_family' => 'Self', 'recipient_is_requester' => true],
            'address_snapshot' => ['address_line1' => '1238 White Flint Circle', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27703'],
            'task_snapshot' => [], 'care_notes' => 'Companionship and meal support.',
            'schedule_days' => [($reportDay->dayOfWeek + 1) % 7],
            'schedule_start_time' => '07:30', 'schedule_end_time' => '09:30',
            'starts_on' => now()->subDays(60)->toDateString(), 'timezone' => 'America/New_York',
            'hourly_rate' => 30, 'accepted_at' => now()->subDays(60), 'activated_at' => now()->subDays(60),
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);

        return [$family, $caregiver, $plan->fresh(['family', 'caregiver.caregiverProfile', 'relationship'])];
    }

    private function caregiver(string $email): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Charles Petrini-Poli', 'email' => $email]);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'status' => 'active', 'slug' => 'charles-'.$caregiver->id,
            'platform_hourly_rate' => 30, 'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'stripe_connect_account_id' => 'acct_bypass_'.$caregiver->id,
            'stripe_connect_onboarding_completed_at' => now(), 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
        ]);

        return $caregiver;
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-07-24', 'start_time' => '10:00', 'end_time' => '11:45',
            'break_minutes' => 15, 'reason_code' => CompletedExtraVisitRequest::REASON_FAMILY_REQUESTED,
            'explanation' => 'Barbara asked me to provide additional care that Friday.',
            'care_notes' => 'Prepared lunch and provided companionship.', 'attested' => true,
        ], $overrides);
    }

    private function submit(CarePlan $plan, User $caregiver): CompletedExtraVisitRequest
    {
        return app(CompletedExtraVisitService::class)->submit($plan->fresh(['family', 'caregiver.caregiverProfile', 'relationship']), $caregiver, $this->input(), (string) Str::uuid());
    }
}
