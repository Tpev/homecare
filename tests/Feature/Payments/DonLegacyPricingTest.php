<?php

namespace Tests\Feature\Payments;

use App\Livewire\Family\BookAgain;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\DonLegacyPricingService;
use App\Services\Payments\StripeClient;
use App\Support\MarketplacePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DonLegacyPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('services.stripe.bypass', true);
        config()->set('services.stripe.bypass_processing_fee_percent', 2.9);
        config()->set('services.stripe.bypass_processing_fee_fixed_cents', 30);
    }

    public function test_preview_changes_nothing_and_identifies_the_exact_split(): void
    {
        [$booking] = $this->scenario();
        $this->artisan('homecare:repair-don-pricing', ['--booking' => $booking->id])
            ->expectsTable(['Booking', 'Minutes', 'Current total', 'Correct total', 'Caregiver receives', 'Repair status'], [
                [$booking->id, 133, '$68.72', '$34.91', '$33.25', 'Eligible'],
            ])->assertSuccessful();
        $this->assertDatabaseCount('care_pricing_agreements', 0);
        $this->assertDatabaseCount('care_booking_events', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(3000, $booking->fresh()->family_care_rate_cents);
    }

    public function test_audit_includes_overlapping_visits_below_the_cutoff_and_reads_payment_states_without_writes(): void
    {
        [$earlier, $admin] = $this->scenario();
        $source = $this->visit($earlier->family, $earlier->caregiver);
        $later = $this->visit($source->family, $source->caregiver);
        $start = now()->addDay()->setTime(10, 0);
        foreach ([$earlier, $later] as $booking) {
            $booking->update(['scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHours(2), 'status' => CareBooking::STATUS_SCHEDULED]);
        }
        $otherFamily = $this->visit(User::factory()->create(['role' => 'family']), $source->caregiver);
        $otherFamily->update(['scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHours(2)]);
        $repair = app(DonLegacyPricingService::class);
        $repair->apply($source, $admin, $source->caregiver_user_id);
        CareBookingPayment::query()->create([
            'care_booking_id' => $earlier->id, 'family_user_id' => $source->family_user_id,
            'caregiver_user_id' => $source->caregiver_user_id, 'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'currency' => 'usd', 'amount_authorized_cents' => 6200, 'amount_captured_cents' => 0,
            'amount_refunded_cents' => 0, 'stripe_payment_intent_id' => 'pi_earlier_hold',
        ]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $later->id, 'family_user_id' => $source->family_user_id,
            'caregiver_user_id' => $source->caregiver_user_id, 'status' => CareBookingPayment::STATUS_CAPTURED,
            'currency' => 'usd', 'amount_authorized_cents' => 3150, 'amount_captured_cents' => 3150,
            'amount_refunded_cents' => 0, 'stripe_payment_intent_id' => 'pi_later_charge',
        ]);
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|create|alter|drop|truncate)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $name = $source->caregiver->name.' (#'.$source->caregiver_user_id.')';

        $this->artisan('homecare:audit-don-visits', [
            '--booking' => $source->id, '--from' => $start->toDateString(), '--to' => $start->toDateString(),
        ])->expectsTable(['Booking', 'Start', 'End', 'Caregiver', 'Request', 'Plan', 'Visit status', 'Family/hr', 'Agreement', 'Possible overlaps'], [
            [$earlier->id, $start->format('Y-m-d H:i'), '12:00', $name, $earlier->care_request_id, '-', 'scheduled', '31.00', '-', (string) $later->id],
            [$later->id, $start->format('Y-m-d H:i'), '12:00', $name, $later->care_request_id, '-', 'scheduled', '15.75', 1, (string) $earlier->id],
        ])->expectsTable(['Booking', 'Payment status', 'Currency', 'Authorized', 'Captured', 'Refunded', 'PaymentIntent'], [
            [$earlier->id, 'authorized', 'USD', '62.00', '0.00', '0.00', 'pi_earlier_hold'],
            [$later->id, 'captured', 'USD', '31.50', '31.50', '0.00', 'pi_later_charge'],
        ])->assertSuccessful();
        $this->assertSame([], $writes);
    }

    public function test_request_estimate_names_the_agreed_caregiver_and_updates_with_visit_duration(): void
    {
        [$source, $admin] = $this->scenario();
        $source->caregiver->update(['name' => 'Madison Fragnito']);
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);

        Livewire::actingAs($source->family)->test(CreateCareRequestWizard::class)
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '60')
            ->assertSet('estimatedTotal', 15.75)
            ->assertSet('estimatedProcessingFee', 0.0)
            ->assertSee('Estimated one-time cost with Madison Fragnito')
            ->assertSee('Your agreed rate with Madison Fragnito: $15.75/hour.')
            ->assertSee('This estimate applies when you hire Madison Fragnito.')
            ->assertSee('Standard rate for other caregivers: $31.00/hour')
            ->assertSee('No additional processing fee.')
            ->assertDontSee('A $1.00/hour processing fee is added')
            ->set('requested_duration_minutes', '120')
            ->assertSet('estimatedTotal', 31.5)
            ->assertSee('$31.50');

        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_pricing_agreements', 1);
    }

    public function test_recurring_request_estimate_adds_separately_rounded_agreement_visits(): void
    {
        [$source, $admin] = $this->scenario();
        $source->caregiver->update(['name' => 'Madison Fragnito']);
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);

        Livewire::actingAs($source->family)->test(CreateCareRequestWizard::class)
            ->set('request_type', CareRequest::TYPE_RECURRING)
            ->set('recurring_days', [2, 4])
            ->set('recurring_schedule', [
                '2' => ['start_time' => '10:00', 'duration_minutes' => '90', 'end_time' => ''],
                '4' => ['start_time' => '11:00', 'duration_minutes' => '90', 'end_time' => ''],
            ])
            ->assertSet('estimatedHours', 3.0)
            ->assertSet('estimatedTotal', 47.26)
            ->assertSet('estimatedProcessingFee', 0.0)
            ->assertSee('Estimated recurring care each week with Madison Fragnito')
            ->assertSee('$47.26');
    }

    public function test_unrelated_and_inactive_agreements_do_not_change_request_estimates(): void
    {
        [$source, $admin] = $this->scenario();
        $source->caregiver->update(['name' => 'Madison Fragnito']);
        $result = app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $otherFamily = User::factory()->create(['role' => 'family']);

        Livewire::actingAs($otherFamily)->test(CreateCareRequestWizard::class)
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '60')
            ->assertSet('estimatedTotal', 31.0)
            ->assertSee('$31.00')
            ->assertDontSee('Madison Fragnito')
            ->assertDontSee('Your agreed rate');

        $result['agreement']->update(['active' => false]);
        Livewire::actingAs($source->family)->test(CreateCareRequestWizard::class)
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '60')
            ->assertSet('estimatedTotal', 31.0)
            ->assertDontSee('Your agreed rate');
    }

    public function test_request_estimate_does_not_choose_between_multiple_agreed_caregivers(): void
    {
        [$source, $admin] = $this->scenario();
        $result = app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $secondAgreement = $result['agreement']->replicate();
        $secondAgreement->caregiver_user_id = User::factory()->create(['role' => 'caregiver'])->id;
        $secondAgreement->save();

        Livewire::actingAs($source->family)->test(CreateCareRequestWizard::class)
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '60')
            ->assertSet('estimatedTotal', 31.0)
            ->assertDontSee('Your agreed rate');
    }

    public function test_repair_and_billing_honor_both_rates_and_lolo_pays_actual_processing_fees(): void
    {
        [$booking, $admin] = $this->scenario();
        $payments = app(BookingPaymentService::class);
        $authorized = $payments->authorizeForBooking($booking, notify: false);
        $authorizationId = $authorized->stripe_payment_intent_id;
        $authorizationAmount = $authorized->amount_authorized_cents;
        $result = app(DonLegacyPricingService::class)->apply($booking, $admin, $booking->caregiver_user_id);
        $this->assertSame([$booking->id], $result['repaired']);
        $this->assertSame(0, (int) $authorized->fresh()->amount_captured_cents);
        $this->assertSame($authorizationId, $authorized->fresh()->stripe_payment_intent_id);
        $this->assertSame($authorizationAmount, $authorized->fresh()->amount_authorized_cents);

        $preview = app(BookingCorrectionService::class)->preview($booking->fresh(), [
            'action' => 'complete_and_bill',
            'started_at' => $booking->started_at->toIso8601String(),
            'completed_at' => $booking->completed_at->toIso8601String(),
            'break_minutes' => 0,
        ]);
        $this->assertSame(3491, $preview['target_charge_cents']);
        $this->assertSame(3325, $preview['target_caregiver_cents']);

        $booking->refresh()->forceFill(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()])->save();
        $payment = $payments->captureForBooking($booking->fresh(), notify: false);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertSame(3491, (int) $payment->amount_captured_cents);
        $this->assertSame(3325, (int) $payment->caregiver_amount_cents);
        $this->assertSame(166, (int) $payment->platform_fee_cents);
        $this->assertGreaterThan(0, (int) $payment->stripe_processing_fee_cents);
        $this->assertSame(0.0, (float) CaregiverPayoutItem::query()->firstOrFail()->processing_fee_amount);
        $this->assertSame(33.25, (float) CaregiverPayoutItem::query()->firstOrFail()->amount);
        $earning = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_EARNING)->firstOrFail();
        $this->assertSame(0, data_get($earning->metadata, 'processing_fee_cents'));
        $this->assertSame('platform_pays_processing', data_get($earning->metadata, 'policy'));
        app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers($payment->fresh());
        $this->assertSame(3325, (int) $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->sum('amount_cents'));
    }

    public function test_book_again_estimate_and_invitation_use_the_current_pair_agreement(): void
    {
        [$source, $admin] = $this->scenario();
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $source->update(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()]);
        $before = $source->fresh()->getRawOriginal();

        Livewire::actingAs($source->family)->test(BookAgain::class, ['careRequest' => $source->care_request_id])
            ->set('durationMinutes', '60')
            ->assertSee('$15.75')
            ->assertSee('No additional processing fee.')
            ->assertDontSee('$30/hour')
            ->assertDontSee('$1/hour')
            ->set('durationMinutes', '120')
            ->assertSee('$31.50')
            ->set('visitDate', now()->addWeek()->toDateString())
            ->set('startTime', '10:00')
            ->call('sendOneTimeInvite')
            ->assertHasNoErrors();

        $request = CareRequest::query()->latest('id')->firstOrFail();
        $this->assertNotSame($source->care_request_id, $request->id);
        $this->assertSame(15.75, (float) $request->budget_max);
        $this->assertSame($source->caregiver_user_id, $request->invitations()->firstOrFail()->caregiver_user_id);
        $this->assertSame($before, $source->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_book_again_keeps_standard_estimates_for_other_caregivers_and_inactive_agreements(): void
    {
        [$source, $admin] = $this->scenario();
        $agreement = app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id)['agreement'];
        $other = $this->visit($source->family, User::factory()->create(['role' => 'caregiver']));
        foreach ([$source, $other] as $booking) {
            $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()]);
        }

        Livewire::actingAs($source->family)->test(BookAgain::class, ['careRequest' => $other->care_request_id])
            ->set('durationMinutes', '120')->assertSee('$62.00')->assertDontSee('$31.50');
        $agreement->update(['active' => false]);
        Livewire::actingAs($source->family)->test(BookAgain::class, ['careRequest' => $source->care_request_id])
            ->set('durationMinutes', '120')->assertSee('$62.00')->assertDontSee('$31.50');
    }

    public function test_visit_details_and_timesheet_show_saved_pricing_instead_of_application_rates(): void
    {
        [$booking, $admin] = $this->scenario();
        $agreement = app(DonLegacyPricingService::class)->apply($booking, $admin, $booking->caregiver_user_id)['agreement'];
        $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'timesheet_submitted_at' => now()]);
        // Existing visits retain their snapshot even if the agreement for new care changes.
        $agreement->update(['family_care_rate_cents' => 2000]);
        $before = $booking->fresh()->getRawOriginal();

        Livewire::actingAs($booking->family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->call('setActiveTab', 'overview')
            ->assertSee('Care rate: $15.75/hr')
            ->assertSee('No additional processing fee.')
            ->assertDontSee('Care rate: $30.00')
            ->call('setActiveTab', 'shift')
            ->assertSee('$34.91')
            ->call('reviewCompletion')
            ->assertSee('Care · $15.75/hour')
            ->assertSee('Approve hours and pay $34.91')
            ->assertDontSee('Care · $30.00/hour')
            ->assertDontSee(' + processing fee');
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_active_plan_displays_pair_agreement_without_rewriting_historical_plan_terms(): void
    {
        [$source, $admin] = $this->scenario();
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $plan = CarePlan::query()->create([
            'family_user_id' => $source->family_user_id, 'caregiver_user_id' => $source->caregiver_user_id,
            'source_care_request_id' => $source->care_request_id, 'source_care_booking_id' => $source->id,
            'status' => CarePlan::STATUS_ACTIVE, 'title' => 'Existing regular care', 'hourly_rate' => 30,
            'starts_on' => now()->toDateString(), 'timezone' => 'America/New_York',
            'schedule_days' => [2, 4], 'schedule_start_time' => '10:00', 'schedule_end_time' => '12:00',
        ]);
        $before = $plan->fresh()->getRawOriginal();
        Livewire::actingAs($source->family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('setActiveTab', 'caregivers')
            ->assertSee('$15.75/hour agreed rate')
            ->assertDontSee('$30.00/hour agreed rate');
        $this->assertSame($before, $plan->fresh()->getRawOriginal());

        $plan->update(['status' => CarePlan::STATUS_ENDED]);
        Livewire::actingAs($source->family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('setActiveTab', 'caregivers')->assertSee('$30.00/hour');
        $plan->update(['status' => CarePlan::STATUS_ACTIVE, 'hourly_rate' => 24.75,
            'caregiver_user_id' => User::factory()->create(['role' => 'caregiver'])->id]);
        Livewire::actingAs($source->family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('setActiveTab', 'caregivers')->assertSee('$24.75/hour agreed rate');
    }

    public function test_new_two_hour_visit_authorizes_buffer_and_captures_only_agreed_total(): void
    {
        [$source, $admin] = $this->scenario();
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $booking = $this->visit($source->family, $source->caregiver);
        $booking->update(['expected_minutes' => 120, 'worked_minutes' => 120,
            'scheduled_end_at' => $booking->scheduled_start_at->copy()->addHours(2),
            'completed_at' => $booking->started_at->copy()->addHours(2), 'status' => CareBooking::STATUS_SCHEDULED]);
        $payments = app(BookingPaymentService::class);
        $payment = $payments->authorizeForBooking($booking->fresh(), notify: false);
        $this->assertSame(3780, (int) $payment->amount_authorized_cents);
        $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()]);
        $payment = $payments->captureForBooking($booking->fresh(), notify: false);
        $this->assertSame(3150, (int) $payment->amount_captured_cents);
        $this->assertSame(3000, (int) $payment->caregiver_amount_cents);
    }

    public function test_new_visits_match_the_family_and_caregiver_pair_and_keep_their_snapshot(): void
    {
        [$source, $admin] = $this->scenario();
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $samePair = $this->visit($source->family, $source->caregiver);
        $otherCaregiver = $this->visit($source->family, User::factory()->create(['role' => 'caregiver']));
        $otherFamily = $this->visit(User::factory()->create(['role' => 'family']), $source->caregiver);
        $this->assertSame(1575, $samePair->family_care_rate_cents);
        $this->assertSame(1500, $samePair->caregiver_gross_rate_cents);
        $this->assertSame(0, $samePair->family_processing_fee_rate_cents);
        foreach ([$otherCaregiver, $otherFamily] as $standard) {
            $this->assertSame(3000, $standard->family_care_rate_cents);
            $this->assertSame(2700, $standard->caregiver_gross_rate_cents);
            $this->assertNull($standard->pricing_agreement_id);
        }
        config()->set('marketplace.pricing_v2.family_care_hourly_cents', 5000);
        config()->set('marketplace.pricing_v2.caregiver_gross_hourly_cents', 4000);
        config()->set('marketplace.pricing_v2.version', 'future-standard-pricing');
        config()->set('marketplace.pricing_v2.enabled', false);
        $this->assertSame(1575, $this->visit($source->family, $source->caregiver)->family_care_rate_cents);
        CarePricingAgreement::query()->update(['active' => false]);
        $quote = app(BookingPaymentService::class)->quoteForWorkedMinutes($samePair->fresh(), 133);
        $this->assertSame(3491, $quote['total_charge_cents']);
        $this->assertSame(3325, $quote['caregiver_amount_cents']);
    }

    public function test_existing_future_visits_are_repaired_idempotently_without_touching_recorded_time(): void
    {
        [$source, $admin] = $this->scenario();
        $future = $this->visit($source->family, $source->caregiver);
        $future->update(['scheduled_start_at' => now()->addWeek(), 'status' => CareBooking::STATUS_SCHEDULED]);
        $repair = app(DonLegacyPricingService::class);
        $original = $source->only(['status', 'started_at', 'completed_at', 'worked_minutes']);
        $first = $repair->apply($source, $admin, $source->caregiver_user_id);
        $second = $repair->apply($source, $admin, $source->caregiver_user_id);
        $this->assertSame([$source->id, $future->id], $first['repaired']);
        $this->assertSame([], $second['repaired']);
        $this->assertEquals($original, $source->fresh()->only(array_keys($original)));
        $this->assertSame(1575, $future->fresh()->family_care_rate_cents);
        $this->assertSame(2, CareBookingEvent::query()->where('event_type', 'legacy_pricing_agreement_applied')->count());
        $this->assertDatabaseCount('care_pricing_agreements', 1);
    }

    public function test_cutoff_excludes_lower_ids_even_when_scheduled_in_the_future_and_keeps_their_payments_working(): void
    {
        [$earlierFuture, $admin] = $this->scenario();
        $earlierFuture->update(['scheduled_start_at' => now()->addWeek(), 'status' => CareBooking::STATUS_SCHEDULED]);
        $earlierPast = $this->visit($earlierFuture->family, $earlierFuture->caregiver);
        $source = $this->visit($earlierFuture->family, $earlierFuture->caregiver);
        $laterFuture = $this->visit($source->family, $source->caregiver);
        $laterFuture->update(['scheduled_start_at' => now()->addWeek(), 'status' => CareBooking::STATUS_SCHEDULED]);
        $payments = app(BookingPaymentService::class);
        $payment = $payments->authorizeForBooking($earlierFuture, notify: false);
        $originalPayment = $payment->fresh()->getAttributes();
        $originalFuture = $earlierFuture->fresh()->getAttributes();
        $originalPast = $earlierPast->fresh()->getAttributes();

        $this->artisan('homecare:repair-don-pricing', ['--booking' => $source->id])
            ->expectsOutput('Scope: booking #'.$source->id.' and higher IDs only. Lower booking IDs are excluded, even when scheduled in the future.')
            ->expectsTable(['Booking', 'Minutes', 'Current total', 'Correct total', 'Caregiver receives', 'Repair status'], [
                [$source->id, 133, '$68.72', '$34.91', '$33.25', 'Eligible'],
                [$laterFuture->id, 133, '$68.72', '$34.91', '$33.25', 'Eligible'],
            ])
            ->doesntExpectOutputToContain('Other historical visits to audit separately:')
            ->assertSuccessful();
        $this->artisan('homecare:repair-don-pricing', [
            '--booking' => $source->id, '--apply' => true, '--admin' => $admin->id, '--caregiver' => $source->caregiver_user_id,
        ])->expectsOutput('Agreement #1 saved. Repaired bookings: '.$source->id.', '.$laterFuture->id)
            ->assertSuccessful();

        $this->assertSame($originalFuture, $earlierFuture->fresh()->getAttributes());
        $this->assertSame($originalPast, $earlierPast->fresh()->getAttributes());
        $this->assertSame($originalPayment, $payment->fresh()->getAttributes());
        $this->assertSame(1575, $source->fresh()->family_care_rate_cents);
        $this->assertSame(1575, $laterFuture->fresh()->family_care_rate_cents);
        $this->assertSame([$source->id, $laterFuture->id], CareBookingEvent::query()
            ->where('event_type', 'legacy_pricing_agreement_applied')->orderBy('care_booking_id')->pluck('care_booking_id')->all());
        $pricing = app(MarketplacePricing::class);
        foreach ([$earlierPast, $earlierFuture] as $excluded) {
            $this->assertFalse($pricing->hasUnappliedAgreement($excluded->fresh()));
            $this->assertSame(3000, $pricing->currentSnapshotAttributes($excluded->fresh())['family_care_rate_cents']);
        }
        $retried = $payments->authorizeForBooking($earlierFuture->fresh(), notify: false);
        $this->assertSame($originalPayment, $retried->getAttributes());
        $earlierFuture->update(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()]);
        $captured = $payments->captureForBooking($earlierFuture->fresh(), notify: false);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $captured->status);
        $this->assertSame(6872, (int) $captured->amount_captured_cents);
        $this->assertNull($captured->pricing_agreement_id);
    }

    public function test_repeat_repair_cannot_change_the_registered_cutoff(): void
    {
        [$earlier, $admin] = $this->scenario();
        $source = $this->visit($earlier->family, $earlier->caregiver);
        $later = $this->visit($source->family, $source->caregiver);
        $repair = app(DonLegacyPricingService::class);
        $result = $repair->apply($source, $admin, $source->caregiver_user_id);
        $original = $result['agreement']->fresh()->getAttributes();

        foreach ([$earlier, $later] as $differentCutoff) {
            try {
                $repair->apply($differentCutoff, $admin, $source->caregiver_user_id);
                $this->fail('A repeat run must preserve the registered cutoff.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('--booking='.$source->id, $exception->getMessage());
                $this->assertSame($original, $result['agreement']->fresh()->getAttributes());
                $this->assertSame(3000, $differentCutoff->fresh()->family_care_rate_cents);
            }
        }
        $this->assertDatabaseCount('care_booking_events', 1);
    }

    public function test_captured_visit_is_reported_and_left_untouched_while_future_agreement_is_saved(): void
    {
        [$source, $admin] = $this->scenario();
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($source, notify: false);
        $source->update(['status' => CareBooking::STATUS_COMPLETED]);
        $payment = $payments->captureForBooking($source->fresh(), notify: false);
        $original = $payment->getAttributes();
        $result = app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $this->assertArrayHasKey($source->id, $result['skipped']);
        $this->assertSame(3000, $source->fresh()->family_care_rate_cents);
        $this->assertSame($original, $payment->fresh()->getAttributes());
        $this->assertSame(1575, $this->visit($source->family, $source->caregiver)->family_care_rate_cents);
    }

    public function test_wrong_caregiver_is_rejected_before_any_changes(): void
    {
        [$source, $admin] = $this->scenario();
        try {
            app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id + 100);
            $this->fail('A mismatched caregiver must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_pricing_agreements', 0);
            $this->assertSame(3000, $source->fresh()->family_care_rate_cents);
        }
    }

    public function test_a_missed_unpaid_visit_cannot_be_charged_at_standard_rates_after_registration(): void
    {
        [$source, $admin] = $this->scenario();
        $missed = $this->visit($source->family, $source->caregiver);
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($missed, notify: false);
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        try {
            $payments->captureForBooking($missed->fresh(), notify: false);
            $this->fail('A stale standard-rate booking must not be captured.');
        } catch (\App\Exceptions\Payments\PaymentException $exception) {
            $this->assertStringContainsString('agreed pricing restored', $exception->getMessage());
            $this->assertSame(0, (int) $missed->fresh()->payment->amount_captured_cents);
        }
    }

    /** @return array{CareBooking, User} */
    private function scenario(): array
    {
        $family = User::factory()->create(['role' => 'family', 'email' => DonLegacyPricingService::FAMILY_EMAIL]);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 30,
            'stripe_connect_account_id' => 'acct_test_don_caregiver',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        return [$this->visit($family, $caregiver), User::factory()->create(['role' => 'admin'])];
    }

    private function visit(User $family, User $caregiver): CareBooking
    {
        $start = now()->subDay()->setTime(10, 12);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Don agreement visit',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $start, 'requested_end_at' => $start->copy()->addMinutes(133),
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 30,
        ]);

        return CareBooking::query()->create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_DISPUTED, 'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addMinutes(133), 'started_at' => $start,
            'completed_at' => $start->copy()->addMinutes(133), 'worked_minutes' => 133, 'expected_minutes' => 133,
        ]);
    }
}
