<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\BrowseCareRequests;
use App\Livewire\Caregiver\WorkInbox;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestInvitation;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareRequestDistanceBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public static function screens(): array
    {
        return [[BrowseCareRequests::class, 'new_to_you'], [WorkInbox::class, 'new_requests']];
    }

    #[DataProvider('screens')]
    public function test_defaults_to_25_miles_and_can_expand_without_changing_the_profile(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver();
        $this->request('Nearby Durham care', '27701');
        $this->request('Expanded area care', '27312');
        $this->request('Distant Charlotte care', '28202');
        $this->request('Unknown distance care', '99999');

        Livewire::actingAs($caregiver)->test($screen)->set('scope', $scope)
            ->assertSet('radiusMiles', '25')->assertSee('Find care requests near you')
            ->assertSee('Nearby Durham care')->assertSee('About 21 miles away')
            ->assertDontSee('Expanded area care')->assertDontSee('Distant Charlotte care')->assertDontSee('Unknown distance care')
            ->set('radiusMiles', '50')->assertHasNoErrors()->assertSee('Expanded area care')
            ->assertDontSee('Distant Charlotte care')
            ->set('radiusMiles', 'all')->assertHasNoErrors()->assertSee('Distant Charlotte care')
            ->assertSee('Unknown distance care')->assertSee('Distance unavailable')
            ->set('radiusMiles', '5')->assertSee('No care requests match this area yet');

        $this->assertSame(5, (int) $caregiver->caregiverProfile->fresh()->service_radius_miles);
        $this->assertSame('27601', $caregiver->caregiverProfile->fresh()->service_area_zip);
    }

    #[DataProvider('screens')]
    public function test_empty_local_results_offer_expansion_and_never_expose_private_or_closed_requests(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver();
        $this->request('Farther public request', '27312');
        $this->request('Private nearby request', '27601', ['is_private' => true]);
        $this->request('Closed nearby request', '27601', ['status' => 'cancelled']);
        $this->request('Expired nearby request', '27601', ['requested_start_at' => now()->subHours(3), 'requested_end_at' => now()->subHour()]);

        Livewire::actingAs($caregiver)->test($screen)->set('scope', $scope)
            ->assertSee('Expand to 50 miles')->assertDontSee('Farther public request')
            ->set('radiusMiles', '50')->assertSee('Farther public request')
            ->set('radiusMiles', 'all')->assertDontSee('Private nearby request')
            ->assertDontSee('Closed nearby request')->assertDontSee('Expired nearby request');
    }

    #[DataProvider('screens')]
    public function test_missing_service_zip_is_explained_and_all_distances_still_works(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver('99999');
        $this->request('Visible public request', '27601');
        Livewire::actingAs($caregiver)->test($screen)->set('scope', $scope)
            ->assertSee('Set your service ZIP to find local requests')->assertDontSee('Visible public request')
            ->set('radiusMiles', 'all')->assertSee('Visible public request');
    }

    #[DataProvider('screens')]
    public function test_radius_is_kept_in_the_url_and_invalid_input_does_not_broaden_results(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver();
        $this->request('Expanded request', '27312');
        Livewire::withQueryParams(['radius' => '50'])->actingAs($caregiver)->test($screen)->set('scope', $scope)
            ->assertSet('radiusMiles', '50')->assertSee('Expanded request')
            ->set('radiusMiles', '-1')->assertHasErrors('radiusMiles')
            ->set('sort', 'newest')->assertDontSee('Expanded request')
            ->set('radiusMiles', '50')->assertHasNoErrors()->assertSee('Expanded request');
    }

    #[DataProvider('screens')]
    public function test_invited_and_applied_work_stays_accessible_outside_the_radius(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver();
        $invited = $this->request('Distant direct invitation', '28202', ['is_private' => true]);
        CareRequestInvitation::create([
            'care_request_id' => $invited->id, 'family_user_id' => $invited->family_user_id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'pending', 'expires_at' => now()->addDay(),
        ]);
        $applied = $this->request('Distant existing application', '28202');
        $applied->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 28]);

        $component = Livewire::actingAs($caregiver)->test($screen)->set('radiusMiles', '5')
            ->set('scope', $screen === WorkInbox::class ? 'needs_response' : 'invited')
            ->assertSee('Distant direct invitation')->assertDontSee('Search radius')
            ->set('scope', 'applied')->assertSee('Distant existing application');
        $component->set('scope', $scope)->assertDontSee('Distant direct invitation')->assertDontSee('Distant existing application');
    }

    #[DataProvider('screens')]
    public function test_nearby_requests_are_found_before_result_limits_are_applied(string $screen, string $scope): void
    {
        $caregiver = $this->caregiver();
        $near = $this->request('Older nearby opportunity', '27603');
        $near->forceFill(['created_at' => now()->subDay()])->save();
        for ($i = 0; $i < 32; $i++) {
            $this->request('Newer far request '.$i, '28202');
        }
        Livewire::actingAs($caregiver)->test($screen)->set('scope', $scope)
            ->assertSee('Older nearby opportunity')->assertDontSee('Newer far request');
    }

    public function test_browser_counts_pagination_other_filters_and_reset_use_the_same_radius(): void
    {
        $caregiver = $this->caregiver();
        for ($i = 0; $i < 12; $i++) {
            $this->request('Local request '.$i, '27603');
        }
        $this->request('Far request', '28202');
        Livewire::actingAs($caregiver)->test(BrowseCareRequests::class)
            ->assertViewHas('scopeCounts', fn ($counts) => $counts['new_to_you'] === 12 && $counts['all'] === 12)
            ->call('setPage', 2)->assertSet('paginators.page', 2)
            ->set('radiusMiles', '10')->assertSet('paginators.page', 1)
            ->set('city', 'No matching city')->assertViewHas('requests', fn ($rows) => $rows->total() === 0)
            ->set('radiusMiles', 'all')->assertViewHas('requests', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->assertSet('radiusMiles', '25')->assertSet('city', '')
            ->assertViewHas('requests', fn ($rows) => $rows->total() === 12);
    }

    public function test_inbox_counts_follow_the_radius_and_zip_plus_four_works(): void
    {
        $caregiver = $this->caregiver(' 27601-1234 ');
        $this->request('Same ZIP request', '27601-9876');
        $this->request('Expanded request', '27312');
        Livewire::actingAs($caregiver)->test(WorkInbox::class)
            ->assertViewHas('counts', fn ($counts) => $counts['new_requests'] === 1)
            ->assertSee('Less than 1 mile away')
            ->set('radiusMiles', '50')->assertViewHas('counts', fn ($counts) => $counts['new_requests'] === 2);
    }

    private function caregiver(string $zip = '27601'): User
    {
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::create([
            'user_id' => $user->id, 'slug' => 'local-care-'.$user->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'identity_verified_at' => now(),
            'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5, 'service_area_zip' => $zip,
            'service_radius_miles' => 5, 'is_accepting_new_clients' => true,
        ]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }

    private function request(string $title, string $zip, array $overrides = []): CareRequest
    {
        return CareRequest::create([
            'family_user_id' => User::factory()->create(['role' => 'family'])->id,
            'title' => $title, 'status' => 'open', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDays(2)->setTime(10, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => $zip,
            ...$overrides,
        ]);
    }
}
