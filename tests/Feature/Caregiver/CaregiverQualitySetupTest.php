<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\InsuranceSetup;
use App\Livewire\Caregiver\IntroVideoSetup;
use App\Livewire\Caregiver\TaskComfortSetup;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverQualitySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_dashboard_shows_quality_setup_cards(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Identity verification');
        $response->assertSee('Task comfort selection');
        $response->assertSee('Insurance setup');
        $response->assertSee('Intro video');
    }

    public function test_caregiver_can_save_task_comfort_preferences(): void
    {
        $skillA = Skill::query()->create(['name' => 'Companionship']);
        $skillB = Skill::query()->create(['name' => 'Meal preparation']);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($caregiver)
            ->test(TaskComfortSetup::class)
            ->set('selectedSkills', [$skillA->id, $skillB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('caregiver_skill', [
            'caregiver_profile_id' => $caregiver->caregiverProfile->id,
            'skill_id' => $skillA->id,
        ]);
        $this->assertDatabaseHas('caregiver_skill', [
            'caregiver_profile_id' => $caregiver->caregiverProfile->id,
            'skill_id' => $skillB->id,
        ]);
    }

    public function test_caregiver_can_save_insurance_yes_with_document(): void
    {
        Storage::fake('public');

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($caregiver)
            ->test(InsuranceSetup::class)
            ->set('insurance_status', CaregiverProfile::INSURANCE_YES)
            ->set('insurance_document', UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => $caregiver->id,
            'insurance_status' => CaregiverProfile::INSURANCE_YES,
        ]);

        $profile = $caregiver->fresh()->caregiverProfile;
        $this->assertNotNull($profile?->insurance_document_path);
    }

    public function test_caregiver_can_upload_intro_video(): void
    {
        Storage::fake('public');

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($caregiver)
            ->test(IntroVideoSetup::class)
            ->set('intro_video', UploadedFile::fake()->create('intro.mp4', 1024, 'video/mp4'))
            ->call('save')
            ->assertHasNoErrors();

        $profile = $caregiver->fresh()->caregiverProfile;

        $this->assertNotNull($profile?->intro_video_path);
        $this->assertNotNull($profile?->intro_video_uploaded_at);
    }

    public function test_completed_setup_card_is_hidden_from_dashboard(): void
    {
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
            'bio' => str_repeat('Experienced caregiver profile. ', 4),
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Task comfort selection');
        $response->assertDontSee('Complete profile basics');
        $response->assertSee('Insurance setup');
    }
}
