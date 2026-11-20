<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\PartnerLeadsIndex;
use App\Models\Lead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\Partners\PartnerLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerLeadsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_only_current_administrators_can_view_or_use_the_inventory(): void
    {
        $this->get(route('admin.partner-leads.index'))->assertRedirect(route('login'));
        foreach (['partner', 'family', 'caregiver', 'sales', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.partner-leads.index'))->assertForbidden();
            Livewire::actingAs($user)->test(PartnerLeadsIndex::class)->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $component = Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)->call('selectLead', $lead->id);
        $admin->update(['role' => 'family']);
        $component->call('assignLead')->assertForbidden();
        $this->assertDatabaseCount('partner_leads', 0);
    }

    public function test_admin_sees_all_family_leads_their_services_and_no_request_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['name' => 'Interested family']);
        $this->lead(['name' => 'No requests family', 'phone' => '9195550102', 'data' => []]);
        $this->lead(['name' => 'Referral source hidden', 'phone' => '9195550103', 'lead_type' => Lead::TYPE_REFERRAL]);

        $this->actingAs($admin)->get(route('admin.partner-leads.index'))->assertOk()
            ->assertSee('Interested family')->assertSee('No requests family')
            ->assertSee('Meal delivery')->assertDontSee('Referral source hidden');
        Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)
            ->call('selectLead', $lead->id, 'home_safety')->assertSet('assignmentCategory', 'home_safety');
    }

    public function test_partner_picker_only_offers_approved_active_matching_partners_with_paid_credits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $eligible = $this->partner(['business_name' => 'Eligible meal provider']);
        $this->credit($eligible, ['used_quantity' => 3]);
        $paused = $this->partner(['status' => 'paused']);
        $this->credit($paused);
        $mismatch = $this->partner(['categories' => ['pharmacy_delivery']]);
        $this->credit($mismatch);
        $empty = $this->partner();
        $this->credit($empty, ['used_quantity' => 25]);
        $held = $this->partner();
        $this->credit($held, ['suspended_at' => now()]);
        $wrongRole = $this->partner();
        $this->credit($wrongRole);
        $wrongRole->user->update(['role' => 'family']);

        Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)->call('selectLead', $lead->id, 'meal_delivery')
            ->assertViewHas('eligiblePartners', fn ($partners) => $partners->modelKeys() === [$eligible->id]
                && (int) $partners->first()->available_credits === 22)
            ->set('assignmentPartnerId', (string) $eligible->id)->assertViewHas('selectedPartnerCredits', 22);
    }

    public function test_admin_sends_to_partner_inbox_and_a_second_send_cannot_use_another_credit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['name' => 'Delivered family']);
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $component = Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)
            ->call('selectLead', $lead->id, 'meal_delivery')->set('assignmentPartnerId', (string) $partner->id)
            ->set('sharingConfirmed', true)->set('geographyConfirmed', true)
            ->call('assignLead')->assertHasNoErrors()->assertSee('One credit used.')
            ->assertViewHas('selectedSent', fn ($sent) => $sent['meal_delivery']['partner_profile_id'] === $partner->id);

        $this->assertSame(1, $credit->fresh()->used_quantity);
        $this->assertDatabaseHas('partner_leads', ['partner_profile_id' => $partner->id, 'lead_id' => $lead->id, 'category' => 'meal_delivery']);
        $component->set('assignmentPartnerId', (string) $partner->id)->set('sharingConfirmed', true)
            ->set('geographyConfirmed', true)->call('assignLead')->assertHasErrors('assignment');
        $this->assertSame(1, $credit->fresh()->used_quantity);
        $this->actingAs($partner->user)->get(route('partner.leads.index'))->assertOk()->assertSee('Delivered family');
        Mail::assertNothingSent();
    }

    public function test_missing_confirmations_and_tampered_partner_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $wrong = $this->partner(['categories' => ['pharmacy_delivery']]);
        $wrongCredit = $this->credit($wrong);
        $component = Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)
            ->call('selectLead', $lead->id, 'meal_delivery')->set('assignmentPartnerId', (string) $partner->id)
            ->call('assignLead')->assertHasErrors('assignment');
        $component->set('assignmentPartnerId', (string) $wrong->id)->set('sharingConfirmed', true)
            ->set('geographyConfirmed', true)->call('assignLead')->assertHasErrors('assignment');
        $this->assertSame(0, $credit->fresh()->used_quantity);
        $this->assertSame(0, $wrongCredit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 0);
    }

    public function test_changing_recipient_or_service_clears_confirmations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $first = $this->partner();
        $second = $this->partner();
        $this->credit($first);
        $this->credit($second);
        Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)
            ->call('selectLead', $lead->id)->set('assignmentPartnerId', (string) $first->id)
            ->set('sharingConfirmed', true)->set('geographyConfirmed', true)
            ->set('assignmentPartnerId', (string) $second->id)
            ->assertSet('sharingConfirmed', false)->assertSet('geographyConfirmed', false)
            ->set('sharingConfirmed', true)->set('geographyConfirmed', true)
            ->set('assignmentCategory', 'home_safety')->assertSet('assignmentPartnerId', '')
            ->assertSet('sharingConfirmed', false)->assertSet('geographyConfirmed', false);
    }

    public function test_claimed_category_is_visible_and_next_available_category_is_selected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $partner = $this->partner();
        $this->credit($partner);
        app(PartnerLeadService::class)->assign($partner, $lead, 'meal_delivery', $admin, true, true);

        Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)->call('selectLead', $lead->id)
            ->assertSet('assignmentCategory', 'home_safety')
            ->set('assignmentCategory', 'meal_delivery')->assertViewHas('eligiblePartners', fn ($partners) => $partners->isEmpty())
            ->assertSee($partner->business_name);
    }

    public function test_do_not_contact_lead_remains_visible_but_cannot_be_sent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['name' => 'Contact restricted family', 'do_not_contact_at' => now()]);
        $partner = $this->partner();
        $credit = $this->credit($partner);
        Livewire::actingAs($admin)->test(PartnerLeadsIndex::class)->assertSee('Contact restricted family')
            ->call('selectLead', $lead->id)->assertViewHas('selectedBlockedReason', fn ($reason) => is_string($reason))
            ->assertViewHas('eligiblePartners', fn ($partners) => $partners->isEmpty())
            ->set('assignmentPartnerId', (string) $partner->id)->set('sharingConfirmed', true)
            ->set('geographyConfirmed', true)->call('assignLead')->assertHasErrors('assignment');
        $this->assertSame(0, $credit->fresh()->used_quantity);
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_replace([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Interested person', 'email' => 'interested@example.test',
            'phone' => '9195550101', 'location' => 'Raleigh, NC', 'zip' => '27601', 'status' => 'new',
            'data' => ['care_preferences' => ['additional_services' => ['Meal delivery', 'Home safety improvements']]],
        ], $overrides));
    }

    private function partner(array $overrides = []): PartnerProfile
    {
        $user = User::factory()->create(['role' => 'partner']);

        return PartnerProfile::create(array_replace([
            'user_id' => $user->id, 'business_name' => 'Partner '.$user->id, 'contact_name' => $user->name,
            'contact_email' => $user->email, 'contact_phone' => '9195550100', 'categories' => ['meal_delivery', 'home_safety'],
            'coverage_states' => ['NC'], 'coverage_type' => 'entire_states', 'status' => 'approved',
            'submitted_at' => now(), 'terms_accepted_at' => now(),
        ], $overrides));
    }

    private function credit(PartnerProfile $partner, array $overrides = []): PartnerLeadCredit
    {
        $order = PartnerSubscription::create([
            'public_id' => (string) Str::uuid(), 'plan' => 'monthly', 'partner_profile_id' => $partner->id,
            'stripe_customer_id' => 'cus_fixture_'.$partner->id, 'quantity' => 25,
            'unit_price_cents' => 3000, 'currency' => 'usd', 'status' => 'active',
        ]);

        return PartnerLeadCredit::create(array_replace([
            'partner_profile_id' => $partner->id, 'partner_subscription_id' => $order->id,
            'stripe_invoice_id' => 'in_fixture_'.$order->id, 'quantity' => 25, 'used_quantity' => 0,
            'amount_cents' => 75000, 'currency' => 'usd', 'period_start' => now()->startOfMonth(),
            'period_end' => now()->addMonth()->startOfMonth(), 'paid_at' => now(),
        ], $overrides));
    }
}
