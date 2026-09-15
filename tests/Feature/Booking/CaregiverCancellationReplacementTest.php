<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\Booking\CaregiverCancellationService;
use App\Services\Marketplace\CareRequestHiringService;
use App\Services\Payments\StripeClient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaregiverCancellationReplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.bypass', true);
        Notification::fake();
        Http::preventStrayRequests();
    }

    public function test_caregiver_cancels_and_family_hires_on_the_same_request_preserving_history(): void
    {
        [$family, $keri, $request, $booking, $payment, $idah, $virna] = $this->scenario();
        $originalRequest = $request->only(['id', 'requested_start_at', 'requested_end_at', 'first_hire_at', 'title', 'family_account_id']);
        $originalConversation = CareRequestConversation::findOrCreateForApplication($booking->application, $family->id);
        $oldAgreement = $booking->agreement_snapshot;
        $pending = $booking->changeRequests()->create(['requester_user_id' => $family->id, 'type' => 'reschedule', 'status' => 'pending', 'reason' => 'Please arrive a little later.']);

        Livewire::actingAs($keri)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee("I can't make it", false)
            ->set('cancellationReason', 'I am unable to attend this visit.')
            ->call('cancelBookingForReplacement')->assertHasNoErrors()
            ->assertSee('Your visit is cancelled.');

        $this->assertSame('open', $request->fresh()->status);
        $this->assertEquals($originalRequest, $request->fresh()->only(array_keys($originalRequest)));
        $this->assertNull($request->fresh()->booking);
        $this->assertSame('withdrawn', $booking->application->fresh()->status);
        $this->assertSame('applied', $idah->fresh()->status);
        $this->assertSame('applied', $virna->fresh()->status);
        $this->assertSame('withdrawn', $pending->fresh()->status);
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertDatabaseCount('care_requests', 1);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Your request is open again')
            ->assertSee('Cancelled visit #'.$booking->id)
            ->call('hire', $idah->id)->assertHasNoErrors();

        $replacement = $request->fresh()->booking;
        $this->assertNotNull($replacement);
        $this->assertNotSame($booking->id, $replacement->id);
        $this->assertSame($idah->caregiver_user_id, $replacement->caregiver_user_id);
        $this->assertSame($request->id, $replacement->care_request_id);
        $this->assertSame('filled', $request->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame($keri->id, $booking->fresh()->caregiver_user_id);
        $this->assertSame($keri->id, $booking->fresh()->cancelled_by_user_id);
        $this->assertSame($oldAgreement, $booking->fresh()->agreement_snapshot);
        $this->assertSame($booking->id, $originalConversation->application->booking->id);
        $this->assertSame($booking->id, $payment->fresh()->care_booking_id);
        $this->assertNotSame($payment->id, $replacement->payment->id);
        $this->assertNotSame($payment->stripe_payment_intent_id, $replacement->payment->stripe_payment_intent_id);
        $this->assertSame($booking->id, $request->fresh()->releasedBookings->sole()->id);
        $this->assertDatabaseCount('care_bookings', 2);

        // A stale caregiver browser retry cannot reopen the newly hired request.
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I am unable to attend this visit.');
        $this->assertSame('filled', $request->fresh()->status);
        $this->assertSame($replacement->id, $request->fresh()->booking->id);
        $this->assertSame(1, $booking->events()->where('event_type', 'caregiver_cancelled_request_reopened')->count());
    }

    public function test_shared_hiring_service_creates_a_fresh_visit_for_a_replacement(): void
    {
        [$family, $keri, $request, $booking, $payment, $idah] = $this->scenario();
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        $result = app(CareRequestHiringService::class)->hire($family, $idah->fresh());
        $this->assertNotSame($booking->id, $result['booking']->id);
        $this->assertSame($request->id, $result['request']->id);
        $this->assertSame('cancelled', $payment->fresh()->status);
    }

    public function test_existing_cancellation_form_also_reopens_without_waiting_for_family_approval(): void
    {
        [, $keri, $request, $booking] = $this->scenario();
        Livewire::actingAs($keri)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('changeType', 'cancel')->set('changeReason', 'I cannot attend this visit.')
            ->call('submitChangeRequest')->assertHasNoErrors();
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseCount('care_booking_change_requests', 0);
    }

    public function test_accepting_an_existing_caregiver_cancellation_request_also_reopens(): void
    {
        [$family, $keri, $request, $booking] = $this->scenario();
        $change = $booking->changeRequests()->create(['requester_user_id' => $keri->id, 'type' => 'cancel', 'status' => 'pending', 'reason' => 'I cannot attend this visit.']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('resolveChangeRequest', $change->id, 'accept')->assertHasNoErrors();
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('accepted', $change->fresh()->status);
        $this->assertSame($keri->id, $booking->fresh()->cancelled_by_user_id);
    }

    public function test_authorization_release_failure_can_be_retried_without_reopening_or_overwriting(): void
    {
        [, $keri, $request, $booking, $payment] = $this->scenario();
        $this->mock(StripeClient::class)->shouldReceive('cancelPaymentIntent')->once()->with('pi_original_visit')
            ->andThrow(new PaymentException('Processor unavailable.'));
        try {
            app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
            $this->fail('An unreleased authorization must prevent a replacement hire.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('could not be released', $e->getMessage());
        }
        $this->assertSame('filled', $request->fresh()->status);
        $this->assertSame('scheduled', $booking->fresh()->status);
        $this->assertSame('authorized', $payment->fresh()->status);
        $this->assertNull($booking->fresh()->replacement_released_at);
        $this->mock(StripeClient::class)->shouldReceive('cancelPaymentIntent')->once()->with('pi_original_visit')->andReturnNull();
        // Re-resolve the payment service after replacing its Stripe dependency.
        $this->app->forgetInstance(\App\Services\Payments\BookingPaymentService::class);
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('cancelled', $payment->fresh()->status);
    }

    #[DataProvider('unsafeStates')]
    public function test_recorded_care_or_settled_payment_cannot_be_cancelled_for_replacement(string $model, array $attributes): void
    {
        [, $keri, $request, $booking, $payment] = $this->scenario();
        ($model === 'booking' ? $booking : $payment)->update($attributes);
        $this->mock(StripeClient::class)->shouldNotReceive('cancelPaymentIntent');
        try {
            app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
            $this->fail('An unsafe cancellation must be refused.');
        } catch (ValidationException) {
            $this->assertSame('filled', $request->fresh()->status);
            $this->assertNull($booking->fresh()->replacement_released_at);
        }
    }

    public static function unsafeStates(): array
    {
        return [
            'checked in' => ['booking', ['started_at' => '2026-09-15 12:00:00']],
            'worked minutes' => ['booking', ['worked_minutes' => 15]],
            'timesheet' => ['booking', ['timesheet_submitted_at' => '2026-09-15 12:00:00']],
            'captured' => ['payment', ['amount_captured_cents' => 7440]],
            'transferred' => ['payment', ['stripe_transfer_id' => 'tr_already_paid']],
        ];
    }

    public function test_only_hired_caregiver_can_directly_cancel_and_withdrawn_caregiver_cannot_be_hired(): void
    {
        [$family, $keri, $request, $booking, , $idah] = $this->scenario();
        try {
            app(CaregiverCancellationService::class)->cancel($booking, $idah->caregiver, 'I cannot attend this visit.');
            $this->fail('An unrelated caregiver cannot cancel.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $booking->care_request_application_id)->assertHasErrors('hire');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 1);
    }

    public function test_only_not_selected_applicants_are_restored(): void
    {
        [, $keri, $request, $booking, , $idah, $virna] = $this->scenario();
        $idah->update(['status' => 'rejected']);
        $virna->update(['status' => 'withdrawn']);
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        $this->assertSame('rejected', $idah->fresh()->status);
        $this->assertSame('withdrawn', $virna->fresh()->status);
        $this->assertSame('open', $request->fresh()->status);
    }

    public function test_database_still_rejects_two_current_bookings_on_one_request(): void
    {
        [, , , $booking] = $this->scenario();
        $this->expectException(QueryException::class);
        CareBooking::create($booking->only(['care_request_id', 'care_request_application_id', 'family_user_id', 'caregiver_user_id', 'status']));
    }

    public function test_check_in_cannot_revive_a_visit_cancelled_while_the_page_was_open(): void
    {
        [, $keri, $request, $booking] = $this->scenario();
        $component = Livewire::actingAs($keri)->test(ApplyToCareRequest::class, ['careRequest' => $request->id]);
        $policy = app(\App\Services\RegularCare\CareBookingCheckInPolicy::class);
        $cancelled = false;
        $this->mock(\App\Services\RegularCare\CareBookingCheckInPolicy::class)->shouldReceive('evaluate')
            ->andReturnUsing(function ($candidate) use ($keri, $booking, $policy, &$cancelled) {
                $result = $policy->evaluate($candidate);
                if (! $cancelled) {
                    $cancelled = true;
                    app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');

                    return [...$result, 'allowed' => true];
                }

                return $result;
            });
        $component->call('startBooking')->assertHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->started_at);
        $this->assertSame('open', $request->fresh()->status);
    }

    #[DataProvider('unprotectedPayments')]
    public function test_cancellation_works_before_card_authorization(string $state): void
    {
        [, $keri, $request, $booking, $payment] = $this->scenario();
        if ($state === 'missing') {
            $payment->delete();
        } else {
            $payment->update(['status' => $state, 'stripe_payment_intent_id' => null, 'amount_authorized_cents' => null]);
        }
        $this->mock(StripeClient::class)->shouldNotReceive('cancelPaymentIntent');
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public static function unprotectedPayments(): array
    {
        return [['missing'], ['draft'], ['authorization_required']];
    }

    public function test_family_can_still_reconsider_a_previously_declined_applicant(): void
    {
        [$family, $keri, $request, $booking, , $idah] = $this->scenario();
        app(CaregiverCancellationService::class)->cancel($booking, $keri, 'I cannot attend this visit.');
        $idah->update(['status' => 'rejected']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $idah->id)->assertHasNoErrors();
        $this->assertSame($idah->caregiver_user_id, $request->fresh()->booking->caregiver_user_id);
    }

    private function scenario(): array
    {
        $family = User::factory()->create(['role' => 'family', 'name' => 'John Grady Eberdt']);
        $account = app(\App\Services\FamilyAccounts\FamilyAccountProvisioner::class)->provisionOwner($family, 'replacement_test');
        $keri = User::factory()->create(['role' => 'caregiver', 'name' => 'Keri Battles']);
        $others = collect(['Idah Ongwacho', 'Virna Little'])->map(fn ($name) => User::factory()->create(['role' => 'caregiver', 'name' => $name]));
        foreach ([$keri, ...$others] as $caregiver) {
            CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 30,
                'bio' => str_repeat('Experienced caregiver. ', 4), 'years_experience' => 5, 'service_area_zip' => '27601',
                'service_radius_miles' => 25, 'insurance_status' => CaregiverProfile::INSURANCE_NO,
                'identity_verified_at' => now(), 'identity_verification_status' => 'approved']);
        }
        $request = CareRequest::withoutEvents(fn () => CareRequest::create([
            'family_user_id' => $family->id, 'family_account_id' => $account->id, 'title' => 'John overnight transportation',
            'status' => 'filled', 'request_type' => 'one_time', 'first_hire_at' => now(),
            'requested_start_at' => now()->addDay()->setTime(23, 30), 'requested_end_at' => now()->addDays(2)->setTime(1, 30),
            'address_line1' => '123 Main St', 'city' => 'Wake Forest', 'state' => 'NC', 'zip' => '27601',
        ]));
        $request->recipient()->create(['full_name' => 'John Grady Eberdt', 'recipient_is_requester' => true, 'relationship_to_family' => 'Self']);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $keri->id, 'status' => 'hired', 'proposed_rate' => 36]);
        $applications = $others->map(fn ($caregiver) => CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'not_selected', 'proposed_rate' => 30, 'cover_note' => 'I can help John.',
        ]));
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $keri->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'agreement_snapshot' => ['application_id' => $application->id, 'proposed_rate' => 36],
        ]);
        $payment = CareBookingPayment::create(['care_booking_id' => $booking->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $keri->id, 'status' => 'authorized', 'amount_authorized_cents' => 7440,
            'stripe_payment_intent_id' => 'pi_original_visit', 'currency' => 'usd']);

        return [$family, $keri, $request, $booking, $payment, ...$applications];
    }
}
