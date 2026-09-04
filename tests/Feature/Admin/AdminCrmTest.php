<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\LeadsIndex;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_crm_and_compact_admin_navigation(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.crm.index'));

        $response->assertOk();
        $response->assertSee('Lead command center');
        $response->assertSee('Family / care receiver leads');
        $response->assertSee('Referral source recruiting');
        $response->assertSee('data-admin-compact-navigation', false);
        $response->assertSee('data-admin-all-tools', false);
        $response->assertSee('Dashboard');
        $response->assertSee('Work queues');
        $response->assertSee('Insights');
        $response->assertSee('All tools');
        $response->assertSee('Search admin tools');
        $response->assertSee('Care operations');
        $response->assertSee('People');
        $response->assertSee('Communications &amp; money', false);
        $response->assertSee('Analytics');
        $response->assertSee('Admin Requests');
        $response->assertSee('Admin Users');

        $this->actingAs($admin)->get(route('admin.leads.index'))->assertOk();
    }

    public function test_admin_can_manage_family_lead_through_crm_timeline(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->set('leadForm.name', 'Don Johnson')
            ->set('leadForm.contact_role', 'Self')
            ->set('leadForm.email', 'don@example.com')
            ->set('leadForm.phone', '555-111-2222')
            ->set('leadForm.location', 'Durham, NC')
            ->set('leadForm.zip', '27703')
            ->set('leadForm.source', 'phone')
            ->set('leadForm.priority', Lead::PRIORITY_HIGH)
            ->set('leadForm.assigned_admin_id', (string) $admin->id)
            ->set('leadForm.next_follow_up_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('leadForm.notes', 'Needs companionship twice per week.')
            ->call('createLead')
            ->assertHasNoErrors();

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_FAMILY, $lead->lead_type);
        $this->assertSame('Don Johnson', $lead->name);
        $this->assertSame('phone', $lead->source);
        $this->assertSame(Lead::PRIORITY_HIGH, $lead->priority);
        $this->assertSame($admin->id, $lead->assigned_admin_id);
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Lead created',
        ]);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->call('openLead', $lead->id)
            ->set('activityForm.type', LeadActivity::TYPE_CALL)
            ->set('activityForm.body', 'Called Don. He wants to talk again tomorrow with his daughter.')
            ->set('activityForm.occurred_at', now()->format('Y-m-d\TH:i'))
            ->set('activityForm.next_follow_up_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('logActivity')
            ->set('leadForm.status', 'qualified')
            ->call('saveLead')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertSame('qualified', $lead->status);
        $this->assertNotNull($lead->last_contacted_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_CALL,
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_STAGE_CHANGE,
        ]);
    }

    public function test_sales_user_can_access_only_crm_and_own_referral_leads(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);
        $sales = User::factory()->create([
            'email' => 'jess.eberdt@gmail.com',
            'role' => 'sales',
            'name' => 'Jess Eberdt',
        ]);
        $otherSales = User::factory()->create([
            'email' => 'rj@antonellilawfirm.com',
            'role' => 'sales',
            'name' => 'RJ Antonelli',
        ]);

        $this->actingAs($sales)
            ->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('Lead command center')
            ->assertSee('CRM')
            ->assertDontSee('data-admin-compact-navigation', false)
            ->assertDontSee('data-admin-all-tools', false)
            ->assertDontSee('Work queues')
            ->assertDontSee('Admin Users')
            ->assertDontSee('Admin Requests')
            ->assertDontSee('Communications &amp; money', false);

        $this->actingAs($sales)->get(route('admin.leads.index'))->assertOk();
        $this->actingAs($sales)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.requests.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.sms.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.payments.ops'))->assertForbidden();

        Livewire::actingAs($sales)
            ->test(LeadsIndex::class, ['pipeline' => Lead::TYPE_REFERRAL])
            ->set('leadForm.name', 'Duke discharge team')
            ->set('leadForm.contact_role', 'Case manager')
            ->set('leadForm.company', 'Duke Hospital')
            ->set('leadForm.email', 'duke@example.com')
            ->set('leadForm.phone', '555-111-4444')
            ->set('leadForm.location', 'Durham, NC')
            ->set('leadForm.source', 'hospital')
            ->set('leadForm.priority', Lead::PRIORITY_HIGH)
            ->set('leadForm.assigned_admin_id', (string) $otherSales->id)
            ->call('createLead')
            ->assertHasNoErrors();

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_REFERRAL, $lead->lead_type);
        $this->assertSame($otherSales->id, $lead->assigned_admin_id);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->set('leadForm.name', 'Don Johnson')
            ->set('leadForm.status', 'new')
            ->set('leadForm.priority', Lead::PRIORITY_NORMAL)
            ->set('leadForm.assigned_admin_id', (string) $sales->id)
            ->call('createLead')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'name' => 'Don Johnson',
            'assigned_admin_id' => $sales->id,
        ]);
    }

    public function test_sales_user_dashboard_redirects_to_crm(): void
    {
        $sales = User::factory()->create([
            'email' => 'rj@antonellilawfirm.com',
            'role' => 'sales',
            'name' => 'RJ Antonelli',
        ]);

        $this->actingAs($sales)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.crm.index', absolute: false));
    }

    public function test_create_sales_user_command_upserts_crm_only_accounts(): void
    {
        $this->artisan('crm:create-sales-user rj@antonellilawfirm.com --name="RJ Antonelli" --password=TemporaryPass123!')
            ->assertSuccessful();

        $user = User::query()->where('email', 'rj@antonellilawfirm.com')->sole();

        $this->assertSame('sales', $user->role);
        $this->assertSame('RJ Antonelli', $user->name);
        $this->assertTrue(Hash::check('TemporaryPass123!', $user->password));

        $this->artisan('crm:create-sales-user rj@antonellilawfirm.com --name="RJ Sales" --password=ChangedPass123!')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $user->refresh();

        $this->assertSame('RJ Sales', $user->name);
        $this->assertSame('sales', $user->role);
        $this->assertTrue(Hash::check('ChangedPass123!', $user->password));
    }

    public function test_admin_can_create_and_advance_referral_source_lead(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->call('setPipeline', Lead::TYPE_REFERRAL)
            ->set('leadForm.name', 'Dr. Sarah Smith')
            ->set('leadForm.contact_role', 'Primary care physician')
            ->set('leadForm.company', 'Triangle Primary Care')
            ->set('leadForm.email', 'sarah@example.com')
            ->set('leadForm.phone', '555-222-3333')
            ->set('leadForm.location', 'Raleigh, NC')
            ->set('leadForm.source', 'pcp_outreach')
            ->set('leadForm.priority', Lead::PRIORITY_URGENT)
            ->set('leadForm.assigned_admin_id', (string) $admin->id)
            ->call('createLead')
            ->assertHasNoErrors();

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_REFERRAL, $lead->lead_type);
        $this->assertSame('Dr. Sarah Smith', $lead->name);
        $this->assertSame('pcp_outreach', $lead->source);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class, ['pipeline' => Lead::TYPE_REFERRAL])
            ->call('updateStatus', $lead->id, 'active_referral')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertSame('active_referral', $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_STAGE_CHANGE,
        ]);
    }

    public function test_referral_recruiting_has_material_stages_after_qualified(): void
    {
        $stages = array_keys(Lead::REFERRAL_STAGES);
        $qualifiedIndex = array_search('qualified', $stages, true);

        $this->assertIsInt($qualifiedIndex);
        $this->assertSame([
            'qualified',
            'need_to_drop_material',
            'need_to_email_material',
            'material_dropped_sent',
        ], array_slice($stages, $qualifiedIndex, 4));
    }

    public function test_referral_board_loads_each_stage_independently_with_accurate_counts_and_load_more(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);
        $now = now();

        Lead::query()->insert(collect(range(1, 121))->map(fn (int $index): array => [
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Earlier outreach '.$index,
            'status' => 'outreach',
            'priority' => Lead::PRIORITY_NORMAL,
            'next_follow_up_at' => $now->copy()->subDays(2),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
        Lead::query()->insert(collect(range(1, 10))->map(fn (int $index): array => [
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Material drop-off '.$index,
            'status' => 'need_to_drop_material',
            'priority' => Lead::PRIORITY_NORMAL,
            'next_follow_up_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now->copy()->addSeconds($index),
        ])->all());

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class, ['pipeline' => Lead::TYPE_REFERRAL])
            ->assertViewHas('boardStageCounts', fn (array $counts): bool => ($counts['outreach'] ?? null) === 121
                && ($counts['need_to_drop_material'] ?? null) === 10)
            ->assertViewHas('boardLeads', fn ($stages): bool => $stages->get('outreach')->count() === 8
                && $stages->get('need_to_drop_material')->count() === 8)
            ->assertSee('Load more · showing 8 of 10')
            ->call('loadMoreBoardStage', 'need_to_drop_material')
            ->assertSet('boardStageLimits.need_to_drop_material', 16)
            ->assertViewHas('boardLeads', fn ($stages): bool => $stages->get('need_to_drop_material')->count() === 10)
            ->assertDontSee('Load more · showing 8 of 10');
    }

    public function test_admin_can_export_every_filtered_lead_in_a_referral_stage_with_full_activity_history(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);
        $otherOwner = User::factory()->create([
            'role' => 'sales',
            'name' => 'Other Owner',
        ]);

        foreach (range(1, 10) as $index) {
            $lead = Lead::query()->create([
                'lead_type' => Lead::TYPE_REFERRAL,
                'name' => 'Export Practice '.$index,
                'email' => "contact{$index}@example.com",
                'phone' => '+1919555'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'company' => 'Practice '.$index,
                'contact_role' => 'Office manager',
                'location' => 'Raleigh, NC',
                'zip' => '27601',
                'status' => 'outreach',
                'priority' => Lead::PRIORITY_HIGH,
                'source' => 'sdr_import',
                'source_detail' => 'September provider list',
                'assigned_admin_id' => $index === 10 ? $otherOwner->id : $admin->id,
                'data' => ['sdr' => ['tags' => ['pcp', 'raleigh']]],
            ]);

            $lead->activities()->create([
                'actor_user_id' => $admin->id,
                'type' => LeadActivity::TYPE_NOTE,
                'summary' => 'Internal note',
                'body' => $index === 1 ? '=Important spreadsheet-safe comment' : 'Comment for lead '.$index,
                'occurred_at' => now()->subMinutes($index),
                'metadata' => ['source' => 'test'],
            ]);
        }

        Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Different Stage Practice',
            'status' => 'qualified',
            'priority' => Lead::PRIORITY_NORMAL,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LeadsIndex::class, ['pipeline' => Lead::TYPE_REFERRAL])
            ->set('assigned', (string) $admin->id)
            ->assertSee('Export all Outreach leads')
            ->assertViewHas('boardLeads', fn ($stages): bool => $stages->get('outreach')->count() === 8);

        $response = $component->instance()->exportStage('outreach');

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Activity history and comments', $csv);
        $this->assertStringContainsString('Additional lead data', $csv);
        $this->assertStringContainsString('Export Practice 1', $csv);
        $this->assertStringContainsString('Export Practice 9', $csv);
        $this->assertStringNotContainsString('Export Practice 10', $csv);
        $this->assertStringNotContainsString('Different Stage Practice', $csv);
        $this->assertStringContainsString("'+19195550001", $csv);
        $this->assertStringContainsString('=Important spreadsheet-safe comment', $csv);
        $this->assertStringContainsString('Internal note', $csv);
        $this->assertStringContainsString('Ops Admin', $csv);
        $this->assertSame(9, substr_count($csv, 'Export Practice '));
        $this->assertTrue(Str::startsWith($csv, "\xEF\xBB\xBF"));
    }

    public function test_admin_can_drag_move_lead_across_board_stages(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Move Me Lead',
            'email' => 'move@example.com',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'phone',
            'data' => ['source' => 'test'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.crm.index'))
            ->assertOk()
            ->assertSee('Move Me Lead')
            ->assertSee('draggable="true"', false)
            ->assertSee('moveLeadToStage', false)
            ->assertSee('Delete');

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->call('moveLeadToStage', $lead->id, 'qualified')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertSame('qualified', $lead->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_STAGE_CHANGE,
            'summary' => 'Stage changed',
        ]);
    }

    public function test_admin_can_delete_lead_from_crm(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Delete Me Lead',
            'email' => 'delete@example.com',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'manual_admin',
            'data' => ['source' => 'test'],
        ]);

        Livewire::actingAs($admin)
            ->test(LeadsIndex::class)
            ->call('openLead', $lead->id)
            ->call('deleteLead', $lead->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }
}
