<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersIndex;
use App\Livewire\Admin\FunnelAnalytics as FunnelAnalyticsComponent;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\PageViewEvent;
use App\Models\Skill;
use App\Models\User;
use App\Services\Analytics\PageViewTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersAndFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_delete_users_but_not_delete_self(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);
        $family = User::factory()->create(['role' => 'family', 'name' => 'Family Demo']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Caregiver Demo']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('User Management');
        $response->assertSee('Family Demo');
        $response->assertSee('Caregiver Demo');
        $response->assertSee('Missing: Profile basics, Identity verification, Task comfort selection');
        $response->assertSee(route('admin.users.show', $family), false);

        $profileResponse = $this->actingAs($admin)->get(route('admin.users.show', $caregiver));
        $profileResponse->assertOk();
        $profileResponse->assertSee('User Profile Review');
        $profileResponse->assertSee('Caregiver Demo');

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('deleteUser', $family->id);

        $this->assertDatabaseMissing('users', ['id' => $family->id]);

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('deleteUser', $admin->id)
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_access_admin_users_or_funnel_pages(): void
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        $anotherUser = User::factory()->create(['role' => 'family']);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.show', $anotherUser))->assertForbidden();
        $this->actingAs($user)->get('/admin/analytics/funnel')->assertForbidden();
        $this->actingAs($user)->get('/admin/analytics/caregiver-map')->assertForbidden();
    }

    public function test_admin_can_login_as_non_admin_user_from_users_table(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);
        $family = User::factory()->create(['role' => 'family']);

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('loginAs', $family->id)
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($family);
        $this->get('/dashboard')->assertOk();
    }

    public function test_admin_cannot_login_as_self_or_another_admin_from_users_table(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin2@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('loginAs', $admin->id)
            ->assertHasErrors(['loginAs']);

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('loginAs', $otherAdmin->id)
            ->assertHasErrors(['loginAs']);
    }

    public function test_funnel_page_renders_caregiver_lifecycle_steps_with_admin_nav_only(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);
        PageViewEvent::query()->create([
            'event_name' => PageViewTracker::CAREGIVER_LANDING_EVENT,
            'anon_id' => (string) \Illuminate\Support\Str::uuid(),
            'url' => 'https://homecare.test/caregivers',
        ]);
        PageViewEvent::query()->create([
            'event_name' => PageViewTracker::CAREGIVER_LANDING_EVENT,
            'anon_id' => (string) \Illuminate\Support\Str::uuid(),
            'url' => 'https://homecare.test/caregivers',
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);

        $family = User::factory()->create(['role' => 'family']);

        $caregiverActive = User::factory()->create(['role' => 'caregiver']);
        $activeProfile = $this->createFilledProfile($caregiverActive, $skill, $language, 'active', true);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Companionship shift',
            'status' => 'open',
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiverActive->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiverActive->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $caregiverFilledOnly = User::factory()->create(['role' => 'caregiver']);
        $this->createFilledProfile($caregiverFilledOnly, $skill, $language, 'draft', false);

        $caregiverUnderReview = User::factory()->create(['role' => 'caregiver']);
        $this->createFilledProfile($caregiverUnderReview, $skill, $language, 'under_review', true);

        $response = $this->actingAs($admin)->get('/admin/analytics/funnel');

        $response->assertOk();
        $response->assertSee('Caregiver Lifecycle Funnel');
        $response->assertSee('Visited Landing Page');
        $response->assertSee('Registered');
        $response->assertSee('Profile Info Filled');
        $response->assertSee('Under Review');
        $response->assertSee('Fully Validated Profile');
        $response->assertSee('Applied for a Shift');
        $response->assertSee('Completed a Shift');
        $response->assertSee('Traffic & Signup Trends', false);
        $response->assertSee('Caregiver signups');
        $response->assertSee('Landing page views');
        $response->assertSee('Onboarding Email Performance');
        $response->assertSee('Welcome email');
        $response->assertSee('24h incomplete reminder');

        $response->assertSee('Admin Users');
        $response->assertDontSee('My Requests');
        $response->assertDontSee('My Shifts');
        $response->assertDontSee('Find Caregivers');

        $this->assertNotNull($activeProfile->fresh());
    }

    public function test_funnel_histogram_can_toggle_granularity(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);

        PageViewEvent::query()->create([
            'event_name' => PageViewTracker::CAREGIVER_LANDING_EVENT,
            'anon_id' => (string) \Illuminate\Support\Str::uuid(),
            'url' => 'https://homecare.test/caregivers',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        User::factory()->create([
            'role' => 'caregiver',
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);

        Livewire::actingAs($admin)
            ->test(FunnelAnalyticsComponent::class)
            ->set('days', 90)
            ->set('trendGranularity', 'week')
            ->assertSee('Histogram grouping: weekly buckets.')
            ->set('trendGranularity', 'month')
            ->assertSee('Histogram grouping: monthly buckets.');
    }

    public function test_admin_can_view_caregiver_coverage_map_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);

        User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Charlotte',
            'state' => 'NC',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin)->get('/admin/analytics/caregiver-map');

        $response->assertOk();
        $response->assertSee('Caregiver Coverage Map');
        $response->assertSee('US coverage intensity map');
        $response->assertSee('Top cities');
        $response->assertSee('Raleigh, NC');
        $response->assertSee('Admin Coverage');
    }

    private function createFilledProfile(
        User $caregiver,
        Skill $skill,
        Language $language,
        string $status,
        bool $submitted
    ): CaregiverProfile {
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'cg-'.$caregiver->id,
            'bio' => str_repeat('Reliable caregiver profile. ', 4),
            'years_experience' => 2,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'status' => $status,
            'review_submitted_at' => $submitted ? now() : null,
        ]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        return $profile;
    }
}
