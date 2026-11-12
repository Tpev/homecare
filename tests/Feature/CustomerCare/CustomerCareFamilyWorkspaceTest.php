<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\CustomerCare\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareHours\ManualCareHourCreditService;
use App\Services\CustomerCare\ManagedCareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCareFamilyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_staff_and_family_render_the_same_request_view_and_caregiver_cards(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $caregiver = $this->caregiver();
        foreach ([$actor, $customer] as $viewer) {
            $class = $viewer->role === 'family' ? \App\Livewire\Family\ManageCareRequest::class : ManageCareRequest::class;
            Livewire::actingAs($viewer)->test($class, ['careRequest' => $request->id])
                ->assertViewIs('livewire.family.manage-care-request')
                ->call('setCaregiverView', 'search')
                ->assertSee('Care &amp; schedule details', false)
                ->assertSee('Search &amp; invite', false)
                ->assertSee('data-testid="discovery-row"', false)
                ->assertSee($caregiver->name)
                ->assertSee('View profile')
                ->set('caregiverSearch', 'unmatched-person')
                ->assertSee('No caregivers found')
                ->call('clearCaregiverSearch')->assertSee($caregiver->name);
        }
    }

    public function test_invitation_uses_family_context_and_real_staff_author_without_customer_notification(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $caregiver = $this->caregiver();
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->set('caregiverInviteMessage', 'The care team would like to invite you.')
            ->call('sendCaregiverInvitation')->assertHasNoErrors()
            ->call('setCaregiverView', 'invited')->assertSee('Message caregiver');
        $this->assertDatabaseHas('care_request_invitations', ['care_request_id' => $request->id,
            'family_user_id' => $customer->id, 'invited_by_user_id' => $actor->id, 'caregiver_user_id' => $caregiver->id]);
        $this->assertDatabaseHas('customer_care_actions', ['care_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => 'caregiver_invited']);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_shared_hiring_can_select_prepaid_funds_and_keeps_staff_attribution(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $caregiver = $this->caregiver();
        $application = $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $component->call('reviewHire', $application->id)->assertSee('Confirm hire')->assertSee('Usual card payment')
            ->set('careHourAccountId', null)->assertSet('careHourAccountId', null)
            ->set('careHourAccountId', $wallet->id)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertNotNull($booking);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertSame(120, $wallet->balance()['reserved_minutes']);
        $this->travel(3)->days();
        $component->call('reviewCompletion')
            ->set('actualStartAt', now()->subHours(2)->format('Y-m-d\TH:i'))
            ->set('actualEndAt', now()->format('Y-m-d\TH:i'))
            ->set('actualBreakMinutes', 15)
            ->call('confirmReviewedCompletion')->assertHasNoErrors()
            ->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertSame(105, $wallet->balance()['used_minutes']);
        $this->assertSame($actor->id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_staff_cannot_submit_a_customer_review(): void
    {
        [$actor, , $request] = $this->requestFixture();
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('submitReview')->assertHasErrors('customer_care');
        $this->assertDatabaseCount('care_reviews', 0);
    }

    public function test_changed_offers_must_be_reviewed_and_partial_hours_use_family_payment_flow(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 0.25]));
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id);
        $application->increment('offer_version');
        $component->call('confirmReviewedHire')->assertHasErrors('hire')
            ->call('reviewHire', $application->id)->call('confirmReviewedHire')->assertHasNoErrors();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertSame('hired', $application->fresh()->status);
        $this->assertSame(105, $request->fresh()->booking->careHourFunding->shortfall_minutes);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_no_show_returns_prepaid_hours_without_customer_card_actions(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        app(ManagedCareService::class)->hire($actor, $request, $this->caregiver()->id, 'Caregiver confirmed by the care team.');
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $component->call('markNoShow')->assertHasErrors('customer_care');
        $this->travel(3)->days();
        $component->call('markNoShow')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertTrue($booking->no_show_flag);
        $this->assertSame($actor->id, $booking->cancelled_by_user_id);
        $this->assertSame(300, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_livewire_rechecks_revoked_staff_and_archived_or_unmanaged_requests(): void
    {
        [$actor, , $request] = $this->requestFixture();
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        User::whereKey($actor->id)->update(['access_revoked_at' => now()]);
        $component->call('setCaregiverView', 'search')->assertForbidden();
        User::whereKey($actor->id)->update(['access_revoked_at' => null]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $request->update(['staff_archived_at' => now()]);
        $component->call('withdrawRequest')->assertForbidden();
        $request->update(['staff_archived_at' => null, 'staff_managed' => false]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertForbidden();
    }

    public function test_other_account_applicants_and_conversations_cannot_be_selected(): void
    {
        [$actor, , $request] = $this->requestFixture();
        [, , $otherRequest] = $this->requestFixture();
        $caregiver = $this->caregiver();
        $application = $otherRequest->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 28]);
        try {
            Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
                ->call('reviewHire', $application->id);
            $this->fail('An unrelated family application must not be available.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(CareRequestApplication::class, $exception->getModel());
        }
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('messageCaregiver', $caregiver->id)->assertNotFound();
        $this->assertDatabaseCount('care_request_conversations', 0);
    }

    public function test_staff_roles_can_open_the_page_but_customers_cannot_use_the_staff_component(): void
    {
        [, , $request] = $this->requestFixture();
        foreach (['admin', 'sdr', 'customer_success'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('customer-care.show', $request))->assertOk()
                ->assertSeeLivewire(ManageCareRequest::class)->assertSee('Remove request');
        }
        foreach (['family', 'caregiver', 'sales'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertForbidden();
        }
    }

    public function test_staff_hires_and_approves_with_the_family_card_using_the_family_payment_flow(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $account = $request->familyAccount;
        $account->update(['stripe_customer_id' => 'cus_saved_family']);
        $actor->update(['stripe_customer_id' => 'cus_staff_must_not_be_used']);
        $this->partialMock(\App\Services\Payments\StripeClient::class)
            ->shouldReceive('defaultPaymentMethodForCustomer')->with('cus_saved_family')
            ->andReturn(['id' => 'pm_saved_family', 'type' => 'card', 'brand' => 'visa', 'last4' => '4242']);
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSee('Usual card payment')
            ->set('careHourAccountId', null)->call('$refresh')->assertSet('careHourAccountId', null)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertSame($customer->id, $booking->family_user_id);
        $this->assertSame($account->id, $booking->family_account_id);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertSame($actor->id, $booking->payment->initiated_by_user_id);
        $this->assertSame('cus_saved_family', $booking->payment->stripe_customer_id);
        $this->assertSame('pm_saved_family', $booking->payment->stripe_payment_method_id);
        $this->assertSame('authorized', $booking->payment->status);
        $this->assertSame(0, $wallet->balance()['reserved_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseHas('customer_care_actions', ['care_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => 'caregiver_hired']);
        $this->assertDatabaseHas('care_hour_request_preferences', ['care_request_id' => $request->id, 'selected_by_user_id' => $actor->id, 'enabled' => false]);

        $this->travel(3)->days();
        $component->call('reviewCompletion')
            ->set('actualStartAt', now()->subHours(2)->format('Y-m-d\TH:i'))
            ->set('actualEndAt', now()->format('Y-m-d\TH:i'))
            ->call('confirmReviewedCompletion')->assertHasNoErrors()
            ->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $paid = $booking->fresh()->payment;
        $this->assertGreaterThan(0, $paid->amount_captured_cents);
        $this->assertSame($actor->id, $booking->fresh()->family_confirmed_by_user_id);
        $component->call('completeBooking')->assertHasNoErrors();
        $this->assertSame($paid->amount_captured_cents, $paid->fresh()->amount_captured_cents);
        $this->assertDatabaseCount('care_booking_payments', 1);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_staff_uses_the_same_stripe_confirmation_and_scopes_it_to_the_visit(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        config(['services.stripe.publishable_key' => 'pk_test_local_fixture']);
        $stripe = $this->partialMock(\App\Services\Payments\StripeClient::class);
        $stripe->shouldReceive('createManualAuthorizationIntent')->once()->andReturn([
            'payment_intent_id' => 'pi_staff_confirmation', 'client_secret' => 'pi_staff_confirmation_secret_test',
            'status' => 'requires_confirmation', 'amount' => 6200, 'authorization_expires_at' => null,
        ]);
        $stripe->shouldReceive('retrievePaymentIntent')->with('pi_staff_confirmation')->twice()->andReturn([
            'payment_intent_id' => 'pi_staff_confirmation', 'id' => 'pi_staff_confirmation', 'status' => 'requires_capture',
            'amount' => 6200, 'amount_received' => 0, 'authorization_expires_at' => now()->addDays(6), 'last_payment_error' => null,
        ]);
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->call('confirmReviewedHire')->assertHasNoErrors()
            ->assertDispatched('confirm-stripe-booking-payment');
        $payment = $request->fresh()->booking->payment;
        $this->assertSame('authorization_required', $payment->status);
        $component->call('finalizeStripeAuthorization', 'pi_unrelated_family');
        $this->assertSame('authorization_required', $payment->fresh()->status);
        $component->call('failStripeAuthorization', 'pi_unrelated_family', 'Unrelated failure');
        $this->assertSame('authorization_required', $payment->fresh()->status);
        $component->call('finalizeStripeAuthorization', 'pi_staff_confirmation')->assertHasNoErrors();
        $this->assertSame('authorized', $payment->fresh()->status);
        $component->call('finalizeStripeAuthorization', 'pi_staff_confirmation')->assertHasNoErrors();
        $this->assertDatabaseCount('care_booking_payments', 1);
        User::whereKey($actor->id)->update(['access_revoked_at' => now()]);
        $component->call('startPaymentAuthorization')->assertForbidden();
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_missing_family_card_blocks_hiring_without_using_the_staff_card(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        config(['services.stripe.bypass' => false]);
        $this->mock(\App\Services\Payments\FamilyBillingService::class)->shouldReceive('summaryFor')
            ->withArgs(fn (User $payer) => $payer->is($customer))->andReturn(['ready' => false]);
        $this->mock(\App\Services\Payments\BookingPaymentService::class)->shouldNotReceive('prepareOnSessionAuthorization');
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSee('No saved card for this family')
            ->call('confirmReviewedHire');
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_card_authorization_failure_does_not_mark_the_request_hired(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $this->partialMock(\App\Services\Payments\StripeClient::class)->shouldReceive('createManualAuthorizationIntent')
            ->once()->andThrow(new \App\Exceptions\Payments\PaymentException('Your card was declined.'));
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->call('confirmReviewedHire')->assertHasErrors('hire');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('applied', $application->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_recurring_card_hire_uses_family_schedule_and_keeps_future_visits_silent(): void
    {
        // Keep the first 10am visit inside the existing 48-hour authorization window.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 14:00:00', config('app.timezone')));
        $actor = User::factory()->create(['role' => 'customer_success']);
        $customer = User::factory()->create(['role' => 'family']);
        $date = now()->addDays(2);
        $request = app(ManagedCareService::class)->save($actor, $customer, array_replace($this->requestInput(), [
            'request_type' => 'recurring', 'recurring_days' => [$date->dayOfWeek],
            'recurring_start_time' => '10:00', 'recurring_end_time' => '12:00', 'recurring_starts_on' => $date->toDateString(),
            'recurring_ends_on' => $date->copy()->addWeeks(2)->toDateString(),
        ]));
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $plan = $request->fresh()->carePlan;
        $this->assertNotNull($plan);
        $visits = $plan->generatedBookings()->orderBy('scheduled_start_at')->get();
        $this->assertGreaterThanOrEqual(2, $visits->count());
        foreach ($visits as $visit) {
            $this->assertSame($customer->id, $visit->family_user_id);
            $this->assertTrue($visit->careRequest->staff_managed);
            $this->assertNull($visit->family_terms_accepted_at);
        }
        $payment = $visits->first()->payment;
        $this->assertNotNull($payment);
        $this->assertSame($actor->id, $payment->initiated_by_user_id);
        $this->assertSame('authorized', $payment->status);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_offline_card_placement_can_be_cancelled_through_existing_payment_service(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $actor->update(['role' => 'admin']);
        $this->actingAs($actor)->post(route('customer-care.action', $request), [
            'action' => 'hire', 'caregiver_id' => $this->caregiver()->id, 'funding_source' => 'card',
            'reason' => 'Caregiver placement confirmed by phone.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $booking = $request->fresh()->booking;
        $this->assertSame('authorized', $booking->payment->status);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('directCancelReason', 'Care is no longer needed for this visit.')
            ->call('cancelScheduledBooking')->assertHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->payment->status);
        $this->assertSame(0, (int) $booking->fresh()->payment->amount_captured_cents);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_staff_can_review_a_card_funded_time_correction_in_the_shared_family_view(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        config(['marketplace.time_corrections.enabled' => true]);
        $caregiver = $this->caregiver();
        $this->actingAs($actor);
        app(ManagedCareService::class)->hire($actor, $request, $caregiver->id, 'Placement agreed with caregiver.', 'card');
        $booking = $request->fresh()->booking;
        $this->travel(3)->days();
        $correction = app(\App\Services\Booking\CareBookingTimeCorrectionService::class)->submit($booking, $caregiver, [
            'reason_code' => \App\Models\CareBookingTimeCorrection::REASON_FORGOT_BOTH,
            'started_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i'),
            'completed_at' => $booking->scheduled_end_at->format('Y-m-d\TH:i'), 'break_minutes' => 0,
            'explanation' => 'I provided the scheduled care but forgot to use the visit timer.', 'confirmed' => true,
        ], (string) Str::uuid());
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('beginTimeCorrectionApproval', $correction->id)
            ->call('approveTimeCorrection', $correction->id)->assertHasNoErrors();
        $this->assertSame('applied', $correction->fresh()->status);
        $this->assertSame($actor->id, $correction->fresh()->approved_by_user_id);
        $this->assertSame(120, $booking->fresh()->worked_minutes);
        $this->assertGreaterThan(0, $booking->fresh()->payment->amount_captured_cents);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_selected_day_card_hire_preserves_staff_context_and_access_to_generated_visits(): void
    {
        // Keep the first 10am visit inside the existing 48-hour authorization window.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 14:00:00', config('app.timezone')));
        [$actor, $customer, $request] = $this->requestFixture();
        $date = now()->addDays(2);
        $days = [$date->dayOfWeek, $date->copy()->addDay()->dayOfWeek];
        $request->update(['request_type' => 'recurring', 'recurring_days' => $days,
            'recurring_start_time' => '10:00', 'recurring_end_time' => '12:00', 'recurring_starts_on' => $date->toDateString(),
            'recurring_ends_on' => $date->copy()->addWeeks(2)->toDateString()]);
        $shared = app(\App\Services\RegularCare\SharedCareService::class);
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied',
            'proposed_rate' => 28, 'selected_days' => [$days[0]], 'offered_schedule' => $shared->offer($request, [$days[0]]),
            'schedule_fingerprint' => $shared->fingerprint($request)]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->call('confirmReviewedHire')->assertHasNoErrors();
        $plan = \App\Models\CarePlan::where('care_arrangement_id', $request->fresh()->care_arrangement_id)->firstOrFail();
        $visit = $plan->generatedBookings()->orderBy('scheduled_start_at')->firstOrFail();
        $this->assertSame('open', $request->fresh()->status);
        $this->assertTrue($visit->careRequest->staff_managed);
        $this->assertNull($visit->family_terms_accepted_at);
        $this->assertSame($actor->id, $visit->payment->initiated_by_user_id);
        $this->actingAs($actor)->get(route('customer-care.show', ['careRequest' => $request, 'panel' => 'visits']))
            ->assertOk()->assertSee('Visit #'.$visit->id);
        $this->post(route('customer-care.action', $request), ['action' => 'archive', 'reason' => 'Attempt to remove ongoing selected-day care.'])
            ->assertSessionHasErrors('customer_care');
        app(ManagedCareService::class)->visit($actor, $request, $visit->id, 'cancel', ['reason' => 'This occurrence is no longer needed.']);
        $this->assertSame('cancelled', $visit->fresh()->payment->status);
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
