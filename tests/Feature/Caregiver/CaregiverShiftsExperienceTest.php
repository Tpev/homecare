<?php

namespace Tests\Feature\Caregiver;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverShiftsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_open_my_shifts_and_reach_shift_command_center(): void
    {
        [$caregiver, $request] = $this->seedScheduledShift();

        $response = $this->actingAs($caregiver)->get('/caregiver/shifts');

        $response->assertOk();
        $response->assertSee('My shifts');
        $response->assertSee('Start shift');
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
        $response->assertSee('My shifts');
        $response->assertSee('Shift quick access');
        $response->assertSee($request->title);
        $response->assertSee('/care-requests/'.$request->id.'/apply', false);
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

