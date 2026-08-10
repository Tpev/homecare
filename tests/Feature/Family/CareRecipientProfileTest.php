<?php

namespace Tests\Feature\Family;

use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\FamilyAccountMember;
use App\Models\FamilyRecipientProfile;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileAttachmentService;
use App\Services\CareRecipientProfiles\CareRecipientProfileBackfill;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\CareRecipientProfiles\CareRecipientProfileSnapshotBuilder;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use App\Services\RegularCare\CarePlanService;
use App\Notifications\MarketplaceEventNotification;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\CreateCareRequestWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Livewire\Livewire;
use Tests\TestCase;

class CareRecipientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_versions_are_immutable_and_candidate_snapshot_excludes_private_fields(): void
    {
        $owner = $this->family('profile-owner@example.com');
        $this->actingAs($owner);
        $profile = $this->readyProfile($owner, [
            'full_name' => 'Charles Petrini-Poli',
            'preferred_name' => 'Charles',
            'date_of_birth' => '1942-04-09',
            'about_them' => 'Charles enjoys baseball and quiet conversation.',
            'good_visit_notes' => 'Explain the plan slowly before starting.',
            'include_additional_contact' => true,
            'additional_contact_name' => 'Sarah',
            'additional_contact_phone' => '919-555-0100',
            'additional_contact_email' => 'sarah@example.com',
        ]);

        $versionOne = $profile->latestReadyVersion;
        $candidateJson = json_encode($versionOne->candidate_snapshot, JSON_THROW_ON_ERROR);
        $assignedJson = json_encode($versionOne->assigned_snapshot, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('1942-04-09', $candidateJson);
        $this->assertStringNotContainsString('919-555-0100', $candidateJson);
        $this->assertStringNotContainsString('sarah@example.com', $candidateJson);
        $this->assertArrayNotHasKey('full_name', $versionOne->candidate_snapshot);
        $this->assertStringContainsString('919-555-0100', $assignedJson);
        app(CareRecipientProfileSnapshotBuilder::class)->assertCandidateSafe($versionOne->candidate_snapshot);

        $service = app(CareRecipientProfileService::class);
        $updated = $service->makeReady($owner, $profile, [
            ...$profile->toArray(),
            'about_them' => 'Charles now prefers conversations in the sunroom.',
        ], (int) $profile->revision, true);

        $this->assertSame(2, $updated->latestReadyVersion->version_number);
        $this->assertSame(
            'Charles enjoys baseball and quiet conversation.',
            data_get($versionOne->fresh()->candidate_snapshot, 'sections.at_a_glance.about'),
        );

        $this->expectException(LogicException::class);
        $versionOne->forceFill(['candidate_snapshot' => ['preferred_name' => 'Changed']])->save();
    }

    public function test_candidate_snapshot_verification_rejects_private_keys(): void
    {
        $this->expectException(ValidationException::class);

        app(CareRecipientProfileSnapshotBuilder::class)->assertCandidateSafe([
            'sections' => ['contact' => ['full_name' => 'Private name']],
        ]);
    }

    public function test_stale_family_editor_cannot_overwrite_a_newer_revision(): void
    {
        $owner = $this->family('revision-owner@example.com');
        $this->actingAs($owner);
        $service = app(CareRecipientProfileService::class);
        $draft = $service->saveDraft($owner, null, [
            'preferred_name' => 'Charles',
            'about_them' => 'Original detail.',
        ]);
        $staleRevision = (int) $draft->revision;
        $service->saveDraft($owner, $draft, [
            'preferred_name' => 'Charles',
            'about_them' => 'Saved by another family member.',
        ], $staleRevision);

        try {
            $service->saveDraft($owner, $draft, [
                'preferred_name' => 'Charles',
                'about_them' => 'Stale overwrite.',
            ], $staleRevision);
            $this->fail('A stale profile edit overwrote the current revision.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('profile', $exception->errors());
        }

        $this->assertSame('Saved by another family member.', $draft->fresh()->about_them);
    }

    public function test_family_members_share_profiles_but_other_accounts_and_caregivers_cannot_open_editor(): void
    {
        $owner = $this->family('shared-owner@example.com');
        $account = app(FamilyAccountContext::class)->account($owner);
        $member = $this->family('shared-member@example.com');
        FamilyAccountMember::query()->where('user_id', $member->id)->delete();
        $sharedMembership = FamilyAccountMember::query()->create([
            'family_account_id' => $account->id,
            'user_id' => $member->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner);
        $profile = $this->readyProfile($owner);

        $this->actingAs($member)
            ->get(route('family.care-profiles.edit', $profile))
            ->assertOk();

        $sharedMembership->forceFill(['status' => FamilyAccountMember::STATUS_REMOVED, 'ended_at' => now()])->save();
        $this->actingAs($member)
            ->get(route('family.care-profiles.edit', $profile))
            ->assertNotFound();

        $other = $this->family('different-family@example.com');
        $this->actingAs($other)
            ->get(route('family.care-profiles.edit', $profile->id))
            ->assertNotFound();

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiver)
            ->get(route('family.care-profiles.edit', $profile->id))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('family.care-profiles.edit', $profile->id))
            ->assertRedirect(route('login'));
    }

    public function test_request_attachment_pins_a_version_and_presenter_uses_candidate_allowlist(): void
    {
        $owner = $this->family('request-owner@example.com');
        $this->actingAs($owner);
        $account = app(FamilyAccountContext::class)->account($owner);
        $profile = $this->readyProfile($owner, [
            'about_them' => 'Charles loves jazz.',
            'include_additional_contact' => true,
            'additional_contact_name' => 'Anne',
            'additional_contact_phone' => '919-555-0123',
        ]);
        $request = CareRequest::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'title' => 'Companion visit',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '100 Private Lane',
            'home_access_notes' => 'Door code 1234',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $recipient = $request->recipient()->create([
            'full_name' => 'Charles Petrini-Poli',
            'relationship_to_family' => 'Father',
        ]);

        app(CareRecipientProfileAttachmentService::class)->attachToRequestRecipient($recipient, $profile, $owner);
        $recipient->refresh();
        $pinnedVersionId = $recipient->care_recipient_profile_version_id;
        $this->assertSame($profile->id, $recipient->care_recipient_profile_id);

        $caregiver = $this->readyCaregiver('candidate-profile');
        $candidate = app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request->fresh());
        $this->assertSame('Charles', $candidate['preferred_name']);
        $this->assertArrayNotHasKey('contacts_and_care_coordination', $candidate);
        $this->assertStringNotContainsString('Private Lane', json_encode($candidate, JSON_THROW_ON_ERROR));

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Charles')
            ->assertDontSee('Charles Petrini-Poli')
            ->assertDontSee('100 Private Lane')
            ->assertDontSee('Door code 1234');

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'family_terms_accepted_at' => now(),
            'caregiver_terms_accepted_at' => now(),
        ]);
        $assigned = app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request->fresh());
        $this->assertSame('919-555-0123', data_get($assigned, 'contacts_and_care_coordination.phone'));
        $this->assertFalse($assigned['_is_updated']);

        $profile = app(CareRecipientProfileService::class)->makeReady($owner, $profile, [
            ...$profile->toArray(),
            'about_them' => 'Charles now prefers classical music.',
        ], (int) $profile->revision, true);
        $this->assertNotSame($pinnedVersionId, $profile->latest_ready_version_id);
        $this->assertSame($pinnedVersionId, $recipient->fresh()->care_recipient_profile_version_id);
    }

    public function test_attachment_rejects_a_profile_from_another_family_account(): void
    {
        $first = $this->family('first-account@example.com');
        $this->actingAs($first);
        $firstAccount = app(FamilyAccountContext::class)->account($first);
        $request = CareRequest::query()->create([
            'family_account_id' => $firstAccount->id,
            'family_user_id' => $first->id,
            'title' => 'First family care',
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '1 First St',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $recipient = $request->recipient()->create(['full_name' => 'First recipient', 'relationship_to_family' => 'Father']);

        $second = $this->family('second-account@example.com');
        $this->actingAs($second);
        $otherProfile = $this->readyProfile($second);

        $this->actingAs($first);
        $this->expectException(ModelNotFoundException::class);
        app(CareRecipientProfileAttachmentService::class)->attachToRequestRecipient($recipient, $otherProfile, $first);
    }

    public function test_legacy_backfill_is_idempotent_and_only_attaches_an_exact_name_match(): void
    {
        $owner = $this->family('backfill-owner@example.com');
        $this->actingAs($owner);
        $account = app(FamilyAccountContext::class)->account($owner);
        $legacy = FamilyRecipientProfile::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'full_name' => 'Charles Petrini-Poli',
            'relationship_to_family' => 'Father',
            'care_notes' => 'Enjoys a calm morning routine.',
            'mobility_level' => 'independent',
        ]);
        $matching = CareRequest::query()->create([
            'family_account_id' => $account->id, 'family_user_id' => $owner->id,
            'title' => 'Matching request', 'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '1 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $matchingRecipient = $matching->recipient()->create(['full_name' => 'Charles Petrini-Poli', 'relationship_to_family' => 'Father']);
        $different = CareRequest::query()->create([
            'family_account_id' => $account->id, 'family_user_id' => $owner->id,
            'title' => 'Different request', 'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '2 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $differentRecipient = $different->recipient()->create(['full_name' => 'Charlotte Petrini-Poli', 'relationship_to_family' => 'Mother']);

        $service = app(CareRecipientProfileBackfill::class);
        $service->run();
        $service->run();

        $this->assertSame(1, CareRecipientProfile::query()->withoutGlobalScopes()->where('legacy_family_recipient_profile_id', $legacy->id)->count());
        $this->assertNotNull($matchingRecipient->fresh()->care_recipient_profile_version_id);
        $this->assertNull($differentRecipient->fresh()->care_recipient_profile_version_id);
        $version = CareRecipientProfileVersion::query()->findOrFail($matchingRecipient->fresh()->care_recipient_profile_version_id);
        $this->assertStringNotContainsString('calm morning routine', json_encode($version->candidate_snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_continuous_coverage_uses_one_pinned_version_and_revokes_removed_roster_access(): void
    {
        config()->set('marketplace.continuous_coverage.enabled', true);
        config()->set('marketplace.continuous_coverage.pilot_emails', []);
        config()->set('marketplace.continuous_coverage.generation_weeks', 1);
        $owner = $this->family('coverage-profile-owner@example.com');
        $this->actingAs($owner);
        $profile = $this->readyProfile($owner, [
            'include_additional_contact' => true,
            'additional_contact_name' => 'Sarah',
            'additional_contact_phone' => '919-555-0199',
        ]);

        $plan = app(ContinuousCoverageScheduleService::class)->createPlan($owner, [
            'title' => 'Charles continuous care',
            'timezone' => 'America/New_York',
            'starts_on' => now('America/New_York')->addDay()->toDateString(),
            'ends_on' => null,
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 720,
            'coverage_start_time' => '07:00',
            'coverage_end_time' => '07:00',
            'custom_windows' => [],
            'recipient_snapshot' => ['full_name' => 'Charles Petrini-Poli', 'relationship_to_family' => 'Father'],
            'address_snapshot' => ['address_line1' => '100 Private Lane', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
            'task_snapshot' => [],
            'care_notes' => 'Companionship.',
            'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_FAMILY,
            'marketplace_applications_enabled' => true,
            'care_recipient_profile_id' => $profile->id,
        ]);

        $this->assertSame($profile->id, $plan->care_recipient_profile_id);
        $this->assertSame($profile->latest_ready_version_id, $plan->care_recipient_profile_version_id);
        $this->assertTrue($plan->templates->isNotEmpty());
        $this->assertTrue($plan->shifts->every(fn ($shift) => (int) $shift->continuous_coverage_plan_id === (int) $plan->id));

        $caregiver = $this->readyCaregiver('coverage-candidate');
        $presenter = app(CareRecipientProfilePresenter::class);
        $candidate = $presenter->forCoveragePlan($caregiver, $plan);
        $this->assertSame('Charles', $candidate['preferred_name']);
        $this->assertArrayNotHasKey('contacts_and_care_coordination', $candidate);

        $member = ContinuousCoverageRosterMember::query()->create([
            'continuous_coverage_plan_id' => $plan->id,
            'caregiver_user_id' => $caregiver->id,
            'invited_by_user_id' => $owner->id,
            'status' => ContinuousCoverageRosterMember::STATUS_ACTIVE,
            'role' => ContinuousCoverageRosterMember::ROLE_PRIMARY,
            'family_approved_at' => now(),
            'caregiver_accepted_at' => now(),
        ]);
        $assigned = $presenter->forCoveragePlan($caregiver, $plan->fresh());
        $this->assertSame('919-555-0199', data_get($assigned, 'contacts_and_care_coordination.phone'));

        $member->forceFill(['status' => ContinuousCoverageRosterMember::STATUS_REMOVED, 'removed_at' => now()])->save();
        $this->assertNull($presenter->forCoveragePlan($caregiver, $plan->fresh()));
    }

    public function test_regular_care_plan_and_generated_visits_inherit_the_source_version(): void
    {
        config()->set('services.stripe.bypass', true);
        $owner = $this->family('regular-profile-owner@example.com');
        $this->actingAs($owner);
        $account = app(FamilyAccountContext::class)->account($owner);
        $profile = $this->readyProfile($owner);
        $caregiver = $this->readyCaregiver('regular-profile');
        $task = CareTask::query()->firstOrCreate(['name' => 'Profile companionship']);
        $request = CareRequest::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'title' => 'Completed visit for Charles',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'scope_of_work' => 'Companionship.',
            'requested_start_at' => now()->subWeek()->setTime(9, 0),
            'requested_end_at' => now()->subWeek()->setTime(11, 0),
            'address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $recipient = $request->recipient()->create([
            'full_name' => 'Charles Petrini-Poli',
            'relationship_to_family' => 'Father',
        ]);
        app(CareRecipientProfileAttachmentService::class)->attachToRequestRecipient($recipient, $profile, $owner);
        $request->tasks()->sync([$task->id => ['task_note' => null]]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subWeek()->setTime(9, 0),
            'scheduled_end_at' => now()->subWeek()->setTime(11, 0),
            'completed_at' => now()->subWeek()->setTime(11, 0),
            'reviewed_at' => now()->subDays(6),
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);
        $request = $request->fresh(['recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile']);
        $firstVisit = now()->addDay();

        $plan = app(CarePlanService::class)->sendOfferFromRequest($request, $owner, [
            'title' => 'Regular care for Charles',
            'schedule_days' => [$firstVisit->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '11:00',
            'starts_on' => $firstVisit->toDateString(),
            'ends_on' => null,
            'care_notes' => 'Morning companionship.',
        ]);

        $this->assertSame($profile->id, $plan->care_recipient_profile_id);
        $this->assertSame($profile->latest_ready_version_id, $plan->care_recipient_profile_version_id);
        $this->assertSame($profile->id, $plan->relationship->care_recipient_profile_id);

        $plan = app(CarePlanService::class)->acceptOffer($plan, $caregiver);
        $generated = CareRequest::query()->where('care_plan_id', $plan->id)->where('id', '!=', $request->id)->firstOrFail();
        $this->assertSame($profile->id, $generated->recipient->care_recipient_profile_id);
        $this->assertSame($profile->latest_ready_version_id, $generated->recipient->care_recipient_profile_version_id);
    }

    public function test_family_can_apply_latest_version_to_active_care_and_only_assigned_caregiver_is_notified(): void
    {
        Notification::fake();
        $owner = $this->family('active-update-owner@example.com');
        $this->actingAs($owner);
        $account = app(FamilyAccountContext::class)->account($owner);
        $profile = $this->readyProfile($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $candidateOnly = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'title' => 'Active Charles visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $recipient = $request->recipient()->create([
            'full_name' => 'Charles Petrini-Poli',
            'relationship_to_family' => 'Father',
        ]);
        app(CareRecipientProfileAttachmentService::class)->attachToRequestRecipient($recipient, $profile, $owner);
        $oldVersionId = $recipient->fresh()->care_recipient_profile_version_id;
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => $account->id,
            'family_user_id' => $owner->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'family_terms_accepted_at' => now(),
            'caregiver_terms_accepted_at' => now(),
        ]);

        $firstAssignedView = app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request->fresh());
        $this->assertFalse($firstAssignedView['_is_updated']);

        $profile = app(CareRecipientProfileService::class)->makeReady($owner, $profile, [
            ...$profile->toArray(),
            'about_them' => 'Updated private care-profile narrative that must not enter the notification.',
        ], (int) $profile->revision, true);
        $result = app(CareRecipientProfileAttachmentService::class)->applyLatestToActiveCare($profile, $owner);

        $this->assertSame(1, $result['requests']);
        $this->assertSame(1, $result['notified']);
        $this->assertNotSame($oldVersionId, $recipient->fresh()->care_recipient_profile_version_id);
        $this->assertSame($profile->latest_ready_version_id, $recipient->fresh()->care_recipient_profile_version_id);
        $updatedAssignedView = app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request->fresh());
        $this->assertTrue($updatedAssignedView['_is_updated']);
        $this->assertFalse(app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request->fresh())['_is_updated']);
        Notification::assertSentTo($caregiver, MarketplaceEventNotification::class, function ($notification) use ($caregiver): bool {
            $payload = $notification->toArray($caregiver);

            return $payload['title'] === 'Care profile updated'
                && ! str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'Updated private care-profile narrative');
        });
        Notification::assertNotSentTo($candidateOnly, MarketplaceEventNotification::class);
    }

    public function test_archive_restore_and_default_selection_preserve_versions_and_legacy_compatibility(): void
    {
        $owner = $this->family('profile-lifecycle-owner@example.com');
        $this->actingAs($owner);
        $service = app(CareRecipientProfileService::class);
        $first = $this->readyProfile($owner);
        $second = $this->readyProfile($owner, [
            'full_name' => 'Maria Petrini-Poli',
            'preferred_name' => 'Maria',
            'relationship_to_family' => 'Mother',
        ]);
        $secondVersionId = $second->latest_ready_version_id;

        $service->makeDefault($owner, $second);
        $account = app(FamilyAccountContext::class)->account($owner)->fresh();
        $this->assertSame($second->id, $account->default_care_recipient_profile_id);
        $this->assertSame('Maria Petrini-Poli', FamilyRecipientProfile::query()->where('family_account_id', $account->id)->value('full_name'));

        $archived = $service->archive($owner, $second);
        $this->assertSame(CareRecipientProfile::STATUS_ARCHIVED, $archived->status);
        $this->assertSame($secondVersionId, $archived->latest_ready_version_id);
        $this->assertSame($first->id, $account->fresh()->default_care_recipient_profile_id);

        $restored = $service->restore($owner, $archived);
        $this->assertSame(CareRecipientProfile::STATUS_READY, $restored->status);
        $this->assertSame($secondVersionId, $restored->latest_ready_version_id);
    }

    public function test_request_wizard_can_publish_with_a_saved_profile_or_without_one(): void
    {
        $owner = $this->family('wizard-profile-owner@example.com');
        $this->actingAs($owner);
        $profile = $this->readyProfile($owner);
        $task = CareTask::query()->firstOrCreate(['name' => 'Wizard companionship']);
        $tomorrow = now()->addDay()->toDateString();

        Livewire::actingAs($owner)
            ->test(CreateCareRequestWizard::class)
            ->call('selectCareRecipientProfile', $profile->id)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', $tomorrow)
            ->set('requested_start_time', '09:00')
            ->set('requested_duration_minutes', '120')
            ->set('address_line1', '100 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->call('publish')
            ->assertHasNoErrors();

        $attached = CareRequest::query()->latest('id')->firstOrFail();
        $this->assertSame($profile->id, $attached->recipient->care_recipient_profile_id);
        $this->assertSame($profile->latest_ready_version_id, $attached->recipient->care_recipient_profile_version_id);

        Livewire::actingAs($owner)
            ->test(CreateCareRequestWizard::class)
            ->set('care_for', CreateCareRequestWizard::CARE_FOR_OTHER)
            ->set('recipient_full_name', 'A different person')
            ->set('recipient_relationship_to_family', 'Friend')
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', now()->addDays(2)->toDateString())
            ->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '60')
            ->set('address_line1', '200 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->call('publish')
            ->assertHasNoErrors();

        $unattached = CareRequest::query()->latest('id')->firstOrFail();
        $this->assertNull($unattached->recipient->care_recipient_profile_id);
        $this->assertNull($unattached->recipient->care_recipient_profile_version_id);
    }

    public function test_request_wizard_quick_profile_creates_a_ready_pinned_version(): void
    {
        $owner = $this->family('quick-profile-owner@example.com');
        $task = CareTask::query()->firstOrCreate(['name' => 'Quick profile companionship']);

        Livewire::actingAs($owner)
            ->test(CreateCareRequestWizard::class)
            ->set('care_for', CreateCareRequestWizard::CARE_FOR_OTHER)
            ->set('recipient_full_name', 'Maria')
            ->set('recipient_relationship_to_family', 'Mother')
            ->set('createQuickCareProfile', true)
            ->set('quick_profile_about', 'Maria enjoys gardening and soft music.')
            ->set('quick_profile_good_visit', 'Greet her slowly and explain each next step.')
            ->set('quick_profile_sharing_acknowledged', true)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '11:00')
            ->set('requested_duration_minutes', '60')
            ->set('address_line1', '300 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->call('publish')
            ->assertHasNoErrors();

        $request = CareRequest::query()->latest('id')->firstOrFail();
        $profile = CareRecipientProfile::query()->findOrFail($request->recipient->care_recipient_profile_id);
        $this->assertTrue($profile->isReady());
        $this->assertSame($profile->latest_ready_version_id, $request->recipient->care_recipient_profile_version_id);
        $this->assertSame('Maria', $profile->preferred_name);
    }

    private function family(string $email): User
    {
        $family = User::factory()->create(['role' => 'family', 'email' => $email]);
        app(FamilyAccountContext::class)->account($family);

        return $family;
    }

    /** @param array<string,mixed> $overrides */
    private function readyProfile(User $owner, array $overrides = []): CareRecipientProfile
    {
        $service = app(CareRecipientProfileService::class);
        $data = array_merge([
            'full_name' => 'Charles Petrini-Poli',
            'preferred_name' => 'Charles',
            'relationship_to_family' => 'Father',
            'about_them' => 'Charles enjoys baseball and quiet conversation.',
            'good_visit_notes' => 'Introduce yourself and explain the plan.',
        ], $overrides);
        $draft = $service->saveDraft($owner, null, $data);

        return $service->makeReady($owner, $draft, $data, (int) $draft->revision, true);
    }

    private function readyCaregiver(string $suffix): User
    {
        $skill = Skill::query()->firstOrCreate(['name' => 'Companionship '.$suffix]);
        $language = Language::query()->firstOrCreate(['name' => 'English '.$suffix]);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'caregiver-'.$suffix,
            'status' => 'active',
            'bio' => str_repeat('Reliable caregiver support. ', 3),
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $caregiver->fresh('caregiverProfile');
    }
}
