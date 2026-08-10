<?php

namespace Tests\Feature\Caregiver;

use App\Livewire\Caregiver\BrowseCaregivers;
use App\Models\CaregiverCertification;
use App\Models\CaregiverCertificationType;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Marketplace\CaregiverCertificationFilter;
use App\Services\Matching\CaregiverSuggestionService;
use App\Support\CaregiverCertificationCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverCertificationDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_criteria_normalizes_active_unique_types_and_untrusted_values(): void
    {
        $cpr = $this->type('cpr');
        $inactive = $this->type('first-aid');
        $inactive->update(['active' => false]);

        $criteria = CaregiverCertificationCriteria::fromInput([
            $cpr->slug,
            (string) $cpr->id,
            $inactive->slug,
            'forged-type',
            ['not-a-scalar'],
        ], CaregiverCertificationCriteria::VERIFICATION_VERIFIED_ONLY);

        $this->assertSame([$cpr->id], $criteria->typeIds());
        $this->assertSame(['cpr'], $criteria->typeSlugs());
        $this->assertSame(['CPR'], $criteria->typeLabels());
        $this->assertTrue($criteria->requiresVerification());

        $empty = CaregiverCertificationCriteria::fromInput(['forged-type'], 'forged-mode');
        $this->assertFalse($empty->hasSelections());
        $this->assertSame(CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT, $empty->verification());
    }

    public function test_current_scope_and_public_status_respect_status_and_app_timezone_date_boundaries(): void
    {
        config()->set('app.timezone', 'America/New_York');
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'America/New_York'));
        $profile = $this->caregiver('Boundary Caregiver')->caregiverProfile;

        $today = $this->credential($profile, 'cpr', CaregiverCertification::STATUS_VERIFIED, '2026-08-10');
        $noExpiry = $this->credential($profile, 'first-aid', CaregiverCertification::STATUS_SELF_REPORTED);
        $pending = $this->credential($profile, 'bls', CaregiverCertification::STATUS_PENDING, '2026-08-11');
        $expired = $this->credential($profile, 'cna', CaregiverCertification::STATUS_VERIFIED, '2026-08-09');
        $rejected = $this->credential($profile, 'hha', CaregiverCertification::STATUS_REJECTED, '2026-08-11');

        $currentIds = CaregiverCertification::query()->current()->pluck('id')->all();

        $this->assertContains($today->id, $currentIds);
        $this->assertContains($noExpiry->id, $currentIds);
        $this->assertContains($pending->id, $currentIds);
        $this->assertNotContains($expired->id, $currentIds);
        $this->assertNotContains($rejected->id, $currentIds);
        $this->assertSame('LoLo verified', $today->publicStatusLabel());
        $this->assertSame('Reported by caregiver', $pending->publicStatusLabel());
        $this->assertSame('Expired', $expired->publicStatusLabel());
    }

    public function test_shared_query_filter_requires_every_selected_type_and_supports_verified_only(): void
    {
        $cprOnly = $this->caregiver('CPR Only')->caregiverProfile;
        $reportedBoth = $this->caregiver('Reported Both')->caregiverProfile;
        $verifiedBoth = $this->caregiver('Verified Both')->caregiverProfile;

        $this->credential($cprOnly, 'cpr', CaregiverCertification::STATUS_VERIFIED);
        $this->credential($reportedBoth, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED);
        $this->credential($reportedBoth, 'cna', CaregiverCertification::STATUS_PENDING);
        $this->credential($verifiedBoth, 'cpr', CaregiverCertification::STATUS_VERIFIED);
        $this->credential($verifiedBoth, 'cna', CaregiverCertification::STATUS_VERIFIED);

        $anyCurrent = CaregiverCertificationCriteria::fromInput(['cpr', 'cna']);
        $anyQuery = CaregiverProfile::query()->orderBy('id');
        app(CaregiverCertificationFilter::class)->apply($anyQuery, $anyCurrent);
        $this->assertSame(
            [$reportedBoth->id, $verifiedBoth->id],
            $anyQuery->pluck('id')->all(),
        );

        $verifiedOnly = CaregiverCertificationCriteria::fromInput(
            ['cpr', 'cna'],
            CaregiverCertificationCriteria::VERIFICATION_VERIFIED_ONLY,
        );
        $verifiedQuery = CaregiverProfile::query()->orderBy('id');
        app(CaregiverCertificationFilter::class)->apply($verifiedQuery, $verifiedOnly);
        $this->assertSame([$verifiedBoth->id], $verifiedQuery->pluck('id')->all());

        $baseline = CaregiverProfile::query()->orderBy('id')->pluck('id')->all();
        $unfiltered = CaregiverProfile::query()->orderBy('id');
        app(CaregiverCertificationFilter::class)->apply($unfiltered, CaregiverCertificationCriteria::empty());
        $this->assertSame($baseline, $unfiltered->pluck('id')->all());
    }

    public function test_presenter_prioritizes_selected_tags_keeps_all_selected_and_never_queries_or_returns_private_evidence(): void
    {
        $profile = $this->caregiver('Safe Presenter')->caregiverProfile;
        $this->credential($profile, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED, null, [
            'document_path' => 'caregiver-certifications/private-cpr.pdf',
            'rejection_reason' => 'Internal note',
        ]);
        $this->credential($profile, 'cna', CaregiverCertification::STATUS_PENDING);
        $this->credential($profile, 'bls', CaregiverCertification::STATUS_VERIFIED);
        $this->credential($profile, 'first-aid', CaregiverCertification::STATUS_REJECTED);
        $profile->load('publicSearchCertifications');
        $criteria = CaregiverCertificationCriteria::fromInput(['cpr', 'cna']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = $profile->publicCertificationSummary(
            $criteria,
            1,
        );
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'Rendering loaded certification tags issued an N+1 query.');
        $this->assertSame(['CPR', 'Certified Nursing Assistant (CNA)'], array_column($summary['tags'], 'label'));
        $this->assertSame(1, $summary['hidden_count']);
        $this->assertSame(['Reported by caregiver', 'Reported by caregiver'], array_column($summary['tags'], 'status_label'));

        $json = json_encode($summary);
        $this->assertStringNotContainsString('private-cpr.pdf', $json);
        $this->assertStringNotContainsString('Internal note', $json);
        $this->assertStringNotContainsString('document_path', $json);
        $this->assertStringNotContainsString('rejection_reason', $json);
    }

    public function test_browse_filter_has_url_state_text_matching_clear_actions_and_visible_verification_labels(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);
        $reported = $this->caregiver('Reported CPR');
        $verified = $this->caregiver('Verified CPR');
        $other = $this->caregiver('No Credential');
        $custom = $this->caregiver('Custom Training');
        $this->credential($reported->caregiverProfile, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED, null, [
            'document_path' => 'caregiver-certifications/do-not-render.pdf',
        ]);
        $this->credential($verified->caregiverProfile, 'cpr', CaregiverCertification::STATUS_VERIFIED);
        $this->credential($custom->caregiverProfile, 'other', CaregiverCertification::STATUS_SELF_REPORTED, null, [
            'custom_name' => 'Trauma-informed companion training',
        ]);

        Livewire::withQueryParams([
            'certifications' => ['cpr'],
            'certification_verification' => 'verified_only',
        ])
            ->actingAs($viewer)
            ->test(BrowseCaregivers::class)
            ->assertSet('certificationTypes', ['cpr'])
            ->assertSet('certificationVerification', 'verified_only')
            ->assertSee($verified->name)
            ->assertDontSee($reported->name)
            ->assertDontSee($other->name)
            ->assertSee('CPR')
            ->assertSee('LoLo verified')
            ->assertDontSee('do-not-render.pdf')
            ->call('includeReportedCertifications')
            ->assertSee($reported->name)
            ->assertSee('Reported by caregiver')
            ->call('setPage', 2)
            ->set('certificationTypes', [])
            ->assertSet('paginators.page', 1)
            ->set('search', 'Trauma-informed')
            ->call('applyFilters')
            ->assertSee($custom->name)
            ->assertDontSee($other->name)
            ->call('clearFilters')
            ->assertSet('certificationTypes', [])
            ->assertSet('certificationVerification', 'any_current');
    }

    public function test_other_filter_uses_the_structured_type_and_preserves_marketplace_eligibility(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);
        $custom = $this->caregiver('Custom Other Training');
        $withoutOther = $this->caregiver('Standard Training');
        $inactive = $this->caregiver('Inactive Other Training');
        $inactive->caregiverProfile->update(['status' => 'draft']);

        $this->credential($custom->caregiverProfile, 'other', CaregiverCertification::STATUS_SELF_REPORTED, null, [
            'custom_name' => 'Trauma-informed companion training',
        ]);
        $this->credential($inactive->caregiverProfile, 'other', CaregiverCertification::STATUS_VERIFIED, null, [
            'custom_name' => 'Private respite training',
        ]);

        Livewire::withQueryParams(['certifications' => ['other']])
            ->actingAs($viewer)
            ->test(BrowseCaregivers::class)
            ->assertSet('certificationTypes', ['other'])
            ->assertSee($custom->name)
            ->assertSee('Trauma-informed companion training')
            ->assertDontSee($withoutOther->name)
            ->assertDontSee($inactive->name);
    }

    public function test_verified_only_empty_state_can_explicitly_include_reported_credentials(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);
        $reported = $this->caregiver('Reported Recovery Caregiver');
        $this->credential($reported->caregiverProfile, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED);

        Livewire::withQueryParams([
            'certifications' => ['cpr'],
            'certification_verification' => 'verified_only',
        ])
            ->actingAs($viewer)
            ->test(BrowseCaregivers::class)
            ->assertDontSee($reported->name)
            ->assertSee('No caregivers match CPR with LoLo verification right now.')
            ->assertSee('Include credentials reported by caregivers')
            ->call('includeReportedCertifications')
            ->assertSet('certificationVerification', 'any_current')
            ->assertSee($reported->name)
            ->assertSee('Reported by caregiver');
    }

    public function test_browse_certification_queries_do_not_grow_with_the_number_of_result_cards(): void
    {
        $viewer = User::factory()->create(['role' => 'family']);
        $first = $this->caregiver('Query Count One');
        $this->credential($first->caregiverProfile, 'cpr', CaregiverCertification::STATUS_VERIFIED);

        $singleResultQueries = $this->certificationQueryCountForBrowse($viewer);

        foreach (range(2, 7) as $index) {
            $caregiver = $this->caregiver('Query Count '.$index);
            $this->credential($caregiver->caregiverProfile, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED);
        }

        $manyResultQueries = $this->certificationQueryCountForBrowse($viewer);

        $this->assertSame(
            $singleResultQueries,
            $manyResultQueries,
            'Certification queries increased with the number of caregiver cards.',
        );
        $this->assertLessThanOrEqual(3, $manyResultQueries);
    }

    public function test_suggestions_apply_certification_requirements_before_limiting_without_changing_no_filter_results(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $request = $this->request($family);
        $highRatedWithoutCredential = $this->caregiver('High Rated', ['average_rating' => 5, 'reviews_count' => 40, 'top_caregiver' => true]);
        $lowerRatedWithCredential = $this->caregiver('Qualified Match', ['average_rating' => 3.5, 'reviews_count' => 1]);
        $this->credential($lowerRatedWithCredential->caregiverProfile, 'cpr', CaregiverCertification::STATUS_SELF_REPORTED);

        $service = app(CaregiverSuggestionService::class);
        $baseline = $service->topMatchesForRequest($request, 2);
        $explicitEmpty = $service->topMatchesForRequest($request, 2, CaregiverCertificationCriteria::empty());
        $filtered = $service->topMatchesForRequest(
            $request,
            1,
            CaregiverCertificationCriteria::fromInput(['cpr']),
        );

        $this->assertSame($baseline->pluck('user_id')->all(), $explicitEmpty->pluck('user_id')->all());
        $this->assertSame([$lowerRatedWithCredential->id], $filtered->pluck('user_id')->all());
        $this->assertSame('CPR', $filtered->first()['certification_summary']['tags'][0]['label']);
        $this->assertSame('Reported by caregiver', $filtered->first()['certification_summary']['tags'][0]['status_label']);
        $this->assertContains($highRatedWithoutCredential->id, $baseline->pluck('user_id')->all());
    }

    private function caregiver(string $name, array $profileOverrides = []): User
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => $name,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $profile = CaregiverProfile::query()->create(array_merge([
            'user_id' => $caregiver->id,
            'slug' => str($name)->slug().'-'.$caregiver->id,
            'status' => 'active',
            'bio' => 'Experienced non-medical caregiver available for family support.',
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'background_check_verified_at' => now(),
            'average_rating' => 4.5,
            'reviews_count' => 5,
            'reliability_score' => 95,
        ], $profileOverrides));
        $skill = Skill::query()->create(['name' => 'Skill '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'Language '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create([
                'day_of_week' => $day,
                'start_time' => '00:00',
                'end_time' => '23:59',
            ]);
        }

        return $caregiver->fresh('caregiverProfile');
    }

    private function certificationQueryCountForBrowse(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::withQueryParams(['certifications' => ['cpr']])
            ->actingAs($viewer)
            ->test(BrowseCaregivers::class)
            ->assertSee('Caregiver certifications and training');

        $count = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'caregiver_certifications'))
            ->count();

        DB::disableQueryLog();

        return $count;
    }

    private function credential(
        CaregiverProfile $profile,
        string $typeSlug,
        string $status,
        ?string $expiresAt = null,
        array $overrides = [],
    ): CaregiverCertification {
        $type = $this->type($typeSlug);

        return CaregiverCertification::query()->create(array_merge([
            'caregiver_profile_id' => $profile->id,
            'caregiver_certification_type_id' => $type->id,
            'verification_status' => $status,
            'expires_at' => $expiresAt,
            'verified_at' => $status === CaregiverCertification::STATUS_VERIFIED ? now() : null,
        ], $overrides));
    }

    private function type(string $slug): CaregiverCertificationType
    {
        return CaregiverCertificationType::query()->where('slug', $slug)->firstOrFail();
    }

    private function request(User $family): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Certification discovery request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Barbara Example',
            'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }
}
