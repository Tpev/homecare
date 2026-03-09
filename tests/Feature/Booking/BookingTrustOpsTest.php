<?php

namespace Tests\Feature\Booking;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingTaskCheck;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingTrustOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hire_creates_agreement_snapshot_task_checks_and_audit_event(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHireScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->firstOrFail();

        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertNotNull($booking->agreement_snapshot);
        $this->assertNotNull($booking->family_terms_accepted_at);
        $this->assertDatabaseCount('care_booking_task_checks', 2);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'booking_hired',
        ]);
    }

    public function test_caregiver_can_accept_agreement_check_in_check_out_and_complete_tasks(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHireScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->firstOrFail();
        $taskCheck = CareBookingTaskCheck::query()->where('care_booking_id', $booking->id)->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->call('acceptBookingAgreement')
            ->set('checkInNote', 'Arrived and greeted family.')
            ->set('checkInLat', 35.7796)
            ->set('checkInLng', -78.6382)
            ->call('startBooking')
            ->call('toggleTaskCheck', $taskCheck->id)
            ->set('checkOutNote', 'Shift completed successfully.')
            ->call('completeBooking');

        $booking->refresh();

        $this->assertNotNull($booking->caregiver_terms_accepted_at);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->status);
        $this->assertNotNull($booking->started_at);
        $this->assertNotNull($booking->completed_at);
        $this->assertNotNull($booking->timesheet_submitted_at);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'checked_in_by_caregiver',
        ]);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'checked_out_by_caregiver',
        ]);
        $this->assertDatabaseHas('care_booking_task_checks', [
            'id' => $taskCheck->id,
            'is_completed' => 1,
        ]);
    }

    public function test_family_can_confirm_timesheet_open_dispute_and_report_incident(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHireScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('confirmationNote', 'Timesheet looks correct.')
            ->call('completeBooking')
            ->set('disputeReason', 'Time logged does not match arrival time.')
            ->call('openDispute')
            ->set('incidentTitle', 'Minor safety issue')
            ->set('incidentDescription', 'Door left unlocked after departure.')
            ->set('incidentSeverity', 'medium')
            ->call('reportIncident');

        $booking->refresh();

        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertSame(CareBooking::STATUS_DISPUTED, $booking->status);
        $this->assertSame('open', $booking->dispute_status);

        $this->assertDatabaseHas('support_tickets', [
            'care_booking_id' => $booking->id,
            'category' => 'dispute',
        ]);
        $this->assertDatabaseHas('care_booking_incidents', [
            'care_booking_id' => $booking->id,
            'severity' => 'medium',
            'title' => 'Minor safety issue',
        ]);
    }

    public function test_family_can_mark_caregiver_no_show_after_grace_period(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'No show scenario',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subHour(),
            'requested_end_at' => now()->addHour(),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient',
            'relationship_to_family' => 'Parent',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 28.00,
            'cover_note' => 'Ready for shift.',
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now()->addHour(),
            'family_terms_accepted_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('markNoShow');

        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'status' => CareBooking::STATUS_CANCELLED,
            'no_show_flag' => 1,
        ]);
        $this->assertDatabaseHas('care_booking_events', [
            'event_type' => 'caregiver_no_show_marked',
        ]);
    }

    /**
     * @return array{User,User,CareRequest,CareRequestApplication}
     */
    private function seedHireScenario(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'hourly_rate' => 30,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $taskA = CareTask::query()->create(['name' => 'Companionship']);
        $taskB = CareTask::query()->create(['name' => 'Meal preparation']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning support',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(13, 0),
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'scope_of_work' => 'Companionship and meal prep',
            'time_expectations' => 'Arrive on time',
            'home_access_notes' => 'Use side door',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Name',
            'relationship_to_family' => 'Mother',
        ]);
        $request->tasks()->sync([
            $taskA->id => ['task_note' => 'Conversation and supervision'],
            $taskB->id => ['task_note' => 'Prepare lunch'],
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 29.50,
            'cover_note' => str_repeat('I can support this shift. ', 3),
        ]);

        return [$family, $caregiver, $request, $application];
    }
}

