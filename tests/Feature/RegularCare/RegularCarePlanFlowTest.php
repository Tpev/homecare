<?php

namespace Tests\Feature\RegularCare;

use App\Livewire\Admin\CarePlanShow as AdminCarePlanShow;
use App\Livewire\Admin\CareRequestShow as AdminCareRequestShow;
use App\Livewire\Admin\CareRequestsIndex as AdminCareRequestsIndex;
use App\Livewire\Admin\UsageAnalytics as AdminUsageAnalytics;
use App\Livewire\Caregiver\RegularClients;
use App\Livewire\Dashboard\Home as DashboardHome;
use App\Livewire\Family\RegularCareComposer;
use App\Livewire\Family\RegularCareIndex as FamilyRegularCareIndex;
use App\Livewire\Family\RegularCareShow;
use App\Livewire\Family\RequestsIndex;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CareBookingCheckInPolicy;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Services\RegularCare\CarePlanOperationsService;
use App\Services\RegularCare\CarePlanPaymentWindowService;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\RegularCareMigrationService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RegularCarePlanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_offer_and_caregiver_acceptance_generates_real_booking_with_payment(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $firstVisit = now()->addDay();

        Livewire::actingAs($family)
            ->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->set('scheduleDays', [(string) $firstVisit->dayOfWeek])
            ->set('scheduleStartTime', '09:00')
            ->set('scheduleEndTime', '12:00')
            ->set('startsOn', $firstVisit->toDateString())
            ->call('sendOffer');

        $plan = CarePlan::query()->firstOrFail();
        $this->assertSame(CarePlan::STATUS_PENDING_CAREGIVER, $plan->status);
        $this->assertSame(30.00, (float) $plan->hourly_rate);

        $this->actingAs($caregiver)
            ->get(route('caregiver.regular-clients.index'))
            ->assertOk()
            ->assertSee('Direct regular-care offers')
            ->assertSee('Family member receives care')
            ->assertSee('Accept schedule');

        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->call('acceptOffer', $plan->id);

        $plan->refresh();
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);

        $generatedRequest = CareRequest::query()
            ->where('care_plan_id', $plan->id)
            ->where('id', '!=', $request->id)
            ->firstOrFail();

        $booking = CareBooking::query()
            ->where('care_plan_id', $plan->id)
            ->where('care_request_id', $generatedRequest->id)
            ->firstOrFail();

        $this->assertSame(CareRequest::STATUS_FILLED, $generatedRequest->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertSame($booking->id, $plan->fresh()->next_booking_id);

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
        ]);
    }

    public function test_caregiver_can_counter_and_family_accepts_counter_schedule(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $firstVisit = now()->addDay();

        Livewire::actingAs($family)
            ->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->set('scheduleDays', [(string) $firstVisit->dayOfWeek])
            ->set('scheduleStartTime', '09:00')
            ->set('scheduleEndTime', '12:00')
            ->set('startsOn', $firstVisit->toDateString())
            ->call('sendOffer');

        $plan = CarePlan::query()->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->call('openCounter', $plan->id)
            ->set('counterScheduleDays', [(string) $firstVisit->dayOfWeek])
            ->set('counterStartTime', '13:00')
            ->set('counterEndTime', '16:00')
            ->set('counterStartsOn', $firstVisit->toDateString())
            ->set('counterNote', 'Afternoons work better for my route.')
            ->call('sendCounter');

        $plan->refresh();
        $this->assertSame(CarePlan::STATUS_COUNTERED, $plan->status);
        $this->assertSame([(string) $firstVisit->dayOfWeek], array_map('strval', $plan->counter_schedule_days));

        Livewire::actingAs($family)
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('acceptCounter');

        $plan->refresh();
        $booking = CareBooking::query()->where('care_plan_id', $plan->id)->firstOrFail();

        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertSame([$firstVisit->dayOfWeek], $plan->schedule_days);
        $this->assertSame('13:00:00', $plan->schedule_start_time);
        $this->assertSame(13, (int) $booking->scheduled_start_at->format('H'));
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
    }

    public function test_caregiver_can_decline_direct_offer_and_family_is_notified(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $firstVisit = now()->addDays(3);
        $plan = app(CarePlanService::class)->sendOfferFromRequest($request, $family, [
            'title' => 'Regular care for Don',
            'schedule_days' => [$firstVisit->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'starts_on' => $firstVisit->toDateString(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->call('declineOffer', $plan->id)
            ->assertHasNoErrors();

        $this->assertSame(CarePlan::STATUS_DECLINED, $plan->fresh()->status);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::REGULAR_CARE_DECLINED,
        ]);
    }

    public function test_generation_is_idempotent_and_honors_end_date(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(3)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first, $first->copy()->addDays(14));
        $count = $plan->generatedBookings()->count();

        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());

        $this->assertSame($count, $plan->generatedBookings()->count());
        $this->assertLessThanOrEqual(3, $count);
        $this->assertFalse($plan->generatedBookings()->where('scheduled_start_at', '>', $plan->ends_on->copy()->endOfDay())->exists());
    }

    public function test_extra_visit_and_skip_affect_only_the_selected_visit(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(3)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);
        $originalCount = $plan->generatedBookings()->count();
        $extraStart = now()->addDays(5)->setTime(14, 0);

        $change = app(CarePlanService::class)->requestExtraVisit($plan, $family, $extraStart, $extraStart->copy()->addHours(2), 'Afternoon appointment support.');
        app(CarePlanService::class)->respondToScheduleChange($change, $caregiver, true);

        $this->assertDatabaseHas('care_plan_schedule_changes', ['id' => $change->id, 'status' => CarePlanScheduleChange::STATUS_ACCEPTED]);
        $this->assertSame($originalCount + 1, $plan->generatedBookings()->count());
        $this->assertDatabaseHas('care_bookings', ['care_plan_id' => $plan->id, 'plan_visit_kind' => 'extra']);

        $toSkip = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->orderBy('scheduled_start_at')->firstOrFail();
        app(CarePlanService::class)->skipVisit($plan, $toSkip, $family);
        $this->assertSame(CareBooking::STATUS_CANCELLED, $toSkip->fresh()->status);
        $this->assertTrue($plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->exists());
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
    }

    public function test_schedule_change_requires_caregiver_and_only_replaces_future_visits(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(3)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);
        $effective = now()->addDays(10)->startOfDay();
        $keptIds = $plan->generatedBookings()->where('scheduled_start_at', '<', $effective)->pluck('id')->all();
        $newDay = $effective->dayOfWeek;

        $change = app(CarePlanService::class)->requestScheduleChange($plan, $family, [
            'schedule_days' => [$newDay], 'schedule_start_time' => '13:00', 'schedule_end_time' => '16:00',
            'starts_on' => $effective->toDateString(), 'effective_on' => $effective->toDateString(),
        ]);
        $this->assertSame(CarePlanScheduleChange::STATUS_PENDING, $change->status);
        foreach ($keptIds as $id) {
            $this->assertNotSame(CareBooking::STATUS_CANCELLED, CareBooking::query()->findOrFail($id)->status);
        }

        app(CarePlanService::class)->respondToScheduleChange($change, $caregiver, true);
        $plan->refresh();
        $this->assertSame(2, $plan->schedule_version);
        $this->assertSame([$newDay], $plan->schedule_days);
        $this->assertSame('13:00:00', $plan->schedule_start_time);
        $this->assertTrue($plan->generatedBookings()->where('plan_schedule_version', 2)->where('status', CareBooking::STATUS_SCHEDULED)->exists());
    }

    public function test_pause_resume_and_end_have_explicit_visit_behavior(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(3)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);

        app(CarePlanService::class)->pausePlan($plan, $family, now()->startOfDay());
        $this->assertSame(CarePlan::STATUS_PAUSED, $plan->fresh()->status);
        $this->assertFalse($plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->exists());

        app(CarePlanService::class)->resumePlan($plan->fresh(), $family);
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
        $this->assertTrue($plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->exists());

        $next = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->orderBy('scheduled_start_at')->firstOrFail();
        app(CarePlanService::class)->endPlan($plan->fresh(), $family, false);
        $this->assertSame(CarePlan::STATUS_ENDED, $plan->fresh()->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $next->fresh()->status);
        $this->assertSame(0, $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->where('care_bookings.id', '!=', $next->id)->count());
    }

    public function test_payment_authorization_only_runs_inside_window_and_plan_recovers(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(5)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);
        $booking = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->orderBy('scheduled_start_at')->firstOrFail();
        $this->assertNull($booking->payment);

        $booking->forceFill(['scheduled_start_at' => now()->addHours(24), 'scheduled_end_at' => now()->addHours(27)])->save();
        app(CarePlanPaymentWindowService::class)->preparePlan($plan->fresh());

        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $booking->fresh()->payment?->status);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->fresh()->payment_status);
    }

    public function test_regular_check_in_requires_time_and_payment_unless_admin_override_exists(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(3)->startOfDay());
        $booking = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->firstOrFail();
        $booking->forceFill([
            'scheduled_start_at' => now()->addHour(), 'scheduled_end_at' => now()->addHours(4),
            'caregiver_terms_accepted_at' => now(),
        ])->save();
        $policy = app(CareBookingCheckInPolicy::class);

        $this->assertSame('too_early', $policy->evaluate($booking->fresh(), now())['code']);
        $this->assertSame('payment_not_protected', $policy->evaluate($booking->fresh(), now()->addMinutes(35))['code']);
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id, 'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED, 'currency' => 'usd', 'amount_authorized_cents' => 10800,
        ]);
        $this->assertTrue($policy->evaluate($booking->fresh('payment'), now()->addMinutes(35))['allowed']);

        $booking->payment->forceFill(['authorization_expires_at' => now()->subMinute()])->save();
        $this->assertSame('payment_not_protected', $policy->evaluate($booking->fresh('payment'), now()->addMinutes(35))['code']);

        $booking->payment()->delete();
        $booking->forceFill(['check_in_override_at' => now(), 'check_in_override_by_user_id' => $family->id, 'check_in_override_reason' => 'Operations approved test override.'])->save();
        $this->assertTrue($policy->evaluate($booking->fresh(), now())['allowed']);
    }

    public function test_second_and_third_visits_capture_and_transfer_independently(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $caregiver->caregiverProfile->forceFill([
            'stripe_connect_account_id' => 'acct_regular_'.$caregiver->id,
            'stripe_connect_onboarding_completed_at' => now(),
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
        ])->save();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(3)->startOfDay());
        $bookings = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->orderBy('scheduled_start_at')->limit(3)->get();
        $this->assertCount(3, $bookings);
        $payments = app(BookingPaymentService::class);

        foreach ($bookings->slice(1, 2) as $booking) {
            $authorized = $payments->authorizeForBooking($booking->load(['family', 'caregiver.caregiverProfile', 'application', 'payment']));
            $booking->forceFill([
                'status' => CareBooking::STATUS_COMPLETED,
                'started_at' => $booking->scheduled_start_at,
                'completed_at' => $booking->scheduled_end_at,
                'timesheet_submitted_at' => now(),
                'worked_minutes' => 180,
                'family_confirmed_at' => now(),
            ])->save();
            $captured = $payments->captureForBooking($booking->fresh(['family', 'caregiver.caregiverProfile', 'application', 'payment']));

            $this->assertNotNull($authorized->stripe_payment_intent_id);
            $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $captured->status);
            $this->assertNotNull($captured->stripe_transfer_id);
        }

        $visitPayments = CareBookingPayment::query()->whereIn('care_booking_id', $bookings->slice(1, 2)->pluck('id'))->get();
        $this->assertCount(2, $visitPayments);
        $this->assertSame(2, $visitPayments->pluck('stripe_payment_intent_id')->unique()->count());
        $this->assertSame(2, $visitPayments->pluck('stripe_transfer_id')->unique()->count());
    }

    public function test_admin_check_in_override_requires_reason_and_is_audited(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(3)->startOfDay());
        $booking = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(AdminCareRequestShow::class, ['careRequest' => $booking->careRequest])
            ->set('checkInOverrideReason', 'Payment confirmed manually by operations.')
            ->call('allowRegularCareCheckIn')
            ->assertHasNoErrors();

        $booking->refresh();
        $this->assertSame($admin->id, $booking->check_in_override_by_user_id);
        $this->assertNotNull($booking->check_in_override_at);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
            'event_type' => 'regular_care_check_in_override',
        ]);
    }

    public function test_admin_plan_state_changes_retain_actor_and_reason(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(3)->startOfDay());
        $admin = User::factory()->create(['role' => 'admin']);
        $operations = app(CarePlanOperationsService::class);

        $operations->pause($plan, $admin, 'Family called support to pause while travelling.');
        $operations->resume($plan->fresh(), $admin, 'Family confirmed they returned home.');

        $this->assertDatabaseHas('care_plan_events', [
            'care_plan_id' => $plan->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'admin_paused',
            'reason' => 'Family called support to pause while travelling.',
        ]);
        $this->assertDatabaseHas('care_plan_events', [
            'care_plan_id' => $plan->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'admin_resumed',
            'reason' => 'Family confirmed they returned home.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.care-plans.show', $plan))
            ->assertOk()
            ->assertSee('Schedule history')
            ->assertSee('Operations audit')
            ->assertSee('Notification history')
            ->assertSee('Family confirmed they returned home.');
    }

    public function test_existing_customer_migration_dry_run_and_execute_are_idempotent(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $request->forceFill([
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_days' => [now()->addDay()->dayOfWeek],
            'recurring_starts_on' => now()->addDay()->toDateString(),
            'recurring_start_time' => '09:00:00', 'recurring_end_time' => '12:00:00',
        ])->save();
        $booking = $request->booking;
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id, 'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_CAPTURED, 'currency' => 'usd', 'amount_captured_cents' => 8400,
            'stripe_payment_intent_id' => 'pi_preserved_migration',
        ]);

        $this->artisan('homecare:migrate-regular-care-customer --request='.$request->id)->assertSuccessful();
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertSame('pi_preserved_migration', $booking->fresh()->payment?->stripe_payment_intent_id);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->artisan('homecare:migrate-regular-care-customer', [
            '--request' => $request->id,
            '--execute' => true,
            '--actor' => $admin->id,
            '--confirm-request' => $request->id,
        ])->assertFailed();

        $service = app(RegularCareMigrationService::class);
        $confirmedSchedule = [
            'days' => $request->recurring_days,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'starts_on' => $request->recurring_starts_on->toDateString(),
            'ends_on' => null,
            'timezone' => config('app.timezone'),
        ];
        $request->forceFill(['recurring_days' => null])->save();
        $this->artisan('homecare:migrate-regular-care-customer', [
            '--request' => $request->id,
            '--days' => implode(',', $confirmedSchedule['days']),
            '--start-time' => $confirmedSchedule['start_time'],
            '--end-time' => $confirmedSchedule['end_time'],
            '--starts-on' => $confirmedSchedule['starts_on'],
            '--timezone' => $confirmedSchedule['timezone'],
            '--actor' => $admin->id,
            '--confirm-request' => $request->id,
            '--execute' => true,
        ])->assertSuccessful();
        $plan = CarePlan::query()->where('source_care_request_id', $request->id)->firstOrFail();
        $count = $plan->generatedBookings()->count();
        $samePlan = $service->execute($request->fresh(), $confirmedSchedule);

        $this->assertSame($plan->id, $samePlan->id);
        $this->assertSame($count, $samePlan->generatedBookings()->count());
        $this->assertSame('pi_preserved_migration', $booking->fresh()->payment?->stripe_payment_intent_id);
        $this->assertDatabaseHas('care_booking_events', ['care_booking_id' => $booking->id, 'event_type' => 'regular_care_existing_customer_migrated']);
        $this->assertDatabaseHas('care_plan_events', [
            'care_plan_id' => $plan->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'existing_customer_migrated',
        ]);

        $repairedSchedule = array_merge($confirmedSchedule, ['end_time' => '13:00']);
        $repairedPlan = $service->execute($request->fresh(), $repairedSchedule);
        $repairEventCount = $repairedPlan->events()->where('event_type', 'migration_schedule_repaired')->count();
        $sameRepair = $service->execute($request->fresh(), $repairedSchedule);

        $this->assertSame($plan->id, $repairedPlan->id);
        $this->assertSame('13:00:00', $sameRepair->schedule_end_time);
        $this->assertSame($repairEventCount, $sameRepair->events()->where('event_type', 'migration_schedule_repaired')->count());
        $this->assertSame('pi_preserved_migration', $booking->fresh()->payment?->stripe_payment_intent_id);
        $this->assertDatabaseHas('care_plan_events', [
            'care_plan_id' => $plan->id,
            'event_type' => 'migration_schedule_repaired',
        ]);
    }

    public function test_duplicate_offer_submission_cannot_create_a_second_plan(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $payload = [
            'title' => 'Stable regular care offer',
            'schedule_days' => [now()->addDays(2)->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'starts_on' => now()->addDays(2)->toDateString(),
        ];

        $plan = app(CarePlanService::class)->sendOfferFromRequest($request, $family, $payload);
        try {
            app(CarePlanService::class)->sendOfferFromRequest($request, $family, $payload);
        } catch (ValidationException) {
            // A repeated stale submission may be rejected after the first transaction commits.
        }

        $this->assertSame(1, CarePlan::query()->where('source_care_request_id', $request->id)->count());
        $this->assertSame($plan->id, $request->fresh()->care_plan_id);
        $this->assertSame(1, $caregiver->notifications()->where('data->event_key', MarketplaceEvent::REGULAR_CARE_OFFERED)->count());
    }

    public function test_repeated_recurring_marketplace_activation_reuses_the_plan_and_source_booking(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $request->booking()->delete();
        $request->forceFill([
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_days' => [now()->addDays(2)->dayOfWeek],
            'recurring_start_time' => '09:00:00',
            'recurring_end_time' => '12:00:00',
            'recurring_starts_on' => now()->addDays(2)->toDateString(),
        ])->save();
        $source = $request->fresh(['recipient', 'tasks', 'applications.caregiver.caregiverProfile', 'booking']);
        $application = $source->applications->firstOrFail();

        $first = app(CarePlanService::class)->activateFromRecurringRequest($source, $application, $family);
        $second = app(CarePlanService::class)->activateFromRecurringRequest($source, $application, $family);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CarePlan::query()->where('source_care_request_id', $source->id)->count());
        $this->assertSame(1, CareBooking::query()->where('care_request_id', $source->id)->count());
        $this->assertSame(
            1,
            $caregiver->notifications()->where('data->event_key', MarketplaceEvent::CAREGIVER_HIRED)->count()
        );
    }

    public function test_duration_only_schedule_change_restores_and_updates_the_same_occurrence(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(3)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);
        $booking = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->orderBy('scheduled_start_at')->firstOrFail();

        $change = app(CarePlanService::class)->requestScheduleChange($plan, $family, [
            'schedule_days' => $plan->schedule_days,
            'schedule_start_time' => substr((string) $plan->schedule_start_time, 0, 5),
            'schedule_end_time' => '13:00',
            'starts_on' => $booking->scheduled_start_at->toDateString(),
            'effective_on' => $booking->scheduled_start_at->toDateString(),
        ]);
        app(CarePlanService::class)->respondToScheduleChange($change, $caregiver, true);

        $booking->refresh();
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertSame('13:00', $booking->scheduled_end_at->format('H:i'));
        $this->assertSame(240, $booking->expected_minutes);
        $this->assertSame(1, $plan->generatedBookings()->where('scheduled_start_at', $booking->scheduled_start_at)->count());
    }

    public function test_extra_visit_cannot_silently_overlap_a_regular_occurrence(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(3)->startOfDay());
        $booking = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->firstOrFail();
        $change = app(CarePlanService::class)->requestExtraVisit(
            $plan,
            $family,
            $booking->scheduled_start_at->copy(),
            $booking->scheduled_end_at->copy(),
            'Conflicting request should be rejected.'
        );

        $this->expectException(ValidationException::class);
        try {
            app(CarePlanService::class)->respondToScheduleChange($change, $caregiver, true);
        } finally {
            $this->assertSame(CarePlanScheduleChange::STATUS_PENDING, $change->fresh()->status);
            $this->assertSame(0, $plan->generatedBookings()->where('plan_visit_kind', 'extra')->count());
        }
    }

    public function test_cancelled_regular_occurrence_is_reactivated_without_creating_a_duplicate(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDays(2)->startOfDay());
        $booking = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $booking->forceFill([
            'status' => CareBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Previous materialization was interrupted and must be replaced.',
        ])->save();

        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());

        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancellation_reason);
        $this->assertSame(1, $plan->generatedBookings()->where('scheduled_start_at', $booking->scheduled_start_at)->count());
    }

    public function test_extra_visit_cannot_claim_a_regular_slot_beyond_the_materialized_window(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $first = now()->addDays(2)->startOfDay();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);
        $futureStart = $first->copy()->addWeeks(8)->setTime(9, 0);

        $this->expectException(ValidationException::class);
        app(CarePlanOccurrenceService::class)->createExtraVisit(
            $plan,
            $futureStart,
            $futureStart->copy()->addHours(2)
        );
    }

    public function test_generated_occurrences_do_not_pollute_request_queues_or_create_empty_conversations(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDay()->startOfDay(), now()->addWeeks(6));
        $plan->forceFill(['schedule_days' => [0, 1, 2, 3, 4, 5, 6]])->save();
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());

        $this->assertGreaterThanOrEqual(30, $plan->generatedBookings()->count());
        $this->assertSame(0, CareRequestConversation::query()
            ->whereHas('careRequest', fn ($query) => $query->where('care_plan_id', $plan->id))
            ->count());

        Livewire::actingAs($family)
            ->test(RequestsIndex::class)
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 1);
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)
            ->test(AdminCareRequestsIndex::class)
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 1);
        Livewire::actingAs($admin)
            ->test(AdminUsageAnalytics::class)
            ->assertViewHas('summary', fn (array $summary) => $summary['requests_posted'] === 1
                && $summary['requests_filled'] === 1);
        Livewire::actingAs($family)
            ->test(DashboardHome::class)
            ->assertViewHas('familyData', fn (array $data) => data_get($data, 'stats.active_shifts') === 1);
        Livewire::actingAs($caregiver)
            ->test(DashboardHome::class)
            ->assertViewHas('caregiverData', fn (array $data) => data_get($data, 'stats.hired') === 1
                && data_get($data, 'work_inbox_counts.all') === 2);
    }

    public function test_family_next_visit_is_earliest_across_multiple_regular_care_plans(): void
    {
        [$family, $caregiver, $firstRequest] = $this->seedCompletedCareRelationship();
        [$unusedFamily, $unusedCaregiver, $secondRequest] = $this->seedCompletedCareRelationship();
        $secondApplication = $secondRequest->applications->firstOrFail();
        $secondApplication->forceFill(['caregiver_user_id' => $caregiver->id])->save();
        $familyAccountId = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->account($family)->id;
        $secondRequest->forceFill([
            'family_account_id' => $familyAccountId,
            'family_user_id' => $family->id,
        ])->save();
        $secondRequest->booking->forceFill([
            'family_account_id' => $familyAccountId,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'family_confirmed_at' => now(),
        ])->save();
        $firstRequest->booking->forceFill(['family_confirmed_at' => now()])->save();

        $laterPlan = $this->activateDirectPlan($family, $caregiver, $firstRequest->fresh([
            'recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile',
        ]), now()->addDays(4)->startOfDay());
        $earlierPlan = $this->activateDirectPlan($family, $caregiver, $secondRequest->fresh([
            'recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile',
        ]), now()->addDays(2)->startOfDay());
        $earlierBooking = $earlierPlan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();

        Livewire::actingAs($family)
            ->test(FamilyRegularCareIndex::class)
            ->assertViewHas('nextPlan', fn (?CarePlan $plan) => $plan?->id === $earlierPlan->id);
        Livewire::actingAs($family)
            ->test(DashboardHome::class)
            ->assertViewHas('familyData', function (array $data) use ($earlierBooking): bool {
                $nextRequest = collect($data['active_shifts'])->first(
                    fn (CareRequest $request) => $request->booking?->status === CareBooking::STATUS_SCHEDULED
                );

                return $nextRequest?->booking?->id === $earlierBooking->id;
            });

        $this->assertNotSame($laterPlan->id, $earlierPlan->id);
    }

    public function test_admin_payment_retry_is_blocked_for_healthy_authorization_and_recovers_failed_attempt(): void
    {
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDay()->startOfDay());
        $booking = $plan->generatedBookings()->whereHas('payment')->orderBy('scheduled_start_at')->firstOrFail();
        $payment = $booking->payment;
        $payments = app(BookingPaymentService::class);

        $this->assertFalse($payments->canRetryAuthorization($booking));
        try {
            $payments->retryAuthorizationForBooking($booking);
            $this->fail('A healthy authorization must not be retried.');
        } catch (\App\Exceptions\Payments\PaymentException) {
            $this->assertSame($payment->stripe_payment_intent_id, $booking->fresh()->payment?->stripe_payment_intent_id);
        }

        $payment->forceFill(['status' => CareBookingPayment::STATUS_FAILED, 'last_error' => 'Card was declined.'])->save();
        $recovered = $payments->retryAuthorizationForBooking($booking->fresh(['family', 'caregiver.caregiverProfile', 'application', 'payment']));
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $recovered->status);
        $this->assertSame(2, (int) data_get($recovered->metadata, 'authorization_attempt_count'));

        $expiredIntentId = $recovered->stripe_payment_intent_id;
        $recovered->forceFill(['authorization_expires_at' => now()->subMinute()])->save();
        $replacement = $payments->retryAuthorizationForBooking($booking->fresh([
            'family', 'caregiver.caregiverProfile', 'application', 'payment',
        ]));
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $replacement->status);
        $this->assertSame(3, (int) data_get($replacement->metadata, 'authorization_attempt_count'));
        $this->assertSame($expiredIntentId, data_get($replacement->metadata, 'previous_payment_intent_id'));
        $this->assertSame($expiredIntentId, data_get($replacement->metadata, 'authorization_history.0.payment_intent_id'));

        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)
            ->test(AdminCarePlanShow::class, ['carePlan' => $plan->fresh()])
            ->assertDontSee('Retry payment');
    }

    public function test_repeated_payment_worker_does_not_repeat_a_failed_attempt_or_notification(): void
    {
        config()->set('services.stripe.bypass', false);
        $stripe = new class extends StripeClient
        {
            public int $authorizationCalls = 0;

            public string $paymentMethodId = 'pm_regular_failure';

            public bool $authorizeSuccessfully = false;

            public function ensureFamilyCustomer(User $family): string
            {
                return 'cus_regular_failure';
            }

            public function defaultPaymentMethodForCustomer(string $customerId): ?array
            {
                return ['id' => $this->paymentMethodId];
            }

            public function createManualAuthorization(
                CareBooking $booking,
                string $customerId,
                string $paymentMethodId,
                int $amountCents,
                string $currency,
                ?string $idempotencyKey = null,
            ): array {
                $this->authorizationCalls++;

                if ($this->authorizeSuccessfully) {
                    return [
                        'payment_intent_id' => 'pi_regular_recovered_'.$booking->id,
                        'client_secret' => 'pi_regular_recovered_secret',
                        'status' => 'requires_capture',
                        'amount' => $amountCents,
                        'authorization_expires_at' => now()->addDays(6),
                    ];
                }

                return [
                    'payment_intent_id' => 'pi_regular_declined_'.$booking->id,
                    'status' => 'requires_payment_method',
                    'amount' => $amountCents,
                    'authorization_expires_at' => null,
                    'failure_message' => 'Card declined for this visit.',
                ];
            }

            public function currency(): string
            {
                return 'usd';
            }
        };
        $this->app->instance(StripeClient::class, $stripe);
        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
        $family->forceFill(['stripe_customer_id' => 'cus_regular_failure'])->save();
        $first = now()->addDay()->startOfDay();
        $plan = app(CarePlanService::class)->sendOfferFromRequest($request, $family, [
            'title' => 'Payment failure deduplication',
            'schedule_days' => [$first->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'starts_on' => $first->toDateString(),
        ]);
        $plan = app(CarePlanService::class)->acceptOffer($plan, $caregiver);
        $booking = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();

        app(CarePlanPaymentWindowService::class)->preparePlan($plan->fresh());
        app(CarePlanPaymentWindowService::class)->preparePlan($plan->fresh());

        $this->assertSame(1, $stripe->authorizationCalls);
        $this->assertSame(CareBookingPayment::STATUS_FAILED, $booking->fresh()->payment?->status);
        $this->assertSame(CarePlan::STATUS_PAYMENT_ATTENTION, $plan->fresh()->status);
        $this->assertSame(1, $family->notifications()
            ->where('data->event_key', MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION)
            ->where('data->payload->care_booking_id', $booking->id)
            ->count());

        $stripe->paymentMethodId = 'pm_regular_replacement';
        $stripe->authorizeSuccessfully = true;
        $recovered = app(BookingPaymentService::class)->retryAuthorizationForBooking(
            $booking->fresh(['family', 'caregiver.caregiverProfile', 'application', 'payment'])
        );

        $this->assertSame(2, $stripe->authorizationCalls);
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $recovered->status);
        $this->assertSame('pm_regular_replacement', $recovered->stripe_payment_method_id);
        $this->assertSame('pi_regular_recovered_'.$booking->id, $recovered->stripe_payment_intent_id);
        $this->assertSame('pi_regular_declined_'.$booking->id, data_get($recovered->metadata, 'authorization_history.0.payment_intent_id'));
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
    }

    public function test_schedule_change_uses_the_actual_visit_time_at_the_twenty_four_hour_boundary(): void
    {
        config()->set('app.timezone', 'America/New_York');
        $clock = Carbon::parse('2026-08-03 08:00:00', 'America/New_York');
        Carbon::setTestNow($clock);

        try {
            [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
            $first = Carbon::parse('2026-08-04', 'America/New_York');
            $plan = $this->activateDirectPlan($family, $caregiver, $request, $first);

            try {
                app(CarePlanService::class)->requestScheduleChange($plan, $family, [
                    'schedule_days' => [$first->dayOfWeek],
                    'schedule_start_time' => '07:59',
                    'schedule_end_time' => '11:00',
                    'starts_on' => $first->toDateString(),
                    'effective_on' => $first->toDateString(),
                ]);
                $this->fail('A schedule occurrence inside 24 hours must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('effectiveOn', $exception->errors());
            }

            $change = app(CarePlanService::class)->requestScheduleChange($plan, $family, [
                'schedule_days' => [$first->dayOfWeek],
                'schedule_start_time' => '08:00',
                'schedule_end_time' => '11:00',
                'starts_on' => $first->toDateString(),
                'effective_on' => $first->toDateString(),
            ]);

            $this->assertSame(CarePlanScheduleChange::STATUS_PENDING, $change->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_skip_marks_only_visits_inside_the_twenty_four_hour_window_as_late(): void
    {
        config()->set('app.timezone', 'America/New_York');
        $clock = Carbon::parse('2026-08-03 09:00:00', 'America/New_York');
        Carbon::setTestNow($clock);

        try {
            [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
            $plan = $this->activateDirectPlan($family, $caregiver, $request, Carbon::parse('2026-08-04', 'America/New_York'));
            $booking = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
            app(CarePlanService::class)->skipVisit($plan, $booking, $family, 'Family no longer needs this visit.');
            $this->assertFalse((bool) $booking->fresh()->late_cancel_flag);

            [$insideFamily, $insideCaregiver, $insideRequest] = $this->seedCompletedCareRelationship();
            $insidePlan = $this->activateDirectPlan(
                $insideFamily,
                $insideCaregiver,
                $insideRequest,
                Carbon::parse('2026-08-04', 'America/New_York')
            );
            $insideBooking = $insidePlan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
            Carbon::setTestNow($clock->copy()->addMinute());
            app(CarePlanService::class)->skipVisit($insidePlan, $insideBooking, $insideFamily, 'Family cancelled inside 24 hours.');

            $this->assertTrue((bool) $insideBooking->fresh()->late_cancel_flag);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_scheduled_pause_transitions_and_resumes_automatically(): void
    {
        $clock = Carbon::parse('2026-08-03 09:00:00');
        Carbon::setTestNow($clock);
        try {
            [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();
            $plan = $this->activateDirectPlan($family, $caregiver, $request, now()->addDay()->startOfDay());
            app(CarePlanService::class)->pausePlan($plan, $family, now()->addDay(), now()->addDays(3));
            $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);

            Carbon::setTestNow($clock->copy()->addDay()->setTime(10, 0));
            app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
            $this->assertSame(CarePlan::STATUS_PAUSED, $plan->fresh()->status);

            Carbon::setTestNow($clock->copy()->addDays(3)->setTime(10, 0));
            app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
            $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
            $this->assertNull($plan->fresh()->pause_starts_on);
            $this->assertDatabaseHas('care_plan_events', ['care_plan_id' => $plan->id, 'event_type' => 'automatic_resume']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{User,User,CareRequest}
     */
    private function seedCompletedCareRelationship(): array
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $caregiver = $this->createReadyCaregiver();
        $task = CareTask::query()->firstOrCreate(['name' => 'Companionship']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning care for Don',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'scope_of_work' => 'Medication reminders, breakfast, and companionship.',
            'requested_start_at' => now()->subWeek()->setTime(9, 0),
            'requested_end_at' => now()->subWeek()->setTime(12, 0),
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Don',
            'relationship_to_family' => 'Father',
            'care_notes' => 'Don prefers tea before breakfast.',
        ]);
        $request->tasks()->sync([$task->id => ['task_note' => 'Keep Don company during breakfast.']]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 28.00,
            'cover_note' => 'Happy to support Don.',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subWeek()->setTime(9, 0),
            'scheduled_end_at' => now()->subWeek()->setTime(12, 0),
            'completed_at' => now()->subWeek()->setTime(12, 0),
            'reviewed_at' => now()->subDays(6),
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);

        $booking->forceFill([
            'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot(
                $request->fresh(['recipient', 'tasks']),
                $application
            ),
        ])->save();

        return [$family, $caregiver, $request->fresh(['recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile'])];
    }

    private function activateDirectPlan(User $family, User $caregiver, CareRequest $request, Carbon $first, ?Carbon $ends = null): CarePlan
    {
        config()->set('services.stripe.bypass', true);
        $plan = app(CarePlanService::class)->sendOfferFromRequest($request, $family, [
            'title' => 'Regular care for Don',
            'schedule_days' => [$first->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'starts_on' => $first->toDateString(),
            'ends_on' => $ends?->toDateString(),
            'care_notes' => 'Morning companionship.',
        ]);

        return app(CarePlanService::class)->acceptOffer($plan, $caregiver);
    }

    private function createReadyCaregiver(): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Caroline Care',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'caroline-care-'.$caregiver->id,
            'bio' => str_repeat('Reliable regular care specialist. ', 4),
            'platform_hourly_rate' => 28.00,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 12,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $skill = Skill::query()->create(['name' => 'Regular companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        return $caregiver;
    }
}
