<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Livewire\Admin\UserShow;
use App\Livewire\Caregiver\OnboardingWizard;
use App\Livewire\Caregiver\ProfileEditor;
use App\Mail\Ops\CaregiverReadyForReviewOpsAlertMail;
use App\Models\CaregiverCertification;
use App\Models\CaregiverCertificationType;
use App\Models\CaregiverExperienceType;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Caregiver\CaregiverBackgroundService;
use App\Services\Marketplace\CaregiverInvitationDiscoveryService;
use App\Support\CaregiverOnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CaregiverCareBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_provides_taxonomies_without_changing_legacy_profile_requirements(): void
    {
        $this->assertSame(10, CaregiverExperienceType::query()->where('active', true)->count());
        $this->assertSame(9, CaregiverCertificationType::query()->where('active', true)->count());

        [$caregiver, $profile] = $this->readyCaregiver('legacy', 'active');
        $beforeChecks = $profile->marketplaceReadinessChecks();
        $beforePercent = $profile->marketplaceCompletenessPercent();
        $state = app(CaregiverOnboardingState::class)->build($caregiver);

        $this->assertNull($profile->care_background_schema_version);
        $this->assertSame(3, $state['required_total']);
        $this->assertTrue($state['ready_for_review']);
        $this->assertSame($beforeChecks, $profile->fresh()->marketplaceReadinessChecks());
        $this->assertSame($beforePercent, $profile->fresh()->marketplaceCompletenessPercent());
        $this->assertTrue($profile->fresh()->isMarketplaceReady());
    }

    public function test_new_versioned_profile_must_answer_both_background_questions_before_submission(): void
    {
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('new-required', 'draft', true);

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('bio', $profile->bio)
            ->set('years_experience', 4)
            ->set('date_of_birth', $caregiver->date_of_birth->format('Y-m-d'))
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('is_accepting_new_clients', true)
            ->call('submitForReview')
            ->assertHasErrors(['selectedExperienceTypes', 'selectedCertificationTypes']);

        $this->assertSame('draft', $profile->fresh()->status);
        $this->assertNull($profile->fresh()->care_experience_answered_at);
    }

    public function test_legacy_draft_and_under_review_profiles_keep_the_existing_requirement_set(): void
    {
        [$draftUser, $draftProfile] = $this->readyCaregiver('legacy-draft', 'draft');
        [$reviewUser, $reviewProfile] = $this->readyCaregiver('legacy-review', 'under_review');

        foreach ([[$draftUser, $draftProfile], [$reviewUser, $reviewProfile]] as [$user, $profile]) {
            $state = app(CaregiverOnboardingState::class)->build($user);

            $this->assertNull($profile->care_background_schema_version);
            $this->assertSame(3, $state['required_total']);
            $this->assertTrue($state['ready_for_review']);
            $this->assertNull($profile->care_experience_answered_at);
            $this->assertNull($profile->certifications_answered_at);
        }
    }

    public function test_none_answers_are_valid_and_mutually_exclusive(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('none-valid', 'draft', true);
        $experience = CaregiverExperienceType::query()->firstOrFail();
        $certification = CaregiverCertificationType::query()->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->call('toggleExperience', (string) $experience->id)
            ->call('toggleExperience', CaregiverBackgroundService::NONE)
            ->assertSet('selectedExperienceTypes', [CaregiverBackgroundService::NONE])
            ->call('toggleExperience', (string) $experience->id)
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->call('toggleCertification', (string) $certification->id)
            ->call('toggleCertification', CaregiverBackgroundService::NONE)
            ->assertSet('selectedCertificationTypes', [CaregiverBackgroundService::NONE])
            ->call('toggleCertification', (string) $certification->id)
            ->assertSet('selectedCertificationTypes', [$certification->id]);

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [CaregiverBackgroundService::NONE, $experience->id])
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->set('selectedCertificationTypes', [CaregiverBackgroundService::NONE, $certification->id])
            ->assertSet('selectedCertificationTypes', [$certification->id]);

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [CaregiverBackgroundService::NONE])
            ->set('selectedCertificationTypes', [CaregiverBackgroundService::NONE])
            ->call('nextStep')
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertNotNull($profile->care_experience_answered_at);
        $this->assertNotNull($profile->certifications_answered_at);
        $this->assertCount(0, $profile->careExperiences);
        $this->assertCount(0, $profile->certifications);
    }

    public function test_onboarding_persists_normalized_experience_credentials_and_private_evidence(): void
    {
        Storage::fake('local');
        [$caregiver, $profile] = $this->readyCaregiver('onboarding-background', 'draft', true);
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $certification = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('care_experience_notes', 'I supported daily routines, redirection, and calm companionship.')
            ->set('selectedCertificationTypes', [$certification->id])
            ->set('certificationDetails.'.$certification->id.'.issuer', 'American Red Cross')
            ->set('certificationDetails.'.$certification->id.'.issuing_state', 'NC')
            ->set('certificationDetails.'.$certification->id.'.expires_at', now()->addYear()->toDateString())
            ->set('certificationDocuments.'.$certification->id, UploadedFile::fake()->create('cpr-proof.pdf', 120, 'application/pdf'))
            ->call('nextStep')
            ->assertHasNoErrors();

        $credential = CaregiverCertification::query()->where('caregiver_profile_id', $profile->id)->firstOrFail();
        $this->assertSame(CaregiverCertification::STATUS_PENDING, $credential->verification_status);
        $this->assertSame('American Red Cross', $credential->issuer);
        $this->assertStringStartsWith('caregiver-certifications/', $credential->document_path);
        Storage::disk('local')->assertExists($credential->document_path);
        $this->assertDatabaseHas('caregiver_profile_experience', [
            'caregiver_profile_id' => $profile->id,
            'caregiver_experience_type_id' => $experience->id,
        ]);
        $version = $profile->versions()->latest()->firstOrFail();
        $this->assertArrayHasKey('care_background', $version->snapshot);
        $this->assertArrayNotHasKey('document_path', $version->snapshot['care_background']['certifications'][0]);
    }

    public function test_background_survives_validation_navigation_reload_and_profile_editing_with_other_name_validation(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('background-reload', 'draft', true);
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $other = CaregiverCertificationType::query()->where('slug', 'other')->firstOrFail();

        $wizard = Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('care_experience_notes', 'General memory-support routines without identifying a former client.')
            ->set('selectedCertificationTypes', [$other->id])
            ->call('nextStep')
            ->assertHasErrors(['certificationDetails.'.$other->id.'.custom_name'])
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->assertSet('selectedCertificationTypes', [$other->id]);

        $wizard
            ->set('certificationDetails.'.$other->id.'.custom_name', 'Senior companion training')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->call('previousStep')
            ->assertSet('step', 2)
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->assertSet('selectedCertificationTypes', [$other->id]);

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->assertSet('selectedCertificationTypes', [$other->id])
            ->assertSet('certificationDetails.'.$other->id.'.custom_name', 'Senior companion training');

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->assertSet('selectedExperienceTypes', [$experience->id])
            ->assertSet('selectedCertificationTypes', [$other->id])
            ->assertSet('certificationDetails.'.$other->id.'.custom_name', 'Senior companion training');

        $this->assertTrue($profile->fresh()->careBackgroundIsAnswered());
    }

    public function test_legacy_active_caregiver_can_save_unrelated_profile_fields_without_answering_background(): void
    {
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('legacy-edit', 'active');

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', str_repeat('Updated legacy caregiver profile. ', 3))
            ->set('years_experience', 6)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27602')
            ->set('service_radius_miles', 15)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->call('save')
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertSame('active', $profile->status);
        $this->assertNull($profile->care_experience_answered_at);
        $this->assertNull($profile->certifications_answered_at);
        $this->assertTrue($profile->isMarketplaceReady());
    }

    public function test_profile_editor_updates_background_without_deactivating_caregiver_and_logs_change(): void
    {
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('background-edit', 'active');
        $experience = CaregiverExperienceType::query()->where('slug', 'mobility-fall-risk')->firstOrFail();
        $certification = CaregiverCertificationType::query()->where('slug', 'first-aid')->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', $profile->bio)
            ->set('years_experience', 4)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('selectedCertificationTypes', [$certification->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('active', $profile->fresh()->status);
        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'care_background_updated',
        ]);
        $this->assertDatabaseHas('caregiver_certifications', [
            'caregiver_profile_id' => $profile->id,
            'verification_status' => CaregiverCertification::STATUS_SELF_REPORTED,
        ]);
    }

    public function test_public_profile_qualifies_self_reported_verified_and_expired_credentials(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        [$caregiver, $profile] = $this->readyCaregiver('public-background', 'active');
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $cpr = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        $firstAid = CaregiverCertificationType::query()->where('slug', 'first-aid')->firstOrFail();

        $profile->forceFill([
            'care_experience_answered_at' => now(),
            'certifications_answered_at' => now(),
            'care_experience_notes' => 'General memory-care routine support.',
        ])->save();
        $profile->careExperiences()->sync([$experience->id]);
        $profile->certifications()->create([
            'caregiver_certification_type_id' => $cpr->id,
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        $profile->certifications()->create([
            'caregiver_certification_type_id' => $firstAid->id,
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'family']))
            ->get(route('caregivers.show', $profile->slug))
            ->assertOk()
            ->assertSee('Care experience')
            ->assertSee('Self-reported by caregiver')
            ->assertSee('Memory loss, dementia, or Alzheimer&#039;s support', false)
            ->assertSee('LoLo verified')
            ->assertSee('Expired')
            ->assertDontSee('document_path');
    }

    public function test_legacy_unanswered_public_profile_hides_background_sections_and_remains_searchable(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        [$caregiver, $profile] = $this->readyCaregiver('legacy-public', 'active');
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('caregivers.show', $profile->slug))
            ->assertOk()
            ->assertDontSee('Care experience')
            ->assertDontSee('Credentials &amp; training', false);

        $this->actingAs($family)
            ->get('/caregivers/search')
            ->assertOk()
            ->assertSee($caregiver->name);
    }

    public function test_legacy_unanswered_caregiver_remains_available_in_invitation_search(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('legacy-invitation', 'active');
        $family = User::factory()->create(['role' => 'family']);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Legacy invitation compatibility',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '100 Compatibility Lane',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $results = app(CaregiverInvitationDiscoveryService::class)
            ->search($request, $family, 'legacy-invitation');

        $this->assertTrue($results->contains('user_id', $caregiver->id));
        $this->assertSame([], $results->firstWhere('user_id', $caregiver->id)['care_background_tags']);
        $this->assertNull($profile->care_experience_answered_at);
    }

    public function test_admin_can_verify_evidence_and_non_admin_cannot_access_or_review_it(): void
    {
        Storage::fake('local');
        [$caregiver, $profile] = $this->readyCaregiver('admin-review', 'active');
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        Storage::disk('local')->put('caregiver-certifications/proof.pdf', 'valid proof');
        $credential = $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'document_path' => 'caregiver-certifications/proof.pdf',
            'document_original_name' => 'proof.pdf',
            'document_mime' => 'application/pdf',
            'document_size' => 11,
            'verification_status' => CaregiverCertification::STATUS_PENDING,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        [$otherCaregiver] = $this->readyCaregiver('document-idor', 'active');

        $this->actingAs($family)
            ->get(route('caregiver.certifications.document', $credential))
            ->assertForbidden();
        $this->actingAs($caregiver)
            ->get(route('caregiver.certifications.document', $credential))
            ->assertOk();
        $this->actingAs($otherCaregiver)
            ->get(route('caregiver.certifications.document', $credential))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('caregiver.certifications.document', $credential))
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->call('verifyCertification', $credential->id)
            ->assertHasNoErrors();

        $credential->refresh();
        $this->assertTrue($credential->isCurrentlyVerified());
        $this->assertSame($admin->id, $credential->verified_by_user_id);
        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'credential_verified',
        ]);

        Livewire::actingAs($family)
            ->test(CaregiverReviewsQueue::class)
            ->call('verifyCertification', $credential->id)
            ->assertForbidden();
    }

    public function test_admin_can_return_credential_with_audited_reason(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('admin-reject', 'active');
        $type = CaregiverCertificationType::query()->where('slug', 'first-aid')->firstOrFail();
        $credential = $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'verification_status' => CaregiverCertification::STATUS_SELF_REPORTED,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->set('certificationRejectionReasons.'.$credential->id, 'The uploaded information does not show a current expiration date.')
            ->call('rejectCertification', $credential->id)
            ->assertHasNoErrors();

        $credential->refresh();
        $this->assertSame(CaregiverCertification::STATUS_REJECTED, $credential->verification_status);
        $this->assertSame('The uploaded information does not show a current expiration date.', $credential->rejection_reason);
        $this->assertDatabaseHas('caregiver_moderation_logs', [
            'caregiver_profile_id' => $profile->id,
            'action' => 'credential_rejected',
        ]);
        $this->assertSame('active', $profile->fresh()->status);
    }

    public function test_search_cards_cap_background_tags_and_never_expose_document_paths(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        [$caregiver, $profile] = $this->readyCaregiver('tag-cap', 'active');
        $experiences = CaregiverExperienceType::query()->orderBy('sort_order')->take(4)->get();
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        $profile->careExperiences()->sync($experiences->pluck('id'));
        $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'document_path' => 'caregiver-certifications/private-secret.pdf',
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $html = $this->actingAs(User::factory()->create(['role' => 'family']))
            ->get('/caregivers/search')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('CPR', $html);
        $this->assertStringContainsString('LoLo verified', $html);
        $this->assertStringContainsString($experiences[0]->label, html_entity_decode($html));
        $this->assertStringContainsString($experiences[1]->label, html_entity_decode($html));
        $this->assertStringContainsString($experiences[2]->label, html_entity_decode($html));
        $this->assertStringNotContainsString($experiences[3]->label, html_entity_decode($html));
        $this->assertStringNotContainsString('private-secret.pdf', $html);
    }

    public function test_admin_queue_and_user_detail_show_safe_background_review_information(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('admin-summary', 'under_review');
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        $profile->forceFill([
            'care_experience_notes' => 'General routine and companionship support.',
            'care_experience_answered_at' => now(),
            'certifications_answered_at' => now(),
        ])->save();
        $profile->careExperiences()->sync([$experience->id]);
        $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'issuer' => 'American Red Cross',
            'verification_status' => CaregiverCertification::STATUS_SELF_REPORTED,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(CaregiverReviewsQueue::class)
            ->assertSee('Memory loss, dementia, or Alzheimer')
            ->assertSee('American Red Cross')
            ->assertDontSee('document_path');

        Livewire::actingAs($admin)
            ->test(UserShow::class, ['user' => $caregiver])
            ->assertSee('Memory loss, dementia, or Alzheimer')
            ->assertSee('American Red Cross')
            ->assertDontSee('document_path');
    }

    public function test_ready_for_review_email_summarizes_background_without_private_document_link(): void
    {
        [$caregiver, $profile] = $this->readyCaregiver('mail-summary', 'under_review', true);
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        $profile->forceFill([
            'care_experience_answered_at' => now(),
            'certifications_answered_at' => now(),
        ])->save();
        $profile->careExperiences()->sync([$experience->id]);
        $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'document_path' => 'caregiver-certifications/never-email-this.pdf',
            'verification_status' => CaregiverCertification::STATUS_PENDING,
        ]);

        $html = (new CaregiverReadyForReviewOpsAlertMail($caregiver, $profile))->render();

        $this->assertStringContainsString('Memory loss, dementia, or Alzheimer', html_entity_decode($html));
        $this->assertStringContainsString('CPR', $html);
        $this->assertStringNotContainsString('never-email-this.pdf', $html);
        $this->assertStringNotContainsString('/caregiver/certifications/', $html);
    }

    public function test_editing_verified_credential_resets_only_credential_status_and_replaces_file_safely(): void
    {
        Storage::fake('local');
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('credential-reset', 'active');
        $experience = CaregiverExperienceType::query()->where('slug', 'memory-care')->firstOrFail();
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        Storage::disk('local')->put('caregiver-certifications/old.pdf', 'old');
        $profile->forceFill(['care_experience_answered_at' => now(), 'certifications_answered_at' => now()])->save();
        $profile->careExperiences()->sync([$experience->id]);
        $credential = $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'issuer' => 'Old issuer',
            'document_path' => 'caregiver-certifications/old.pdf',
            'document_original_name' => 'old.pdf',
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_by_user_id' => User::factory()->create(['role' => 'admin'])->id,
            'verified_at' => now(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', $profile->bio)
            ->set('years_experience', 4)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('certificationDetails.'.$type->id.'.issuer', 'New issuer')
            ->set('certificationDocuments.'.$type->id, UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $credential->refresh();
        $this->assertSame('active', $profile->fresh()->status);
        $this->assertSame(CaregiverCertification::STATUS_PENDING, $credential->verification_status);
        $this->assertNull($credential->verified_at);
        $this->assertSame('New issuer', $credential->issuer);
        Storage::disk('local')->assertMissing('caregiver-certifications/old.pdf');
        Storage::disk('local')->assertExists($credential->document_path);
    }

    public function test_invalid_credential_upload_is_rejected_without_storing_a_file(): void
    {
        Storage::fake('local');
        [$caregiver] = $this->readyCaregiver('invalid-upload', 'draft', true);
        $experience = CaregiverExperienceType::query()->firstOrFail();
        $type = CaregiverCertificationType::query()->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('selectedCertificationTypes', [$type->id])
            ->set('certificationDocuments.'.$type->id, UploadedFile::fake()->create('script.exe', 20, 'application/x-msdownload'))
            ->call('nextStep')
            ->assertHasErrors(['certificationDocuments.'.$type->id]);

        $this->assertSame([], Storage::disk('local')->allFiles('caregiver-certifications'));
    }

    public function test_oversized_credential_upload_is_rejected(): void
    {
        Storage::fake('local');
        [$caregiver] = $this->readyCaregiver('oversized-upload', 'draft', true);
        $experience = CaregiverExperienceType::query()->firstOrFail();
        $type = CaregiverCertificationType::query()->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(OnboardingWizard::class)
            ->set('step', 2)
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('selectedCertificationTypes', [$type->id])
            ->set('certificationDocuments.'.$type->id, UploadedFile::fake()->create('too-large.pdf', 6145, 'application/pdf'))
            ->call('nextStep')
            ->assertHasErrors(['certificationDocuments.'.$type->id]);

        $this->assertSame([], Storage::disk('local')->allFiles('caregiver-certifications'));
    }

    public function test_caregiver_can_remove_private_evidence_without_removing_the_reported_credential(): void
    {
        Storage::fake('local');
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('remove-evidence', 'active');
        $experience = CaregiverExperienceType::query()->firstOrFail();
        $type = CaregiverCertificationType::query()->firstOrFail();
        Storage::disk('local')->put('caregiver-certifications/remove-me.pdf', 'proof');
        $profile->forceFill(['care_experience_answered_at' => now(), 'certifications_answered_at' => now()])->save();
        $profile->careExperiences()->sync([$experience->id]);
        $credential = $profile->certifications()->create([
            'caregiver_certification_type_id' => $type->id,
            'document_path' => 'caregiver-certifications/remove-me.pdf',
            'document_original_name' => 'remove-me.pdf',
            'document_mime' => 'application/pdf',
            'document_size' => 5,
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', $profile->bio)
            ->set('years_experience', 4)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->call('removeCertificationDocument', $type->id)
            ->call('save')
            ->assertHasNoErrors();

        $credential->refresh();
        $this->assertNull($credential->document_path);
        $this->assertSame(CaregiverCertification::STATUS_SELF_REPORTED, $credential->verification_status);
        $this->assertSame('active', $profile->fresh()->status);
        Storage::disk('local')->assertMissing('caregiver-certifications/remove-me.pdf');
    }

    public function test_failed_database_save_discards_new_private_evidence(): void
    {
        Storage::fake('local');
        [$caregiver, $profile, $skill, $language] = $this->readyCaregiver('failed-save-cleanup', 'active');
        $experience = CaregiverExperienceType::query()->firstOrFail();
        $type = CaregiverCertificationType::query()->firstOrFail();

        $component = Livewire::actingAs($caregiver)
            ->test(ProfileEditor::class)
            ->set('bio', $profile->bio)
            ->set('years_experience', 4)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('service_area_zip', '27601')
            ->set('service_radius_miles', 10)
            ->set('selectedSkills', [$skill->id])
            ->set('selectedLanguages', [$language->id])
            ->set('selectedExperienceTypes', [$experience->id])
            ->set('selectedCertificationTypes', [$type->id])
            ->set('certificationDocuments.'.$type->id, UploadedFile::fake()->create('rollback.pdf', 100, 'application/pdf'));

        $thrown = false;
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_contains($query, 'caregiver_profile_versions')) {
                throw new RuntimeException('Simulated profile-version write failure.');
            }
        });

        try {
            $component->call('save');
        } catch (RuntimeException $exception) {
            $thrown = true;
            $this->assertSame('Simulated profile-version write failure.', $exception->getMessage());
        }

        $this->assertTrue($thrown);
        $this->assertSame([], Storage::disk('local')->allFiles('caregiver-certifications'));
        $this->assertDatabaseMissing('caregiver_certifications', [
            'caregiver_profile_id' => $profile->id,
            'caregiver_certification_type_id' => $type->id,
        ]);
    }

    /**
     * @return array{0:User,1:CaregiverProfile,2:Skill,3:Language}
     */
    private function readyCaregiver(string $suffix, string $status, bool $newSchema = false): array
    {
        $skill = Skill::query()->firstOrCreate(['name' => 'Companionship '.$suffix]);
        $language = Language::query()->firstOrCreate(['name' => 'English '.$suffix]);
        $caregiver = User::factory()->create([
            'name' => 'Caregiver '.$suffix,
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(35)->toDateString(),
        ]);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'caregiver-'.$suffix,
            'status' => $status,
            'bio' => str_repeat('Reliable non-medical caregiver support. ', 3),
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'care_background_schema_version' => $newSchema ? CaregiverBackgroundService::SCHEMA_VERSION : null,
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        return [$caregiver, $profile, $skill, $language];
    }
}
