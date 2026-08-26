<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\WorkInbox;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareTask;
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

        $invitedRequest = $this->createOneTimeRequest($family->id, 'Invited morning support', CareRequest::STATUS_OPEN, [
            'recipient_is_requester' => true,
            'full_name' => $family->name,
            'relationship_to_family' => 'Self',
        ]);
        $invitation = CareRequestInvitation::query()->create([
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
        $response->assertSee('You have 1 request to answer.');
        $response->assertSee('Right now');
        $response->assertDontSee('Visible visit value');
        $response->assertDontSee('Ready-to-respond value');
        $response->assertDontSee('Stay on top of new opportunities.');
        $response->assertSee('New requests');
        $response->assertSee($invitedRequest->title);
        $response->assertDontSee($appliedRequest->title);
        $response->assertDontSee($hiredRequest->title);
        $response->assertDontSee($recommendedRequest->title);
        $response->assertSee('Accept invite');
        $response->assertSee(route('caregiver.invitations.accept', $invitation), false);
        $response->assertSee(route('caregiver.invitations.decline', $invitation), false);
        $response->assertSee('Requester receives care');
        $response->assertSee($family->name);
        $response->assertSee('3h visit');
        $response->assertSee('@ $27.00/hr*');
        $response->assertSee('$81.00');
        $response->assertSee('Gross earnings before Stripe processing fees.');

        Livewire::actingAs($caregiver)
            ->test(WorkInbox::class)
            ->assertSet('scope', 'needs_response')
            ->assertSee($invitedRequest->title)
            ->assertDontSee($appliedRequest->title)
            ->set('scope', 'all')
            ->assertSee($invitedRequest->title)
            ->assertSee($appliedRequest->title)
            ->assertSee($hiredRequest->title)
            ->assertSee($recommendedRequest->title)
            ->assertSee('Open application')
            ->assertSee('Start visit')
            ->assertSee('Apply now')
            ->assertSee('3h @ $27.00/hr*')
            ->assertSee('$81.00 gross earnings')
            ->set('scope', 'new_requests')
            ->assertSee($recommendedRequest->title)
            ->assertDontSee($appliedRequest->title);
    }

    public function test_work_inbox_defaults_to_new_requests_when_no_replies_are_waiting(): void
    {
        $family = User::factory()->create(['role' => 'family', 'city' => 'Raleigh', 'state' => 'NC']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'Fresh request to consider');

        Livewire::actingAs($caregiver)
            ->test(WorkInbox::class)
            ->assertSet('scope', 'new_requests')
            ->assertSee('New requests are available.')
            ->assertSee($request->title)
            ->assertSee('Apply now');
    }

    public function test_caregiver_open_request_detail_is_decision_first_before_applying(): void
    {
        $family = User::factory()->create(['role' => 'family', 'city' => 'Raleigh', 'state' => 'NC']);
        $caregiver = $this->createReadyCaregiver();
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $request = $this->createOneTimeRequest($family->id, 'Simple companionship visit');
        $request->update([
            'scope_of_work' => 'Keep the visit calm, help with conversation, and support light movement.',
            'time_expectations' => 'Please arrive on time.',
        ]);
        $request->tasks()->sync([$task->id => ['task_note' => null]]);

        $this->actingAs($caregiver)
            ->get(route('care-requests.apply', $request->id))
            ->assertOk()
            ->assertSee('Interested in this visit?')
            ->assertSee('I can do this visit')
            ->assertSee('Add a short note')
            ->assertSee('This can be left blank.')
            ->assertSee('Send with note')
            ->assertSee('Estimated earnings')
            ->assertSee('Companionship')
            ->assertDontSee('Request context')
            ->assertDontSee('caregiver-apply-tabs', false);
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
        $component->assertRedirect(route('care-requests.apply', $request->id, false));

        $this->actingAs($caregiver)
            ->get(route('care-requests.apply', $request->id))
            ->assertOk()
            ->assertSee('Waiting for family')
            ->assertSee('You accepted the invitation.')
            ->assertSee('No action needed.')
            ->assertSee('What happens next')
            ->assertSee('If hired')
            ->assertSee('Open chat')
            ->assertSee('Review request details')
            ->assertSee('Tasks, address, and notes')
            ->assertSee('Your reply')
            ->assertDontSee('Update application')
            ->assertDontSee('caregiver-apply-tabs', false)
            ->assertDontSee('No active visit yet')
            ->assertDontSee('setActiveTab(\'support\')', false);
    }

    public function test_caregiver_can_apply_without_cover_note(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'No cover note required');

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('cover_note', '')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('care-requests.index', absolute: false));

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'cover_note' => null,
        ]);
    }

    public function test_caregiver_can_accept_invitation_from_plain_post_fallback(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'Invitation form fallback');

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Could you take this shift?',
            'expires_at' => now()->addHours(10),
        ]);

        $response = $this->actingAs($caregiver)
            ->post(route('caregiver.invitations.accept', $invitation->id));

        $application = CareRequestApplication::query()
            ->where('care_request_id', $request->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->firstOrFail();

        $conversation = CareRequestConversation::query()
            ->where('care_request_id', $request->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->firstOrFail();

        $response->assertRedirect(route('care-requests.apply', $request->id));
        $this->assertSame(CareRequestApplication::STATUS_SHORTLISTED, $application->status);
        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_ACCEPTED,
            'care_request_application_id' => $application->id,
        ]);
    }

    public function test_invite_response_metrics_never_store_negative_minutes(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOneTimeRequest($family->id, 'Future timestamp invite');

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Could you take this shift?',
            'expires_at' => now()->addHours(10),
            'created_at' => now()->addMinutes(30),
            'updated_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($caregiver)
            ->post(route('caregiver.invitations.accept', $invitation->id))
            ->assertRedirect();

        $this->assertSame(0, (int) $caregiver->caregiverProfile()->value('avg_invite_response_minutes'));
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

    public function test_caregiver_browse_requests_surfaces_new_request_context(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $task = CareTask::query()->create(['name' => 'Morning routine']);
        $request = $this->createOneTimeRequest($family->id, 'Detailed morning support');
        $request->update([
            'scope_of_work' => 'Help with breakfast setup, companionship, and safe movement around the home.',
            'time_expectations' => 'Please arrive a few minutes early and keep the morning routine calm.',
            'preferred_response_hours' => 8,
        ]);
        $request->tasks()->sync([$task->id => ['task_note' => 'Use the walker for hallway movement.']]);

        $response = $this->actingAs($caregiver)->get('/care-requests');

        $response->assertOk();
        $response->assertSee('New to you');
        $response->assertSee($request->title);
        $response->assertSee('Help with breakfast setup');
        $response->assertSee('8h response target');
        $response->assertSee('3h at $27.00/hr* - about $81.00 gross');
        $response->assertSee('Gross earnings before Stripe processing fees.');
        $response->assertSee('Use the walker for hallway movement.');
    }

    private function createOneTimeRequest(
        int $familyUserId,
        string $title,
        string $status = CareRequest::STATUS_OPEN,
        array $recipientOverrides = []
    ): CareRequest
    {
        $request = CareRequest::query()->create([
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

        $request->recipient()->create(array_merge([
            'recipient_is_requester' => false,
            'full_name' => 'Care recipient',
            'relationship_to_family' => 'Mother',
        ], $recipientOverrides));

        return $request;
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
