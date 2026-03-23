<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Caregiver\WorkInbox;
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

class CaregiverPrelaunchModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_prelaunch_hides_caregivers_from_public_search(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);

        $caregiver = $this->createReadyCaregiver();

        $response = $this->get('/caregivers/search');

        $response->assertOk();
        $response->assertSee('Caregiver marketplace is in pre-launch mode.');
        $response->assertDontSee($caregiver->name);
    }

    public function test_prelaunch_blocks_caregiver_accepting_invitation(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOpenRequest($family->id, 'Prelaunch invitation');

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Can you take this shift?',
            'expires_at' => now()->addHours(24),
        ]);

        Livewire::actingAs($caregiver)
            ->test(WorkInbox::class)
            ->call('acceptInvitation', $invitation->id);

        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
        ]);
    }

    public function test_prelaunch_blocks_family_hire(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);

        $family = User::factory()->create(['role' => 'family', 'email_verified_at' => now()]);
        $caregiver = $this->createReadyCaregiver();
        $request = $this->createOpenRequest($family->id, 'Prelaunch hire flow');

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27.00,
            'cover_note' => 'I am available and ready to help.',
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
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_prelaunch_banner_is_visible_on_caregiver_dashboard(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);

        $caregiver = $this->createReadyCaregiver();

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Matching opens soon in your area.');
        $response->assertSee('we will notify you as soon as matching opens');
    }

    public function test_prelaunch_still_allows_caregiver_setup_and_kyc_pages(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => true]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
            'slug' => 'setup-'.$caregiver->id,
            'bio' => null,
            'platform_hourly_rate' => 27.00,
            'insurance_status' => CaregiverProfile::INSURANCE_NOT_PROVIDED,
            'identity_verification_status' => 'not_started',
        ]);

        $this->actingAs($caregiver)->get('/caregiver/verification')->assertOk();
        $this->actingAs($caregiver)->get('/caregiver/profile/tasks')->assertOk();
        $this->actingAs($caregiver)->get('/caregiver/payouts/connect')->assertOk();

        $this->actingAs($caregiver)
            ->get('/dashboard')
            ->assertRedirect('/caregiver/setup');

        $this->actingAs($caregiver)
            ->get('/caregiver/setup')
            ->assertOk()
            ->assertSee('Finish setup to start getting booked.')
            ->assertSee('Identity verification')
            ->assertSee('Task comfort');
    }

    private function createOpenRequest(int $familyUserId, string $title): CareRequest
    {
        return CareRequest::query()->create([
            'family_user_id' => $familyUserId,
            'title' => $title,
            'status' => CareRequest::STATUS_OPEN,
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
            'slug' => 'prelaunch-caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced and reliable caregiver. ', 4),
            'platform_hourly_rate' => 27.00,
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
