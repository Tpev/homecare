<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Admin\SupportTicketShow;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\ShowCaregiver;
use App\Livewire\Family\BookAgain;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\DonFamilyPricingService;
use App\Services\Payments\DonLegacyPricingService;
use App\Services\Payments\StripeClient;
use App\Support\CaregiverWorkInboxBuilder;
use App\Support\MarketplacePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DonFamilyPricingTest extends TestCase
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

    public function test_repair_extends_existing_agreement_and_preserves_hold_times_and_settled_history(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $original->update(['status' => CareBooking::STATUS_COMPLETED, 'worked_minutes' => 133]);
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($original->fresh(), notify: false);
        $settled = $payments->captureForBooking($original->fresh(), notify: false);
        $originalBefore = $original->fresh()->getRawOriginal();
        $settledBefore = $settled->fresh()->getRawOriginal();
        $hold = $payments->authorizeForBooking($booking, notify: false);
        $this->assertSame(7440, (int) $hold->amount_authorized_cents);
        $holdBefore = $hold->only(['stripe_payment_intent_id', 'amount_authorized_cents', 'amount_captured_cents', 'status', 'metadata']);
        $times = $booking->only(['scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at', 'worked_minutes', 'status']);
        // Repricing must never call Stripe, send a message, or complete care.
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $result = $this->repair($booking, $admin);
        $this->assertSame([$booking->id], $result['repaired']);
        $this->assertTrue($result['agreement']->all_caregivers);
        $this->assertSame($original->id, $result['agreement']->source_booking_id);
        $this->assertSame($original->caregiver_user_id, $result['agreement']->caregiver_user_id);
        $this->assertSame($originalBefore, $original->fresh()->getRawOriginal());
        $this->assertSame($settledBefore, $settled->fresh()->getRawOriginal());
        $this->assertSame($holdBefore, $hold->fresh()->only(array_keys($holdBefore)));
        $this->assertEquals($times, $booking->fresh()->only(array_keys($times)));
        $this->assertRates($booking->fresh());
        $this->assertSame(1500, $hold->fresh()->caregiver_gross_rate_cents);
        $this->assertSame(15.0, (float) $booking->application->fresh()->proposed_rate);
        Livewire::actingAs($booking->family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->call('setActiveTab', 'shift')->assertSee('Authorized $74.40')
            ->assertSee('Temporary card hold. Final billing is $15.75/hour total for approved care.');
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\CareRequestShow::class, ['careRequest' => $booking->careRequest])
            ->assertSee('Authorized: $74.40')->assertSee('Captured: $0.00')
            ->assertSee('Family total: $15.75/hr')->assertSee('Caregiver: $15.00/hr');
        $events = CareBookingEvent::count();
        $this->assertSame([], $this->repair($booking, $admin)['repaired']);
        $this->assertSame($events, CareBookingEvent::count());
        Notification::assertNothingSent();
    }

    public function test_preview_is_read_only_and_apply_requires_verified_family_and_admin(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $before = $booking->fresh()->getRawOriginal();
        $events = CareBookingEvent::count();
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $this->artisan('homecare:repair-don-family-pricing', ['--booking' => $booking->id])
            ->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertSame($events, CareBookingEvent::count());
        $this->assertFalse((bool) CarePricingAgreement::first()->all_caregivers);
        try {
            app(DonFamilyPricingService::class)->apply($booking, $admin, $booking->family_account_id + 100);
            $this->fail('Wrong family must fail.');
        } catch (ValidationException) {
            $this->assertSame($before, $booking->fresh()->getRawOriginal());
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->repair($booking, $booking->family);
    }

    public function test_any_caregiver_uses_separate_rates_and_other_families_keep_standard_pricing(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $this->repair($booking, $admin);
        $third = $this->caregiver();
        $new = $this->visit($booking->family, $third);
        $this->assertRates($new);
        $quote = app(MarketplacePricing::class)->quoteForCurrentBooking($new, 120, 123);
        $this->assertSame(3150, $quote['total_charge_cents']);
        $this->assertSame(3000, $quote['caregiver_amount_cents']);
        $hold = app(BookingPaymentService::class)->authorizeForBooking($new, notify: false);
        $this->assertSame(3780, (int) $hold->amount_authorized_cents);
        $other = $this->visit(User::factory()->create(['role' => 'family']), $third);
        $this->assertSame(3000, $other->family_care_rate_cents);
        $this->assertSame(2700, $other->caregiver_gross_rate_cents);
        $this->assertNull($other->pricing_agreement_id);
        // Explicit agreements remain in force when default pricing is disabled or changes version.
        config()->set('marketplace.pricing_v2.enabled', false);
        config()->set('marketplace.pricing_v2.version', 'next-default');
        $this->assertRates($this->visit($booking->family, $this->caregiver()));
    }

    public function test_account_wide_repair_includes_pending_visits_below_original_cutoff(): void
    {
        $don = User::factory()->create(['role' => 'family', 'email' => DonLegacyPricingService::FAMILY_EMAIL]);
        $earlier = $this->visit($don, $this->caregiver());
        $source = $this->visit($don, $this->caregiver());
        $admin = User::factory()->create(['role' => 'admin']);
        app(DonLegacyPricingService::class)->apply($source, $admin, $source->caregiver_user_id);
        $this->repair($source, $admin);
        $this->assertRates($earlier->fresh());
    }

    public function test_capture_bills_approved_minutes_and_caregiver_never_pays_processing(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($booking, notify: false);
        $this->repair($booking, $admin);
        $booking->refresh()->update(['status' => CareBooking::STATUS_COMPLETED, 'worked_minutes' => 133,
            'started_at' => now()->subMinutes(133), 'completed_at' => now(), 'family_confirmed_at' => now()]);
        $preview = app(BookingCorrectionService::class)->preview($booking->fresh(), [
            'action' => 'complete_and_bill', 'started_at' => $booking->started_at->toIso8601String(),
            'completed_at' => $booking->completed_at->toIso8601String(), 'break_minutes' => 0,
        ]);
        $this->assertSame(3491, $preview['target_charge_cents']);
        $this->assertSame(3325, $preview['target_caregiver_cents']);
        $payment = $payments->captureForBooking($booking->fresh(), notify: false);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertSame(3491, (int) $payment->amount_captured_cents);
        $this->assertSame(3325, (int) $payment->caregiver_amount_cents);
        $this->assertGreaterThan(0, (int) $payment->stripe_processing_fee_cents);
        $payout = CaregiverPayoutItem::where('care_booking_id', $booking->id)->firstOrFail();
        $this->assertSame(33.25, (float) $payout->amount);
        $this->assertSame(0.0, (float) $payout->processing_fee_amount);
    }

    public function test_stale_snapshot_cannot_authorize_or_capture_at_standard_rate(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($booking, notify: false);
        $this->repair($booking, $admin);
        $booking->refresh()->update(['family_care_rate_cents' => 3000]);
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        foreach (['authorizeForBooking', 'prepareOnSessionAuthorization', 'captureForBooking'] as $method) {
            try {
                app(BookingPaymentService::class)->{$method}($booking->fresh());
                $this->fail($method.' must block stale pricing.');
            } catch (PaymentException $exception) {
                $this->assertStringContainsString('agreed pricing restored', $exception->getMessage());
            }
        }
        $this->assertSame(0, (int) $booking->payment->fresh()->amount_captured_cents);
    }

    public function test_family_estimates_booking_details_and_profiles_use_account_rate(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $this->repair($booking, $admin);
        Livewire::actingAs($booking->family)->test(CreateCareRequestWizard::class)
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')->set('requested_duration_minutes', '120')
            ->assertSet('estimatedTotal', 31.5)->assertSet('estimatedProcessingFee', 0.0)
            ->assertSee('any caregiver')->assertDontSee('Standard rate for other caregivers');
        Livewire::actingAs($booking->family)->test(ShowCaregiver::class, ['slug' => $booking->caregiver->caregiverProfile->slug])
            ->assertSee('$15.75/hr')->assertSee('your agreed rate')->assertDontSee('$30.00/hr');
        Livewire::actingAs($booking->family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->call('setActiveTab', 'overview')->assertSee('Care rate: $15.75/hr')
            ->assertSee('No additional processing fee.')->assertDontSee('Care rate: $30.00');
        $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'family_confirmed_at' => now()]);
        Livewire::actingAs($booking->family)->test(BookAgain::class, ['careRequest' => $booking->care_request_id])
            ->set('durationMinutes', '120')->assertSee('$31.50')->assertDontSee('$62.00');
    }

    public function test_caregiver_visit_and_inbox_show_fifteen_dollars_and_processing_covered(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $this->repair($booking, $admin);
        $caregiver = $booking->caregiver;
        Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->assertSee('$15.00/hr')->assertDontSee('$27.00/hr')->assertSee('LoLo covers payment processing costs.');
        $items = app(CaregiverWorkInboxBuilder::class)->buildForUser($caregiver);
        $compensation = $items->first(fn ($item) => (float) data_get($item, 'compensation.hourly_rate') === 15.0)['compensation'] ?? null;
        $this->assertNotNull($compensation);
        $this->assertTrue($compensation['processing_covered']);
        $this->assertEquals(30, $compensation['total']);
    }

    public function test_admin_ticket_action_repairs_without_completing_visit_or_resolving_ticket(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $ticket = SupportTicket::query()->create(['opener_user_id' => $booking->family_user_id, 'category' => 'payment', 'description' => 'Price shown incorrectly',
            'care_booking_id' => $booking->id, 'subject' => 'Incorrect price', 'status' => SupportTicket::STATUS_OPEN]);
        Notification::fake();
        Livewire::actingAs($admin)->test(SupportTicketShow::class, ['ticket' => $ticket->id])
            ->call('applyDonFamilyPricing')->assertHasNoErrors();
        $this->assertRates($booking->fresh());
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->completed_at);
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->fresh()->status);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNothingSent();
    }

    private function repair(CareBooking $booking, User $admin): array
    {
        return app(DonFamilyPricingService::class)->apply($booking, $admin, $booking->family_account_id);
    }

    public function test_invitation_acceptance_and_recruitment_quote_caregiver_pay_not_family_price(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $this->repair($booking, $admin);
        $caregiver = $this->caregiver();
        $profile = $caregiver->caregiverProfile;
        $profile->update(['bio' => 'Experienced caregiver', 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved']);
        $profile->skills()->attach(\App\Models\Skill::create(['name' => 'Companionship'])->id);
        $profile->languages()->attach(\App\Models\Language::create(['name' => 'English'])->id);
        $request = $booking->careRequest->replicate();
        $request->status = CareRequest::STATUS_OPEN;
        $request->save();
        $invitation = CareRequestInvitation::create(['care_request_id' => $request->id,
            'family_user_id' => $booking->family_user_id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING, 'expires_at' => now()->addDay()]);
        $result = app(\App\Services\Marketplace\CareRequestInvitationResponseService::class)->accept($invitation, $caregiver);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertEquals(15, $invitation->fresh()->application->proposed_rate);
        $email = app(\App\Services\Marketplace\CareRequestRecruitmentEmail::class)->content($caregiver, $request);
        $details = json_encode($email['payload']['email_details']);
        $this->assertStringContainsString('$15.00', $details);
        $this->assertStringContainsString('LoLo covers payment processing costs', $details);
        $this->assertStringNotContainsString('$27.00', $details);
    }

    public function test_new_recurring_visits_for_another_caregiver_and_coverage_estimates_follow_account_policy(): void
    {
        [$original, $admin, $booking] = $this->scenario();
        $this->repair($booking, $admin);
        $caregiver = $this->caregiver();
        $start = now()->addDay();
        $plan = \App\Models\CarePlan::create(['family_user_id' => $booking->family_user_id,
            'caregiver_user_id' => $caregiver->id, 'status' => \App\Models\CarePlan::STATUS_ACTIVE,
            'title' => 'Recurring Don care', 'hourly_rate' => 30, 'timezone' => config('app.timezone'),
            'starts_on' => $start->toDateString(), 'schedule_days' => [$start->dayOfWeek],
            'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00']);
        $generated = app(\App\Services\RegularCare\CarePlanOccurrenceService::class)->materialize($plan)['created'];
        $this->assertNotEmpty($generated);
        foreach ($generated as $visit) {
            $this->assertRates($visit);
            $this->assertEquals(15, $visit->application->proposed_rate);
        }
        $coverage = new \App\Models\ContinuousCoveragePlan(['family_account_id' => $booking->family_account_id,
            'family_user_id' => $booking->family_user_id, 'hourly_rate' => 30]);
        $quote = app(\App\Services\ContinuousCoverage\ContinuousCoveragePricingService::class)->quoteForPlan($coverage, $caregiver, 120);
        $this->assertSame(3150, $quote['total_charge_cents']);
        $this->assertSame(3000, $quote['caregiver_amount_cents']);
    }

    private function assertRates(CareBooking $booking): void
    {
        $this->assertSame(1575, $booking->family_care_rate_cents);
        $this->assertSame(0, $booking->family_processing_fee_rate_cents);
        $this->assertSame(1500, $booking->caregiver_gross_rate_cents);
        $this->assertSame(CarePricingAgreement::PLATFORM_PAYS_PROCESSING, $booking->caregiver_fee_policy);
    }

    private function scenario(): array
    {
        $don = User::factory()->create(['role' => 'family', 'email' => DonLegacyPricingService::FAMILY_EMAIL]);
        $original = $this->visit($don, $this->caregiver());
        $admin = User::factory()->create(['role' => 'admin']);
        app(DonLegacyPricingService::class)->apply($original, $admin, $original->caregiver_user_id);
        return [$original, $admin, $this->visit($don, $this->caregiver())];
    }

    private function caregiver(): User
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'slug' => 'caregiver-'.$user->id,
            'status' => 'active', 'platform_hourly_rate' => 30, 'screening_initial_approved_at' => now(),
            'stripe_connect_account_id' => 'acct_test_'.$user->id, 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
        return $user;
    }

    private function visit(User $family, User $caregiver): CareBooking
    {
        $start = now()->addDays(2)->setTime(12, 20);
        $request = CareRequest::query()->create(['family_user_id' => $family->id, 'title' => 'Don care visit',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $start, 'requested_end_at' => $start->copy()->addHours(2),
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $application = CareRequestApplication::query()->create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 15.75]);
        return CareBooking::query()->create(['care_request_id' => $request->id,
            'care_request_application_id' => $application->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHours(2), 'expected_minutes' => 120]);
    }
}
