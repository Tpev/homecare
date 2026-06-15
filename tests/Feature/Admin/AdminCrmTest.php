<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\LeadsIndex;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_crm_and_grouped_admin_navigation(): void
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
        $response->assertSee('Care ops');
        $response->assertSee('People');
        $response->assertSee('Comms &amp; money', false);
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
