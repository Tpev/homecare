<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Caregiver\BrowseCareRequests;
use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestInvitation;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Matching\CaregiverSuggestionService;
use App\Support\CaregiverWorkInboxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateCareRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_create_private_request_and_is_taken_to_existing_invite_flow(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $start = now()->addDay()->setTime(10, 0, 0);

        $component = Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->assertSet('is_private', false)
            ->assertSee('Make this request private')
            ->assertSee('Leave this unchecked to let eligible caregivers discover and apply to the request.')
            ->set('is_private', true)
            ->assertSee('Only caregivers you invite can view and respond to it.')
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', $start->toDateString())
            ->set('requested_start_time', $start->format('H:i'))
            ->set('requested_duration_minutes', '180')
            ->set('address_line1', '100 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('recipient_full_name', 'Mary Johnson')
            ->call('publish')
            ->assertHasNoErrors();

        $request = CareRequest::query()->latest('id')->firstOrFail();

        $this->assertTrue($request->is_private);
        $component->assertRedirect(route('family.requests.show', [
            'careRequest' => $request->id,
            'tab' => 'applicants',
            'invite' => 1,
        ], false));

        Livewire::withQueryParams(['tab' => 'applicants', 'invite' => 1])
            ->actingAs($family)
            ->test(\App\Livewire\Family\ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'applicants')
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSee('This request is private and cannot be discovered in the caregiver marketplace.')
            ->assertSee('PRIVATE');
    }

    public function test_private_request_is_visible_only_to_invited_caregiver(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $invitedCaregiver = $this->createReadyCaregiver('Invited Caregiver');
        $otherCaregiver = $this->createReadyCaregiver('Other Caregiver');
        $publicRequest = $this->createOpenRequest($family, 'Public companionship request');
        $privateRequest = $this->createOpenRequest($family, 'Private companionship request', true);

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $privateRequest->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $invitedCaregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($invitedCaregiver)
            ->test(BrowseCareRequests::class)
            ->set('scope', 'all')
            ->assertSee($publicRequest->title)
            ->assertSee($privateRequest->title)
            ->set('scope', 'invited')
            ->assertSee($privateRequest->title);

        Livewire::actingAs($otherCaregiver)
            ->test(BrowseCareRequests::class)
            ->set('scope', 'all')
            ->assertSee($publicRequest->title)
            ->assertDontSee($privateRequest->title);

        $this->actingAs($invitedCaregiver)
            ->get(route('care-requests.apply', $privateRequest->id))
            ->assertOk();

        $this->actingAs($otherCaregiver)
            ->get(route('care-requests.apply', $privateRequest->id))
            ->assertForbidden();

        $this->actingAs($otherCaregiver)
            ->get(route('care-requests.apply', $publicRequest->id))
            ->assertOk();

        Livewire::actingAs($invitedCaregiver)
            ->test(InvitationsIndex::class)
            ->call('accept', $invitation->id)
            ->assertRedirect(route('care-requests.apply', $privateRequest->id, false));

        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $privateRequest->id,
            'caregiver_user_id' => $invitedCaregiver->id,
        ]);
    }

    public function test_expired_private_invitation_no_longer_grants_access(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Expired Invite Caregiver');
        $request = $this->createOpenRequest($family, 'Expired private request', true);

        CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(BrowseCareRequests::class)
            ->set('scope', 'all')
            ->assertDontSee($request->title);

        $this->actingAs($caregiver)
            ->get(route('care-requests.apply', $request->id))
            ->assertForbidden();
    }

    public function test_private_request_is_not_recommended_but_still_appears_as_direct_invitation(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Inbox Caregiver');
        $request = $this->createOpenRequest($family, 'Private inbox request', true);
        $builder = app(CaregiverWorkInboxBuilder::class);

        $this->assertFalse($builder->buildForUser($caregiver)->contains(
            fn (array $item): bool => $item['title'] === $request->title,
        ));

        CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($builder->buildForUser($caregiver)->contains(
            fn (array $item): bool => $item['title'] === $request->title && $item['state'] === 'invited',
        ));
    }

    public function test_private_request_is_excluded_from_automated_matching_notifications(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->createOpenRequest($family, 'Private notification request', true);

        $this->mock(CaregiverSuggestionService::class, function ($mock): void {
            $mock->shouldNotReceive('topMatchesForRequest');
        });

        $this->artisan('homecare:dispatch-notifications --type=matching')
            ->assertExitCode(0);

        $this->assertDatabaseCount('marketplace_notification_deliveries', 0);
    }

    private function createReadyCaregiver(string $name): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => $name,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 27,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
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
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        return $caregiver;
    }

    private function createOpenRequest(User $family, string $title, bool $private = false): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => $title,
            'status' => CareRequest::STATUS_OPEN,
            'is_private' => $private,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(10, 0),
            'requested_end_at' => now()->addDay()->setTime(13, 0),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Mary Johnson',
            'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }
}
