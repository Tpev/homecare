<?php

namespace Tests\Feature\AiSupport;

use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\KnowledgeBaseEntry;
use App\Models\User;
use App\Services\AiSupport\FamilyExperienceKnowledgeBaseCatalog;
use App\Services\AiSupport\FamilyExperienceKnowledgeBaseImportService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyExperienceKnowledgeAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_alignment_package_revises_published_knowledge_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $catalog = app(FamilyExperienceKnowledgeBaseCatalog::class);

        foreach ($catalog->definitions() as $definition) {
            $payload = $definition['payload'];
            if ($definition['stable_id'] === 'KB-FAM-001') {
                $payload['title'] = 'Your Family dashboard';
                $payload['answer_body'] = 'Legacy Family dashboard guidance.';
            }
            if ($definition['stable_id'] === 'KB-CARE-006') {
                $payload['title'] = 'Approved hourly price - publication held';
                $payload['answer_body'] = 'Legacy held pricing guidance.';
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

        $importer = app(FamilyExperienceKnowledgeBaseImportService::class);
        $this->assertSame(1, $importer->plan()['counts']['revisions']);
        $this->assertSame(1, $importer->plan()['counts']['updates']);
        $this->assertSame(count(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS) - 2, $importer->plan()['counts']['noops']);

        $result = $importer->publishPackage($admin, 'Publish current Family experience guidance.');
        $this->assertCount(2, $result['published']);
        $this->assertSame('Your Family Care overview', KnowledgeBaseEntry::query()
            ->where('stable_id', 'KB-FAM-001')->firstOrFail()->publishedVersion->title);

        $again = $importer->plan();
        $this->assertSame(0, $again['counts']['revisions']);
        $this->assertSame(0, $again['counts']['updates']);
        $this->assertSame(count(FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS), $again['counts']['noops']);
        $this->assertSame(0, $again['counts']['conflicts']);

        $this->artisan('ai-support:realign-family-kb')
            ->expectsOutputToContain('Family experience KB package: family-experience-alignment-v1')
            ->expectsOutputToContain('Plan only. No knowledge or application state changed.')
            ->assertSuccessful();
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
        $this->assertSame(route('family.requests.index'), $navigation->urlFor($family, 'family.dashboard'));
        $this->assertSame(route('family.care.actions'), $navigation->urlFor($family, 'family.care_actions'));
        $this->assertSame(route('family.care.schedule'), $navigation->urlFor($family, 'family.care_schedule'));
        $this->assertSame(route('family.care.index'), $navigation->urlFor($family, 'family.care_arrangements'));
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
            'family.notifications.index' => 'family.notifications',
            'caregivers.search' => 'family.caregivers',
        ] as $routeName => $marker) {
            $this->actingAs($family)->get(route($routeName))
                ->assertOk()
                ->assertSee('data-ai-target="'.$marker.'"', false);
        }
    }
}
