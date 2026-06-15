<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\ManageCareRequest;
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

class FamilyPrelaunchPilotFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_hire_pilot_caregiver_while_prelaunch_mode_is_enabled(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', true);
        config()->set('marketplace.family_prelaunch_auto_applicants.emails', ['pilot@homecare.test']);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('pilot@homecare.test');
        $request = $this->createOpenRequest($family);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30.00,
            'cover_note' => 'I can support this request.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
    }

    public function test_family_cannot_hire_non_pilot_caregiver_while_prelaunch_mode_is_enabled(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', true);
        config()->set('marketplace.family_prelaunch_auto_applicants.emails', ['pilot@homecare.test']);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('nonpilot@homecare.test');
        $request = $this->createOpenRequest($family);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30.00,
            'cover_note' => 'I can support this request.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_OPEN,
        ]);
    }

    public function test_family_can_invite_non_pilot_caregiver_while_prelaunch_mode_is_enabled(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', true);
        config()->set('marketplace.family_prelaunch_auto_applicants.emails', ['pilot@homecare.test']);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('caroline.invited@homecare.test');
        $request = $this->createOpenRequest($family);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('inviteSuggestedCaregiver', $caregiver->id);

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
        ]);
    }

    public function test_family_can_hire_non_pilot_caregiver_after_direct_invite_is_accepted(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', true);
        config()->set('marketplace.family_prelaunch_auto_applicants.emails', ['pilot@homecare.test']);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('caroline.direct@homecare.test');
        $request = $this->createOpenRequest($family);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 30.00,
            'cover_note' => null,
        ]);

        CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_ACCEPTED,
            'message' => 'Can you help with this shift?',
            'responded_at' => now(),
            'care_request_application_id' => $application->id,
            'expires_at' => now()->addHours(24),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
    }

    private function createReadyCaregiver(string $email): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => $email,
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Trusted caregiver support. ', 4),
            'platform_hourly_rate' => 30.00,
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        return $caregiver;
    }

    private function createOpenRequest(User $family): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Family pilot flow request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '123 Pilot Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Pilot Recipient',
            'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }
}
