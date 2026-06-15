<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverShiftsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_open_my_shifts_and_reach_visit_screen(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();

        $response = $this->actingAs($caregiver)->get('/caregiver/shifts');

        $response->assertOk();
        $response->assertSee('My visits');
        $response->assertSee('Start visit');
        $response->assertSee($request->title);
        $response->assertSee('/care-requests/'.$request->id.'/apply', false);
    }

    public function test_family_cannot_access_caregiver_shifts_page(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/caregiver/shifts');

        $response->assertForbidden();
    }

    public function test_caregiver_dashboard_shows_shift_quick_access_cta(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('My visits');
        $response->assertSee('Visit quick access');
        $response->assertSee($request->title);
        $response->assertSee('/care-requests/'.$request->id.'/apply', false);
    }

    public function test_caregiver_can_see_family_review_feedback_on_shift_page(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();
        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        $booking->update([
            'status' => CareBooking::STATUS_REVIEWED,
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subHour(),
            'worked_minutes' => 180,
            'family_confirmed_at' => now()->subMinutes(45),
        ]);

        CareReview::query()->create([
            'care_booking_id' => $booking->id,
            'care_request_id' => $request->id,
            'reviewer_user_id' => $booking->family_user_id,
            'reviewee_user_id' => $caregiver->id,
            'rating' => 5,
            'comment' => 'Great caregiver, very punctual.',
        ]);

        $response = $this->actingAs($caregiver)->get('/care-requests/'.$request->id.'/apply');

        $response->assertOk();
        $response->assertSee('Visit recap');
        $response->assertSee('Completed visit');
        $response->assertSee('Visit is fully closed');
        $response->assertSee('This visit is closed.');
        $response->assertSee('Family review is complete. Your visit record is saved here.');
        $response->assertSee('Family feedback on this visit');
        $response->assertSee('Great caregiver, very punctual.');

        $content = $response->getContent();
        $recapPosition = strpos($content, 'Visit recap');
        $schedulePosition = strpos($content, 'Scheduled window');
        $this->assertNotFalse($recapPosition);
        $this->assertNotFalse($schedulePosition);
        $this->assertLessThan($schedulePosition, $recapPosition);
    }

    public function test_live_visit_surface_hides_payout_setup_distraction(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();
        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        $this->actingAs($caregiver)
            ->get('/care-requests/'.$request->id.'/apply')
            ->assertOk()
            ->assertSee('Payout setup is incomplete.');

        $booking->update([
            'status' => CareBooking::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($caregiver)
            ->get('/care-requests/'.$request->id.'/apply')
            ->assertOk()
            ->assertSee('You are checked in.')
            ->assertSee('Stay focused on Margaret Johnson.')
            ->assertSee('Live timer')
            ->assertSee('Time with client')
            ->assertSee('Pause visit')
            ->assertSee('End visit')
            ->assertSee('Care location')
            ->assertDontSee('Payout setup is incomplete.');
    }

    public function test_booked_request_workspace_is_visit_first_not_application_first(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();

        $this->actingAs($caregiver)
            ->get('/care-requests/'.$request->id.'/apply')
            ->assertOk()
            ->assertSee('Upcoming visit')
            ->assertSee('Get ready for Margaret Johnson')
            ->assertSee('Right now')
            ->assertSee('Check in when you arrive.')
            ->assertSee('Next visit for Margaret Johnson')
            ->assertSee('Visit record and notes')
            ->assertSee('Map and visit record')
            ->assertSee('Care details')
            ->assertSee('Support')
            ->assertDontSee('setActiveTab(\'application\')', false);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'shift')
            ->call('setActiveTab', 'application')
            ->assertSet('activeTab', 'shift');
    }

    /**
     * @return array{User,CareRequest}
     */
    private function seedScheduledShift(): array
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Afternoon companionship shift',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(14, 0),
            'requested_end_at' => now()->addDay()->setTime(18, 0),
            'address_line1' => '500 Elm Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Margaret Johnson',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 29.00,
            'cover_note' => 'I am available and can provide companionship support.',
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(14, 0),
            'scheduled_end_at' => now()->addDay()->setTime(18, 0),
            'family_terms_accepted_at' => now(),
            'caregiver_terms_accepted_at' => now(),
        ]);

        return [$caregiver, $request];
    }
}
