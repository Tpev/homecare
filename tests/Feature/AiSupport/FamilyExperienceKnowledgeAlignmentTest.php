<?php

namespace Tests\Feature\AiSupport;

use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\FamilyExperienceKnowledgeBaseCatalog;
use App\Services\AiSupport\FamilyExperienceKnowledgeBaseImportService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\FamilyAccounts\FamilyAccountContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyExperienceKnowledgeAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_alignment_package_creates_missing_knowledge_revises_published_knowledge_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $catalog = app(FamilyExperienceKnowledgeBaseCatalog::class);

        foreach ($catalog->definitions() as $definition) {
            if ($definition['stable_id'] === 'KB-FOP-ORI-001') {
                continue;
            }
            $payload = $definition['payload'];
            if ($definition['stable_id'] === 'KB-FAM-001') {
                $payload['title'] = 'Your Family dashboard';
                $payload['answer_body'] = 'Legacy Family dashboard guidance.';
            }
            $entry = $workflow->createDraftWithStableId(
                $admin,
                $definition['stable_id'],
                $payload,
                $definition['sources'],
            );
            $this->assertTrue($workflow->validateAndStore($admin, $entry->workingVersion)['passed']);
            if ($definition['stable_id'] === 'KB-CARE-006') {
                continue;
            }
            $version = $workflow->submitForReview($admin, $entry->workingVersion->fresh());
            $version = $workflow->approve($admin, $version);
            $workflow->publish($admin, $version, 'Publish alignment baseline fixture.');
        }

        $existing = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-001')->firstOrFail();
        $released = $existing->publishedVersion;
        $importer = app(FamilyExperienceKnowledgeBaseImportService::class);
        $this->assertSame(1, $importer->plan()['counts']['creates']);
        $this->assertSame(1, $importer->plan()['counts']['revisions']);
        $this->assertSame(0, $importer->plan()['counts']['updates']);
        $this->assertSame(1, $importer->plan()['counts']['pending_publication']);
        $this->assertSame(count(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS) - 2, $importer->plan()['counts']['noops']);

        $publishedIds = KnowledgeBaseEntry::query()->pluck('published_version_id', 'stable_id')->all();
        $applied = $importer->apply($admin);
        $this->assertCount(0, $applied['updated']);
        foreach ($publishedIds as $stableId => $publishedId) {
            $this->assertSame($publishedId, KnowledgeBaseEntry::query()->where('stable_id', $stableId)->value('published_version_id'));
        }
        $this->assertSame('Legacy Family dashboard guidance.', $released->fresh()->answer_body);
        $this->assertNotSame($released->id, $existing->fresh()->working_version_id);
        $this->assertSame(count(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS), $importer->plan()['counts']['noops']);

        $result = $importer->publishPackage($admin, 'Publish current Family experience guidance.');
        $this->assertCount(3, $result['published']);
        $this->assertSame(KnowledgeBaseVersion::STATUS_SUPERSEDED, $released->fresh()->status);
        $this->assertSame('What LoLo helps Families do', KnowledgeBaseEntry::query()
            ->where('stable_id', 'KB-FOP-ORI-001')->firstOrFail()->publishedVersion->title);
        $this->assertSame('Your Family Care overview', KnowledgeBaseEntry::query()
            ->where('stable_id', 'KB-FAM-001')->firstOrFail()->publishedVersion->title);

        $again = $importer->plan();
        $this->assertSame(0, $again['counts']['creates']);
        $this->assertSame(0, $again['counts']['revisions']);
        $this->assertSame(0, $again['counts']['updates']);
        $this->assertSame(count(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS), $again['counts']['noops']);
        $this->assertSame(0, $again['counts']['conflicts']);
        $this->assertCount(0, $importer->publishPackage($admin, 'Idempotence check.')['published']);

        $this->artisan('ai-support:realign-family-kb')
            ->expectsOutputToContain('Family experience KB package: family-experience-alignment-v2')
            ->expectsOutputToContain('Plan only. No knowledge or application state changed.')
            ->assertSuccessful();
    }

    public function test_unified_manifest_covers_all_packages_and_reports_unmanaged_published_content_without_retiring_it(): void
    {
        $catalog = app(FamilyExperienceKnowledgeBaseCatalog::class);
        $this->assertCount(193, $catalog->definitions());
        $this->assertCount(193, array_unique(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS));
        $this->assertCount(26, FamilyExperienceKnowledgeBaseCatalog::CHANGED_STABLE_IDS);
        foreach (FamilyExperienceKnowledgeBaseCatalog::CHANGED_STABLE_IDS as $stableId) {
            $this->assertSame($stableId, $catalog->definition($stableId)['stable_id']);
        }
        foreach ($catalog->definitions() as $definition) {
            $this->assertDoesNotMatchRegularExpression('/\$(?:30|27|3)(?:\.00)?\b/', json_encode($definition));
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $definition = $catalog->definition('KB-FAM-001');
        $custom = $workflow->createDraftWithStableId($admin, 'KB-CUSTOM-001', $definition['payload'], $definition['sources']);
        $workflow->validateAndStore($admin, $custom->workingVersion);
        $review = $workflow->submitForReview($admin, $custom->workingVersion->fresh());
        $workflow->publish($admin, $workflow->approve($admin, $review), 'Independent custom knowledge.');
        $before = $custom->fresh()->toArray();
        $plan = app(FamilyExperienceKnowledgeBaseImportService::class)->plan();
        $this->assertSame(['KB-CUSTOM-001'], $plan['unmanaged_published_ids']);
        $this->assertSame(['KB-FAM-004'], $plan['canonical_revision_ids']);
        $this->assertSame($before, $custom->fresh()->toArray());
    }

    public function test_differing_reviewed_content_refuses_the_entire_package_without_partial_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $definition = app(FamilyExperienceKnowledgeBaseCatalog::class)->definition('KB-FAM-001');
        $definition['payload']['answer_body'] = 'A separately reviewed editorial change.';
        $entry = $workflow->createDraftWithStableId($admin, 'KB-FAM-001', $definition['payload'], $definition['sources']);
        $workflow->validateAndStore($admin, $entry->workingVersion);
        $version = $workflow->approve($admin, $workflow->submitForReview($admin, $entry->workingVersion->fresh()));
        $before = $version->attributesToArray();
        $importer = app(FamilyExperienceKnowledgeBaseImportService::class);
        $this->assertSame(1, $importer->plan()['counts']['conflicts']);
        try {
            $importer->apply($admin);
            $this->fail('Reviewed content must be resolved before applying the package.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('refused', $exception->getMessage());
        }
        $this->assertDatabaseCount('knowledge_base_entries', 1);
        $this->assertSame($before, $version->fresh()->attributesToArray());
    }

    public function test_current_family_navigation_targets_are_semantic_resource_bound_and_role_aware(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        $family = User::factory()->create(['role' => 'family']);
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $account = app(FamilyAccountContext::class)->account($family);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiverUser->id,
            'slug' => 'current-caregiver-'.$caregiverUser->id,
            'status' => 'active',
        ]);
        $request = CareRequest::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'created_by_user_id' => $family->id,
            'title' => 'Current care request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $plan = CarePlan::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiverUser->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Weekly companionship',
            'schedule_days' => [1],
            'schedule_start_time' => '09:00:00',
            'schedule_end_time' => '11:00:00',
            'starts_on' => now()->addWeek()->toDateString(),
            'hourly_rate' => 30,
        ]);

        $navigation = app(NavigationTargetRegistry::class);
        $otherFamily = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($otherFamily);
        foreach ([
            'family.request.summary' => ['tab' => 'home'],
            'family.request.discovery' => ['tab' => 'invite', 'view' => 'search'],
            'family.request.invitations' => ['tab' => 'invite', 'view' => 'invited'],
            'family.request.saved' => ['tab' => 'invite', 'view' => 'saved'],
            'family.request.start' => ['tab' => 'start'],
            'family.request.overview' => ['tab' => 'overview'],
        ] as $target => $query) {
            $resource = ['resource_type' => 'care_request', 'resource_id' => $request->id];
            $this->assertSame(route('family.requests.show', ['careRequest' => $request->id, ...$query]), $navigation->urlFor($family, $target, $resource));
            $this->assertFalse($navigation->allowedFor($otherFamily, $target, $resource));
            $this->assertFalse($navigation->allowedFor($caregiverUser, $target, $resource));
            $this->assertFalse($navigation->allowedFor($family, $target));
        }
        $this->assertSame(route('family.requests.index'), $navigation->urlFor($family, 'family.dashboard'));
        $this->assertSame(route('family.care.actions'), $navigation->urlFor($family, 'family.care_actions'));
        $this->assertSame(route('family.care.schedule'), $navigation->urlFor($family, 'family.care_schedule'));
        $this->assertSame(route('family.care.index'), $navigation->urlFor($family, 'family.care_arrangements'));
        $this->assertSame(route('family.notifications.index'), $navigation->urlFor($family, 'family.notifications.preferences'));
        $this->assertSame(
            route('family.care.journey', ['resourceType' => 'request', 'resourceId' => $request->id]),
            $navigation->urlFor($family, 'family.request.journey', ['resource_type' => 'care_request', 'resource_id' => $request->id]),
        );
        $this->assertSame(
            route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]),
            $navigation->urlFor($family, 'family.recurring_care.journey', ['resource_type' => 'care_plan', 'resource_id' => $plan->id]),
        );
        $this->assertSame(
            route('caregivers.show', $profile->slug),
            $navigation->urlFor($family, 'family.caregiver_profile', ['resource_type' => 'caregiver_profile', 'resource_id' => $profile->id]),
        );
        $profile->update(['status' => 'pending']);
        $this->assertFalse($navigation->allowedFor($family, 'family.caregiver_profile', [
            'resource_type' => 'caregiver_profile',
            'resource_id' => $profile->id,
        ]));
        $this->assertFalse($navigation->allowedFor($caregiverUser, 'family.care_actions'));
    }

    public function test_only_unchanged_managed_drafts_refresh_and_previous_versions_and_sources_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $catalog = app(FamilyExperienceKnowledgeBaseCatalog::class);
        $older = collect($catalog->definitions())->keyBy('stable_id')->all();
        $older['KB-FAM-002']['payload']['answer_body'] = 'Previous managed package guidance.';
        $oldCatalog = \Mockery::mock(FamilyExperienceKnowledgeBaseCatalog::class);
        $oldCatalog->shouldReceive('definitions')->andReturn(array_values($older));
        $oldCatalog->shouldReceive('definition')->andReturnUsing(fn ($id) => $older[$id]);
        (new FamilyExperienceKnowledgeBaseImportService($oldCatalog, $workflow))->apply($admin);
        $entry = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-002')->firstOrFail();
        $oldVersion = $entry->workingVersion;
        $oldAttributes = $oldVersion->attributesToArray();
        $oldSources = $oldVersion->sources()->orderBy('id')->get()->toArray();

        $importer = new FamilyExperienceKnowledgeBaseImportService($catalog, $workflow);
        $this->assertSame(['KB-FAM-002'], $importer->plan()['updates']);
        $importer->apply($admin);
        $this->assertNotSame($oldVersion->id, $entry->fresh()->working_version_id);
        $this->assertSame($oldAttributes, $oldVersion->fresh()->attributesToArray());
        $this->assertSame($oldSources, $oldVersion->sources()->orderBy('id')->get()->toArray());
        $this->assertSame(193, $importer->plan()['counts']['pending_publication']);
        $this->assertSame(0, $importer->plan()['counts']['published_noops']);
        $this->assertCount(0, $importer->apply($admin)['updated']);

        $draft = $entry->fresh()->workingVersion;
        $manual = $catalog->definition('KB-FAM-002');
        $manual['payload']['answer_body'] = 'Administrator edits must stay intact.';
        $manual['sources'][0]['fact_supported'] = 'Independent editorial research.';
        $draft = $workflow->updateWorkingVersion($admin, $draft, $draft->edit_version, $manual['payload'], $manual['sources']);
        $before = $draft->attributesToArray();
        $sources = $draft->sources()->orderBy('id')->get()->toArray();
        $this->assertSame([['stable_id' => 'KB-FAM-002', 'reason' => 'unrecognized_or_manually_modified_draft_requires_review']], $importer->plan()['conflicts']);
        try {
            $importer->apply($admin);
            $this->fail('An edited managed draft must refuse alignment.');
        } catch (DomainException) {
            $this->assertSame($draft->id, $entry->fresh()->working_version_id);
            $this->assertSame($before, $draft->fresh()->attributesToArray());
            $this->assertSame($sources, $draft->sources()->orderBy('id')->get()->toArray());
        }
    }

    public function test_apply_replans_after_a_working_pointer_changes_and_does_not_replace_manual_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $definition = app(FamilyExperienceKnowledgeBaseCatalog::class)->definition('KB-FAM-001');
        $definition['payload']['answer_body'] = 'Old published source guidance.';
        $entry = $workflow->createDraftWithStableId($admin, 'KB-FAM-001', $definition['payload'], $definition['sources']);
        $workflow->validateAndStore($admin, $entry->workingVersion);
        $published = $workflow->publish($admin, $workflow->approve($admin,
            $workflow->submitForReview($admin, $entry->workingVersion->fresh())), 'Baseline published version.');
        $importer = app(FamilyExperienceKnowledgeBaseImportService::class);
        $this->assertSame(['KB-FAM-001'], $importer->plan()['revisions']);

        // An editor creates a different working version after the preview was read.
        $manual = $workflow->createDraftFrom($admin, $published, 'Administrator work after plan preview.');
        $before = $manual->attributesToArray();
        try {
            $importer->publishPackage($admin, 'Stale preview must not win.');
            $this->fail('A stale plan must not replace a newly created manual draft.');
        } catch (DomainException) {
            $this->assertSame($manual->id, $entry->fresh()->working_version_id);
            $this->assertSame($published->id, $entry->fresh()->published_version_id);
            $this->assertSame($before, $manual->fresh()->attributesToArray());
            $this->assertDatabaseCount('knowledge_base_entries', 1);
            $this->assertDatabaseCount('knowledge_base_versions', 2);
        }
    }

    public function test_exact_paused_and_withdrawn_content_is_reported_as_a_conflict_instead_of_a_published_noop(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $definition = app(FamilyExperienceKnowledgeBaseCatalog::class)->definition('KB-FAM-001');
        $entry = $workflow->createDraftWithStableId($admin, 'KB-FAM-001', $definition['payload'], $definition['sources']);
        $workflow->validateAndStore($admin, $entry->workingVersion);
        $version = $workflow->publish($admin, $workflow->approve($admin,
            $workflow->submitForReview($admin, $entry->workingVersion->fresh())), 'Lifecycle fixture.');
        foreach (['pause', 'withdraw'] as $action) {
            $version = $workflow->{$action}($admin, $version, 'Administrator lifecycle decision.');
            $plan = app(FamilyExperienceKnowledgeBaseImportService::class)->plan();
            $this->assertSame(1, $plan['counts']['conflicts']);
            $this->assertSame(0, $plan['counts']['published_noops']);
            $this->assertSame(0, $plan['counts']['pending_publication']);
        }
    }

    public function test_current_family_pages_render_their_registered_highlight_markers(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);

        foreach ([
            'family.requests.index' => 'family.care_requests',
            'family.care.actions' => 'family.care_actions',
            'family.care.schedule' => 'family.care_schedule',
            'family.care.index' => 'family.care_arrangements',
            'family.care.history' => 'family.care_history',
            'family.notifications.index' => 'family.notifications',
            'caregivers.search' => 'family.caregivers',
        ] as $routeName => $marker) {
            $this->actingAs($family)->get(route($routeName))
                ->assertOk()
                ->assertSee('data-ai-target="'.$marker.'"', false);
        }

        $this->actingAs($family)->get(route('family.notifications.index'))
            ->assertOk()
            ->assertSee('data-ai-target="family.notifications.preferences"', false)
            ->assertSee('Delivery preferences');

        foreach ([
            'family.care.schedule' => ['family.care_schedule'],
            'family.care.index' => ['family.care_arrangements', 'family.regular_care'],
            'family.care.history' => ['family.care_history'],
        ] as $routeName => $markers) {
            $html = $this->actingAs($family)->get(route($routeName))->assertOk()->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            foreach ($markers as $marker) {
                $nodes = $xpath->query('//*[@data-ai-target="'.$marker.'"]');
                $this->assertCount(1, $nodes, $marker);
                $node = $nodes->item(0);
                $this->assertSame('-1', $node->getAttribute('tabindex'), $marker);
                $this->assertNotEmpty(trim($node->textContent), $marker);
                while ($node instanceof \DOMElement) {
                    $this->assertStringNotContainsString('sr-only', $node->getAttribute('class'), $marker);
                    $this->assertFalse($node->hasAttribute('hidden'), $marker);
                    $node = $node->parentNode;
                }
            }
        }
    }

    public function test_guided_details_targets_are_reopened_after_livewire_removes_the_open_attribute(): void
    {
        $client = file_get_contents(resource_path('js/support-chat.js'));

        $this->assertIsString($client);
        $this->assertStringContainsString("attributeFilter: ['class', 'data-ai-guided', 'open']", $client);
        $this->assertStringContainsString('collapsedGuidedDetails', $client);
        $this->assertStringContainsString('target.open = true', $client);
        $this->assertStringContainsString('then(() => this.$nextTick(() => this.initializeGuidance()))', $client);
    }

    public function test_care_profile_guidance_names_useful_content_and_safe_boundaries(): void
    {
        $catalog = app(FamilyExperienceKnowledgeBaseCatalog::class);

        foreach (['KB-FOP-PRO-001', 'KB-B5-PROFILE-001'] as $stableId) {
            $answer = mb_strtolower((string) data_get($catalog->definition($stableId), 'payload.answer_body'));
            foreach (['communication', 'routine', 'mobility', 'comfort', 'safety', 'daily assistance'] as $expected) {
                $this->assertStringContainsString($expected, $answer, $stableId);
            }
            $this->assertStringContainsString('clinical instructions', $answer, $stableId);
        }
    }
}
