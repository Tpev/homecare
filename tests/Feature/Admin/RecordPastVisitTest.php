<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RecordPastVisit;
use App\Models\AdminRecordedVisit;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\AdminRecordedVisitService;
use App\Services\Ops\SlackOpsNotificationService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RecordPastVisitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $caregiver;

    private FamilyAccount $family;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-03 16:00:00', 'UTC'));
        config(['app.timezone' => 'UTC', 'services.stripe.bypass' => true, 'services.stripe.secret' => null,
            'services.stripe.currency' => 'usd', 'marketplace.pricing_v2.enabled' => true,
            'marketplace.pricing_v2.family_care_hourly_cents' => 3000,
            'marketplace.pricing_v2.family_processing_fee_hourly_cents' => 100,
            'marketplace.pricing_v2.caregiver_gross_hourly_cents' => 2700]);
        date_default_timezone_set('UTC');
        Http::preventStrayRequests();
        Notification::fake();
        $this->mock(SlackOpsNotificationService::class, function ($mock): void {
            $mock->shouldNotReceive('queueCaregiverHired');
            $mock->shouldNotReceive('queueCareRequestCreated');
        });
        $this->admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'family', 'name' => 'Test Family']);
        $this->family = FamilyAccount::create(['owner_user_id' => $owner->id, 'status' => 'active']);
        $this->caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Test Caregiver']);
        CaregiverProfile::create(['user_id' => $this->caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 30,
            'stripe_connect_account_id' => 'acct_recorded_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $task = CareTask::firstOrCreate(['name' => 'Transportation']);
        $this->input = [
            'family_account_id' => $this->family->id, 'caregiver_user_id' => $this->caregiver->id, 'source_request_id' => null,
            'date' => '2026-10-20', 'start_time' => '11:30', 'timezone' => 'America/New_York', 'start_occurrence' => 'first',
            'duration_minutes' => 90, 'break_minutes' => 15, 'recipient_name' => 'Test Recipient', 'relationship' => 'Parent',
            'title' => 'Transportation visit', 'scope_of_work' => 'Help getting to an appointment.',
            'address_line1' => '123 Test Street', 'address_line2' => '', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609',
            'task_ids' => [$task->id], 'reason' => 'Family and caregiver confirmed the unbooked visit.',
        ];
        $this->actingAs($this->admin);
    }

    public function test_admin_screen_and_entry_link_render_and_other_roles_are_denied(): void
    {
        $this->get(route('admin.requests.record-past'))->assertOk()->assertSee('Record a past visit')->assertSee('Review visit');
        $this->get(route('admin.requests.index'))->assertOk()->assertSee(route('admin.requests.record-past'));
        foreach (['family', 'caregiver', 'sales'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.requests.record-past'))->assertForbidden();
            Livewire::actingAs($user)->test(RecordPastVisit::class)->assertForbidden();
        }
        $this->admin->forceFill(['access_revoked_at' => now()])->save();
        Livewire::actingAs($this->admin)->test(RecordPastVisit::class)->assertForbidden();
    }

    public function test_preview_is_read_only_and_prices_actual_worked_minutes(): void
    {
        $preview = $this->service()->preview($this->admin, $this->input);
        $this->assertSame('2026-10-20 15:30:00', $preview['input']['started_at']);
        $this->assertSame('2026-10-20 17:00:00', $preview['input']['completed_at']);
        $this->assertSame(75, $preview['input']['worked_minutes']);
        $this->assertSame(3875, $preview['quote']['total_charge_cents']);
        $this->assertSame('simulated', $preview['payment_mode']);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('admin_recorded_visits', 0);
        Http::assertNothingSent();
    }

    public function test_complete_visit_and_repeated_submission_charge_once_with_admin_audit(): void
    {
        $token = (string) Str::uuid();
        $preview = $this->service()->preview($this->admin, $this->input);
        $visit = $this->service()->create($this->admin, $this->input, $token, $preview['fingerprint']);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertNull($visit->fresh()->last_error);
        $again = $this->service()->create($this->admin, $this->input, $token, $preview['fingerprint']);
        $operations = CareBookingPaymentOperation::orderBy('id')->pluck('id')->all();
        $this->service()->processPayment($this->admin, $again);
        $this->assertSame($visit->id, $again->id);
        $this->assertSame($operations, CareBookingPaymentOperation::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_request_applications', 1);
        $this->assertDatabaseCount('care_request_conversations', 1);
        $booking = CareBooking::sole();
        $this->assertSame('completed', $booking->status);
        $this->assertSame(75, (int) $booking->worked_minutes);
        $this->assertSame(900, (int) $booking->total_paused_seconds);
        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertNull($booking->family_confirmed_by_user_id);
        $this->assertNull($booking->timesheet_submitted_at);
        $this->assertNull($booking->caregiver_terms_accepted_at);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertSame(1, $booking->taskChecks()->count());
        $this->assertSame(1, $booking->events()->where('event_type', 'historical_visit_completed_and_paid_by_admin')->where('actor_user_id', $this->admin->id)->count());
        $payment = CareBookingPayment::sole();
        $this->assertSame('transferred', $payment->status);
        $this->assertSame(3875, $payment->amount_captured_cents);
        $this->assertSame($this->admin->id, $payment->initiated_by_user_id);
        $this->get(route('admin.requests.recorded-visit', $visit->id))->assertOk()->assertSee('payment settled')->assertSee('no real money moved');
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_second_token_for_same_hours_is_blocked_and_tampered_replay_is_rejected(): void
    {
        $token = (string) Str::uuid();
        $preview = $this->service()->preview($this->admin, $this->input);
        $this->service()->create($this->admin, $this->input, $token, $preview['fingerprint']);
        $this->assertValidation(fn () => $this->service()->create($this->admin, $this->input, (string) Str::uuid(), $preview['fingerprint']), 'overlapping booking');
        $changed = array_replace($this->input, ['duration_minutes' => 120]);
        $this->assertValidation(fn () => $this->service()->create($this->admin, $changed, $token, $preview['fingerprint']), 'different details');
        $this->assertDatabaseCount('care_bookings', 1);
    }

    public function test_changed_price_or_details_invalidates_review_before_any_write(): void
    {
        $preview = $this->service()->preview($this->admin, $this->input);
        $changed = array_replace($this->input, ['recipient_name' => 'Someone Else']);
        $this->assertValidation(fn () => $this->service()->create($this->admin, $changed, (string) Str::uuid(), $preview['fingerprint']), 'changed');
        config(['marketplace.pricing_v2.family_care_hourly_cents' => 3500]);
        $this->assertValidation(fn () => $this->service()->create($this->admin, $this->input, (string) Str::uuid(), $preview['fingerprint']), 'changed');
        $this->assertDatabaseCount('care_requests', 0);
    }

    public function test_missing_card_saves_visit_and_retry_uses_that_booking(): void
    {
        $visit = $this->createVisit();
        $this->partialMock(StripeClient::class, fn ($mock) => $mock->shouldReceive('defaultPaymentMethodForCustomer')->once()->andReturn(null));
        $this->service()->processPayment($this->admin, $visit);
        $this->assertStringContainsString('payment method', $visit->fresh()->last_error);
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertNull($visit->booking->family_confirmed_at);
        $this->app->forgetInstance(StripeClient::class);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertNull($visit->fresh()->last_error);
        $this->assertSame('transferred', $visit->booking->payment()->sole()->status);
        $this->assertDatabaseCount('care_bookings', 1);
    }

    public function test_transfer_pending_resumes_without_recapturing(): void
    {
        $visit = $this->createVisit();
        $this->caregiver->caregiverProfile->update(['stripe_payouts_enabled' => false]);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertSame(3875, CareBookingPayment::sole()->amount_captured_cents);
        $this->assertStringContainsString('captured', $visit->fresh()->last_error);
        $intent = CareBookingPayment::sole()->stripe_payment_intent_id;
        $this->caregiver->caregiverProfile->update(['stripe_payouts_enabled' => true]);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertSame('transferred', CareBookingPayment::sole()->status);
        $this->assertSame($intent, CareBookingPayment::sole()->stripe_payment_intent_id);
        $this->assertSame(1, CareBookingPaymentOperation::where('type', 'charge')->count());
        $this->assertNull($visit->fresh()->last_error);
    }

    public function test_changed_booking_and_dispute_block_payment(): void
    {
        $visit = $this->createVisit();
        $visit->booking->update(['dispute_opened_at' => now()]);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertStringContainsString('needs review', $visit->fresh()->last_error);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $visit->booking->update(['dispute_opened_at' => null, 'worked_minutes' => 100]);
        $this->service()->processPayment($this->admin, $visit);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_refunded_payment_is_never_charged_again(): void
    {
        $visit = $this->createVisit();
        $this->service()->processPayment($this->admin, $visit);
        $payment = CareBookingPayment::sole();
        $payment->update(['amount_refunded_cents' => 3875, 'status' => 'refunded']);
        $operations = CareBookingPaymentOperation::count();
        $this->service()->processPayment($this->admin, $visit);
        $this->assertStringContainsString('refund', $visit->fresh()->last_error);
        $this->assertSame($operations, CareBookingPaymentOperation::count());
    }

    public function test_dst_transitions_and_overnight_duration_are_explicit(): void
    {
        $spring = array_replace($this->input, ['date' => '2026-03-08', 'start_time' => '02:30']);
        $this->assertValidation(fn () => $this->service()->normalize($spring), 'does not exist');
        $fall = array_replace($this->input, ['date' => '2026-11-01', 'start_time' => '01:30']);
        $this->assertSame('2026-11-01 05:30:00', $this->service()->normalize($fall)['started_at']);
        $fall['start_occurrence'] = 'second';
        $this->assertSame('2026-11-01 06:30:00', $this->service()->normalize($fall)['started_at']);
        $overnight = array_replace($this->input, ['start_time' => '23:30', 'duration_minutes' => 120, 'break_minutes' => 0]);
        $this->assertSame('2026-10-21 05:30:00', $this->service()->normalize($overnight)['completed_at']);
        config(['app.timezone' => 'America/New_York']);
        $this->assertSame('2026-10-20 11:30:00', $this->service()->normalize($this->input)['started_at']);
        $this->assertValidation(fn () => $this->service()->normalize($fall), 'cannot store this repeated');
        $endsInRepeatedHour = array_replace($fall, ['start_time' => '00:30', 'duration_minutes' => 120]);
        $this->assertValidation(fn () => $this->service()->normalize($endsInRepeatedHour), 'cannot store this repeated');
    }

    public function test_future_times_invalid_breaks_and_foreign_templates_are_rejected(): void
    {
        $this->assertValidation(fn () => $this->service()->preview($this->admin, array_replace($this->input, ['date' => '2026-11-04'])), 'past');
        $this->assertValidation(fn () => $this->service()->normalize(array_replace($this->input, ['break_minutes' => 90])), 'less than');
        $other = FamilyAccount::create(['owner_user_id' => User::factory()->create(['role' => 'family'])->id, 'status' => 'active']);
        $request = $this->sourceRequest($other);
        $this->assertValidation(fn () => $this->service()->preview($this->admin, array_replace($this->input, ['source_request_id' => $request->id])), 'selected family');
        $this->caregiver->caregiverProfile->update(['status' => 'under_review']);
        $this->assertValidation(fn () => $this->service()->preview($this->admin, $this->input), 'active caregiver');
    }

    public function test_livewire_requires_review_and_confirmation_and_can_complete_and_reopen(): void
    {
        $component = Livewire::actingAs($this->admin)->test(RecordPastVisit::class)->set('form', $this->input);
        $component->set('acknowledged', true)->call('createVisit')->assertHasErrors('visit');
        $component->call('reviewVisit')->assertHasNoErrors()->assertSee('Check the completed visit')->assertSee('$38.75');
        $component->call('createVisit')->assertHasErrors('acknowledged');
        $component->set('acknowledged', true)->call('createVisit')->assertHasNoErrors();
        $visit = AdminRecordedVisit::sole();
        $component->assertRedirect(route('admin.requests.recorded-visit', $visit->id));
        Livewire::actingAs($this->admin)->test(RecordPastVisit::class, ['recordedVisit' => $visit->id])
            ->assertSee('payment settled')->call('refreshPayment')->assertHasNoErrors();
        $this->assertSame(1, CareBookingPaymentOperation::where('type', 'charge')->count());
    }

    public function test_editing_form_clears_review_and_selection_copies_care_details(): void
    {
        $source = $this->sourceRequest($this->family);
        CareRecipient::create(['care_request_id' => $source->id, 'full_name' => 'Previous Recipient', 'relationship_to_family' => 'Self']);
        $component = Livewire::actingAs($this->admin)->test(RecordPastVisit::class)
            ->call('selectFamily', $this->family->id)->assertSet('form.recipient_name', 'Previous Recipient')
            ->assertSet('form.source_request_id', $source->id)->call('selectCaregiver', $this->caregiver->id)
            ->set('form', $this->input)->call('reviewVisit')->assertHasNoErrors();
        $component->set('form.duration_minutes', 120)->assertSet('review', [])->assertSet('acknowledged', false);
    }

    public function test_production_refuses_test_configuration_and_revoked_admin_actions(): void
    {
        $this->app->instance('env', 'production');
        $this->assertValidation(fn () => $this->service()->preview($this->admin, $this->input), 'live Stripe');
        $this->assertDatabaseCount('care_bookings', 0);
        $this->app->instance('env', 'testing');
        $component = Livewire::actingAs($this->admin)->test(RecordPastVisit::class)->set('form', $this->input);
        $this->admin->forceFill(['access_revoked_at' => now()])->save();
        $component->call('reviewVisit')->assertForbidden();
    }

    public function test_booking_reassigned_to_another_payer_is_not_charged(): void
    {
        $visit = $this->createVisit();
        $other = User::factory()->create(['role' => 'family']);
        $visit->booking->forceFill(['family_user_id' => $other->id])->saveQuietly();
        $this->service()->processPayment($this->admin, $visit);
        $this->assertStringContainsString('needs review', $visit->fresh()->last_error);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_pending_card_authentication_is_not_reauthorized_by_retry(): void
    {
        $visit = $this->createVisit();
        app(\App\Services\Payments\BookingPaymentService::class)->authorizeForBooking($visit->booking, notify: false);
        $payment = CareBookingPayment::sole();
        $payment->update(['status' => 'authorization_required']);
        $intent = $payment->stripe_payment_intent_id;
        $this->service()->processPayment($this->admin, $visit);
        $this->assertStringContainsString('confirm their card', $visit->fresh()->last_error);
        $this->assertSame($intent, CareBookingPayment::sole()->stripe_payment_intent_id);
        $this->assertSame(0, CareBookingPaymentOperation::where('type', 'charge')->count());
    }

    public function test_a_concurrent_payment_attempt_is_refused_without_capturing(): void
    {
        $visit = $this->createVisit();
        $lock = \Illuminate\Support\Facades\Cache::lock('admin-recorded-visit:'.$visit->id, 600);
        $this->assertTrue($lock->get());
        try {
            $this->assertValidation(fn () => $this->service()->processPayment($this->admin, $visit), 'already processing');
            $this->assertDatabaseCount('care_booking_payments', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_family_pricing_agreement_is_used_in_review_and_capture(): void
    {
        $this->input['date'] = '2026-10-19';
        $source = $this->createVisit();
        $this->input['date'] = '2026-10-20';
        \App\Models\CarePricingAgreement::create(['family_account_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'all_caregivers' => true, 'active' => true,
            'family_care_rate_cents' => 2000, 'family_processing_fee_rate_cents' => 0,
            'caregiver_gross_rate_cents' => 1800, 'caregiver_fee_policy' => 'platform_pays_processing',
            'source_booking_id' => $source->care_booking_id, 'reason' => 'Agreed family pricing', 'created_by_user_id' => $this->admin->id]);
        $preview = $this->service()->preview($this->admin, $this->input);
        $this->assertSame(2500, $preview['quote']['total_charge_cents']);
        $visit = $this->createVisit();
        $this->service()->processPayment($this->admin, $visit);
        $this->assertNull($visit->fresh()->last_error);
        $this->assertSame(2500, CareBookingPayment::sole()->amount_captured_cents);
    }

    private function service(): AdminRecordedVisitService
    {
        return app(AdminRecordedVisitService::class);
    }

    private function createVisit(): AdminRecordedVisit
    {
        $preview = $this->service()->preview($this->admin, $this->input);

        return $this->service()->create($this->admin, $this->input, (string) Str::uuid(), $preview['fingerprint']);
    }

    private function assertValidation(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected validation to reject this operation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, implode(' ', $exception->validator->errors()->all()));
        }
    }

    private function sourceRequest(FamilyAccount $family): CareRequest
    {
        return CareRequest::withoutEvents(fn () => CareRequest::create(['family_user_id' => $family->owner_user_id,
            'family_account_id' => $family->id, 'title' => 'Previous care', 'status' => 'filled', 'request_type' => 'one_time',
            'address_line1' => '456 Previous Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609']));
    }
}
