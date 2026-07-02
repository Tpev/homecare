<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SdrOutreachCenter;
use App\Livewire\Sdr\CallingConsole;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\SdrOutreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SdrOutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_provider_call_list_with_tags(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);

        $rows = implode("\n", [
            "Practice\tContact\tRole\tPhone\tEmail\tLocation\tNotes",
            "Triangle Primary Care\tSteve Miller\tOffice manager\t+19195550100\tsteve@example.com\tRaleigh, NC\tAsked for community resource.",
            "Durham Senior Center\tJess Parker\tProgram director\t+19195550101\tjess@example.com\tDurham, NC\tSenior center call.",
        ]);

        Livewire::actingAs($admin)
            ->test(SdrOutreachCenter::class)
            ->set('tags', 'Raleigh, PCP')
            ->set('pasteRows', $rows)
            ->call('importLeads')
            ->assertHasNoErrors()
            ->assertSee('2 created');

        $this->assertDatabaseCount('leads', 2);
        $this->assertDatabaseHas('leads', [
            'lead_type' => Lead::TYPE_REFERRAL,
            'source' => SdrOutreach::SOURCE,
            'company' => 'Triangle Primary Care',
            'name' => 'Steve Miller',
            'contact_role' => 'Office manager',
            'phone' => '+19195550100',
            'status' => 'new',
        ]);

        $lead = Lead::query()->where('company', 'Triangle Primary Care')->sole();

        $this->assertSame(['raleigh', 'pcp'], data_get($lead->data, 'sdr.tags'));
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'summary' => 'SDR lead imported',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.crm.index', ['pipeline' => Lead::TYPE_REFERRAL, 'source' => SdrOutreach::SOURCE]))
            ->assertOk()
            ->assertSee('Triangle Primary Care')
            ->assertSee('SDR call list');
    }

    public function test_sdr_role_only_accesses_calling_workspace(): void
    {
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        $this->actingAs($sdr)
            ->get(route('dashboard'))
            ->assertRedirect(route('sdr.calling', absolute: false));

        $this->actingAs($sdr)
            ->get(route('sdr.calling'))
            ->assertOk()
            ->assertSee('Provider calling queue')
            ->assertSee('Call queue')
            ->assertDontSee('Admin Users');

        $this->actingAs($sdr)->get(route('admin.crm.index'))->assertForbidden();
        $this->actingAs($sdr)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($sdr)->get(route('admin.sdr-outreach.index'))->assertForbidden();
    }

    public function test_sdr_can_claim_lead_call_with_zoom_and_log_outcome(): void
    {
        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'name' => 'Ops Admin',
        ]);
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Steve Miller',
            'company' => 'Triangle Primary Care',
            'contact_role' => 'Office manager',
            'phone' => '+19195550100',
            'location' => 'Raleigh, NC',
            'status' => 'new',
            'priority' => Lead::PRIORITY_HIGH,
            'source' => SdrOutreach::SOURCE,
            'source_detail' => 'SDR call list: pcp',
            'data' => ['sdr' => ['tags' => ['pcp']]],
        ]);

        Livewire::actingAs($sdr)
            ->test(CallingConsole::class)
            ->call('claimNextLead')
            ->assertHasNoErrors()
            ->assertSee('Triangle Primary Care')
            ->assertSee('zoomphonecall://+19195550100', false)
            ->set('outcome', 'resource_requested')
            ->set('note', 'Steve asked us to email the one-page resource.')
            ->call('logOutcome')
            ->assertHasNoErrors()
            ->assertSee('Claim a lead to start calling');

        $lead->refresh();

        $this->assertSame($sdr->id, $lead->assigned_admin_id);
        $this->assertSame('nurturing', $lead->status);
        $this->assertNotNull($lead->last_contacted_at);
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertSame('resource_requested', data_get($lead->data, 'sdr.last_outcome'));

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'actor_user_id' => $sdr->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: Send one-page resource',
        ]);

        $callActivity = LeadActivity::query()
            ->where('lead_id', $lead->id)
            ->where('type', LeadActivity::TYPE_CALL)
            ->sole();

        $this->assertSame('resource_requested', data_get($callActivity->metadata, 'sdr_outcome'));

        $this->actingAs($admin)
            ->get(route('admin.crm.index', ['pipeline' => Lead::TYPE_REFERRAL, 'source' => SdrOutreach::SOURCE]))
            ->assertOk()
            ->assertSee('Triangle Primary Care')
            ->assertSee('SDR Caller');
    }

    public function test_create_sdr_user_command_upserts_restricted_calling_accounts(): void
    {
        $this->artisan('crm:create-sdr-user caller@example.com --name="SDR Caller" --password=TemporaryPass123!')
            ->assertSuccessful();

        $user = User::query()->where('email', 'caller@example.com')->sole();

        $this->assertSame('sdr', $user->role);
        $this->assertSame('SDR Caller', $user->name);
        $this->assertTrue(Hash::check('TemporaryPass123!', $user->password));

        $this->artisan('crm:create-sdr-user caller@example.com --name="Updated Caller" --password=ChangedPass123!')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $user->refresh();

        $this->assertSame('Updated Caller', $user->name);
        $this->assertSame('sdr', $user->role);
        $this->assertTrue(Hash::check('ChangedPass123!', $user->password));
    }
}
