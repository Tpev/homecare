<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\BrowseCaregivers;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Marketplace\CaregiverInvitationDiscoveryService;
use App\Services\Matching\CaregiverSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverDiscoveryExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_homepage_replaces_the_excluded_caregiver_before_limiting_featured_profiles(): void
    {
        $this->excludedCaregiver();
        foreach (range(1, 3) as $number) {
            $this->caregiver('Visible Caregiver '.$number);
        }

        $this->get(route('landing'))
            ->assertOk()
            ->assertViewHas('featuredCaregivers', fn (Collection $profiles): bool => $profiles->count() === 3)
            ->assertSeeText('Visible Caregiver 1')
            ->assertSeeText('Visible Caregiver 2')
            ->assertSeeText('Visible Caregiver 3')
            ->assertDontSee('Charles Petrini-Poli')
            ->assertDontSee('charles-petrini-poli-17');
    }

    public function test_search_excludes_the_profile_before_pagination_even_with_name_and_top_filters(): void
    {
        $this->excludedCaregiver();
        foreach (range(1, 13) as $number) {
            $this->caregiver('Charles Neighbor '.$number);
        }

        Livewire::test(BrowseCaregivers::class)
            ->assertViewHas('caregivers', fn (LengthAwarePaginator $profiles): bool => $profiles->total() === 13 && $profiles->count() === 12)
            ->assertDontSee('Charles Petrini-Poli')
            ->set('search', 'Charles')
            ->set('trust', 'top')
            ->set('sort', 'top')
            ->assertViewHas('caregivers', fn (LengthAwarePaginator $profiles): bool => $profiles->total() === 13 && $profiles->count() === 12)
            ->assertDontSee('Charles Petrini-Poli')
            ->set('search', 'Charles Petrini-Poli')
            ->assertViewHas('caregivers', fn (LengthAwarePaginator $profiles): bool => $profiles->total() === 0);
    }

    public function test_invitation_search_and_recommendations_respect_the_exclusion_without_removing_the_profile(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $excluded = $this->excludedCaregiver();
        $visible = $this->caregiver('Charles Neighbor');
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Discovery exclusion request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '123 Example Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $discovery = app(CaregiverInvitationDiscoveryService::class);
        $suggestions = app(CaregiverSuggestionService::class);

        $this->assertSame([$visible->user_id], $discovery->search($request, $family, 'Charles')->pluck('user_id')->all());
        $this->assertSame([$visible->user_id], $suggestions->topMatchesForRequest($request)->pluck('user_id')->all());
        $this->assertNull($discovery->caregiver($request, $family, $excluded->user_id));

        $this->get(route('caregivers.show', ['slug' => $excluded->slug]))
            ->assertOk()
            ->assertSeeText('Charles Petrini-Poli');
        $this->assertSame('active', $excluded->fresh()->status);

        config(['marketplace.caregiver_discovery_excluded_slugs' => []]);

        $this->assertCount(2, $discovery->search($request, $family, 'Charles'));
        $this->assertCount(2, $suggestions->topMatchesForRequest($request));
    }

    private function excludedCaregiver(): CaregiverProfile
    {
        return $this->caregiver('Charles Petrini-Poli', [
            'slug' => 'charles-petrini-poli-17',
            'average_rating' => 5,
            'reviews_count' => 100,
        ]);
    }

    private function caregiver(string $name, array $overrides = []): CaregiverProfile
    {
        $user = User::factory()->create([
            'role' => 'caregiver',
            'name' => $name,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $profile = CaregiverProfile::query()->create(array_merge([
            'user_id' => $user->id,
            'slug' => str($name)->slug().'-'.$user->id,
            'status' => 'active',
            'bio' => 'Companionship and reliable everyday support.',
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'top_caregiver' => true,
            'average_rating' => 4.5,
            'reviews_count' => 5,
        ], $overrides));
        $profile->skills()->attach(Skill::query()->firstOrCreate(['name' => 'Companionship']));
        $profile->languages()->attach(Language::query()->firstOrCreate(['name' => 'English']));
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create([
                'day_of_week' => $day,
                'start_time' => '00:00',
                'end_time' => '23:59',
            ]);
        }

        return $profile;
    }
}
