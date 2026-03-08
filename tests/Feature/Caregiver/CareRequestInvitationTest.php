<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Caregiver\ShowCaregiver;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\CaregiverProfile;
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
            'hourly_rate' => 28,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
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

        Livewire::actingAs($caregiver)
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

    private function seedContext(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'hourly_rate' => 26,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
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
