<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Caregiver\ShowCaregiver;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_invite_caregiver_from_profile(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'caregiver-profile-1',
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 28,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning companionship support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '500 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        Livewire::actingAs($family)
            ->test(ShowCaregiver::class, ['slug' => $profile->slug])
            ->call('openInviteModal')
            ->set('selectedCareRequestId', $request->id)
            ->set('inviteMessage', 'We think you are a strong fit for this request.')
            ->call('sendInvite');

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'We think you are a strong fit for this request.',
        ]);
    }

    public function test_only_open_request_is_selected_server_side_before_invitation_is_sent(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();
        $profile = CaregiverProfile::query()->where('user_id', $caregiver->id)->firstOrFail();
        $expectedLabel = $request->requested_start_at->format('D, M j · g:i A')
            .'–'.$request->requested_end_at->format('g:i A').' — '.$request->title;

        Livewire::actingAs($family)
            ->test(ShowCaregiver::class, ['slug' => $profile->slug])
            ->call('openInviteModal')
            ->assertSet('familyRequestOptions.0.label', $expectedLabel)
            ->assertSet('selectedCareRequestId', $request->id)
            ->call('sendInvite')
            ->assertHasNoErrors('selectedCareRequestId');

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
        ]);
    }

    public function test_request_with_hired_caregiver_is_not_offered_for_another_invitation(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();
        $profile = CaregiverProfile::query()->where('user_id', $caregiver->id)->firstOrFail();
        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 27,
        ]);

        Livewire::actingAs($family)
            ->test(ShowCaregiver::class, ['slug' => $profile->slug])
            ->call('openInviteModal')
            ->assertSet('familyRequestOptions', [])
            ->assertSet('selectedCareRequestId', null)
            ->assertSee('No care request is available for a new invitation.')
            ->assertSee('Click here to create a new request.');

        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_past_one_time_request_is_not_offered_even_when_its_status_is_open(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();
        $profile = CaregiverProfile::query()->where('user_id', $caregiver->id)->firstOrFail();
        $request->update([
            'requested_start_at' => now()->subDays(2)->setTime(12, 0),
            'requested_end_at' => now()->subDays(2)->setTime(19, 0),
        ]);

        Livewire::withQueryParams(['careRequest' => $request->id])
            ->actingAs($family)
            ->test(ShowCaregiver::class, ['slug' => $profile->slug])
            ->assertSet('contextCareRequestId', null)
            ->call('openInviteModal')
            ->assertSet('familyRequestOptions', [])
            ->assertSee('Click here to create a new request.')
            ->set('selectedCareRequestId', $request->id)
            ->call('sendInvite')
            ->assertHasErrors('selectedCareRequestId');

        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_caregiver_can_accept_invitation_and_conversation_is_created(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Can you support this request?',
            'expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($caregiver)
            ->get(route('caregiver.invitations.index'))
            ->assertOk()
            ->assertSee(route('caregiver.invitations.accept', $invitation), false)
            ->assertSee(route('caregiver.invitations.decline', $invitation), false);

        $component = Livewire::actingAs($caregiver)
            ->test(InvitationsIndex::class)
            ->call('accept', $invitation->id);

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

        $this->assertDatabaseHas('care_request_conversations', [
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'care_request_application_id' => $application->id,
        ]);

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
            ->assertDontSee('caregiver-apply-tabs', false)
            ->assertDontSee('No active visit yet')
            ->assertDontSee('setActiveTab(\'support\')', false);
    }

    public function test_caregiver_can_apply_without_cover_note(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('cover_note', '')
            ->call('submit')
            ->assertHasNoErrors('cover_note');

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'cover_note' => null,
        ]);
    }

    public function test_caregiver_can_decline_invitation(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        Livewire::actingAs($caregiver)
            ->test(InvitationsIndex::class)
            ->call('decline', $invitation->id);

        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_DECLINED,
        ]);
    }

    public function test_caregiver_cannot_check_out_before_check_in(): void
    {
        [$family, $caregiver, $request] = $this->seedContext();

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 29,
            'cover_note' => 'Ready to support.',
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(9, 0),
            'scheduled_end_at' => now()->addDay()->setTime(12, 0),
            'caregiver_terms_accepted_at' => now(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'status' => CareBooking::STATUS_SCHEDULED,
        ]);
    }

    private function seedContext(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 26,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $profile = CaregiverProfile::query()->where('user_id', $caregiver->id)->firstOrFail();
        $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Evening support required',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(17, 0),
            'requested_end_at' => now()->addDay()->setTime(20, 0),
            'address_line1' => '11 Oak St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27602',
        ]);

        return [$family, $caregiver, $request];
    }
}
