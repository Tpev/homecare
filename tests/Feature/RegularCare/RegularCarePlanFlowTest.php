<?php

namespace Tests\Feature\RegularCare;

use App\Livewire\Caregiver\RegularClients;
use App\Livewire\Family\RegularCareComposer;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegularCarePlanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_offer_and_caregiver_acceptance_generates_real_booking_with_payment(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();

        Livewire::actingAs($family)
            ->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->set('scheduleDays', ['1', '3'])
            ->set('scheduleStartTime', '09:00')
            ->set('scheduleEndTime', '12:00')
            ->set('startsOn', now()->next(1)->toDateString())
            ->call('sendOffer');

        $plan = CarePlan::query()->firstOrFail();
        $this->assertSame(CarePlan::STATUS_PENDING_CAREGIVER, $plan->status);
        $this->assertSame(30.00, (float) $plan->hourly_rate);

        $this->actingAs($caregiver)
            ->get(route('caregiver.regular-clients.index'))
            ->assertOk()
            ->assertSee('Direct regular-care offers')
            ->assertSee('Family member receives care')
            ->assertSee('Accept schedule');

        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->call('acceptOffer', $plan->id);

        $plan->refresh();
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);

        $generatedRequest = CareRequest::query()
            ->where('care_plan_id', $plan->id)
            ->where('id', '!=', $request->id)
            ->firstOrFail();

        $booking = CareBooking::query()
            ->where('care_plan_id', $plan->id)
            ->where('care_request_id', $generatedRequest->id)
            ->firstOrFail();

        $this->assertSame(CareRequest::STATUS_FILLED, $generatedRequest->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertSame($booking->id, $plan->fresh()->next_booking_id);

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
        ]);
    }

    public function test_caregiver_can_counter_and_family_accepts_counter_schedule(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $caregiver, $request] = $this->seedCompletedCareRelationship();

        Livewire::actingAs($family)
            ->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->set('scheduleDays', ['1'])
            ->set('scheduleStartTime', '09:00')
            ->set('scheduleEndTime', '12:00')
            ->set('startsOn', now()->next(1)->toDateString())
            ->call('sendOffer');

        $plan = CarePlan::query()->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(RegularClients::class)
            ->call('openCounter', $plan->id)
            ->set('counterScheduleDays', ['2'])
            ->set('counterStartTime', '13:00')
            ->set('counterEndTime', '16:00')
            ->set('counterStartsOn', now()->next(2)->toDateString())
            ->set('counterNote', 'Afternoons work better for my route.')
            ->call('sendCounter');

        $plan->refresh();
        $this->assertSame(CarePlan::STATUS_COUNTERED, $plan->status);
        $this->assertSame(['2'], array_map('strval', $plan->counter_schedule_days));

        Livewire::actingAs($family)
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('acceptCounter');

        $plan->refresh();
        $booking = CareBooking::query()->where('care_plan_id', $plan->id)->firstOrFail();

        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertSame([2], $plan->schedule_days);
        $this->assertSame('13:00:00', $plan->schedule_start_time);
        $this->assertSame(13, (int) $booking->scheduled_start_at->format('H'));
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
    }

    /**
     * @return array{User,User,CareRequest}
     */
    private function seedCompletedCareRelationship(): array
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $caregiver = $this->createReadyCaregiver();
        $task = CareTask::query()->create(['name' => 'Companionship']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning care for Don',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'scope_of_work' => 'Medication reminders, breakfast, and companionship.',
            'requested_start_at' => now()->subWeek()->setTime(9, 0),
            'requested_end_at' => now()->subWeek()->setTime(12, 0),
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Don',
            'relationship_to_family' => 'Father',
            'care_notes' => 'Don prefers tea before breakfast.',
        ]);
        $request->tasks()->sync([$task->id => ['task_note' => 'Keep Don company during breakfast.']]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 28.00,
            'cover_note' => 'Happy to support Don.',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subWeek()->setTime(9, 0),
            'scheduled_end_at' => now()->subWeek()->setTime(12, 0),
            'completed_at' => now()->subWeek()->setTime(12, 0),
            'reviewed_at' => now()->subDays(6),
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);

        $booking->forceFill([
            'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot(
                $request->fresh(['recipient', 'tasks']),
                $application
            ),
        ])->save();

        return [$family, $caregiver, $request->fresh(['recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile'])];
    }

    private function createReadyCaregiver(): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Caroline Care',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'caroline-care-'.$caregiver->id,
            'bio' => str_repeat('Reliable regular care specialist. ', 4),
            'platform_hourly_rate' => 28.00,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 12,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $skill = Skill::query()->create(['name' => 'Regular companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        return $caregiver;
    }
}
