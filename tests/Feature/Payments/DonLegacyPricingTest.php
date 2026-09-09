<?php

namespace Tests\Feature\Payments;

use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\DonLegacyPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
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
