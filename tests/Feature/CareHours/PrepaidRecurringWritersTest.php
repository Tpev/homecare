<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Dashboard\Home;
use App\Livewire\Family\RegularCareComposer;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareHourAccount;
use App\Models\CareHourPlanPreference;
use App\Models\CareHourPurchase;
use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\RegularCare\CarePlanHealthService;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Services\RegularCare\CarePlanOperationsService;
use App\Services\RegularCare\CarePlanPaymentWindowService;
use App\Services\RegularCare\CarePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidRecurringWritersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => false]);
        Notification::fake();
    }

    public function test_offer_acceptance_without_a_card_commits_prepaid_before_generating_and_preparing_visits(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->offer($family, $source, $account);
        $this->assertSame(CarePlan::STATUS_PENDING_CAREGIVER, $plan->status);
        $this->assertSame($account->id, CareHourPlanPreference::where('care_plan_id', $plan->id)->sole()->care_hour_account_id);
        $this->assertSame(0, $account->balance()['reserved_minutes']);

        $plan = app(CarePlanService::class)->acceptOffer($plan, $caregiver);
        $this->assertPlanFunding($plan, $account);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame(1080, $account->balance()['available_minutes']);
        $count = $plan->generatedBookings()->count();
        app(CarePlanService::class)->acceptOffer($plan->fresh(), $caregiver);
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
        app(CarePlanPaymentWindowService::class)->preparePlan($plan->fresh());
        $this->assertSame($count, $plan->generatedBookings()->count());
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertNull($family->fresh()->stripe_customer_id);
        $this->assertNoCardOrSettlement();
    }

    public function test_composer_defaults_to_existing_hours_and_offer_preserves_the_old_visit(): void
    {
        [$family, , $source, $account] = $this->scenario();
        $booking = $source->booking;
        $before = $booking->getRawOriginal();
        Livewire::actingAs($family)->test(RegularCareComposer::class, ['careRequest' => $source->id])
            ->assertSet('careHourAccountId', $account->id)
            ->set('startsOn', now()->addDay()->toDateString())
            ->call('sendOffer')->assertHasNoErrors();
        $plan = CarePlan::query()->sole();
        $this->assertSame($account->id, CareHourPlanPreference::where('care_plan_id', $plan->id)->sole()->care_hour_account_id);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertNoCardOrSettlement();
    }

    public function test_counter_acceptance_without_a_card_uses_the_confirmed_duration_and_prepaid_preference(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $service = app(CarePlanService::class);
        $plan = $this->offer($family, $source, $account);
        $plan = $service->counterOffer($plan, $caregiver, array_replace($this->schedule(), [
            'schedule_start_time' => '13:00', 'schedule_end_time' => '16:00',
        ]));
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $plan = $service->acceptCounter($plan, $family);
        $this->assertPlanFunding($plan, $account);
        $next = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $this->assertSame('13:00', $next->scheduled_start_at->format('H:i'));
        $this->assertSame(180, $next->expected_minutes);
        $this->assertSame(180, $account->balance()['reserved_minutes']);
        $service->acceptCounter($plan->fresh(), $family);
        $this->assertSame(180, $account->balance()['reserved_minutes']);
        $this->assertNoCardOrSettlement();
    }

    public function test_positive_balance_does_not_bypass_missing_card_for_an_offer_without_prepaid_selection(): void
    {
        [$family, , $source, $account] = $this->scenario();
        try {
            $this->offer($family, $source);
            $this->fail('A card offer must still require the existing payment setup.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('payment method', $exception->getMessage());
        }
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_hour_plan_preferences', 0);
        $this->assertSame(1200, $account->balance()['available_minutes']);
    }

    public function test_default_card_offer_and_counter_leave_positive_hours_untouched(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        config(['services.stripe.bypass' => true]);
        $service = app(CarePlanService::class);
        $plan = $service->counterOffer($this->offer($family, $source), $caregiver, $this->schedule());
        $plan = $service->acceptCounter($plan, $family);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
        $this->assertSame('authorized', $plan->nextBooking->payment->status);
        $this->assertDatabaseCount('care_hour_plan_preferences', 0);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertSame(1200, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
    }

    public function test_marketplace_activation_adopts_source_and_generates_replayed_occurrences_in_prepaid_mode(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario(recurring: true);
        $application = $source->applications()->sole();
        $service = app(CarePlanService::class);
        $plan = $service->activateFromRecurringRequest($source, $application, $family, $account);
        $sourceBooking = $source->booking()->sole();
        $this->assertSame($sourceBooking->id, $plan->source_care_booking_id);
        $this->assertSame($plan->id, $sourceBooking->care_plan_id);
        $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($sourceBooking)->status);
        $this->assertPlanFunding($plan, $account);
        $count = $plan->generatedBookings()->count();
        $again = $service->activateFromRecurringRequest($source->fresh(), $application, $family, $account);
        app(CarePlanOccurrenceService::class)->attachSourceRequestAsFirstVisit($again, $source->fresh(), $application);
        $this->assertSame($plan->id, $again->id);
        $this->assertSame($count, $plan->generatedBookings()->count());
        $this->assertSame($sourceBooking->id, $source->booking()->sole()->id);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertNoCardOrSettlement();
    }

    #[DataProvider('fundingModes')]
    public function test_accepted_extra_visit_is_prepared_immediately_and_skip_restores_only_that_visit(bool $prepaid): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $prepaid ? $account : null);
        $service = app(CarePlanService::class);
        $regular = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $extraStart = now()->addDays(4)->setTime(14, 0);
        $change = $service->requestExtraVisit($plan, $family, $extraStart, $extraStart->copy()->addHours(2), 'Appointment support.');
        $this->assertFalse($plan->generatedBookings()->where('plan_visit_kind', 'extra')->exists());
        $service->respondToScheduleChange($change, $caregiver, true);
        $extra = $plan->generatedBookings()->where('plan_visit_kind', 'extra')->sole();
        $this->assertSame(CarePlanScheduleChange::STATUS_ACCEPTED, $change->fresh()->status);
        $this->assertSame($prepaid ? 240 : 0, $account->balance()['reserved_minutes']);
        $this->assertSame($prepaid ? 'ready' : 'authorized', $prepaid
            ? app(PrepaidBookingFundingService::class)->forBooking($extra)->status : $extra->payment->status);
        $service->respondToScheduleChange($change->fresh(), $caregiver, true);
        $this->assertSame(1, $plan->generatedBookings()->where('plan_visit_kind', 'extra')->count());
        $service->skipVisit($plan->fresh(), $extra, $family);
        $this->assertSame(CareBooking::STATUS_CANCELLED, $extra->fresh()->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $regular->fresh()->status);
        $this->assertSame($prepaid ? 120 : 0, $account->balance()['reserved_minutes']);
        $this->assertSame($prepaid ? 1080 : 1200, $account->balance()['available_minutes']);
        if ($prepaid) {
            $this->assertSame('released', app(PrepaidBookingFundingService::class)->forBooking($extra)->status);
            $this->assertNoCardOrSettlement();
        } else {
            $this->assertSame('cancelled', $extra->fresh()->payment->status);
            $this->assertSame('authorized', $regular->fresh()->payment->status);
            $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        }
    }

    #[DataProvider('fundingModes')]
    public function test_pause_resume_and_end_preserve_only_the_kept_next_visit(bool $prepaid): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $prepaid ? $account : null);
        $service = app(CarePlanService::class);
        $first = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $count = $plan->generatedBookings()->count();
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false]);
        $plan = $service->pausePlan($plan, $family, now()->startOfDay());
        $this->assertSame(CarePlan::STATUS_PAUSED, $plan->status);
        $this->assertFalse($plan->generatedBookings()->where('status', 'scheduled')->exists());
        $this->assertSame(1200, $account->balance()['available_minutes']);
        $plan = $service->resumePlan($plan, $family);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $first->fresh()->status);
        $this->assertSame($count, $plan->generatedBookings()->count());
        $this->assertSame($prepaid ? 120 : 0, $account->balance()['reserved_minutes']);
        $plan = $service->endPlan($plan, $family, false);
        $service->endPlan($plan->fresh(), $family, false);
        $this->assertSame(CarePlan::STATUS_ENDED, $plan->status);
        $this->assertSame($first->id, $plan->generatedBookings()->where('status', 'scheduled')->sole()->id);
        $this->assertSame($prepaid ? 120 : 0, $account->balance()['reserved_minutes']);
        if ($prepaid) {
            $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($first)->status);
            $this->assertNoCardOrSettlement();
        } else {
            $this->assertSame('authorized', $first->fresh()->payment->status);
            $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        }
    }

    public function test_payment_worker_prepares_only_due_prepaid_occurrences_with_new_sales_disabled(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $account);
        $service = app(CarePlanService::class);
        $plan = $service->pausePlan($plan, $family, now()->startOfDay());
        $plan = $service->resumePlan($plan, $family);
        $bookings = $plan->generatedBookings()->orderBy('scheduled_start_at')->get();
        $next = $bookings[1];
        $this->assertSame('selected', app(PrepaidBookingFundingService::class)->forBooking($next)->status);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false]);
        $this->travelTo($next->scheduled_start_at->copy()->subHours(24));
        $this->artisan('homecare:prepare-regular-care-payments', ['--plan' => $plan->id, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->artisan('homecare:prepare-regular-care-payments', ['--plan' => $plan->id])->assertSuccessful();
        $this->artisan('homecare:prepare-regular-care-payments', ['--plan' => $plan->id])->assertSuccessful();
        $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($next)->status);
        $this->assertSame('selected', app(PrepaidBookingFundingService::class)->forBooking($bookings[2])->status);
        $this->assertSame(240, $account->balance()['reserved_minutes']);
        $this->assertNoCardOrSettlement();
    }

    #[DataProvider('fundingModes')]
    public function test_admin_plan_pause_resume_and_end_use_the_selected_mode_and_record_the_actor(bool $prepaid): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $prepaid ? $account : null);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(CarePlanOperationsService::class);
        $plan = $service->pause($plan, $admin, 'Family requested a temporary pause.');
        $this->assertSame(1200, $account->balance()['available_minutes']);
        $plan = $service->resume($plan, $admin, 'Family confirmed care can resume.');
        $this->assertSame($prepaid ? 120 : 0, $account->balance()['reserved_minutes']);
        $plan = $service->end($plan, $admin, 'Family confirmed this agreement has ended.');
        $this->assertSame(CarePlan::STATUS_ENDED, $plan->status);
        $this->assertSame(1200, $account->balance()['available_minutes']);
        $this->assertFalse($plan->generatedBookings()->where('status', 'scheduled')->exists());
        foreach (['admin_paused', 'admin_resumed', 'admin_ended'] as $event) {
            $this->assertDatabaseHas('care_plan_events', ['care_plan_id' => $plan->id, 'actor_user_id' => $admin->id, 'event_type' => $event]);
        }
        if ($prepaid) {
            $this->assertNoCardOrSettlement();
        } else {
            $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        }
    }

    public function test_unfunded_duration_increase_cannot_displace_the_original_prepaid_reservation(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        [$family, $caregiver, $source, $account] = $this->scenario(minutes: 120);
        $plan = $this->activate($family, $caregiver, $source, $account);
        $first = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $service = app(CarePlanService::class);
        $change = $service->requestScheduleChange($plan, $family, array_replace($this->schedule(), [
            'schedule_end_time' => '12:00', 'effective_on' => now()->addDay()->toDateString(),
        ]));
        try {
            $service->respondToScheduleChange($change, $caregiver, true);
            $this->fail('Additional care requires funded coverage before replacing a protected visit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('changeRequest', $exception->errors());
        }
        $this->assertSame(CarePlanScheduleChange::STATUS_PENDING, $change->fresh()->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $first->fresh()->status);
        $this->assertSame(120, $first->fresh()->expected_minutes);
        $this->assertSame(1, $plan->fresh()->schedule_version);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($first)->status);
        $this->assertNoCardOrSettlement();
    }

    public function test_ending_with_next_visit_cancelled_returns_every_prepaid_minute(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $account);
        app(CarePlanService::class)->endPlan($plan, $family, true);
        $this->assertFalse($plan->generatedBookings()->where('status', 'scheduled')->exists());
        $this->assertSame(1200, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertNoCardOrSettlement();
    }

    #[DataProvider('fundingModes')]
    public function test_accepted_duration_change_reuses_occurrence_and_replaces_its_funding_once(bool $prepaid): void
    {
        $this->travelTo(now()->setTime(8, 0));
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, $prepaid ? $account : null);
        $first = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $count = $plan->generatedBookings()->count();
        $service = app(CarePlanService::class);
        $change = $service->requestScheduleChange($plan, $family, array_replace($this->schedule(), [
            'schedule_end_time' => '12:00', 'effective_on' => now()->addDay()->toDateString(),
        ]));
        $this->assertSame(120, $first->fresh()->expected_minutes);
        $service->respondToScheduleChange($change, $caregiver, true);
        $service->respondToScheduleChange($change->fresh(), $caregiver, true);
        $first->refresh();
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $first->status);
        $this->assertSame(180, $first->expected_minutes);
        $this->assertSame(2, $first->plan_schedule_version);
        $this->assertSame($count, $plan->generatedBookings()->count());
        $this->assertSame($prepaid ? 180 : 0, $account->balance()['reserved_minutes']);
        $this->assertSame($prepaid ? 1020 : 1200, $account->balance()['available_minutes']);
        if ($prepaid) {
            $this->assertSame(180, app(PrepaidBookingFundingService::class)->forBooking($first)->reserved_minutes);
            $this->assertNoCardOrSettlement();
        } else {
            $this->assertSame('authorized', $first->payment->status);
            $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        }
    }

    public static function fundingModes(): array
    {
        return ['explicit prepaid without card' => [true], 'default card with positive balance' => [false]];
    }

    #[DataProvider('prepaidCoverage')]
    public function test_plan_health_and_visible_dashboard_read_prepaid_coverage_without_marking_card_billing_ready(int $minutes): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario(minutes: $minutes);
        $plan = $this->activate($family, $caregiver, $source, $account);
        $booking = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $ready = $minutes >= 120;
        $this->assertSame($ready ? CarePlan::PAYMENT_AUTHORIZED : CarePlan::PAYMENT_ACTION_REQUIRED, $plan->payment_status);
        Livewire::actingAs($family)->test(Home::class)
            ->assertViewHas('familyData', fn ($data) => $data['billing_ready'] === false
                && $data['care_hour_readiness'][$booking->id]['ready'] === $ready
                && $data['care_hour_readiness'][$booking->id]['needs_action'] === ! $ready)
            ->assertSee($ready ? 'Visit coverage confirmed. No action needed.' : 'Visit coverage needs attention.')
            ->assertDontSee('Your card will be confirmed closer to the visit.')
            ->assertDontSee('Confirm or replace your card for this visit.');

        if ($ready) {
            $purchase = CareHourPurchase::where('care_hour_account_id', $account->id)->sole();
            $ledger = app(CareHourLedgerService::class);
            $ledger->holdPurchase($purchase, 'Provider payment review.');
            // A hold can arrive after preparation; cached funding status is deliberately still ready.
            $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($booking)->status);
            $plan = app(CarePlanHealthService::class)->reconcile($plan);
            $this->assertSame(CarePlan::STATUS_PAYMENT_ATTENTION, $plan->status);
            $this->assertSame(CarePlan::PAYMENT_ACTION_REQUIRED, $plan->payment_status);
            Livewire::actingAs($family)->test(Home::class)
                ->assertViewHas('familyData', fn ($data) => $data['billing_ready'] === false
                    && $data['care_hour_readiness'][$booking->id]['needs_action'] === true)
                ->assertSee('Visit coverage needs attention.')->assertSee('Review visit coverage')
                ->assertDontSee('Visit coverage confirmed. No action needed.');
            $ledger->releasePurchaseHold($purchase, 'Provider confirmed payment.');
            $plan = app(CarePlanHealthService::class)->reconcile($plan);
            $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
            $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
            Livewire::actingAs($family)->test(Home::class)
                ->assertViewHas('familyData', fn ($data) => $data['billing_ready'] === false)
                ->assertSee('Visit coverage confirmed. No action needed.');
        }
        $this->assertNull($family->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
        $this->assertNoCardOrSettlement();
    }

    public function test_plan_health_and_dashboard_keep_the_existing_card_read_model_when_unrelated_hours_are_held(): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario();
        $plan = $this->activate($family, $caregiver, $source, null);
        $purchase = CareHourPurchase::where('care_hour_account_id', $account->id)->sole();
        app(CareHourLedgerService::class)->holdPurchase($purchase, 'Unrelated package payment review.');
        $plan = app(CarePlanHealthService::class)->reconcile($plan);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
        $before = $account->balance();
        $billingReady = filled($account->familyAccount->fresh()->stripe_customer_id) || filled($family->fresh()->stripe_customer_id);
        Livewire::actingAs($family)->test(Home::class)
            ->assertViewHas('familyData', fn ($data) => $data['billing_ready'] === $billingReady && $data['care_hour_readiness']->isEmpty())
            ->assertSee('Payment confirmed. No action needed.')
            ->assertDontSee('Visit coverage needs attention.');
        $plan->nextBooking->payment->update(['status' => 'authorization_required', 'last_error' => 'Confirm or replace your card for this visit.']);
        $plan = app(CarePlanHealthService::class)->reconcile($plan);
        $this->assertSame(CarePlan::PAYMENT_ACTION_REQUIRED, $plan->payment_status);
        Livewire::actingAs($family)->test(Home::class)
            ->assertViewHas('familyData', fn ($data) => $data['billing_ready'] === $billingReady && $data['care_hour_readiness']->isEmpty())
            ->assertSee('Payment needs attention.')->assertSee('Confirm or replace your card for this visit.');
        $this->assertSame($before, $account->balance());
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public static function prepaidCoverage(): array
    {
        return ['fully covered then held and released' => [120], 'shortfall without a card' => [60]];
    }

    public static function reminderCoverage(): array
    {
        $cases = [];
        foreach ([60, 1440] as $minutes) {
            foreach (['prepaid_ready', 'prepaid_held', 'prepaid_shortfall', 'card_ready', 'card_attention'] as $state) {
                $cases[$state.' '.$minutes] = [$state, $minutes];
            }
        }

        return $cases;
    }

    #[DataProvider('reminderCoverage')]
    public function test_regular_visit_reminders_check_selected_funding_and_do_not_duplicate(string $state, int $minutesAhead): void
    {
        [$family, $caregiver, $source, $account] = $this->scenario(minutes: $state === 'prepaid_shortfall' ? 60 : 1200);
        $plan = $this->activate($family, $caregiver, $source, str_starts_with($state, 'prepaid') ? $account : null);
        $booking = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        if ($state === 'prepaid_held') {
            app(CareHourLedgerService::class)->holdPurchase(CareHourPurchase::where('care_hour_account_id', $account->id)->sole(), 'Payment review.');
        } elseif ($state === 'card_attention') {
            $booking->payment->update(['status' => 'authorization_required']);
        }
        $this->travelTo($booking->scheduled_start_at->copy()->subMinutes($minutesAhead));
        $event = $minutesAhead === 60 ? \App\Support\MarketplaceEvent::SHIFT_STARTING_SOON : \App\Support\MarketplaceEvent::SHIFT_REMINDER_24H;
        $before = $account->balance();
        $this->artisan('homecare:dispatch-notifications', ['--type' => 'shift-soon'])->assertExitCode(0);
        $deliveries = fn (User $user) => \App\Models\MarketplaceNotificationDelivery::where('event_key', $event)
            ->where('user_id', $user->id)->where('channel', 'in_app')->count();
        $expected = in_array($state, ['prepaid_ready', 'card_ready'], true) ? 1 : 0;
        $this->assertSame(1, $deliveries($family));
        $this->assertSame($expected, $deliveries($caregiver));
        $this->artisan('homecare:dispatch-notifications', ['--type' => 'shift-soon'])->assertExitCode(0);
        $this->assertSame(1, $deliveries($family));
        $this->assertSame($expected, $deliveries($caregiver));
        $this->assertSame($before, $account->balance());
    }

    private function assertPlanFunding(CarePlan $plan, CareHourAccount $account): void
    {
        $bookings = $plan->generatedBookings()->orderBy('scheduled_start_at')->get();
        $this->assertGreaterThan(1, $bookings->count());
        foreach ($bookings as $index => $booking) {
            $funding = app(PrepaidBookingFundingService::class)->forBooking($booking);
            $this->assertNotNull($funding);
            $this->assertSame($account->id, $funding->care_hour_account_id);
            $this->assertSame($index === 0 ? 'ready' : 'selected', $funding->status);
            $this->assertNotNull($funding->committed_at);
        }
    }

    private function assertNoCardOrSettlement(): void
    {
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('caregiver_payout_items', 0);
    }

    private function activate(User $family, User $caregiver, CareRequest $source, ?CareHourAccount $account): CarePlan
    {
        if (! $account) {
            config(['services.stripe.bypass' => true]);
        }

        return app(CarePlanService::class)->acceptOffer($this->offer($family, $source, $account), $caregiver);
    }

    private function offer(User $family, CareRequest $source, ?CareHourAccount $account = null): CarePlan
    {
        return app(CarePlanService::class)->sendOfferFromRequest($source, $family, $this->schedule() + ['care_hour_account_id' => $account?->id]);
    }

    private function schedule(): array
    {
        return ['title' => 'Recurring companionship', 'schedule_days' => [now()->addDay()->dayOfWeek],
            'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->addDay()->toDateString()];
    }

    private function scenario(bool $recurring = false, int $minutes = 1200): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
            'bio' => str_repeat('Reliable regular care specialist. ', 4), 'platform_hourly_rate' => 27,
            'years_experience' => 6, 'service_area_zip' => '27601', 'service_radius_miles' => 12,
            'insurance_status' => CaregiverProfile::INSURANCE_NO]);
        $profile->skills()->attach(Skill::create(['name' => 'Companionship'])->id);
        $profile->languages()->attach(Language::create(['name' => 'English'])->id);
        $profile->availabilities()->create(['day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '17:00']);
        $source = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Companionship',
            'status' => $recurring ? CareRequest::STATUS_OPEN : CareRequest::STATUS_FILLED,
            'request_type' => $recurring ? CareRequest::TYPE_RECURRING : CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subWeek()->setTime(9, 0), 'requested_end_at' => now()->subWeek()->setTime(11, 0),
            'recurring_days' => $recurring ? [now()->addDay()->dayOfWeek] : null,
            'recurring_start_time' => $recurring ? '09:00' : null, 'recurring_end_time' => $recurring ? '11:00' : null,
            'recurring_starts_on' => $recurring ? now()->addDay()->toDateString() : null,
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $source->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => (int) round($minutes * 3100 / 60),
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 1800, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $application = $source->applications()->create(['caregiver_user_id' => $caregiver->id,
            'status' => $recurring ? CareRequestApplication::STATUS_APPLIED : CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 27]);
        if (! $recurring) {
            CareBooking::create(['care_request_id' => $source->id, 'care_request_application_id' => $application->id,
                'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_REVIEWED,
                'scheduled_start_at' => $source->requested_start_at, 'scheduled_end_at' => $source->requested_end_at,
                'expected_minutes' => 120, 'worked_minutes' => 120, 'completed_at' => $source->requested_end_at,
                'reviewed_at' => now()->subDays(6), 'family_confirmed_at' => now()->subDays(6)]);
        }

        return [$family, $caregiver, $source->fresh(), $account];
    }
}
