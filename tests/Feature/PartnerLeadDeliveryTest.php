<?php

namespace Tests\Feature;

use App\Livewire\Admin\PartnersIndex;
use App\Models\Lead;
use App\Models\PartnerLead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\Partners\PartnerLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerLeadDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_delivery_uses_one_paid_credit_and_only_snapshots_the_selected_request(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead(['email' => ' PERSON@EXAMPLE.TEST ', 'data' => [
            'care_preferences' => ['additional_services' => ['Meal delivery'], 'help_needed' => 'Private health detail'],
            'notes' => 'Internal sales note', 'raw_payload' => ['private' => 'Raw private data'],
        ]]);
        $lead->activities()->create(['type' => 'note', 'body' => 'Private activity', 'occurred_at' => now()]);

        $delivery = $this->send($partner, $lead);

        $this->assertSame($partner->id, $delivery->partner_profile_id);
        $this->assertSame('person@example.test', $delivery->contact_email);
        $this->assertSame('+19195551234', $delivery->contact_phone);
        $this->assertSame('Raleigh, NC', $delivery->location);
        $this->assertSame('Meal delivery', $delivery->categoryLabel());
        $this->assertSame('Requested information about Meal delivery.', $delivery->service_context);
        $this->assertNotNull($delivery->sharing_confirmed_at);
        $this->assertNotNull($delivery->geography_confirmed_at);
        $this->assertSame(1, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_lead_claims', 3);
        foreach (['Private health detail', 'Internal sales note', 'Raw private data', 'Private activity'] as $private) {
            $this->assertStringNotContainsString($private, $delivery->toJson());
        }
        $lead->update(['name' => 'Changed CRM name']);
        $this->assertSame('Pat Example', $delivery->fresh()->contact_name);
    }

    public function test_a_contact_can_only_be_delivered_once_per_category_across_partners(): void
    {
        $first = $this->partner(['categories' => ['meal_delivery', 'home_safety']]);
        $second = $this->partner(['categories' => ['meal_delivery', 'home_safety']]);
        $firstCredit = $this->credit($first);
        $secondCredit = $this->credit($second);
        $lead = $this->lead(['data' => ['care_preferences' => ['additional_services' => [
            'Meal delivery', 'Home safety improvements (ramps, grab bars, stair lifts)',
        ]]]]);

        $this->send($first, $lead);
        $this->assertRejected(fn () => $this->send($first, $lead), 'already been sent');
        $this->assertRejected(fn () => $this->send($second, $lead), 'already been sent');
        $this->send($second, $lead, 'home_safety');

        $this->assertDatabaseCount('partner_leads', 2);
        $this->assertSame(1, $firstCredit->fresh()->used_quantity);
        $this->assertSame(1, $secondCredit->fresh()->used_quantity);
    }

    public function test_deletion_reimport_and_contact_format_changes_do_not_reopen_a_category(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        $delivery = $this->send($partner, $lead);
        $lead->delete();
        $this->assertNull($delivery->fresh()->lead_id);
        $phoneRepeat = $this->lead(['phone' => '+1 (919) 555-1234', 'email' => 'new@example.test']);
        $this->assertRejected(fn () => $this->send($partner, $phoneRepeat), 'already been sent');
        $emailRepeat = $this->lead(['phone' => '9195554321', 'email' => ' PERSON@EXAMPLE.TEST ']);
        $this->assertRejected(fn () => $this->send($partner, $emailRepeat), 'already been sent');
        // Even deleting the delivered snapshot cannot release the durable claims.
        $delivery->delete();
        $this->assertRejected(fn () => $this->send($partner, $emailRepeat), 'already been sent');
        $this->assertSame(1, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_lead_claims', 3);
    }

    public function test_crm_merges_keep_claims_for_old_contact_details_and_deleted_source_ids(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $sold = $this->lead();
        $this->send($partner, $sold);
        $survivor = $this->lead(['phone' => '9195559999', 'email' => 'survivor@example.test']);
        $survivor->update(['phone' => $sold->phone]);
        $this->assertDatabaseMissing('leads', ['id' => $sold->id]);
        $survivor->update(['phone' => '9195557777']);
        // Neither current phone nor email equals the sold snapshot after the edits.
        $this->assertRejected(fn () => $this->send($partner, $survivor), 'already been sent');
        $this->assertSame(1, $credit->fresh()->used_quantity);
    }

    public function test_aliases_known_before_delivery_are_claimed_as_well(): void
    {
        $partner = $this->partner();
        $this->credit($partner);
        $lead = $this->lead();
        $duplicate = $this->lead(['email' => 'alias@example.test']);
        $this->assertSame($lead->id, $duplicate->id);
        $this->send($partner, $lead);
        $lead->delete();
        $alias = $this->lead(['phone' => '9195559876', 'email' => 'ALIAS@example.test']);
        $this->assertRejected(fn () => $this->send($partner, $alias), 'already been sent');
    }

    public function test_category_mapping_handles_imported_lists_and_only_recognized_requests(): void
    {
        $service = app(PartnerLeadService::class);
        $lead = new Lead(['data' => ['raw_payload' => ['additional_services' =>
            'Meal delivery, Home safety improvements (ramps, grab bars, stair lifts), Pharmacy pickup or delivery;Estate planning & wills',
        ]]]);
        $this->assertSame(['meal_delivery', 'home_safety', 'pharmacy_delivery', 'estate_planning'], $service->categoriesFor($lead));
        $lead->data = ['care_preferences' => ['additional_services' => ['meal_delivery', 'Not interested', 'Other services']]];
        $this->assertSame(['meal_delivery'], $service->categoriesFor($lead));
        $lead->data = ['care_preferences' => ['additional_services' => []], 'raw_payload' => ['additional_services' => 'Meal delivery']];
        $this->assertSame([], $service->categoriesFor($lead));
    }

    public function test_permission_and_geography_confirmation_are_required_without_debiting(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        foreach ([[false, false], [false, true], [true, false]] as [$permission, $geography]) {
            $this->assertRejected(fn () => app(PartnerLeadService::class)->assign(
                $partner, $lead, 'meal_delivery', $this->admin, $permission, $geography,
            ), 'Confirm permission');
        }
        $this->assertDatabaseCount('partner_leads', 0);
        $this->assertDatabaseCount('partner_lead_claims', 0);
        $this->assertSame(0, $credit->fresh()->used_quantity);
    }

    public function test_partner_status_role_and_access_are_rechecked_before_delivery(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        foreach (['new', 'paused'] as $status) {
            PartnerProfile::whereKey($partner->id)->update(['status' => $status]);
            $this->assertRejected(fn () => $this->send($partner, $lead), 'approved, active partner');
        }
        PartnerProfile::whereKey($partner->id)->update(['status' => 'approved']);
        $partner->user->update(['role' => 'family']);
        $this->assertRejected(fn () => $this->send($partner, $lead), 'approved, active partner');
        $partner->user->forceFill(['role' => 'partner', 'access_revoked_at' => now()])->save();
        $this->assertRejected(fn () => $this->send($partner, $lead), 'approved, active partner');
        $this->assertSame(0, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 0);
    }

    public function test_missing_interest_unsupported_services_and_do_not_contact_are_rejected(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        foreach (['home_safety', 'invented_service'] as $category) {
            $this->assertRejected(fn () => $this->send($partner, $lead, $category), 'service requested');
        }
        $lead->update(['data' => ['care_preferences' => ['additional_services' => []]]]);
        $this->assertRejected(fn () => $this->send($partner, $lead), 'service requested');
        $lead->update(['data' => ['care_preferences' => ['additional_services' => ['Meal delivery']]], 'do_not_contact_at' => now()]);
        $this->assertRejected(fn () => $this->send($partner, $lead), 'cannot be shared');
        $lead->update(['do_not_contact_at' => null, 'lead_type' => Lead::TYPE_REFERRAL]);
        $this->assertRejected(fn () => $this->send($partner, $lead), 'cannot be shared');
        $lead->update(['lead_type' => Lead::TYPE_FAMILY, 'email' => 'invalid-email', 'phone' => '555']);
        $this->assertRejected(fn () => $this->send($partner, $lead), 'valid phone number or email');
        $this->assertSame(0, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 0);
    }

    public function test_credit_consumption_is_fifo_and_skips_suspended_or_spent_credits(): void
    {
        $partner = $this->partner();
        $lead = $this->lead();
        $this->assertRejected(fn () => $this->send($partner, $lead), 'no available paid lead credits');
        $other = $this->credit($this->partner());
        $this->assertRejected(fn () => $this->send($partner, $lead), 'no available paid lead credits');
        $suspended = $this->credit($partner, ['paid_at' => now()->subDays(5), 'suspended_at' => now()]);
        $spent = $this->credit($partner, ['paid_at' => now()->subDays(4), 'used_quantity' => 25]);
        $oldest = $this->credit($partner, ['paid_at' => now()->subDays(3), 'used_quantity' => 24]);
        $newer = $this->credit($partner, ['paid_at' => now()->subDays(2)]);
        // Canceling renewal does not forfeit previously paid credits.
        $oldest->subscription->update(['status' => 'canceled']);
        $delivery = $this->send($partner, $lead);
        $second = $this->send($partner, $this->lead(['phone' => '9195554321', 'email' => 'second@example.test']));
        $this->assertSame($oldest->id, $delivery->partner_lead_credit_id);
        $this->assertSame($newer->id, $second->partner_lead_credit_id);
        $this->assertSame(25, $oldest->fresh()->used_quantity);
        $this->assertSame(1, $newer->fresh()->used_quantity);
        $this->assertSame(0, $suspended->fresh()->used_quantity);
        $this->assertSame(25, $spent->fresh()->used_quantity);
        $this->assertSame(0, $other->fresh()->used_quantity);
    }

    public function test_credit_debit_and_delivery_roll_back_when_a_claim_is_taken_after_the_check(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        // Simulate another writer winning between the initial check and claim insertion.
        PartnerLead::creating(function (PartnerLead $delivery): void {
            DB::table('partner_lead_claims')->insert([
                'identity_type' => 'email', 'identity_hash' => hash('sha256', $delivery->contact_email),
                'category' => $delivery->category, 'created_at' => now(),
            ]);
        });
        try {
            $this->assertRejected(fn () => $this->send($partner, $lead), 'already been sent');
        } finally {
            PartnerLead::flushEventListeners();
        }
        $this->assertSame(0, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 0);
        $this->assertDatabaseCount('partner_lead_claims', 0);
    }

    public function test_only_current_admins_can_deliver_leads(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        foreach (['partner', 'sales', 'sdr', 'family'] as $role) {
            $this->admin->update(['role' => $role]);
            try {
                $this->send($partner, $lead);
                $this->fail('Non-administrators must not deliver leads.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->admin->update(['role' => 'admin']);
        $staleAdmin = $this->admin->fresh();
        $this->admin->forceFill(['access_revoked_at' => now()])->save();
        try {
            app(PartnerLeadService::class)->assign($partner, $lead, 'meal_delivery', $staleAdmin, true, true);
            $this->fail('A stale administrator session must not deliver leads.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, $credit->fresh()->used_quantity);
        $this->assertDatabaseCount('partner_leads', 0);
    }

    public function test_admin_directory_can_select_confirm_and_send_a_lead(): void
    {
        $partner = $this->partner();
        $credit = $this->credit($partner);
        $lead = $this->lead();
        Livewire::actingAs($this->admin)->test(PartnersIndex::class)
            ->call('selectPartner', $partner->id)->assertSee('25 credits available')
            ->set('leadSearch', (string) $lead->id)->assertSee('Pat Example')
            ->call('selectAssignmentLead', $lead->id)->assertSet('assignmentCategory', 'meal_delivery')
            ->call('assignLead')->assertHasErrors('assignment')
            ->set('sharingConfirmed', true)->set('geographyConfirmed', true)
            ->call('assignLead')->assertHasNoErrors()->assertSet('assignmentLeadId', null)
            ->assertSee('Lead sent to the partner dashboard. One credit used.')->assertSee('24 credits available');
        $this->assertDatabaseHas('partner_leads', ['partner_profile_id' => $partner->id, 'lead_id' => $lead->id]);
        $this->assertSame(1, $credit->fresh()->used_quantity);
        Livewire::actingAs($partner->user)->test(PartnersIndex::class)->assertForbidden();
    }

    public function test_changing_the_lead_or_category_clears_the_admin_confirmations(): void
    {
        $partner = $this->partner(['categories' => ['meal_delivery', 'home_safety']]);
        $this->credit($partner);
        $lead = $this->lead(['data' => ['care_preferences' => ['additional_services' => ['Meal delivery', 'Home safety improvements']]]]);
        $second = $this->lead(['phone' => '9195554321', 'email' => 'second@example.test']);
        Livewire::actingAs($this->admin)->test(PartnersIndex::class)->call('selectPartner', $partner->id)
            ->call('selectAssignmentLead', $lead->id)->set('sharingConfirmed', true)->set('geographyConfirmed', true)
            ->set('assignmentCategory', 'home_safety')->assertSet('sharingConfirmed', false)->assertSet('geographyConfirmed', false)
            ->set('sharingConfirmed', true)->set('geographyConfirmed', true)->call('selectAssignmentLead', $second->id)
            ->assertSet('sharingConfirmed', false)->assertSet('geographyConfirmed', false);
    }

    private function send(PartnerProfile $partner, Lead $lead, string $category = 'meal_delivery'): PartnerLead
    {
        return app(PartnerLeadService::class)->assign($partner, $lead, $category, $this->admin, true, true);
    }

    private function assertRejected(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected delivery to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment', $exception->errors());
            $this->assertStringContainsString($message, $exception->errors()['assignment'][0]);
        }
    }

    private function partner(array $overrides = []): PartnerProfile
    {
        $user = User::factory()->create(['role' => 'partner']);

        return PartnerProfile::create(array_replace([
            'user_id' => $user->id, 'business_name' => 'Carolina Meal Partners',
            'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '9195550100',
            'categories' => ['meal_delivery'], 'coverage_states' => ['NC'], 'coverage_type' => 'entire_states',
            'status' => 'approved', 'submitted_at' => now(), 'terms_accepted_at' => now(),
        ], $overrides));
    }

    private function credit(PartnerProfile $partner, array $overrides = []): PartnerLeadCredit
    {
        $subscription = PartnerSubscription::create([
            'public_id' => (string) Str::uuid(), 'plan' => 'monthly', 'partner_profile_id' => $partner->id,
            'stripe_customer_id' => 'cus_partner_'.$partner->id, 'quantity' => 25, 'unit_price_cents' => 3000,
            'currency' => 'usd', 'status' => 'active',
        ]);

        return PartnerLeadCredit::create(array_replace([
            'partner_profile_id' => $partner->id, 'partner_subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_'.Str::random(20), 'quantity' => 25, 'used_quantity' => 0,
            'amount_cents' => 75000, 'currency' => 'usd', 'period_start' => now()->startOfMonth(),
            'period_end' => now()->addMonth()->startOfMonth(), 'paid_at' => now(),
        ], $overrides));
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_replace([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Pat Example', 'email' => 'person@example.test',
            'phone' => '(919) 555-1234', 'location' => 'Raleigh, NC', 'zip' => '27601', 'status' => 'new',
            'source' => 'manual', 'data' => ['care_preferences' => ['additional_services' => ['Meal delivery']]],
        ], $overrides));
    }
}
