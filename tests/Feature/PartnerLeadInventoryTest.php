<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PartnerLead;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\Partners\PartnerLeadInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PartnerLeadInventoryTest extends TestCase
{
    use RefreshDatabase;

    private int $leadSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_default_inventory_includes_all_family_stages_empty_requests_and_blocked_contacts(): void
    {
        $available = $this->lead(['status' => 'closed']);
        $empty = $this->lead(['data' => []]);
        $blocked = $this->lead(['do_not_contact_at' => now()]);
        $noContact = $this->lead(['email' => null, 'phone' => null]);
        $referral = $this->lead(['lead_type' => Lead::TYPE_REFERRAL]);

        $result = app(PartnerLeadInventory::class)->page();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result['leads']);
        $this->assertEqualsCanonicalizing([$available->id, $empty->id, $blocked->id, $noContact->id], $result['leads']->pluck('id')->all());
        $this->assertFalse($result['leads']->contains('id', $referral->id));
        $this->assertSame(['all' => 4, 'available' => 1, 'sent' => 0, 'no_services' => 1], $result['stats']);
        $this->assertSame(['meal_delivery'], $result['requestedCategories'][$available->id]);
        $this->assertSame([], $result['requestedCategories'][$empty->id]);
        $this->assertNull($result['blockedReasons'][$available->id]);
        $this->assertNotEmpty($result['blockedReasons'][$blocked->id]);
        $this->assertNotEmpty($result['blockedReasons'][$noContact->id]);
        $this->assertSame([], $result['sentCategories'][$available->id]);
    }

    public function test_service_filter_uses_legacy_answer_formats_and_explicit_empty_precedence(): void
    {
        $representations = [
            ['care_preferences' => ['additional_services' => ['meal_delivery']]],
            ['form_answers' => ['additional_services' => '["Meal delivery","Estate planning & wills"]']],
            ['raw_payload' => ['field_data' => [[
                'name' => 'Which additional services would you like more information about? (Select all that apply)',
                'values' => ['meal_delivery'],
            ]]]],
            ['normalized_payload' => ['partner_services' => 'meal_delivery']],
            ['raw_payload' => ['additional_services' => 'Meal delivery, Home safety improvements (ramps, grab bars, stair lifts), Pharmacy pickup or delivery;Estate planning & wills']],
        ];
        $matching = [];
        foreach ($representations as $data) {
            $matching[] = $this->lead(['data' => $data])->id;
        }
        $explicitEmpty = $this->lead(['data' => [
            'care_preferences' => ['additional_services' => []],
            'raw_payload' => ['additional_services' => 'Meal delivery'],
        ]]);
        $unknown = $this->lead(['data' => ['form_answers' => ['additional_services' => ['Not interested', 'Other services']]]]);

        $result = app(PartnerLeadInventory::class)->page(category: 'meal_delivery');
        $this->assertEqualsCanonicalizing($matching, $result['leads']->pluck('id')->all());
        $this->assertSame(['all' => 5, 'available' => 5, 'sent' => 0, 'no_services' => 0], $result['stats']);
        $this->assertSame(['meal_delivery', 'home_safety', 'pharmacy_delivery', 'estate_planning'], $result['requestedCategories'][$matching[4]]);

        $empty = app(PartnerLeadInventory::class)->page(status: 'no_services');
        $this->assertEqualsCanonicalizing([$explicitEmpty->id, $unknown->id], $empty['leads']->pluck('id')->all());
        $this->assertSame(7, $empty['stats']['all']);
        $this->assertSame(2, $empty['stats']['no_services']);
    }

    public function test_service_matches_are_found_across_scan_chunks_before_paginating(): void
    {
        $rows = [];
        for ($index = 0; $index < 225; $index++) {
            $rows[] = [
                'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Imported '.$index,
                'email' => 'imported'.$index.'@example.test', 'status' => 'new',
                'submitted_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now(),
                'data' => json_encode(['care_preferences' => ['additional_services' => [
                    $index % 10 === 0 ? 'Home safety improvements (ramps, grab bars, stair lifts)' : 'Meal delivery',
                ]]]),
            ];
        }
        DB::table('leads')->insert($rows);
        $expected = Lead::query()->where('name', 'like', 'Imported %')->get()
            ->filter(fn (Lead $lead) => str_ends_with($lead->name, '0'))->sortByDesc('id')->pluck('id')->values()->all();

        $first = app(PartnerLeadInventory::class)->page(category: 'home_safety', perPage: 20);
        $second = app(PartnerLeadInventory::class)->page(category: 'home_safety', page: 2, perPage: 20);

        $this->assertSame(23, $first['leads']->total());
        $this->assertSame(2, $first['leads']->lastPage());
        $this->assertSame(array_slice($expected, 0, 20), $first['leads']->pluck('id')->all());
        $this->assertSame(array_slice($expected, 20), $second['leads']->pluck('id')->all());
        $this->assertSame(['all' => 23, 'available' => 23, 'sent' => 0, 'no_services' => 0], $second['stats']);
        $this->assertSame($second['leads']->pluck('id')->all(), array_keys($second['requestedCategories']));
        $this->assertCount(3, $second['sentCategories']);
    }

    public function test_status_filters_share_search_and_category_statistics_and_allow_partial_delivery(): void
    {
        $partial = $this->lead(['name' => 'Coastal partial', 'data' => ['care_preferences' => ['additional_services' => ['Meal delivery', 'Home safety improvements']]]]);
        $this->markSent($partial);
        $unsent = $this->lead(['name' => 'Coastal unsent']);
        $this->lead(['name' => 'Coastal blocked', 'data' => ['care_preferences' => ['additional_services' => ['Meal delivery']], 'provider_outreach' => ['do_not_call' => true]]]);
        $none = $this->lead(['name' => 'Coastal none', 'data' => []]);
        $this->lead(['name' => 'Outside search']);

        $service = app(PartnerLeadInventory::class);
        $available = $service->page(search: 'Coastal', status: 'available');
        $sent = $service->page(search: 'Coastal', status: 'sent');
        $noServices = $service->page(search: 'Coastal', status: 'no_services');

        $this->assertEqualsCanonicalizing([$partial->id, $unsent->id], $available['leads']->pluck('id')->all());
        $this->assertSame([$partial->id], $sent['leads']->pluck('id')->all());
        $this->assertSame([$none->id], $noServices['leads']->pluck('id')->all());
        $this->assertSame(['all' => 4, 'available' => 2, 'sent' => 1, 'no_services' => 1], $available['stats']);
        $this->assertSame($available['stats'], $sent['stats']);
        $this->assertSame($available['stats'], $noServices['stats']);

        $meal = $service->page(search: 'Coastal', category: 'meal_delivery', status: 'available');
        $this->assertSame([$unsent->id], $meal['leads']->pluck('id')->all());
        $this->assertSame(['all' => 3, 'available' => 1, 'sent' => 1, 'no_services' => 0], $meal['stats']);
        $safety = $service->page(search: 'Coastal', category: 'home_safety', status: 'sent');
        $this->assertSame(0, $safety['leads']->total());
        $this->assertSame(['all' => 1, 'available' => 1, 'sent' => 0, 'no_services' => 0], $safety['stats']);
    }

    public function test_shared_contact_claims_and_deleted_history_remain_sent_in_inventory(): void
    {
        $original = $this->lead(['email' => 'identity@example.test']);
        $delivery = $this->markSent($original);
        $duplicate = $this->lead(['email' => ' IDENTITY@EXAMPLE.TEST ']);

        $result = app(PartnerLeadInventory::class)->page(status: 'sent');
        $this->assertEqualsCanonicalizing([$original->id, $duplicate->id], $result['leads']->pluck('id')->all());
        $this->assertSame($delivery->id, $result['sentCategories'][$duplicate->id]['meal_delivery']['delivery_id']);
        $this->assertSame('Inventory partner', $result['sentCategories'][$duplicate->id]['meal_delivery']['partner_name']);
        $this->assertSame(0, $result['stats']['available']);

        $original->delete();
        $delivery->delete();
        $orphaned = app(PartnerLeadInventory::class)->page();
        $this->assertSame([$duplicate->id], $orphaned['leads']->pluck('id')->all());
        $this->assertArrayHasKey('meal_delivery', $orphaned['sentCategories'][$duplicate->id]);
        $this->assertNull($orphaned['sentCategories'][$duplicate->id]['meal_delivery']['delivery_id']);
        $this->assertSame(['all' => 1, 'available' => 0, 'sent' => 1, 'no_services' => 0], $orphaned['stats']);
    }

    public function test_merge_history_prevents_current_contact_changes_from_reopening_inventory(): void
    {
        $sold = $this->lead(['email' => 'sold@example.test']);
        $delivery = $this->markSent($sold);
        $survivor = $this->lead(['email' => 'survivor@example.test']);
        $survivor->activities()->create([
            'type' => 'note', 'body' => 'Merged lead', 'occurred_at' => now(),
            'metadata' => ['source' => 'phone_deduplication', 'merged_lead_id' => $sold->id,
                'original_lead' => ['id' => $sold->id, 'email' => $sold->email]],
        ]);
        $sold->delete();

        $result = app(PartnerLeadInventory::class)->page();
        $this->assertSame(['all' => 1, 'available' => 0, 'sent' => 1, 'no_services' => 0], $result['stats']);
        $this->assertSame($delivery->id, $result['sentCategories'][$survivor->id]['meal_delivery']['delivery_id']);
    }

    public function test_search_supports_contact_location_zip_source_and_exact_numeric_id(): void
    {
        $lead = $this->lead(['name' => 'Distinct name', 'email' => 'unique@example.test', 'phone' => '9195551234',
            'location' => 'Asheville', 'zip' => '28801', 'source_detail' => 'November campaign']);
        $this->lead(['name' => 'Other lead']);
        $this->lead(['lead_type' => Lead::TYPE_REFERRAL, 'name' => 'Distinct name']);

        foreach (['Distinct', 'unique@', '5551234', 'Asheville', '28801', 'November', (string) $lead->id] as $search) {
            $result = app(PartnerLeadInventory::class)->page(search: $search);
            $this->assertSame([$lead->id], $result['leads']->pluck('id')->all(), 'Search: '.$search);
            $this->assertSame(1, $result['stats']['all']);
        }
        $missing = app(PartnerLeadInventory::class)->page(search: 'no such record');
        $this->assertSame(0, $missing['leads']->total());
        $this->assertSame([], $missing['requestedCategories']);
        $this->assertSame([], $missing['sentCategories']);
        $this->assertSame([], $missing['blockedReasons']);
        $this->assertSame(['all' => 0, 'available' => 0, 'sent' => 0, 'no_services' => 0], $missing['stats']);
    }

    public function test_inventory_only_reads_and_keeps_page_metadata_scoped_to_displayed_rows(): void
    {
        $older = $this->lead(['submitted_at' => now()->subDays(2)]);
        $newer = $this->lead(['submitted_at' => now()]);
        $this->markSent($newer);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $result = app(PartnerLeadInventory::class)->page(perPage: 1);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([$newer->id], $result['leads']->pluck('id')->all());
        $this->assertSame(2, $result['leads']->total());
        foreach (['requestedCategories', 'sentCategories', 'blockedReasons'] as $key) {
            $this->assertSame([$newer->id], array_keys($result[$key]));
            $this->assertArrayNotHasKey($older->id, $result[$key]);
        }
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }
        $this->assertDatabaseCount('partner_leads', 1);
        $this->assertDatabaseCount('partner_lead_credits', 0);
    }

    private function lead(array $overrides = []): Lead
    {
        $sequence = ++$this->leadSequence;

        return Lead::create(array_replace([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Family '.$sequence,
            'email' => 'family'.$sequence.'@example.test', 'phone' => null,
            'status' => 'new', 'source' => 'manual', 'submitted_at' => now(),
            'data' => ['care_preferences' => ['additional_services' => ['Meal delivery']]],
        ], $overrides));
    }

    private function markSent(Lead $lead, string $category = 'meal_delivery'): PartnerLead
    {
        $user = User::factory()->create(['role' => 'partner']);
        $partner = PartnerProfile::create([
            'user_id' => $user->id, 'business_name' => 'Inventory partner',
            'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '9195550100',
            'categories' => [$category], 'coverage_states' => ['NC'], 'coverage_type' => 'entire_states',
            'status' => 'approved', 'submitted_at' => now(), 'terms_accepted_at' => now(),
        ]);
        $delivery = PartnerLead::create([
            'partner_profile_id' => $partner->id, 'lead_id' => $lead->id, 'source_lead_id' => $lead->id,
            'category' => $category, 'contact_name' => $lead->name, 'contact_email' => $lead->email,
            'service_context' => 'Requested service', 'assigned_at' => now(),
            'sharing_confirmed_at' => now(), 'geography_confirmed_at' => now(),
        ]);
        foreach (['lead' => (string) $lead->id, 'email' => mb_strtolower(trim($lead->email))] as $type => $value) {
            DB::table('partner_lead_claims')->insert([
                'partner_lead_id' => $delivery->id, 'identity_type' => $type,
                'identity_hash' => hash('sha256', $value), 'category' => $category, 'created_at' => now(),
            ]);
        }

        return $delivery;
    }
}
