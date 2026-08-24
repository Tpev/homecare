<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Admin\CaregiverModerationLogs;
use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Livewire\Caregiver\BrowseCaregivers;
use App\Livewire\Caregiver\OnboardingWizard;
use App\Livewire\Caregiver\ProfileEditor;
use App\Mail\Ops\CaregiverReadyForReviewOpsAlertMail;
use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_submit_sets_under_review_and_creates_audit_records(): void
    {
        config([
            'marketplace.ops_alert_recipients' => [
                'peverelli.t@gmail.com',
                'hello@carelolo.com',
            ],
        ]);
        Mail::fake();

        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'status' => 'draft',
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 3)
            ->set('date_of_birth', now()->subYears(25)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->call('nextStep')
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

        Mail::assertSent(CaregiverReadyForReviewOpsAlertMail::class, function (CaregiverReadyForReviewOpsAlertMail $mail) use ($user, $profile) {
            return $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('hello@carelolo.com')
                && $mail->user->is($user)
                && $mail->profile->id === $profile->id;
        });
    }

    public function test_active_caregiver_is_redirected_away_from_onboarding(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        $this->actingAs($caregiver)
            ->get(route('caregiver.onboarding', ['step' => 5]))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);
    }

    public function test_stale_onboarding_tab_cannot_resubmit_an_approved_profile(): void
    {
        Mail::fake();

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);

        $component = Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 5);

        // Simulate an administrator approving the profile while onboarding is still open.
        $profile->update(['status' => 'active']);

        $component
            ->call('submitForReview')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('caregiver_profile_versions', [
            'caregiver_profile_id' => $profile->id,
            'reason' => 'submitted_for_review',
        ]);
        $this->assertDatabaseMissing('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'submitted',
        ]);
        Mail::assertNothingSent();
    }

    public function test_onboarding_rejects_overlapping_availability_ranges(): void
    {
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('step', 4)
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

    public function test_onboarding_requires_task_preferences_before_submit(): void
    {
        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);

        CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'status' => 'draft',
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'insurance_status' => CaregiverProfile::INSURANCE_NOT_PROVIDED,
        ]);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 3)
            ->set('date_of_birth', now()->subYears(25)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->call('nextStep')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->call('nextStep')
            ->call('addRange', 1)
            ->set('availability.1.0.start', '09:00')
            ->set('availability.1.0.end', '12:00')
            ->call('nextStep')
            ->set('is_accepting_new_clients', true)
            ->call('submitForReview')
            ->assertHasErrors(['task_preferences']);
    }

    public function test_admin_can_approve_under_review_profile(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'under_review',
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('approve', $profile->id);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);

        $freshProfile = $profile->fresh();
        $this->assertNotNull($freshProfile?->identity_verified_at);
        $this->assertNotNull($freshProfile?->background_check_verified_at);

        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'approved',
        ]);
    }

    public function test_admin_can_reject_under_review_profile_with_reason(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'role' => 'admin']);
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

    public function test_admin_cannot_approve_without_identity_verification(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'under_review',
            'identity_verification_status' => 'not_started',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('approve', $profile->id)
            ->assertHasErrors(['approval_'.$profile->id]);

        $this->assertDatabaseHas('caregiver_profiles', [
            'id' => $profile->id,
            'status' => 'under_review',
        ]);
    }

    public function test_admin_can_suspend_and_unsuspend_profile(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'role' => 'admin']);
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
            'years_experience' => 2,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', str_repeat('Updated profile content. ', 4))
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
        ]);

        $this->assertDatabaseHas('caregiver_profile_versions', [
            'caregiver_profile_id' => $profile->id,
            'reason' => 'profile_edit',
        ]);
    }

    public function test_onboarding_accepts_mobile_sized_profile_photo_upload(): void
    {
        Storage::fake('public');

        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 2)
            ->set('date_of_birth', now()->subYears(24)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->set('profile_photo', UploadedFile::fake()->image('profile-photo.jpg')->size(40000))
            ->call('nextStep')
            ->assertHasNoErrors(['profile_photo']);

        $savedProfile = CaregiverProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($savedProfile->profile_photo_path);
        $this->assertStringEndsWith('.jpg', $savedProfile->profile_photo_path);
        Storage::disk('public')->assertExists($savedProfile->profile_photo_path);
    }

    public function test_profile_editor_can_replace_profile_photo(): void
    {
        Storage::fake('public');

        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        Storage::disk('public')->put('caregiver-photos/old-photo.jpg', 'old photo');
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'profile_photo_path' => 'caregiver-photos/old-photo.jpg',
            'bio' => str_repeat('Bio ', 15),
            'years_experience' => 2,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', str_repeat('Updated profile content. ', 4))
            ->set('years_experience', 5)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27602')
            ->set('service_radius_miles', 15)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('profile_photo', UploadedFile::fake()->image('fresh-photo.png')->size(4500))
            ->call('save')
            ->assertHasNoErrors(['profile_photo']);

        $profile->refresh();

        $this->assertNotNull($profile->profile_photo_path);
        $this->assertStringEndsWith('.jpg', $profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);
        Storage::disk('public')->assertMissing('caregiver-photos/old-photo.jpg');
    }

    public function test_profile_photo_rejects_non_image_upload(): void
    {
        Storage::fake('public');

        $language = Language::query()->create(['name' => 'English']);
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        CaregiverProfile::query()->create(['user_id' => $user->id, 'status' => 'draft']);

        Livewire::actingAs($user)
            ->test(OnboardingWizard::class)
            ->set('bio', str_repeat('Great caregiver profile. ', 4))
            ->set('years_experience', 2)
            ->set('date_of_birth', now()->subYears(24)->toDateString())
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('selectedLanguages', [$language->id])
            ->set('profile_photo', UploadedFile::fake()->create('profile.pdf', 500, 'application/pdf'))
            ->call('nextStep')
            ->assertHasErrors(['profile_photo']);
    }

    public function test_search_page_shows_only_active_profiles(): void
    {
        $viewer = User::factory()->create();
        $activeUser = User::factory()->create(['name' => 'Active Caregiver', 'role' => 'caregiver']);
        $draftUser = User::factory()->create(['name' => 'Draft Caregiver', 'role' => 'caregiver']);

        $activeProfile = CaregiverProfile::query()->create([
            'user_id' => $activeUser->id,
            'slug' => 'active-caregiver-1',
            'status' => 'active',
            'bio' => str_repeat('Bio ', 12),
            'platform_hourly_rate' => 28,
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
        ]);
        $this->markProfileMarketplaceReady($activeProfile, 'active');

        CaregiverProfile::query()->create(['user_id' => $draftUser->id, 'slug' => 'draft-caregiver-1', 'status' => 'under_review']);

        $response = $this->actingAs($viewer)->get('/caregivers/search');

        $response->assertOk();
        $response->assertSee('Active Caregiver');
        $response->assertDontSee('Draft Caregiver');
    }

    public function test_search_page_shows_trust_badges_for_verified_top_caregiver(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);
        $caregiverUser = User::factory()->create([
            'name' => 'Trusted Caregiver',
            'role' => 'caregiver',
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiverUser->id,
            'slug' => 'trusted-caregiver-1',
            'status' => 'active',
            'bio' => str_repeat('Bio ', 12),
            'platform_hourly_rate' => 30,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'background_check_verified_at' => now(),
            'top_caregiver' => true,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);
        $this->markProfileMarketplaceReady(
            CaregiverProfile::query()->where('user_id', $caregiverUser->id)->firstOrFail(),
            'trusted'
        );

        $response = $this->actingAs($viewer)->get('/caregivers/search');

        $response->assertOk();
        $response->assertSee('Trusted Caregiver');
        $response->assertSee('Identity verified');
        $response->assertSee('Background check');
        $response->assertSee('Top Caregiver');
    }

    public function test_search_filters_apply_on_submit_without_live_typing_updates(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);

        $raleighUser = User::factory()->create([
            'name' => 'Raleigh Match',
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $durhamUser = User::factory()->create([
            'name' => 'Durham Helper',
            'role' => 'caregiver',
            'city' => 'Durham',
            'state' => 'NC',
        ]);

        $raleighProfile = CaregiverProfile::query()->create([
            'user_id' => $raleighUser->id,
            'slug' => 'raleigh-match',
            'status' => 'active',
            'bio' => str_repeat('Calm companionship support. ', 4),
            'platform_hourly_rate' => 30,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);
        $durhamProfile = CaregiverProfile::query()->create([
            'user_id' => $durhamUser->id,
            'slug' => 'durham-helper',
            'status' => 'active',
            'bio' => str_repeat('Reliable home support. ', 4),
            'platform_hourly_rate' => 30,
            'years_experience' => 6,
            'service_area_zip' => '27703',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);

        $this->markProfileMarketplaceReady($raleighProfile, 'raleigh-search');
        $this->markProfileMarketplaceReady($durhamProfile, 'durham-search');

        $html = $this->actingAs($viewer)
            ->get('/caregivers/search')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('wire:submit.prevent="applyFilters"', $html);
        $this->assertStringContainsString('wire:model="search"', $html);
        $this->assertStringNotContainsString('wire:model.live.debounce.300ms="search"', $html);

        Livewire::actingAs($viewer)
            ->test(BrowseCaregivers::class)
            ->assertSee('Raleigh Match')
            ->assertSee('Durham Helper')
            ->set('search', 'Raleigh')
            ->call('applyFilters')
            ->assertSee('Raleigh Match')
            ->assertDontSee('Durham Helper');
    }

    public function test_admin_can_toggle_trust_badges_for_active_caregiver(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('toggleIdentityVerification', $profile->id)
            ->call('toggleBackgroundCheck', $profile->id)
            ->call('toggleTopCaregiver', $profile->id);

        $freshProfile = $profile->fresh();
        $this->assertNotNull($freshProfile?->identity_verified_at);
        $this->assertNotNull($freshProfile?->background_check_verified_at);
        $this->assertTrue((bool) $freshProfile?->top_caregiver);
    }

    public function test_admin_moderation_logs_page_lists_recent_actions(): void
    {
        $admin = User::factory()->create(['email' => 'test@test.com', 'name' => 'Admin User', 'role' => 'admin']);
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

    private function markProfileMarketplaceReady(CaregiverProfile $profile, string $suffix): void
    {
        $skill = Skill::query()->firstOrCreate(['name' => 'Skill '.$suffix]);
        $language = Language::query()->firstOrCreate(['name' => 'Language '.$suffix]);
        $profile->skills()->syncWithoutDetaching([$skill->id]);
        $profile->languages()->syncWithoutDetaching([$language->id]);
        $profile->availabilities()->firstOrCreate([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $profile->forceFill([
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => $profile->identity_verified_at ?: now(),
            'identity_verification_status' => 'approved',
        ])->save();
    }
}
