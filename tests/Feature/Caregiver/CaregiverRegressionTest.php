<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Admin\CaregiverModerationLogs;
use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Livewire\Caregiver\OnboardingWizard;
use App\Livewire\Caregiver\ProfileEditor;
use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_submit_sets_under_review_and_creates_audit_records(): void
    {
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 3)
            ->set('date_of_birth', now()->subYears(25)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->call('nextStep')
            ->set('hourly_rate', 25)
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->call('nextStep')
            ->call('addRange', 1)
            ->set('availability.1.0.start', '09:00')
            ->set('availability.1.0.end', '12:00')
            ->call('nextStep')
            ->set('is_accepting_new_clients', true)
            ->call('submitForReview');

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'under_review',
        ]);

        $this->assertDatabaseHas('caregiver_profile_versions', [
            'caregiver_profile_id' => $profile->id,
            'reason' => 'submitted_for_review',
        ]);

        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'submitted',
        ]);
    }

    public function test_onboarding_rejects_overlapping_availability_ranges(): void
    {
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('step', 3)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('availability.1', [
                ['start' => '09:00', 'end' => '12:00'],
                ['start' => '11:00', 'end' => '13:00'],
            ])
            ->call('nextStep')
            ->assertHasErrors(['availability.1']);
    }

    public function test_onboarding_rejects_underage_caregiver_date_of_birth(): void
    {
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 1)
            ->set('date_of_birth', now()->subYears(17)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->call('nextStep')
            ->assertHasErrors(['date_of_birth']);
    }

    public function test_admin_can_approve_under_review_profile(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'under_review',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('approve', $profile->id);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'approved',
        ]);
    }

    public function test_admin_can_reject_under_review_profile_with_reason(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'under_review',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->set('rejection_reason', 'Missing detail about transportation support.')
            ->call('reject', $profile->id);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'draft',
            'rejection_reason' => 'Missing detail about transportation support.',
        ]);

        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'rejected',
        ]);
    }

    public function test_admin_can_suspend_and_unsuspend_profile(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('suspend', $profile->id);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'suspended',
        ]);
        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'suspended',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('unsuspend', $profile->id);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'unsuspended',
        ]);
    }

    public function test_profile_editor_keeps_active_status_and_creates_version(): void
    {
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Bio ', 15),
            'hourly_rate' => 22,
            'years_experience' => 2,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', str_repeat('Updated profile content. ', 4))
            ->set('hourly_rate', 30)
            ->set('years_experience', 5)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27602')
            ->set('service_radius_miles', 15)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->call('save');

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
            'hourly_rate' => 30.00,
        ]);

        $this->assertDatabaseHas('caregiver_profile_versions', [
            'caregiver_profile_id' => $profile->id,
            'reason' => 'profile_edit',
        ]);
    }

    public function test_search_page_shows_only_active_profiles(): void
    {
        $viewer = User::factory()->create();
        $activeUser = User::factory()->create(['name' => 'Active Caregiver', 'role' => 'caregiver']);
        $draftUser = User::factory()->create(['name' => 'Draft Caregiver', 'role' => 'caregiver']);

        CaregiverProfile::query()->create(['user_id' => $activeUser->id, 'slug' => 'active-caregiver-1', 'status' => 'active']);
        CaregiverProfile::query()->create(['user_id' => $draftUser->id, 'slug' => 'draft-caregiver-1', 'status' => 'under_review']);

        $response = $this->actingAs($viewer)->get('/caregivers/search');

        $response->assertOk();
        $response->assertSee('Active Caregiver');
        $response->assertDontSee('Draft Caregiver');
    }

    public function test_admin_moderation_logs_page_lists_recent_actions(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'name' => 'Admin User']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Log Target']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        CaregiverModerationLog::query()->create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => $admin->id,
            'action' => 'approved',
            'note' => 'Approved after review',
        ]);

        $response = $this->actingAs($admin)->get('/admin/caregivers/moderation-logs');

        $response->assertOk();
        $response->assertSee('Caregiver Moderation Logs');
        $response->assertSee('APPROVED');
        $response->assertSee('Log Target');
        $response->assertSee('Admin User');

        Livewire::actingAs($admin)
            ->test(CaregiverModerationLogs::class)
            ->assertSee('APPROVED')
            ->assertSee('Approved after review');
    }
}
