<?php

namespace Tests\Feature\Family;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DelayedRecurringHireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.caregiver_prelaunch_mode' => false, 'services.stripe.bypass' => true]);
        Notification::fake();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-02 12:00:00', config('app.timezone')));
    }

    public static function hireDates(): array
    {
        return [
            'weeks after posting' => ['2026-10-02 12:00:00', '2026-09-23', null, '2026-10-05 09:00:00'],
            'before todays visit' => ['2026-10-02 08:00:00', '2026-09-23', null, '2026-10-02 09:00:00'],
            'at todays start' => ['2026-10-02 09:00:00', '2026-09-23', null, '2026-10-05 09:00:00'],
            'during todays visit' => ['2026-10-02 10:00:00', '2026-09-23', null, '2026-10-05 09:00:00'],
            'bounded schedule' => ['2026-10-02 12:00:00', '2026-09-23', '2026-10-07', '2026-10-05 09:00:00'],
            'future start on a non care day' => ['2026-10-02 12:00:00', '2026-10-06', null, '2026-10-07 10:00:00'],
            'beyond the generation window' => ['2026-10-02 12:00:00', '2026-12-01', null, '2026-12-02 10:00:00'],
            'across daylight saving change' => ['2026-10-31 12:00:00', '2026-09-23', null, '2026-11-02 09:00:00'],
        ];
    }

    #[DataProvider('hireDates')]
    public function test_hiring_an_invited_caregiver_uses_the_previewed_next_future_visit(string $now, string $startsOn, ?string $endsOn, string $expectedStart): void
    {
        $this->travelTo(Carbon::parse($now, config('app.timezone')));
        [$family, $caregiver, $request, $application] = $this->fixture($startsOn, $endsOn);
        $originalSlots = $request->recurringScheduleSlots();
        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->call('reviewHire', $application->id)
            ->assertViewHas('hireDecisionStart', fn ($start) => $start?->format('Y-m-d H:i:s') === $expectedStart);
        $previewEnd = $component->viewData('hireDecisionEnd')->copy();
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertSame($startsOn, $request->fresh()->recurring_starts_on->toDateString());

        $component->call('confirmReviewedHire')->assertHasNoErrors()->assertSet('reviewingApplicationId', null);

        $request->refresh();
        $plan = CarePlan::findOrFail($request->care_plan_id);
        $booking = $request->booking;
        $component->assertRedirect(route('family.care.show', $plan->id));
        $this->assertSame(CareRequest::STATUS_FILLED, $request->status);
        $this->assertSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertSame($expectedStart, $booking->scheduled_start_at->format('Y-m-d H:i:s'));
        $this->assertTrue($previewEnd->equalTo($booking->scheduled_end_at));
        $this->assertSame(substr($expectedStart, 0, 10), $plan->starts_on->toDateString());
        $this->assertSame($endsOn, $plan->ends_on?->toDateString());
        $this->assertSame($originalSlots, $plan->weeklyScheduleSlots());
        $this->assertSame(0, $plan->generatedBookings()->where('scheduled_start_at', '<=', now())->count());
        if ($endsOn) {
            $this->assertSame(0, $plan->generatedBookings()->where('scheduled_start_at', '>', Carbon::parse($endsOn)->endOfDay())->count());
        }
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id, 'event_key' => MarketplaceEvent::CAREGIVER_HIRED,
            'channel' => 'email', 'status' => 'sent',
        ]);
    }

    public static function exhaustedSchedules(): array
    {
        return [
            'already ended' => ['2026-09-30'],
            'last visit already started today' => ['2026-10-02'],
            'ends before the next selected weekday' => ['2026-10-04'],
        ];
    }

    #[DataProvider('exhaustedSchedules')]
    public function test_an_exhausted_schedule_keeps_confirmation_and_explains_the_failure_without_hiring(string $endsOn): void
    {
        [$family, $caregiver, $request, $application] = $this->fixture('2026-09-23', $endsOn);
        $before = $request->getRawOriginal();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)
            ->call('confirmReviewedHire')
            ->assertHasErrors('hire')
            ->assertSet('reviewingApplicationId', $application->id)
            ->assertSeeInOrder(['Care with Invited Caregiver', 'This recurring schedule has no future visits.', 'Confirm hire'])
            ->assertNoRedirect();

        $this->assertSame($before, $request->fresh()->getRawOriginal());
        $this->assertUnhired($request, $application);
        Notification::assertNothingSent();
    }

    public function test_other_schedule_errors_are_visible_and_retry_can_succeed_after_correction(): void
    {
        [$family, $caregiver, $request, $application] = $this->fixture('2026-09-23');
        $slots = $request->recurring_schedule;
        $request->update(['recurring_days' => [], 'recurring_schedule' => []]);
        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)
            ->call('confirmReviewedHire')
            ->assertHasErrors('hire')
            ->assertSet('reviewingApplicationId', $application->id)
            ->assertSeeInOrder(['Care with Invited Caregiver', 'Choose at least one care day.', 'Confirm hire']);
        $this->assertUnhired($request, $application);

        $request->update(['recurring_days' => [1, 3, 5], 'recurring_schedule' => $slots]);
        $component->call('confirmReviewedHire')->assertHasNoErrors()->assertSet('reviewingApplicationId', null);
        $this->assertSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
    }

    public static function requestTypes(): array
    {
        return ['recurring' => [CareRequest::TYPE_RECURRING], 'one time' => [CareRequest::TYPE_ONE_TIME]];
    }

    #[DataProvider('requestTypes')]
    public function test_payment_failure_keeps_the_selected_caregiver_and_displays_the_reason(string $type): void
    {
        [$family, $caregiver, $request, $application] = $this->fixture('2026-10-05');
        $request->update(['request_type' => $type, 'requested_start_at' => now()->addDays(3)->setTime(9, 0), 'requested_end_at' => now()->addDays(3)->setTime(12, 0)]);
        $message = 'Your card could not be authorized. Please update your payment method.';
        if ($type === CareRequest::TYPE_RECURRING) {
            $this->mock(CarePlanService::class)->shouldReceive('activateFromRecurringRequest')->once()->andThrow(new PaymentException($message));
        } else {
            $this->mock(BookingPaymentService::class)->shouldReceive('prepareOnSessionAuthorization')->once()->andThrow(new PaymentException($message));
        }

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)
            ->call('confirmReviewedHire')
            ->assertHasErrors('hire')
            ->assertSet('reviewingApplicationId', $application->id)
            ->assertSeeInOrder(['Care with Invited Caregiver', $message, 'Confirm hire']);
        $this->assertUnhired($request, $application);
    }

    private function assertUnhired(CareRequest $request, CareRequestApplication $application): void
    {
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_SHORTLISTED, $application->fresh()->status);
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    private function fixture(string $startsOn, ?string $endsOn = null): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Invited Caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 30]);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Recurring companionship',
            'request_type' => CareRequest::TYPE_RECURRING, 'status' => CareRequest::STATUS_OPEN, 'is_private' => true,
            'address_line1' => '123 Test Lane', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'recurring_starts_on' => $startsOn, 'recurring_ends_on' => $endsOn,
            'recurring_days' => [1, 3, 5], 'recurring_start_time' => '09:00', 'recurring_end_time' => '12:00',
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day' => 3, 'start_time' => '10:00', 'end_time' => '14:00'],
                ['day' => 5, 'start_time' => '09:00', 'end_time' => '12:00'],
            ],
        ]);
        $request->recipient()->create(['full_name' => 'Test Recipient', 'relationship_to_family' => 'Mother']);
        $application = $request->applications()->create([
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 30,
        ]);
        $request->invitations()->create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'care_request_application_id' => $application->id, 'status' => CareRequestInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        return [$family, $caregiver, $request->fresh(), $application];
    }
}
