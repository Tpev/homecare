<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PartnerLead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\Partners\PartnerLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerLeadAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PartnerProfile $partner;

    private PartnerLeadCredit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'partner']);
        $this->partner = PartnerProfile::create([
            'user_id' => $owner->id, 'business_name' => 'Carolina Meal Partners',
            'contact_name' => $owner->name, 'contact_email' => $owner->email, 'contact_phone' => '9195550100',
            'categories' => ['meal_delivery', 'home_safety'], 'coverage_states' => ['NC'],
            'coverage_type' => 'entire_states', 'status' => 'approved',
            'submitted_at' => now(), 'terms_accepted_at' => now(),
        ]);
        $subscription = PartnerSubscription::create([
            'public_id' => (string) Str::uuid(), 'plan' => 'monthly', 'partner_profile_id' => $this->partner->id,
            'stripe_customer_id' => 'cus_availability', 'quantity' => 25, 'unit_price_cents' => 3000,
            'currency' => 'usd', 'status' => 'active',
        ]);
        $this->credit = PartnerLeadCredit::create([
            'partner_profile_id' => $this->partner->id, 'partner_subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_availability', 'quantity' => 25, 'used_quantity' => 0,
            'amount_cents' => 75000, 'currency' => 'usd', 'period_start' => now()->startOfMonth(),
            'period_end' => now()->addMonth()->startOfMonth(), 'paid_at' => now(),
        ]);
    }

    public function test_current_contact_returns_one_sent_category_without_consuming_another_credit(): void
    {
        $lead = $this->lead();
        $delivery = $this->send($lead);

        $sent = app(PartnerLeadService::class)->sentCategoriesFor($lead);

        $this->assertSame(['meal_delivery'], array_keys($sent));
        $this->assertSame($this->partner->id, $sent['meal_delivery']['partner_profile_id']);
        $this->assertSame('Carolina Meal Partners', $sent['meal_delivery']['partner_name']);
        $this->assertSame($delivery->id, $sent['meal_delivery']['delivery_id']);
        $this->assertInstanceOf(Carbon::class, $sent['meal_delivery']['assigned_at']);
        $this->assertTrue($sent['meal_delivery']['assigned_at']->equalTo($delivery->fresh()->assigned_at));
        $this->assertContains('home_safety', app(PartnerLeadService::class)->categoriesFor($lead));
        $this->assertArrayNotHasKey('home_safety', $sent);
        $this->assertSame(1, $this->credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 1);
        $this->assertDatabaseCount('partner_lead_claims', 3);
    }

    public function test_reimports_match_phone_or_email_and_unrelated_contacts_stay_unsent(): void
    {
        $original = $this->lead();
        $delivery = $this->send($original);
        $original->delete();
        $samePhone = $this->lead(['phone' => '+1 (919) 555-1234', 'email' => 'new@example.test']);
        $sameEmail = $this->lead(['phone' => '9195554321', 'email' => ' PERSON@EXAMPLE.TEST ']);
        $unrelated = $this->lead(['phone' => '9195559876', 'email' => 'unrelated@example.test']);

        $sent = app(PartnerLeadService::class)->sentCategoriesForLeads([$samePhone, $sameEmail, $unrelated]);

        $this->assertNull($delivery->fresh()->lead_id);
        $this->assertSame($delivery->id, $sent[$samePhone->id]['meal_delivery']['delivery_id']);
        $this->assertSame($delivery->id, $sent[$sameEmail->id]['meal_delivery']['delivery_id']);
        $this->assertSame([], $sent[$unrelated->id]);
    }

    public function test_merged_historical_identities_remain_sent_after_current_contact_details_change(): void
    {
        $sold = $this->lead();
        $delivery = $this->send($sold);
        $survivor = $this->lead(['phone' => '9195559999', 'email' => 'survivor@example.test']);
        $survivor->update(['phone' => $sold->phone]);
        $survivor->update(['phone' => '9195557777']);
        // A caller may hold an incomplete relation. Read the dedicated merge history.
        $cachedActivities = collect();
        $survivor->setRelation('activities', $cachedActivities);

        $sent = app(PartnerLeadService::class)->sentCategoriesFor($survivor);

        $this->assertDatabaseMissing('leads', ['id' => $sold->id]);
        $this->assertSame($delivery->id, $sent['meal_delivery']['delivery_id']);
        $this->assertSame($cachedActivities, $survivor->getRelation('activities'));
    }

    public function test_orphan_claims_still_mark_the_category_sent(): void
    {
        $lead = $this->lead();
        $delivery = $this->send($lead);
        $sentAt = $delivery->fresh()->assigned_at;
        $delivery->delete();

        $sent = app(PartnerLeadService::class)->sentCategoriesFor($lead);

        $this->assertSame(['meal_delivery'], array_keys($sent));
        $this->assertNull($sent['meal_delivery']['delivery_id']);
        $this->assertNull($sent['meal_delivery']['partner_profile_id']);
        $this->assertNull($sent['meal_delivery']['partner_name']);
        $this->assertTrue($sent['meal_delivery']['assigned_at']->equalTo($sentAt));
        $this->assertDatabaseCount('partner_lead_claims', 3);
    }

    public function test_batched_results_are_isolated_and_queries_do_not_grow_per_lead(): void
    {
        $sold = $this->lead();
        $delivery = $this->send($sold);
        $leads = collect([$sold]);
        foreach (range(1, 30) as $number) {
            $leads->push($this->lead(['phone' => '919555'.str_pad((string) (2000 + $number), 4, '0', STR_PAD_LEFT),
                'email' => 'unsent'.$number.'@example.test']));
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $sent = app(PartnerLeadService::class)->sentCategoriesForLeads($leads);
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertCount(31, $sent);
        $this->assertSame($delivery->id, $sent[$sold->id]['meal_delivery']['delivery_id']);
        foreach ($leads->skip(1) as $lead) {
            $this->assertSame([], $sent[$lead->id]);
        }
        $this->assertLessThanOrEqual(6, $queryCount, 'Availability should batch identities and merge notes, not query each lead.');
    }

    public function test_large_batches_find_claims_beyond_the_first_chunk(): void
    {
        $rows = [];
        foreach (range(1, 501) as $number) {
            $rows[] = [
                'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Batch contact '.$number,
                'email' => 'batch'.$number.'@example.test', 'phone' => null, 'status' => 'new',
                'data' => json_encode(['care_preferences' => ['additional_services' => ['Meal delivery']]]),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('leads')->insert($chunk);
        }
        $leads = Lead::query()->orderBy('id')->get();
        $last = $leads->last();
        $delivery = $this->send($last);

        $sent = app(PartnerLeadService::class)->sentCategoriesForLeads($leads);

        $this->assertCount(501, $sent);
        $this->assertSame([], $sent[$leads->first()->id]);
        $this->assertSame($delivery->id, $sent[$last->id]['meal_delivery']['delivery_id']);
        $this->assertCount(1, array_filter($sent));
    }

    public function test_blocker_helper_matches_contact_rules_without_claiming_partner_or_credit_eligibility(): void
    {
        $service = app(PartnerLeadService::class);
        $base = ['lead_type' => Lead::TYPE_FAMILY, 'phone' => '9195551234', 'email' => null, 'data' => []];
        foreach ([['lead_type' => Lead::TYPE_REFERRAL], ['do_not_contact_at' => now()],
            ['data' => ['provider_outreach' => ['do_not_call' => true]]]] as $blocked) {
            $this->assertStringContainsString('cannot be shared', $service->unavailabilityReason(new Lead(array_replace($base, $blocked))));
        }
        $invalid = new Lead(array_replace($base, ['phone' => '555', 'email' => 'invalid-email']));
        $this->assertStringContainsString('valid phone number or email', $service->unavailabilityReason($invalid));
        $this->assertNull($service->unavailabilityReason(new Lead($base)));
        $this->assertNull($service->unavailabilityReason(new Lead(array_replace($base, ['phone' => null, 'email' => ' PERSON@EXAMPLE.TEST ']))));
        $this->assertSame([], $service->sentCategoriesForLeads([]));
        $this->assertSame([], $service->sentCategoriesFor(new Lead($base)));
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_replace([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Pat Example', 'email' => 'person@example.test',
            'phone' => '(919) 555-1234', 'location' => 'Raleigh, NC', 'zip' => '27601', 'status' => 'new',
            'data' => ['care_preferences' => ['additional_services' => ['Meal delivery', 'Home safety improvements']]],
        ], $overrides));
    }

    private function send(Lead $lead): PartnerLead
    {
        return app(PartnerLeadService::class)->assign($this->partner, $lead, 'meal_delivery', $this->admin, true, true);
    }
}
