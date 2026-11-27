<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\Admin\CareAssistanceApplicationShow;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Models\CareAssistanceApplication;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\CareTask;
use App\Models\ContinuousCoveragePlan;
use App\Models\FamilyAccountActivityLog;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareHours\CareHourRequestFundingService;
use App\Services\CustomerCare\CareAssistanceEnrollmentService;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CareRequestInvitationResponseService;
use App\Services\Marketplace\CareRequestRecruitmentEmail;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Support\MarketplacePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CareAssistanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false, 'services.stripe.bypass' => true,
            'services.stripe.bypass_processing_fee_percent' => 2.9, 'services.stripe.bypass_processing_fee_fixed_cents' => 30]);
    }

    public function test_staff_can_activate_before_any_booking_link_guest_approval_and_see_audited_account_controls(): void
    {
        [$staff, $customer] = $this->people();
        $application = $this->application();
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $this->actingAs($staff)->post(route('customer-care.care-assistance', $customer), $this->enrollmentInput($application))
            ->assertRedirect(route('customer-care.customer', $customer))->assertSessionHasNoErrors();
        $agreement = CarePricingAgreement::sole();
        $this->assertNull($agreement->caregiver_user_id);
        $this->assertNull($agreement->source_booking_id);
        $this->assertSame($agreement->family_account_id, $application->fresh()->family_account_id);
        $this->assertSame($staff->id, $agreement->created_by_user_id);
        $this->assertDatabaseHas('family_account_activity_logs', ['action' => 'care_assistance_activated', 'actor_user_id' => $staff->id]);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->get(route('customer-care.customer', $customer))->assertOk()->assertSee('Active for new requests')->assertSee('$23.00/hour total')
            ->assertSee('Care Assistance')->assertSee('Application #'.$application->id)->assertSee('Record prepaid hours');
        $this->get(route('admin.care-assistance.show', $application))->assertOk()->assertSee('Care Assistance is active for new requests.');
        Notification::assertNothingSent();
    }

    public function test_only_authorized_current_staff_can_enroll_and_approval_cannot_be_forged(): void
    {
        [$staff, $customer] = $this->people();
        $application = $this->application(['status' => 'under_review']);
        $this->actingAs($staff)->post(route('customer-care.care-assistance', $customer), $this->enrollmentInput($application))
            ->assertSessionHasErrors('application_id');
        foreach (['family', 'caregiver', 'sales'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->post(route('customer-care.care-assistance', $customer), $this->enrollmentInput())->assertForbidden();
        }
        $this->actingAs($staff)->post(route('customer-care.care-assistance', $customer), array_diff_key($this->enrollmentInput(), ['approval_confirmed' => true]))
            ->assertSessionHasErrors('approval_confirmed');
        User::whereKey($staff->id)->update(['access_revoked_at' => now()]);
        try {
            app(CareAssistanceEnrollmentService::class)->update($staff, $customer, $this->enrollmentInput());
            $this->fail('Revoked staff must be rejected.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertDatabaseCount('care_pricing_agreements', 0);
        }
    }

    public function test_stale_submissions_cross_account_applications_and_other_custom_rates_are_rejected(): void
    {
        [$staff, $customer] = $this->people();
        $application = $this->application();
        $this->enroll($staff, $customer, $application);
        $this->actingAs($staff)->post(route('customer-care.care-assistance', $customer), $this->enrollmentInput($application))->assertSessionHasErrors('assistance');
        $other = User::factory()->create(['role' => 'family']);
        $this->post(route('customer-care.care-assistance', $other), $this->enrollmentInput($application))->assertSessionHasErrors('application_id');
        $otherAccount = app(FamilyAccountContext::class)->account($other);
        CarePricingAgreement::create(['family_account_id' => $otherAccount->id, 'all_caregivers' => true, 'active' => true,
            'created_by_user_id' => $staff->id, 'reason' => 'Existing custom agreement', 'family_care_rate_cents' => 1575,
            'family_processing_fee_rate_cents' => 0, 'caregiver_gross_rate_cents' => 1500, 'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING]);
        $this->post(route('customer-care.care-assistance', $other), $this->enrollmentInput())->assertSessionHasErrors('assistance');
        $this->assertSame(1, CarePricingAgreement::where('program', CarePricingAgreement::CARE_ASSISTANCE)->count());
    }

    public function test_posted_work_preserves_terms_across_activation_end_and_reenrollment(): void
    {
        [$staff, $customer] = $this->people();
        $caregiver = $this->caregiver();
        $standard = $this->request($staff, $customer);
        $standardBooking = $this->booking($standard, $caregiver);
        $before = $standardBooking->fresh()->getRawOriginal();
        $first = $this->enroll($staff, $customer);
        $program = $this->request($staff, $customer);
        $pricing = app(MarketplacePricing::class);
        $this->assertFalse($standard->isCareAssistance());
        $this->assertTrue($program->isCareAssistance());
        $this->assertSame(3100, $pricing->quoteForRequest($standard, $caregiver->id, 60)['total_charge_cents']);
        $this->assertFalse($pricing->hasUnappliedAgreement($standardBooking));
        $this->end($staff, $customer, $first);
        $this->assertSame(2300, $pricing->quoteForRequest($program, $caregiver->id, 60)['total_charge_cents']);
        $this->assertSame(2000, $this->booking($program, $caregiver)->caregiver_gross_rate_cents);
        $this->assertFalse($this->request($staff, $customer)->isCareAssistance());
        $second = $this->enroll($staff, $customer);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, $program->fresh()->care_assistance_pricing_agreement_id);
        $this->assertSame($before, $standardBooking->fresh()->getRawOriginal());
        $this->assertSame(3, FamilyAccountActivityLog::where('action', 'like', 'care_assistance_%')->count());
    }

    public function test_staff_card_hiring_and_settlement_use_23_and_20_less_actual_processing_fees(): void
    {
        [$staff, $customer] = $this->people();
        $this->enroll($staff, $customer);
        $request = $this->request($staff, $customer);
        $caregiver = $this->caregiver();
        app(ManagedCareService::class)->hire($staff, $request, $caregiver->id, 'Caregiver agreed to program pay.', 'card');
        $booking = $request->fresh()->booking;
        $this->assertSame(2300, $booking->family_care_rate_cents);
        $this->assertSame(0, $booking->family_processing_fee_rate_cents);
        $this->assertSame(2000, $booking->caregiver_gross_rate_cents);
        $this->assertEquals(20, $booking->application->proposed_rate);
        $this->assertNull($booking->family_terms_accepted_at);
        $payments = app(BookingPaymentService::class);
        $payments->authorizeForBooking($booking, notify: false);
        $booking->update(['status' => 'completed', 'worked_minutes' => 133, 'started_at' => now()->subMinutes(133),
            'completed_at' => now(), 'family_confirmed_at' => now()]);
        $payment = $payments->captureForBooking($booking->fresh(), notify: false);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertSame(5098, $payment->amount_captured_cents);
        $this->assertSame(4433, $payment->caregiver_gross_amount_cents);
        $this->assertGreaterThan(0, $payment->stripe_processing_fee_cents);
        $this->assertSame(4433 - $payment->stripe_processing_fee_cents, $payment->caregiver_amount_cents);
        $payout = CaregiverPayoutItem::where('care_booking_id', $booking->id)->sole();
        $this->assertEquals($payment->caregiver_amount_cents / 100, $payout->amount);
        $this->assertEquals($payment->stripe_processing_fee_cents / 100, $payout->processing_fee_amount);
        $this->assertSame($booking->pricing_agreement_id, $payment->pricing_agreement_id);
    }

    public function test_recurring_hire_generated_visits_and_extra_visits_retain_program_after_ending(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $start = now()->addDays(2);
        $request = $this->request($staff, $customer, ['request_type' => 'recurring', 'recurring_days' => [$start->dayOfWeek],
            'recurring_start_time' => '10:00', 'recurring_end_time' => '12:00', 'recurring_starts_on' => $start->toDateString()]);
        $caregiver = $this->caregiver();
        app(ManagedCareService::class)->hire($staff, $request, $caregiver->id, 'Recurring care at program pay agreed.', 'card');
        $plan = CarePlan::where('source_care_request_id', $request->id)->sole();
        $this->assertSame($agreement->id, $plan->care_assistance_pricing_agreement_id);
        $this->assertEquals(23, $plan->hourly_rate);
        $this->end($staff, $customer, $agreement);
        $this->travel(8)->days();
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
        foreach ($plan->generatedBookings()->with('careRequest', 'application')->get() as $booking) {
            $this->assertSame(2300, $booking->family_care_rate_cents);
            $this->assertSame(2000, $booking->caregiver_gross_rate_cents);
            $this->assertEquals(20, $booking->application->proposed_rate);
            $this->assertTrue($booking->careRequest->isCareAssistance());
        }
        $quote = app(MarketplacePricing::class)->quoteForPlan($plan->fresh(), $caregiver->id, 90);
        $this->assertSame(3450, $quote['total_charge_cents']);
        $this->assertSame(3000, $quote['caregiver_gross_amount_cents']);
        $extraStart = now()->addDays(3)->setTime(15, 0);
        $extra = app(CarePlanOccurrenceService::class)->createExtraVisit($plan->fresh(), $extraStart, $extraStart->copy()->addMinutes(90));
        $this->assertSame($agreement->id, $extra->pricing_agreement_id);
        $this->assertSame(2000, $extra->caregiver_gross_rate_cents);
    }

    public function test_old_recurring_plans_keep_standard_pricing_when_account_enrolls(): void
    {
        [$staff, $customer] = $this->people();
        $caregiver = $this->caregiver();
        $start = now()->addDay();
        $plan = CarePlan::create(['family_user_id' => $customer->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'active', 'title' => 'Existing recurring care', 'hourly_rate' => 30, 'timezone' => config('app.timezone'),
            'starts_on' => $start->toDateString(), 'schedule_days' => [$start->dayOfWeek], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00']);
        $this->enroll($staff, $customer);
        $visits = app(CarePlanOccurrenceService::class)->materialize($plan)['created'];
        $this->assertNotEmpty($visits);
        foreach ($visits as $visit) {
            $this->assertFalse($visit->careRequest->isCareAssistance());
            $this->assertSame(3000, $visit->family_care_rate_cents);
            $this->assertSame(2700, $visit->caregiver_gross_rate_cents);
        }
    }

    public function test_caregiver_invitation_rates_and_views_do_not_expose_application_details(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer, $this->application());
        $request = $this->request($staff, $customer);
        $caregiver = $this->caregiver();
        $invitation = CareRequestInvitation::create(['care_request_id' => $request->id, 'family_user_id' => $customer->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'pending', 'expires_at' => now()->addDay()]);
        $this->end($staff, $customer, $agreement);
        Livewire::actingAs($caregiver)->test(InvitationsIndex::class)->assertSee('Care Assistance')->assertSee('$20.00/hour')->assertDontSee('PRIVATE HARDSHIP');
        $result = app(CareRequestInvitationResponseService::class)->accept($invitation, $caregiver);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertEquals(20, $invitation->fresh()->application->proposed_rate);
        Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Care Assistance')->assertSee('20.00')->assertDontSee('PRIVATE HARDSHIP');
        $content = app(CareRequestRecruitmentEmail::class)->content($caregiver, $request);
        $details = json_encode($content['payload']['email_details']);
        $this->assertStringContainsString('Care Assistance', $details);
        $this->assertStringContainsString('$20.00', $details);
        $this->assertStringContainsString('before Stripe processing fees', $details);
        $this->assertStringNotContainsString('PRIVATE HARDSHIP', $details);
    }

    public function test_family_and_staff_creation_show_program_and_require_eligible_prepaid_hours(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $request = $this->request($staff, $customer);
        $this->actingAs($staff)->get(route('customer-care.create', $customer))->assertOk()->assertSee('Care Assistance')->assertSee('$23.00/hour');
        $this->get(route('customer-care.show', $request))->assertOk()->assertSee('Care Assistance')->assertSee('$23.00/hour');
        Livewire::actingAs($customer)->test(CreateCareRequestWizard::class)->assertSee('Care Assistance')->assertSee('$23.00/hour');
        $selfPosted = CareRequest::create(['family_user_id' => $customer->id, 'title' => 'Customer-posted care',
            'status' => 'open', 'request_type' => 'one_time', 'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $this->assertSame($agreement->id, $selfPosted->care_assistance_pricing_agreement_id);
        $this->assertNull(app(CareHourRequestFundingService::class)->accountForRequest($request, $customer));
        $this->actingAs($staff);
        $this->end($staff, $customer, $agreement);
        try {
            app(ManagedCareService::class)->hire($staff, $request, $this->caregiver()->id, 'Program placement agreed.', 'prepaid');
            $this->fail('Program requests require eligible prepaid hours, even after enrollment ends.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('saved card', $exception->getMessage());
            $this->assertDatabaseCount('care_bookings', 0);
        }
    }

    public function test_payment_guard_rejects_corrupted_program_snapshot_after_enrollment_ends(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $booking = $this->booking($this->request($staff, $customer), $this->caregiver());
        $this->end($staff, $customer, $agreement);
        $booking->update(['caregiver_gross_rate_cents' => 2700]);
        app()->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $this->expectException(\App\Exceptions\Payments\PaymentException::class);
        app(BookingPaymentService::class)->authorizeForBooking($booking->fresh(), notify: false);
    }

    public function test_linked_active_application_cannot_be_declined_without_ending_enrollment(): void
    {
        [$staff, $customer] = $this->people();
        $application = $this->application();
        $this->enroll($staff, $customer, $application);
        Livewire::actingAs($staff)->test(CareAssistanceApplicationShow::class, ['application' => $application->fresh()])
            ->call('updateStatus', 'declined')->assertHasErrors('status');
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_continuous_coverage_quotes_pin_program_and_partial_request_loads_preserve_rates(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $caregiver = $this->caregiver();
        $plan = ContinuousCoveragePlan::create(['family_user_id' => $customer->id, 'created_by_user_id' => $staff->id,
            'title' => 'Coverage', 'status' => 'draft', 'hourly_rate' => 23, 'timezone' => config('app.timezone'), 'starts_on' => now()->toDateString(),
            'recipient_snapshot' => ['full_name' => 'Jamie'], 'address_snapshot' => ['city' => 'Raleigh'], 'coverage_pattern' => 'daily', 'shift_length_minutes' => 120]);
        $request = $this->request($staff, $customer);
        $this->end($staff, $customer, $agreement);
        $quote = app(\App\Services\ContinuousCoverage\ContinuousCoveragePricingService::class)->quoteForPlan($plan, $caregiver, 120);
        $this->assertSame(4600, $quote['total_charge_cents']);
        $this->assertSame(4000, $quote['caregiver_gross_amount_cents']);
        $partial = CareRequest::select('id', 'family_account_id')->findOrFail($request->id);
        $this->assertSame(2000, app(MarketplacePricing::class)->caregiverQuoteForRequest($partial, $caregiver->id)['caregiver_gross_rate_cents']);
    }

    private function people(): array
    {
        return [User::factory()->create(['role' => 'customer_success']), User::factory()->create(['role' => 'family'])];
    }

    public function test_received_program_payment_funds_care_at_23_20_without_another_customer_charge(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $input = $this->programCredit($agreement);
        $credits = app(\App\Services\CareHours\ManualCareHourCreditService::class);
        $wallet = $credits->credit($staff, $customer, $input);
        $credits->credit($staff, $customer, $input);
        $credits->credit($staff, $customer, array_replace($input, ['request_key' => (string) Str::uuid()]));
        $this->assertSame(2880, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_manual_credits', 1);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $request = $this->request($staff, $customer);
        $this->assertSame($wallet->id, app(CareHourRequestFundingService::class)->defaultAccountId($request, $customer));
        $partial = CareRequest::select('id', 'family_account_id', 'family_user_id')->findOrFail($request->id);
        $this->assertSame($wallet->id, app(CareHourRequestFundingService::class)->accountForRequest($partial, $customer)?->id);
        app(ManagedCareService::class)->hire($staff, $request, $this->caregiver()->id, 'Program placement agreed.', 'prepaid');
        $booking = $request->fresh()->booking;
        $this->assertSame(2300, $booking->family_care_rate_cents);
        $this->assertSame(0, $booking->family_processing_fee_rate_cents);
        $this->assertSame(2000, $booking->caregiver_gross_rate_cents);
        $this->assertSame(120, $wallet->balance()['reserved_minutes']);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120, 'completed_at' => now(), 'family_confirmed_at' => now()]);
        $settlements = app(\App\Services\CareHours\PrepaidBookingSettlementService::class);
        $settlement = $settlements->settle($booking->fresh());
        $settlement = $settlements->reconcile($settlement);
        $this->assertSame(4000, $settlement->caregiver_gross_cents);
        $this->assertSame(134, $settlement->package_fee_cents);
        $this->assertSame(3866, $settlement->caregiver_net_cents);
        $this->assertSame(0, $settlement->amount_due_cents);
        $this->assertSame(120, $wallet->balance()['used_minutes']);
        $this->assertSame(2760, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(0, \App\Models\CareHourPaymentOperation::where('kind', 'shortfall')->count());
        $booking->update(['worked_minutes' => 2880]);
        $settlement = $settlements->reconcile($settlements->settle($booking->fresh()));
        $this->assertSame(96000, $settlement->caregiver_gross_cents);
        $this->assertSame(3232, $settlement->package_fee_cents);
        $this->assertSame(92768, $settlement->caregiver_net_cents);
        $this->assertSame(0, $settlement->amount_due_cents);
        $this->assertSame(0, $wallet->balance()['available_minutes']);
    }

    public function test_program_credit_cannot_fund_standard_care_and_standard_credit_cannot_fund_program_care(): void
    {
        [$staff, $customer] = $this->people();
        $standardRequest = $this->request($staff, $customer);
        $credits = app(\App\Services\CareHours\ManualCareHourCreditService::class);
        $wallet = $credits->credit($staff, $customer, ['request_key' => (string) Str::uuid(), 'hours' => 1,
            'amount' => 31, 'reference' => 'bank-standard', 'reason' => 'Verified bank payment.']);
        $agreement = $this->enroll($staff, $customer);
        $credits->credit($staff, $customer, $this->programCredit($agreement));
        $programRequest = $this->request($staff, $customer);
        $ledger = app(\App\Services\CareHours\CareHourLedgerService::class);
        $standardBooking = $this->booking($standardRequest, $this->caregiver());
        $programBooking = $this->booking($programRequest, $this->caregiver());
        $this->assertSame(60, $ledger->reserve($wallet, $standardBooking, 120));
        $this->assertSame(2880, $ledger->reserve($wallet, $programBooking, 2940));
        $this->assertNull($ledger->allocationsFor($standardBooking)->sole()->lot->care_assistance_pricing_agreement_id);
        $this->assertEquals($agreement->id, $ledger->allocationsFor($programBooking)->sole()->lot->care_assistance_pricing_agreement_id);
        $ledger->release($programBooking);
        $this->assertSame(2880, $wallet->balance()['available_minutes']);
    }

    public function test_program_credit_requires_correct_paid_amount_fee_and_current_enrollment(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        foreach ([['amount' => 1488], ['processing_fee' => null], ['expected_agreement_id' => 0], ['processing_fee' => 1105]] as $invalid) {
            $this->actingAs($staff)->post(route('customer-care.credit', $customer), array_replace($this->programCredit($agreement), $invalid))
                ->assertSessionHasErrors();
            $this->assertDatabaseCount('care_hour_manual_credits', 0);
        }
        $input = $this->programCredit($agreement);
        $this->end($staff, $customer, $agreement);
        $this->post(route('customer-care.credit', $customer), $input)->assertSessionHasErrors('hours');
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_invoice_reference_and_fee_cannot_be_reused_for_another_credit(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $input = $this->programCredit($agreement);
        $this->actingAs($staff)->post(route('customer-care.credit', $customer), $input)->assertSessionHasNoErrors();
        $this->post(route('customer-care.credit', $customer), array_replace($input, ['processing_fee' => 1]))->assertSessionHasErrors('reference');
        $other = User::factory()->create(['role' => 'family']);
        $otherAgreement = $this->enroll($staff, $other);
        $this->post(route('customer-care.credit', $other), $this->programCredit($otherAgreement))->assertSessionHasErrors('reference');
        $this->assertDatabaseCount('care_hour_manual_credits', 1);
    }

    public function test_ended_enrollment_keeps_prepaid_program_work_and_overrun_uses_program_rate(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $wallet = app(\App\Services\CareHours\ManualCareHourCreditService::class)->credit($staff, $customer, $this->programCredit($agreement));
        $request = $this->request($staff, $customer);
        $this->end($staff, $customer, $agreement);
        $this->assertSame($wallet->id, app(CareHourRequestFundingService::class)->accountForRequest($request, $customer)?->id);
        app(ManagedCareService::class)->hire($staff, $request, $this->caregiver()->id, 'Existing program placement agreed.', 'prepaid');
        $booking = $request->fresh()->booking;
        $preview = app(\App\Services\CareHours\PrepaidBookingFundingService::class)->approvalPreview($booking, 2940);
        $this->assertSame(2880, $preview['prepaid_minutes']);
        $this->assertSame(2300, $preview['card_cents']);
    }

    private function programCredit(CarePricingAgreement $agreement): array
    {
        return ['request_key' => (string) Str::uuid(), 'hours' => 48, 'amount' => 1104, 'processing_fee' => 32.32,
            'expected_agreement_id' => $agreement->id, 'reference' => 'in_test_program_48', 'reason' => 'Verified paid Stripe invoice for 48 program hours.'];
    }

    public function test_program_recurring_visits_inherit_credit_and_restore_cancelled_hours(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        $wallet = app(\App\Services\CareHours\ManualCareHourCreditService::class)->credit($staff, $customer, $this->programCredit($agreement));
        $start = now()->addDays(2);
        $request = $this->request($staff, $customer, ['request_type' => 'recurring', 'recurring_days' => [$start->dayOfWeek],
            'recurring_start_time' => '10:00', 'recurring_end_time' => '12:00', 'recurring_starts_on' => $start->toDateString()]);
        app(ManagedCareService::class)->hire($staff, $request, $this->caregiver()->id, 'Recurring program care agreed.', 'prepaid');
        $plan = CarePlan::where('source_care_request_id', $request->id)->sole();
        $this->end($staff, $customer, $agreement);
        $this->travel(8)->days();
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh());
        $funding = app(\App\Services\CareHours\PrepaidBookingFundingService::class);
        foreach ($plan->generatedBookings()->get() as $booking) {
            $this->assertTrue($funding->isPrepaid($booking));
            $this->assertSame(2300, $booking->family_care_rate_cents);
            $this->assertSame(2000, $booking->caregiver_gross_rate_cents);
        }
        $booking = $request->fresh()->booking;
        $before = $wallet->balance();
        $reserved = $funding->forBooking($booking)->reserved_minutes;
        $booking->update(['status' => 'cancelled']);
        $funding->release($booking->fresh());
        $this->assertSame($before['available_minutes'] + $reserved, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_prepaid_program_settlement_rejects_a_changed_caregiver_rate(): void
    {
        [$staff, $customer] = $this->people();
        $agreement = $this->enroll($staff, $customer);
        app(\App\Services\CareHours\ManualCareHourCreditService::class)->credit($staff, $customer, $this->programCredit($agreement));
        $request = $this->request($staff, $customer);
        app(ManagedCareService::class)->hire($staff, $request, $this->caregiver()->id, 'Program placement agreed.', 'prepaid');
        $booking = $request->fresh()->booking;
        $booking->update(['status' => 'completed', 'worked_minutes' => 120, 'caregiver_gross_rate_cents' => 2700]);
        $this->expectException(ValidationException::class);
        app(\App\Services\CareHours\PrepaidBookingSettlementService::class)->settle($booking->fresh());
    }

    private function enrollmentInput(?CareAssistanceApplication $application = null): array
    {
        return ['action' => 'activate', 'expected_agreement_id' => 0, 'application_id' => $application?->id,
            'approval_confirmed' => 1, 'reason' => 'Approval verified by the care team.'];
    }

    private function enroll(User $staff, User $customer, ?CareAssistanceApplication $application = null): CarePricingAgreement
    {
        app(CareAssistanceEnrollmentService::class)->update($staff, $customer, $this->enrollmentInput($application));

        return CarePricingAgreement::where('family_account_id', app(FamilyAccountContext::class)->account($customer)->id)->where('active', true)->sole();
    }

    private function end(User $staff, User $customer, CarePricingAgreement $agreement): void
    {
        app(CareAssistanceEnrollmentService::class)->update($staff, $customer, ['action' => 'end',
            'expected_agreement_id' => $agreement->id, 'reason' => 'Program eligibility ended for new work.']);
    }

    private function application(array $overrides = []): CareAssistanceApplication
    {
        return CareAssistanceApplication::create(array_replace(['name' => 'Approved Applicant', 'email' => 'applicant@example.test', 'phone' => '9195550100',
            'relationship' => 'self', 'city' => 'Raleigh', 'zip' => '27601', 'care_needs' => ['companionship'], 'weekly_hours' => '5_10',
            'start_timeframe' => 'asap', 'hardship_details' => 'PRIVATE HARDSHIP', 'consented_at' => now(), 'status' => 'approved', 'reviewed_at' => now()], $overrides));
    }

    private function request(User $staff, User $customer, array $overrides = []): CareRequest
    {
        return app(ManagedCareService::class)->save($staff, $customer, array_replace([
            'request_key' => (string) Str::uuid(), 'title' => 'Afternoon companionship', 'scope_of_work' => 'Companionship and meal preparation.',
            'recipient_name' => 'Jamie', 'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(), 'status' => 'open',
            'tasks' => [CareTask::firstOrCreate(['name' => 'Companionship'])->id],
        ], $overrides));
    }

    private function caregiver(): User
    {
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'slug' => 'assistance-care-'.$user->id,
            'status' => 'active', 'screening_initial_approved_at' => now(), 'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'is_accepting_new_clients' => true, 'identity_verified_at' => now(),
            'stripe_connect_account_id' => 'acct_test_'.$user->id, 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }

    private function booking(CareRequest $request, User $caregiver): CareBooking
    {
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'hired', 'proposed_rate' => app(MarketplacePricing::class)->caregiverQuoteForRequest($request, $caregiver->id)['caregiver_gross_rate_cents'] / 100]);

        return CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_account_id' => $request->family_account_id, 'family_user_id' => $request->family_user_id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'scheduled', 'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
    }
}
