<?php

namespace Tests\Feature\Family;

use App\Livewire\Caregiver\ShowCaregiver;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\FamilyCaregiverFavorite;
use App\Models\FunnelEvent;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Marketplace\CaregiverInvitationDiscoveryService;
use App\Services\Marketplace\CareRequestInvitationService;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverInvitationExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_open_search_and_invite_a_named_caregiver_without_leaving_request(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Charles Helpful', 'Raleigh');
        $request = $this->createOpenRequest($family);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSet('activeTab', 'applicants')
            ->assertSee('Invite a caregiver to this request')
            ->set('caregiverSearch', 'Char')
            ->assertSee('Charles Helpful')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->assertSee('Invite Charles to')
            ->set('caregiverInviteMessage', 'Please review this care request for our family.')
            ->call('sendCaregiverInvitation')
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSet('caregiverSearch', 'Char')
            ->assertSee('Invitation sent to Charles Helpful.')
            ->assertSee('Invitation sent');

        $invitation = CareRequestInvitation::query()->firstOrFail();
        $this->assertSame($request->id, $invitation->care_request_id);
        $this->assertSame($family->id, $invitation->family_user_id);
        $this->assertSame($caregiver->id, $invitation->caregiver_user_id);
        $this->assertSame(CareRequestInvitation::STATUS_PENDING, $invitation->status);
        $this->assertSame('Please review this care request for our family.', $invitation->message);
        $this->assertTrue($invitation->expires_at->between(now()->addHours(71), now()->addHours(73)));
    }

    public function test_search_exposes_only_eligible_caregiver_public_card_fields(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $request = $this->createOpenRequest($family);
        $visible = $this->createReadyCaregiver('Morgan Visible', 'Durham', [
            'email' => 'private-morgan@example.test',
            'phone' => '+1 919 555 0199',
        ]);
        $inactive = $this->createReadyCaregiver('Morgan Inactive', 'Durham');
        $inactive->caregiverProfile->update(['status' => 'draft']);
        $incomplete = User::factory()->create(['role' => 'caregiver', 'name' => 'Morgan Incomplete']);
        CaregiverProfile::query()->create([
            'user_id' => $incomplete->id,
            'slug' => 'morgan-incomplete',
            'status' => 'active',
            'is_accepting_new_clients' => true,
        ]);
        $notCaregiver = $this->createReadyCaregiver('Morgan Family Account', 'Durham', ['role' => 'family']);

        $component = Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->set('caregiverSearch', 'Morgan')
            ->assertSee($visible->name)
            ->assertDontSee($inactive->name)
            ->assertDontSee($incomplete->name)
            ->assertDontSee($notCaregiver->name)
            ->assertDontSee('private-morgan@example.test')
            ->assertDontSee('+1 919 555 0199');

        $this->assertSame('Morgan', $component->get('caregiverSearch'));
    }

    public function test_initial_view_has_previous_favorite_and_recommended_sections(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $previous = $this->createReadyCaregiver('Pat Previous');
        $favorite = $this->createReadyCaregiver('Fran Favorite');
        $recommended = $this->createReadyCaregiver('Riley Recommended');
        $request = $this->createOpenRequest($family);

        $pastRequest = $this->createOpenRequest($family, ['title' => 'Past care request', 'status' => CareRequest::STATUS_FILLED]);
        $pastApplication = CareRequestApplication::query()->create([
            'care_request_id' => $pastRequest->id,
            'caregiver_user_id' => $previous->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $pastRequest->id,
            'care_request_application_id' => $pastApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $previous->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subWeek(),
            'scheduled_end_at' => now()->subWeek()->addHours(2),
        ]);
        FamilyCaregiverFavorite::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $favorite->id,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->assertSee('Caregivers you hired before')
            ->assertSee($previous->name)
            ->assertSee('Saved caregivers')
            ->assertSee($favorite->name)
            ->assertSee('Recommended for this request')
            ->assertSee($recommended->name);
    }

    public function test_duplicate_send_is_idempotent_and_notifies_or_tracks_only_once(): void
    {
        Notification::fake();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Jordan Once');
        $request = $this->createOpenRequest($family);
        $service = app(CareRequestInvitationService::class);

        $first = $service->send($family, $request, $caregiver, 'First invitation', source: 'test');
        $second = $service->send($family, $request, $caregiver, 'Changed by double click', source: 'test');

        $this->assertTrue($first->sentNow);
        $this->assertFalse($second->sentNow);
        $this->assertSame(CareRequestInvitationService::STATE_PENDING, $second->state);
        $this->assertDatabaseCount('care_request_invitations', 1);
        $this->assertDatabaseHas('care_request_invitations', ['message' => 'First invitation']);
        Notification::assertSentToTimes($caregiver, MarketplaceEventNotification::class, 2);
        Notification::assertSentToTimes($family, MarketplaceEventNotification::class, 2);
        Notification::assertSentTo($caregiver, MarketplaceEventNotification::class, fn (MarketplaceEventNotification $notification, array $channels): bool => $notification->toArray($caregiver)['event_key'] === MarketplaceEvent::INVITATION_RECEIVED
            && $channels === ['mail']);
        Notification::assertSentTo($caregiver, MarketplaceEventNotification::class, fn (MarketplaceEventNotification $notification, array $channels): bool => $notification->toArray($caregiver)['event_key'] === MarketplaceEvent::INVITATION_RECEIVED
            && $channels === ['database']);
        Notification::assertSentTo($family, MarketplaceEventNotification::class, fn (MarketplaceEventNotification $notification, array $channels): bool => $notification->toArray($family)['event_key'] === MarketplaceEvent::INVITATION_SENT
            && $channels === ['mail']);
        Notification::assertSentTo($family, MarketplaceEventNotification::class, fn (MarketplaceEventNotification $notification, array $channels): bool => $notification->toArray($family)['event_key'] === MarketplaceEvent::INVITATION_SENT
            && $channels === ['database']);
        $this->assertSame(1, FunnelEvent::query()->where('event', 'care_request_invitation_sent')->count());
    }

    public function test_invitation_confirmation_reports_message_validation_errors_without_sending(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Validation Caregiver');
        $request = $this->createOpenRequest($family);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->set('caregiverInviteMessage', str_repeat('x', 1201))
            ->call('sendCaregiverInvitation')
            ->assertHasErrors(['caregiverInviteMessage' => 'max']);

        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_pending_accepted_and_application_relationships_are_never_reset(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $service = app(CareRequestInvitationService::class);

        $pendingCaregiver = $this->createReadyCaregiver('Pending Person');
        $pendingRequest = $this->createOpenRequest($family, ['title' => 'Pending request']);
        $oldExpiry = now()->addDay();
        $pending = CareRequestInvitation::query()->create([
            'care_request_id' => $pendingRequest->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $pendingCaregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Original message',
            'expires_at' => $oldExpiry,
        ]);

        $result = $service->send($family, $pendingRequest, $pendingCaregiver, 'Replacement message');
        $this->assertSame(CareRequestInvitationService::STATE_PENDING, $result->state);
        $this->assertSame('Original message', $pending->fresh()->message);
        $this->assertSame($oldExpiry->format('Y-m-d H:i:s'), $pending->fresh()->expires_at->format('Y-m-d H:i:s'));

        $acceptedCaregiver = $this->createReadyCaregiver('Accepted Person');
        $acceptedRequest = $this->createOpenRequest($family, ['title' => 'Accepted request']);
        $accepted = CareRequestInvitation::query()->create([
            'care_request_id' => $acceptedRequest->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $acceptedCaregiver->id,
            'status' => CareRequestInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        $result = $service->send($family, $acceptedRequest, $acceptedCaregiver, 'Try reset', true);
        $this->assertSame(CareRequestInvitationService::STATE_ACCEPTED, $result->state);
        $this->assertSame(CareRequestInvitation::STATUS_ACCEPTED, $accepted->fresh()->status);

        foreach ([CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED, CareRequestApplication::STATUS_HIRED] as $status) {
            $caregiver = $this->createReadyCaregiver('Application '.$status);
            $request = $this->createOpenRequest($family, ['title' => 'Application '.$status]);
            $application = CareRequestApplication::query()->create([
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => $status,
                'proposed_rate' => 30,
            ]);

            $result = $service->send($family, $request, $caregiver, 'Try reset', true);
            $this->assertSame(
                $status === CareRequestApplication::STATUS_HIRED ? CareRequestInvitationService::STATE_HIRED : CareRequestInvitationService::STATE_REPLIED,
                $result->state,
            );
            $this->assertSame($status, $application->fresh()->status);
            $this->assertFalse(CareRequestInvitation::query()->where('care_request_id', $request->id)->exists());
        }
    }

    public function test_historical_invitations_require_explicit_reinvite_and_are_safely_reset(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $service = app(CareRequestInvitationService::class);

        foreach ([
            CareRequestInvitation::STATUS_DECLINED,
            CareRequestInvitation::STATUS_EXPIRED,
            CareRequestInvitation::STATUS_CANCELLED,
        ] as $status) {
            $caregiver = $this->createReadyCaregiver('History '.$status);
            $request = $this->createOpenRequest($family, ['title' => 'History '.$status]);
            $invitation = CareRequestInvitation::query()->create([
                'care_request_id' => $request->id,
                'family_user_id' => $family->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => $status,
                'message' => 'Old message',
                'expires_at' => now()->subDay(),
                'responded_at' => now()->subDay(),
            ]);

            $withoutExplicitAction = $service->send($family, $request, $caregiver, 'New message');
            $this->assertFalse($withoutExplicitAction->sentNow);
            $this->assertSame($status, $invitation->fresh()->status);

            $explicitReinvite = $service->send($family, $request, $caregiver, 'New message', true);
            $this->assertTrue($explicitReinvite->sentNow);
            $this->assertSame(CareRequestInvitationService::STATE_REINVITED, $explicitReinvite->state);
            $this->assertSame(CareRequestInvitation::STATUS_PENDING, $invitation->fresh()->status);
            $this->assertSame('New message', $invitation->fresh()->message);
            $this->assertNull($invitation->fresh()->responded_at);
            $this->assertNull($invitation->fresh()->care_request_application_id);
            $this->assertTrue($invitation->fresh()->expires_at->isFuture());
        }
    }

    public function test_existing_application_is_presented_as_already_replied(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Alex Replied');
        $request = $this->createOpenRequest($family);
        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->set('caregiverSearch', 'Alex')
            ->assertSee('Already replied')
            ->assertSee('View reply');
    }

    public function test_family_cannot_search_or_invite_through_another_family_request(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $otherFamily = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Private Caregiver');
        $request = $this->createOpenRequest($owner);

        $this->actingAs($otherFamily)
            ->get(route('family.requests.show', $request->id))
            ->assertNotFound();

        $this->expectException(AuthorizationException::class);
        app(CaregiverInvitationDiscoveryService::class)->search($request, $otherFamily, 'Private');

        $this->assertFalse(CareRequestInvitation::query()->where('caregiver_user_id', $caregiver->id)->exists());
    }

    public function test_profile_request_context_is_validated_and_never_requires_reselection(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $otherFamily = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Context Caregiver');
        $ownedRequest = $this->createOpenRequest($owner, ['title' => 'Owned morning care']);
        $otherRequest = $this->createOpenRequest($otherFamily, ['title' => 'Private other request']);

        Livewire::withQueryParams(['careRequest' => $ownedRequest->id])
            ->actingAs($owner)
            ->test(ShowCaregiver::class, ['slug' => $caregiver->caregiverProfile->slug])
            ->assertSet('contextCareRequestId', $ownedRequest->id)
            ->call('openInviteModal')
            ->assertSet('selectedCareRequestId', $ownedRequest->id)
            ->assertSee('This request is already selected.')
            ->assertDontSee('Select request');

        Livewire::withQueryParams(['careRequest' => $otherRequest->id])
            ->actingAs($owner)
            ->test(ShowCaregiver::class, ['slug' => $caregiver->caregiverProfile->slug])
            ->assertSet('contextCareRequestId', null)
            ->assertDontSee('Private other request');
    }

    public function test_non_open_requests_cannot_send_invitations(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $service = app(CareRequestInvitationService::class);

        foreach ([
            CareRequest::STATUS_DRAFT,
            CareRequest::STATUS_FILLED,
            CareRequest::STATUS_CANCELLED,
            CareRequest::STATUS_EXPIRED,
        ] as $status) {
            $caregiver = $this->createReadyCaregiver('Closed '.$status);
            $request = $this->createOpenRequest($family, ['title' => 'Closed '.$status, 'status' => $status]);

            $result = $service->send($family, $request, $caregiver, 'Cannot send');
            $this->assertSame(CareRequestInvitationService::STATE_REQUEST_UNAVAILABLE, $result->state);
            $this->assertFalse($result->sentNow);
            $this->assertFalse(CareRequestInvitation::query()->where('care_request_id', $request->id)->exists());
        }
    }

    public function test_canonical_service_rejects_ineligible_targets_and_search_explains_not_accepting(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $request = $this->createOpenRequest($family);
        $service = app(CareRequestInvitationService::class);

        $notAccepting = $this->createReadyCaregiver('Taylor Paused');
        $notAccepting->caregiverProfile->update(['is_accepting_new_clients' => false]);
        $notAcceptingResult = $service->send($family, $request, $notAccepting->fresh('caregiverProfile'), 'Please help');
        $this->assertSame(CareRequestInvitationService::STATE_NOT_ACCEPTING, $notAcceptingResult->state);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('openCaregiverInvitePanel')
            ->set('caregiverSearch', 'Taylor')
            ->assertSee('Not accepting new clients')
            ->assertSee('invitation is unavailable right now');

        $incomplete = User::factory()->create(['role' => 'caregiver', 'name' => 'Incomplete Target']);
        $incomplete->caregiverProfile()->create([
            'slug' => 'incomplete-target',
            'status' => 'active',
            'is_accepting_new_clients' => true,
        ]);
        $this->assertSame(
            CareRequestInvitationService::STATE_NOT_READY,
            $service->send($family, $request, $incomplete->fresh('caregiverProfile'), 'Please help')->state,
        );

        $notCaregiver = $this->createReadyCaregiver('Family Target', userOverrides: ['role' => 'family']);
        $this->assertSame(
            CareRequestInvitationService::STATE_NOT_CAREGIVER,
            $service->send($family, $request, $notCaregiver, 'Please help')->state,
        );
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_direct_invitation_still_works_in_prelaunch_mode(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', true);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createReadyCaregiver('Prelaunch Direct');
        $request = $this->createOpenRequest($family);

        $result = app(CareRequestInvitationService::class)->send($family, $request, $caregiver, 'Direct invitation');

        $this->assertTrue($result->sentNow);
        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
        ]);
    }

    private function createReadyCaregiver(string $name, string $city = 'Raleigh', array $userOverrides = []): User
    {
        $caregiver = User::factory()->create(array_merge([
            'role' => 'caregiver',
            'name' => $name,
            'city' => $city,
            'state' => 'NC',
        ], $userOverrides));
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => str($name)->slug().'-'.$caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced non-medical caregiver. ', 3),
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'background_check_verified_at' => now(),
            'average_rating' => 4.8,
            'reviews_count' => 8,
        ]);
        $skill = Skill::query()->create(['name' => 'Skill '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'Language '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create([
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '18:00',
            ]);
        }

        return $caregiver->fresh('caregiverProfile');
    }

    private function createOpenRequest(User $family, array $overrides = []): CareRequest
    {
        $request = CareRequest::query()->create(array_merge([
            'family_user_id' => $family->id,
            'title' => 'Morning care for Barbara',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ], $overrides));
        $request->recipient()->create([
            'full_name' => 'Barbara Pearl',
            'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }
}
