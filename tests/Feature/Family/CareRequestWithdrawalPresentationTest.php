<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CareJourney;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestWithdrawalPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('marketplace.caregiver_prelaunch_mode', false);
        Notification::fake();
    }

    public function test_successful_withdrawal_closes_named_hire_review_and_unsent_invitation_preview(): void
    {
        [$family, $request, $application, $invitedCaregiver] = $this->requestFixture();

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->call('reviewHire', $application->id)
            ->assertSee('Confirm hire')
            ->call('beginCaregiverInvitation', $invitedCaregiver->id)
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSet('confirmingCaregiverId', $invitedCaregiver->id)
            ->set('caregiverInviteMessage', 'Please review this unsent invitation.')
            ->call('withdrawRequest')
            ->assertSet('activeTab', 'home')
            ->assertSet('reviewingApplicationId', null)
            ->assertSet('reviewingCompletion', false)
            ->assertSet('showCaregiverInvitePanel', false)
            ->assertSet('confirmingCaregiverId', null)
            ->assertSet('confirmingReinvite', false)
            ->assertSet('caregiverInviteMessage', '')
            ->assertSee('Request withdrawn')
            ->assertDontSee('Confirm hire')
            ->assertDontSee('Invite a caregiver to this request');

        $this->assertSame(CareRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_NOT_SELECTED, $application->fresh()->status);
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_request_applications', 1);
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_rejected_withdrawal_preserves_open_review_and_invitation_draft(): void
    {
        [$family, $request, $application, $invitedCaregiver] = $this->requestFixture();

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->call('reviewHire', $application->id)
            ->call('beginCaregiverInvitation', $invitedCaregiver->id)
            ->set('caregiverInviteMessage', 'Keep this draft if the action is rejected.');

        $request->update(['status' => CareRequest::STATUS_CANCELLED]);

        $component->call('withdrawRequest')
            ->assertSee('Only a draft or open request can be withdrawn.')
            ->assertSet('activeTab', 'applicants')
            ->assertSet('reviewingApplicationId', $application->id)
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSet('confirmingCaregiverId', $invitedCaregiver->id)
            ->assertSet('caregiverInviteMessage', 'Keep this draft if the action is rejected.');

        $this->assertSame(CareRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);
        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_withdrawn_request_timeline_preserves_history_without_prompting_caregiver_selection(): void
    {
        [$family, $request, $application] = $this->requestFixture();

        Livewire::actingAs($family)->test(CareJourney::class, [
            'resourceType' => 'request', 'resourceId' => $request->id,
        ])
            ->assertSee('Waiting for the family to choose a caregiver.')
            ->assertViewHas('journey', fn (array $journey): bool => $journey['timeline']->firstWhere('label', 'Caregiver selected')['state'] === 'current'
                && $journey['timeline']->firstWhere('label', 'Visit scheduled')['state'] === 'pending');

        $request->update(['status' => CareRequest::STATUS_CANCELLED]);
        $application->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);
        $requestBefore = $request->refresh()->getRawOriginal();
        $applicationBefore = $application->refresh()->getRawOriginal();

        Livewire::actingAs($family)->test(CareJourney::class, [
            'resourceType' => 'request', 'resourceId' => $request->id,
        ])
            ->assertSee('Care timeline')
            ->assertSee('This request closed before a caregiver was selected.')
            ->assertSee('No visit was booked for this request.')
            ->assertSee('View this request and its caregiver history.')
            ->assertDontSee('Waiting for the family to choose a caregiver.')
            ->assertDontSee('Review replies and move this request toward confirmed care.')
            ->assertViewHas('journey', fn (array $journey): bool => $journey['timeline']->firstWhere('label', 'Caregiver selected')['state'] === 'pending'
                && $journey['timeline']->firstWhere('label', 'Visit scheduled')['state'] === 'pending');

        $this->assertSame($requestBefore, $request->fresh()->getRawOriginal());
        $this->assertSame($applicationBefore, $application->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_request_applications', 1);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    private function requestFixture(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $applicant = $this->readyCaregiver('Morgan Applicant');
        $invitedCaregiver = $this->readyCaregiver('Taylor Invite');
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning care for Eleanor',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(11, 0),
            'address_line1' => '123 Local Test Lane',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'scope_of_work' => 'Companionship and breakfast preparation.',
        ]);
        $request->recipient()->create(['full_name' => 'Eleanor', 'relationship_to_family' => 'Mother']);
        $application = $request->applications()->create([
            'caregiver_user_id' => $applicant->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        return [$family, $request, $application, $invitedCaregiver];
    }

    private function readyCaregiver(string $name): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $name, 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => str($name)->slug().'-'.$caregiver->id,
            'status' => 'active',
            'bio' => 'Experienced caregiver offering companionship and everyday non-medical help.',
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $profile->skills()->attach(Skill::query()->create(['name' => 'Companionship '.$caregiver->id]));
        $profile->languages()->attach(Language::query()->create(['name' => 'English '.$caregiver->id]));
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '18:00']);
        }

        return $caregiver->fresh('caregiverProfile');
    }
}
