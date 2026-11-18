<?php

namespace Tests\Feature;

use App\Livewire\Partner\Dashboard;
use App\Livewire\Partner\Leads;
use App\Models\PartnerLead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    private function partner(): PartnerProfile
    {
        $user = User::factory()->create(['role' => 'partner']);

        return PartnerProfile::create([
            'user_id' => $user->id, 'business_name' => 'Neighborhood Services',
            'contact_name' => $user->name, 'contact_email' => $user->email,
            'contact_phone' => '9195550100', 'categories' => ['meal_delivery', 'home_safety'],
            'coverage_states' => ['NC'], 'coverage_type' => 'entire_states',
            'status' => 'approved', 'internal_notes' => 'Private administrator note',
            'submitted_at' => now(), 'terms_accepted_at' => now(),
        ]);
    }

    private function delivery(PartnerProfile $profile, string $name, string $category = 'meal_delivery'): PartnerLead
    {
        return PartnerLead::create([
            'partner_profile_id' => $profile->id, 'source_lead_id' => (PartnerLead::query()->max('source_lead_id') ?? 100000) + 1,
            'category' => $category, 'contact_name' => $name, 'contact_email' => Str::slug($name).'@example.test',
            'contact_phone' => '9195550101', 'location' => 'Raleigh, NC', 'zip' => '27601',
            'service_context' => 'Requested this service.', 'assigned_at' => now(),
            'sharing_confirmed_at' => now(), 'geography_confirmed_at' => now(),
        ]);
    }

    private function order(PartnerProfile $profile, array $overrides = []): PartnerSubscription
    {
        return PartnerSubscription::create(array_replace([
            'public_id' => (string) Str::uuid(), 'partner_profile_id' => $profile->id,
            'plan' => 'monthly', 'stripe_customer_id' => 'cus_preview_'.$profile->id,
            'quantity' => 25, 'unit_price_cents' => 3000, 'currency' => 'usd', 'status' => 'active',
        ], $overrides));
    }

    public function test_partner_workspace_routes_require_the_current_partner_account(): void
    {
        foreach (['partner.leads.index', 'partner.billing.show'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $family = User::factory()->create(['role' => 'family']);
        $this->actingAs($family)->get(route('partner.leads.index'))->assertForbidden();
        $this->get(route('partner.billing.show'))->assertForbidden();
        $this->post(route('partner.billing.setup'))->assertForbidden();
        $this->post(route('partner.billing.subscribe'), ['plan' => 'monthly'])->assertForbidden();

        $partner = $this->partner();
        $partner->user->forceFill(['access_revoked_at' => now(), 'role' => 'offboarded', 'content_role' => null])->save();
        $this->actingAs($partner->user->fresh())->get(route('partner.billing.show'))->assertRedirect(route('login'));
        $this->assertGuest();

        $blocked = $this->partner();
        DB::table('staff_access_blocks')->insert([
            'kind' => 'email', 'value' => strtolower($blocked->user->email),
            'reason' => 'Workspace access regression test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($blocked->user)->get(route('partner.leads.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_overview_shows_only_owned_deliveries_and_available_paid_credits(): void
    {
        $own = $this->partner();
        $other = $this->partner();
        $this->delivery($own, 'Owned delivery');
        $this->delivery($other, 'Someone else delivery');
        $order = $this->order($own);
        PartnerLeadCredit::create([
            'partner_profile_id' => $own->id, 'partner_subscription_id' => $order->id,
            'stripe_invoice_id' => 'in_available', 'quantity' => 25, 'used_quantity' => 4,
            'amount_cents' => 75000, 'currency' => 'usd', 'period_start' => now()->subMonths(3),
            'period_end' => now()->subMonths(2), 'paid_at' => now()->subMonths(3),
        ]);
        PartnerLeadCredit::create([
            'partner_profile_id' => $own->id, 'partner_subscription_id' => $order->id,
            'stripe_invoice_id' => 'in_held', 'quantity' => 25, 'used_quantity' => 0,
            'amount_cents' => 75000, 'currency' => 'usd', 'period_start' => now()->subMonth(),
            'period_end' => now(), 'paid_at' => now()->subMonth(), 'suspended_at' => now(),
        ]);

        Livewire::actingAs($own->user)->test(Dashboard::class)
            ->assertViewHas('remainingCredits', 21)->assertViewHas('deliveredCount', 1)
            ->assertViewHas('hasSubscription', true)
            ->assertSee('Owned delivery')->assertDontSee('Someone else delivery')
            ->assertDontSee('Private administrator note');
    }

    public function test_lead_search_and_category_filter_cannot_cross_partner_ownership(): void
    {
        $own = $this->partner();
        $other = $this->partner();
        $this->delivery($own, 'Alex Meals');
        $this->delivery($own, 'Taylor Safety', 'home_safety');
        $this->delivery($other, 'Alex Private', 'home_safety');

        Livewire::actingAs($own->user)->test(Leads::class)
            ->assertSee('Alex Meals')->assertSee('Taylor Safety')->assertDontSee('Alex Private')
            ->set('search', 'Alex')->assertSee('Alex Meals')->assertDontSee('Taylor Safety')->assertDontSee('Alex Private')
            ->call('clearFilters')->set('categoryFilter', 'home_safety')
            ->assertSee('Taylor Safety')->assertDontSee('Alex Meals')->assertDontSee('Alex Private');
    }

    public function test_later_livewire_requests_recheck_access(): void
    {
        $partner = $this->partner();
        $component = Livewire::actingAs($partner->user)->test(Leads::class);
        $partner->user->forceFill(['access_revoked_at' => now()])->save();
        $component->set('search', 'Alex')->assertForbidden();
    }

    public function test_upfront_order_does_not_hide_an_existing_monthly_subscription(): void
    {
        $partner = $this->partner();
        $monthly = $this->order($partner);
        $this->order($partner, ['plan' => 'annual', 'status' => 'paid', 'quantity' => 300, 'unit_price_cents' => 2500]);

        $this->assertTrue($monthly->is($partner->subscription));
        Livewire::actingAs($partner->user)->test(Dashboard::class)->assertViewHas('hasSubscription', true);
    }
}
