<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\CustomerCare\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\Marketplace\CaregiverDistanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverDistanceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_staff_can_filter_initial_results_and_text_search_using_service_zips(): void
    {
        [$staff, , $request] = $this->requestFixture();
        $near = $this->caregiver('Near Morgan', '27513');
        $far = $this->caregiver('Far Morgan', '28202');
        // The stored service ZIP, not hometown or personal travel radius, determines distance.
        $near->update(['city' => 'Asheville']);
        $near->caregiverProfile->update(['service_radius_miles' => 1]);

        $component = Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->assertSet('caregiverDistanceMiles', '')
            ->assertSee('Distance from care location')->assertSee($near->name)->assertSee($far->name)
            ->set('caregiverDistanceMiles', '25')->assertHasNoErrors()
            ->assertSee($near->name)->assertDontSee($far->name)->assertSee('from care location')
            ->set('caregiverSearch', 'Morgan')
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->pluck('user_id')->all() === [$near->id])
            ->set('caregiverDistanceMiles', '5')
            ->assertSee('No caregivers match this distance and your filters')
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->isEmpty());

        $component->set('caregiverDistanceMiles', '')->assertHasNoErrors()
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->count() === 2);
    }

    public function test_distance_filters_the_whole_pool_before_limiting_and_resets_pagination(): void
    {
        [$staff, , $request] = $this->requestFixture();
        for ($i = 0; $i < 14; $i++) {
            $this->caregiver('Far candidate '.$i, '28202')->caregiverProfile->update(['top_caregiver' => true]);
        }
        for ($i = 0; $i < 14; $i++) {
            $this->caregiver('Near candidate '.$i, '27513');
        }

        Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->set('caregiverSearch', 'candidate')
            ->assertDontSee('Near candidate')
            ->set('caregiverDistanceMiles', '25')
            ->assertSet('caregiverDiscoveryLimit', 12)
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->count() === 12 && $rows->every(fn ($row) => str_starts_with($row['name'], 'Near')))
            ->assertViewHas('caregiverDiscoveryHasMore', true)
            ->call('loadMoreCaregivers', 12)
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->count() === 14)
            ->assertViewHas('caregiverDiscoveryHasMore', false)
            ->set('caregiverDistanceMiles', '10')
            ->assertSet('caregiverDiscoveryLimit', 12)
            ->call('loadMoreCaregivers', 24)->assertSet('caregiverDiscoveryLimit', 12);
    }

    public function test_unknown_locations_are_excluded_only_with_an_active_filter_and_zip_plus_four_is_supported(): void
    {
        [$staff, , $request] = $this->requestFixture();
        $request->update(['zip' => ' 27601-1234 ']);
        $near = $this->caregiver('ZIP Plus Four', ' 27601-9876 ');
        $unknown = $this->caregiver('Unknown Distance', '99999');
        $malformed = $this->caregiver('Malformed ZIP', '27601garbage');

        Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')->set('caregiverDistanceMiles', '5')
            ->assertSee($near->name)->assertSee('Less than 1 mile from care location')
            ->assertDontSee($unknown->name)->assertDontSee($malformed->name)
            ->set('caregiverDistanceMiles', '')->assertSee($unknown->name)->assertSee($malformed->name);

        $request->update(['zip' => '99999']);
        Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')->set('caregiverDistanceMiles', '25')
            ->assertSee('Distance is unavailable for this care location')
            ->assertViewHas('caregiverDiscoveryCount', 0)
            ->set('caregiverDistanceMiles', '')->assertSee($near->name);
    }

    public function test_coordinate_data_preserves_leading_zero_zips_and_known_distance_ranges(): void
    {
        [, , $request] = $this->requestFixture();
        $this->caregiver('Durham caregiver', '27701');
        $filter = app(CaregiverDistanceFilter::class);
        $distances = $filter->within($request, 25);
        $this->assertGreaterThan(20, $distances['27701']);
        $this->assertLessThan(25, $distances['27701']);
        $this->assertArrayNotHasKey('27701', $filter->within($request, 10));

        $request->update(['zip' => '00601']);
        $this->caregiver('Leading zero caregiver', '00601');
        $this->assertTrue($filter->hasOrigin($request));
        $this->assertSame(0.0, $filter->within($request, 5)['00601']);
    }

    public function test_filter_keeps_the_existing_invitation_flow_and_history(): void
    {
        [$staff, $family, $request] = $this->requestFixture();
        $caregiver = $this->caregiver('Invited caregiver', '27513');

        Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')->set('caregiverDistanceMiles', '25')
            ->call('openCaregiverInvitePanel')
            ->assertSee('invite-panel-distance', false)
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->set('caregiverInviteMessage', 'Please review this care request.')
            ->call('sendCaregiverInvitation')->assertHasNoErrors()
            ->set('caregiverDistanceMiles', '5')
            ->call('setCaregiverView', 'invited')->assertSee($caregiver->name)->assertSee('Invitation sent')
            ->assertDontSee('Distance from care location');

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'invited_by_user_id' => $staff->id, 'family_user_id' => $family->id,
        ]);
        $this->assertDatabaseHas('customer_care_actions', ['care_request_id' => $request->id, 'actor_user_id' => $staff->id, 'action' => 'caregiver_invited']);
        Notification::assertNotSentTo($family, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_invalid_distance_is_rejected_and_does_not_broaden_results(): void
    {
        [$staff, , $request] = $this->requestFixture();
        $this->caregiver('Local candidate', '27601');
        Livewire::actingAs($staff)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')->set('caregiverDistanceMiles', '-10')
            ->assertHasErrors('caregiverDistanceMiles')
            ->set('caregiverSearch', 'candidate')
            ->assertViewHas('caregiverSearchResults', fn ($rows) => $rows->isEmpty())
            ->set('caregiverDistanceMiles', '25')->assertHasNoErrors();
    }

    public function test_family_discovery_does_not_receive_the_staff_filter(): void
    {
        [, $family, $request] = $this->requestFixture();
        $request->update(['staff_managed' => false]);
        $far = $this->caregiver('Distant caregiver', '28202');
        Livewire::actingAs($family)->test(\App\Livewire\Family\ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')->assertSee($far->name)
            ->assertDontSee('Distance from care location')->assertDontSee('Approximate straight-line miles');
    }

    private function requestFixture(): array
    {
        $staff = User::factory()->create(['role' => 'customer_success']);
        $family = User::factory()->create(['role' => 'family']);
        $request = app(ManagedCareService::class)->save($staff, $family, [
            'request_key' => (string) Str::uuid(), 'title' => 'Afternoon companionship',
            'scope_of_work' => 'Companionship and meal preparation.', 'recipient_name' => 'Jamie',
            'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(), 'status' => 'open',
            'tasks' => [CareTask::firstOrCreate(['name' => 'Companionship'])->id],
        ]);

        return [$staff, $family, $request];
    }

    private function caregiver(string $name, string $zip): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::create([
            'user_id' => $user->id, 'slug' => 'distance-care-'.$user->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'identity_verified_at' => now(),
            'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5, 'service_area_zip' => $zip,
            'service_radius_miles' => 10, 'is_accepting_new_clients' => true,
        ]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }
}
