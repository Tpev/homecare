<?php

namespace Tests\Feature\CustomerCare;

use App\Models\CaregiverProfile;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareHours\ManualCareHourCreditService;
use App\Services\CustomerCare\ManagedCareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCareWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_staff_can_access_workspace_but_not_broader_admin_billing(): void
    {
        foreach (['admin', 'sdr', 'customer_success'] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)->get(route('customer-care.index'))->assertOk()->assertSee('Customer Success');
            $this->get(route('customer-care.gifts'))->assertOk();
            if ($role !== 'admin') {
                $this->get(route('admin.care-hours.index'))->assertForbidden();
            }
        }
        foreach (['family', 'caregiver', 'sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('customer-care.index'))->assertForbidden();
        }
    }

    public function test_revoked_staff_cannot_credit_even_with_a_stale_actor(): void
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        User::whereKey($actor->id)->update(['access_revoked_at' => now()]);
        $customer = User::factory()->create(['role' => 'family']);
        $this->actingAs($actor)->post(route('customer-care.credit', $customer), $this->creditInput())->assertRedirect();
        $this->assertDatabaseCount('care_hour_manual_credits', 0);
    }

    public function test_manual_credit_is_audited_idempotent_and_usable_without_online_sales(): void
    {
        $actor = User::factory()->create(['role' => 'customer_success']);
        $customer = User::factory()->create(['role' => 'family']);
        $data = $this->creditInput();
        $service = app(ManualCareHourCreditService::class);
        $wallet = $service->credit($actor, $customer, $data);
        $service->credit($actor, $customer, $data);
        $service->credit($actor, $customer, array_replace($data, ['request_key' => (string) Str::uuid()]));
        $this->assertSame(300, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_manual_credits', 1);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseHas('care_hour_ledger_entries', ['type' => 'manual_credit', 'actor_user_id' => $actor->id, 'available_delta' => 300]);
        $this->assertDatabaseHas('care_hour_manual_credits', ['reference' => 'bank-001', 'actor_user_id' => $actor->id, 'amount_cents' => 15500]);
        Notification::assertNothingSent();
        $this->actingAs($actor)->get(route('customer-care.customer', $customer))->assertOk()->assertSee('bank-001');
    }

    public function test_reused_payment_reference_cannot_change_amount_or_minutes(): void
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        $customer = User::factory()->create(['role' => 'family']);
        $data = $this->creditInput();
        app(ManualCareHourCreditService::class)->credit($actor, $customer, $data);
        $this->expectException(ValidationException::class);
        app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($data, ['hours' => 10]));
    }

    public function test_staff_posting_keeps_customer_ownership_and_real_staff_attribution(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $this->assertTrue($request->staff_managed);
        $this->assertSame($customer->id, $request->family_user_id);
        $this->assertSame($actor->id, $request->created_by_user_id);
        $this->assertDatabaseHas('customer_care_actions', ['care_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => 'request_published']);
        $this->actingAs($actor)->get(route('customer-care.show', $request))->assertOk()->assertSee('Customer notifications off');
        $this->get(route('customer-care.edit', $request))->assertOk();
        $this->get(route('customer-care.create', $customer))->assertOk();
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_post_retry_does_not_create_a_second_request(): void
    {
        [$actor, $customer] = $this->requestFixture();
        $input = $this->requestInput();
        $first = app(ManagedCareService::class)->save($actor, $customer, $input);
        $again = app(ManagedCareService::class)->save($actor, $customer, $input);
        $this->assertSame($first->id, $again->id);
    }

    public function test_full_prepaid_lifecycle_records_staff_and_never_creates_a_customer_card_payment(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $caregiver = $this->caregiver();
        $care = app(ManagedCareService::class);
        $care->hire($actor, $request, $caregiver->id, 'Placement agreed with caregiver by phone.');
        $booking = $request->fresh()->booking;
        $this->assertNotNull($booking);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertSame(120, $wallet->balance()['reserved_minutes']);
        $care->message($actor, $request, $caregiver->id, 'The team will coordinate the home access details.');
        $care->note($actor, $request, 'Confirmed arrival arrangements with the caregiver.');
        $this->travel(3)->days();
        $care->visit($actor, $request, $booking->id, 'complete', ['reason' => 'Verified actual times with the caregiver.',
            'start' => now()->subHours(2)->toDateTimeString(), 'end' => now()->toDateTimeString(), 'break_minutes' => 15]);
        $care->visit($actor, $request, $booking->id, 'approve', ['reason' => 'Checked and approved actual care.']);
        $care->visit($actor, $request, $booking->id, 'approve', ['reason' => 'Checked and approved actual care.']);
        $this->assertSame(105, $wallet->balance()['used_minutes']);
        $this->assertSame(195, $wallet->balance()['available_minutes']);
        $this->assertSame($actor->id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseHas('care_request_messages', ['sender_user_id' => $actor->id]);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_insufficient_hours_roll_back_hiring_and_reservations(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 1]));
        try {
            app(ManagedCareService::class)->hire($actor, $request, $this->caregiver()->id, 'Placement agreed by phone.');
            $this->fail('Insufficient hours should block hiring.');
        } catch (ValidationException) {
            $this->assertSame('open', $request->fresh()->status);
            $this->assertSame(60, $wallet->balance()['available_minutes']);
            $this->assertDatabaseCount('care_bookings', 0);
        }
    }

    public function test_cancel_returns_hours_and_booking_ids_are_scoped_to_the_request(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $care = app(ManagedCareService::class);
        $care->hire($actor, $request, $this->caregiver()->id, 'Placement agreed by phone.');
        $booking = $request->fresh()->booking;
        [, , $other] = $this->requestFixture();
        $this->actingAs($actor)->post(route('customer-care.action', $other), ['action' => 'visit', 'booking_id' => $booking->id, 'visit_action' => 'cancel', 'reason' => 'Family no longer needs this visit.'])->assertNotFound();
        $care->visit($actor, $request, $booking->id, 'cancel', ['reason' => 'Family no longer needs this visit.']);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(300, $wallet->balance()['available_minutes']);
    }

    public function test_recurring_plan_remains_silent_through_cancel_pause_resume_and_end(): void
    {
        $actor = User::factory()->create(['role' => 'customer_success']);
        $customer = User::factory()->create(['role' => 'family']);
        $date = now()->addDays(2);
        $input = array_replace($this->requestInput(), ['request_type' => 'recurring', 'recurring_days' => [$date->dayOfWeek],
            'recurring_start_time' => '10:00', 'recurring_end_time' => '12:00', 'recurring_starts_on' => $date->toDateString(),
            'recurring_ends_on' => $date->copy()->addWeeks(2)->toDateString()]);
        $care = app(ManagedCareService::class);
        $request = $care->save($actor, $customer, $input);
        app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 20]));
        $care->hire($actor, $request, $this->caregiver()->id, 'Recurring placement agreed by phone.');
        $plan = $request->fresh()->carePlan;
        $this->assertNotNull($plan);
        $visits = $plan->generatedBookings()->orderBy('scheduled_start_at')->get();
        $this->assertGreaterThanOrEqual(2, $visits->count());
        foreach ($visits as $visit) {
            $this->assertTrue($visit->careRequest->staff_managed);
            $this->assertNull($visit->family_terms_accepted_at);
        }
        $second = $visits[1];
        $care->visit($actor, $request, $second->id, 'reschedule', ['start' => $second->scheduled_start_at->copy()->addHours(1)->toDateTimeString(),
            'end' => $second->scheduled_end_at->copy()->addHours(1)->toDateTimeString(), 'reason' => 'Caregiver agreed to arrive one hour later.']);
        $rescheduled = $second->fresh()->scheduled_start_at->toIso8601String();
        app(\App\Services\RegularCare\CarePlanOccurrenceService::class)->materialize($plan->fresh());
        $this->assertSame($rescheduled, $second->fresh()->scheduled_start_at->toIso8601String());
        $care->visit($actor, $request, $second->id, 'cancel', ['reason' => 'Customer does not need this one occurrence.']);
        app(\App\Services\RegularCare\CarePlanOccurrenceService::class)->materialize($plan->fresh());
        $this->assertSame('cancelled', $second->fresh()->status);
        $care->plan($actor, $request, 'pause', 'Pausing while customer is away.');
        $this->assertSame('paused', $plan->fresh()->status);
        $care->plan($actor, $request, 'resume', 'Customer has returned home.');
        $this->assertSame('cancelled', $second->fresh()->status);
        $care->plan($actor, $request, 'end', 'The recurring care engagement has ended.');
        $this->assertSame('ended', $plan->fresh()->status);
        $this->actingAs($actor)->get(route('customer-care.show', $request))->assertOk()->assertSee('Recurring care');
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_offline_credit_pays_from_platform_balance_and_survives_late_overrun_funding(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 2]));
        $caregiver = $this->caregiver();
        $caregiver->caregiverProfile->update(['stripe_connect_account_id' => 'acct_offline_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $care = app(ManagedCareService::class);
        $care->hire($actor, $request, $caregiver->id, 'Offline-funded placement agreed.');
        $booking = $request->fresh()->booking;
        $booking->update(['status' => 'completed', 'worked_minutes' => 180, 'completed_at' => now()]);
        $settler = app(\App\Services\CareHours\PrepaidBookingSettlementService::class);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertGreaterThan(0, $settlement->amount_due_cents);
        $booking->update(['family_confirmed_at' => now()]); // Automatic approval can precede an offline overrun payment.
        $this->actingAs($actor)->get(route('customer-care.index'))->assertOk()->assertSee('Add prepaid coverage');
        app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 1, 'reference' => 'BANK-OVERRUN']));
        $care->visit($actor, $request, $booking->id, 'approve', ['reason' => 'Additional offline payment verified.']);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertSame(0, (int) $settlement->amount_due_cents);
        $this->assertSame(180, $wallet->balance()['used_minutes']);
        $this->assertDatabaseMissing('care_hour_payment_operations', ['care_hour_settlement_id' => $settlement->id, 'kind' => 'transfer', 'status' => 'failed']);
        $this->assertTrue($settlement->operations()->where('kind', 'transfer')->whereNull('source_charge_id')->exists());
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_staff_can_accept_caregiver_cancellation_and_hire_a_replacement_silently(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $caregiver = $this->caregiver();
        $care = app(ManagedCareService::class);
        $care->hire($actor, $request, $caregiver->id, 'Placement agreed by phone.');
        $booking = $request->fresh()->booking;
        $change = $booking->changeRequests()->create(['requester_user_id' => $caregiver->id,
            'type' => 'cancel', 'status' => 'pending', 'reason' => 'Caregiver is no longer available.']);
        $care->change($actor, $request, $booking->id, $change->id, 'accept', 'Confirmed that the caregiver cannot attend.');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame($actor->id, $change->fresh()->resolved_by_user_id);
        $care->hire($actor, $request, $this->caregiver()->id, 'Replacement placement agreed by phone.');
        $this->assertNotSame($booking->id, $request->fresh()->booking->id);
        $this->assertTrue($request->fresh()->staff_managed);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    private function requestFixture(): array
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        $customer = User::factory()->create(['role' => 'family']);
        $request = app(ManagedCareService::class)->save($actor, $customer, $this->requestInput());

        return [$actor, $customer, $request];
    }

    private function requestInput(): array
    {
        return ['request_key' => (string) Str::uuid(), 'title' => 'Afternoon companionship', 'scope_of_work' => 'Companionship and meal preparation.',
            'recipient_name' => 'Jamie', 'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(), 'status' => 'open',
            'tasks' => [CareTask::firstOrCreate(['name' => 'Companionship'])->id]];
    }

    private function creditInput(): array
    {
        return ['request_key' => (string) Str::uuid(), 'hours' => 5, 'amount' => '155.00', 'reference' => 'BANK-001', 'reason' => 'Offline bank transfer received.'];
    }

    private function caregiver(): User
    {
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'slug' => 'staff-care-'.$user->id,
            'status' => 'active', 'screening_initial_approved_at' => now(), 'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'is_accepting_new_clients' => true, 'identity_verified_at' => now()]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }
}
