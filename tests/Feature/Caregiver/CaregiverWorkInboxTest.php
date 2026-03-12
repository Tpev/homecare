<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\WorkInbox;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverWorkInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_open_work_inbox_and_see_offer_states(): void
    {
        $family = User::factory()->create(['role' => 'family', 'city' => 'Raleigh', 'state' => 'NC']);
        $caregiver = $this->createReadyCaregiver();

        $invitedRequest = $this->createOneTimeRequest($family->id, 'Invited morning support');
        CareRequestInvitation::query()->create([
            'care_request_id' => $invitedRequest->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Can you help tomorrow morning?',
            'expires_at' => now()->addHours(8),
        ]);

        $appliedRequest = $this->createOneTimeRequest($family->id, 'Applied evening shift');
        CareRequestApplication::query()->create([
            'care_request_id' => $appliedRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27.00,
            'cover_note' => 'I am available for this shift.',
        ]);

        $hiredRequest = $this->createOneTimeRequest($family->id, 'Scheduled companion visit', CareRequest::STATUS_FILLED);
        $hiredApplication = CareRequestApplication::query()->create([
            'care_request_id' => $hiredRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 28.00,
            'cover_note' => 'I can cover this schedule.',
        ]);
        CareBooking::query()->create([
            'care_request_id' => $hiredRequest->id,
            'care_request_application_id' => $hiredApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(9, 0),
            'scheduled_end_at' => now()->addDay()->setTime(12, 0),
            'family_terms_accepted_at' => now(),
            'caregiver_terms_accepted_at' => now(),
        ]);

        $recommendedRequest = $this->createOneTimeRequest($family->id, 'Recommended mobility support');

        $response = $this->actingAs($caregiver)->get('/caregiver/work-inbox');

        $response->assertOk();
        $response->assertSee('Caregiver Work Inbox');
        $response->assertSee($invitedRequest->title);
        $response->assertSee($appliedRequest->title);
        $response->assertSee($hiredRequest->title);
        $response->assertSee($recommendedRequest->title);
        $response->assertSee('Accept invite');
        $response->assertSee('Open application');
        $response->assertSee('Start shift');
        $response->assertSee('Apply now');
        $response->assertSee('3h @ $27.00/hr');
        $response->assertSee('$81.00 total shift');
    }

    public function test_caregiver_can_accept_invitation_from_work_inbox(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'Invitation acceptance flow');

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Could you take this shift?',
            'expires_at' => now()->addHours(10),
        ]);

        $component = Livewire::actingAs($caregiver)
            ->test(WorkInbox::class)
            ->call('acceptInvitation', $invitation->id);

        $application = CareRequestApplication::query()
            ->where('care_request_id', $request->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->first();

        $this->assertNotNull($application);
        $this->assertSame(CareRequestApplication::STATUS_SHORTLISTED, $application->status);
        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_ACCEPTED,
            'care_request_application_id' => $application->id,
        ]);

        $conversation = CareRequestConversation::query()
            ->where('care_request_id', $request->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->first();

        $this->assertNotNull($conversation);
        $component->assertRedirect(route('messages.show', $conversation->id, false));
    }

    public function test_family_user_cannot_access_caregiver_work_inbox(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/caregiver/work-inbox');
        $response->assertForbidden();
    }

    public function test_caregiver_dashboard_shows_work_inbox_preview(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'Dashboard preview invite');

        CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->addHours(12),
        ]);

        $response = $this->actingAs($caregiver)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Work inbox');
        $response->assertSee($request->title);
        $response->assertSee('Open full inbox');
    }

    private function createOneTimeRequest(int $familyUserId, string $title, string $status = CareRequest::STATUS_OPEN): CareRequest
    {
        return CareRequest::query()->create([
            'family_user_id' => $familyUserId,
            'title' => $title,
            'status' => $status,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '101 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
    }

    private function createReadyCaregiver(): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'work-inbox-caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced and reliable caregiver. ', 4),
            'platform_hourly_rate' => 28.00,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '13:00',
        ]);

        return $caregiver;
    }
}
