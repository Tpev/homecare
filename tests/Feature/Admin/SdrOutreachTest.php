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

    public function test_sdr_role_only_accesses_calling_workspaces(): void
    {
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        $this->actingAs($sdr)
            ->get(route('dashboard'))
            ->assertRedirect(route('sdr.family-calling', absolute: false));

        $this->actingAs($sdr)
            ->get(route('sdr.calling'))
            ->assertOk()
            ->assertSee('Provider calling queue')
            ->assertSee('Family leads')
            ->assertSee('Referral outreach')
            ->assertSee('Validate that the office sees seniors/families who need support')
            ->assertSee('Is that something your team sees with patients or families?')
            ->assertSee('We also do not have hourly minimums')
            ->assertDontSee('Open simply')
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
        $this->assertSame('need_to_email_material', $lead->status);
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

    public function test_sdr_can_log_an_agreed_material_drop_off(): void
    {
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Dr. Rivera',
            'company' => 'Oak Medical Practice',
            'phone' => '+19195550102',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);

        Livewire::actingAs($sdr)
            ->test(CallingConsole::class)
            ->call('claimNextLead')
            ->assertSee('Agreed to a material drop-off')
            ->assertSee('Click the outcome to save')
            ->assertSee("logOutcome('material_drop_agreed')", false)
            ->set('note', 'The doctor approved a brief drop-off at the front desk.')
            ->call('logOutcome', 'material_drop_agreed')
            ->assertHasNoErrors()
            ->assertSet('activeLeadId', null)
            ->assertViewHas('callStats', fn (array $stats): bool => $stats['material_drop_agreed'] === 1)
            ->assertSee('Material drop-offs');

        $lead->refresh();

        $this->assertSame('need_to_drop_material', $lead->status);
        $this->assertSame('material_drop_agreed', data_get($lead->data, 'sdr.last_outcome'));
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: Agreed to a material drop-off',
        ]);
    }

    public function test_admin_can_filter_recent_call_outcomes_and_load_more(): void
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

        foreach (range(1, 13) as $index) {
            $lead = Lead::query()->create([
                'lead_type' => Lead::TYPE_REFERRAL,
                'name' => 'No Answer Practice '.$index,
                'company' => 'No Answer Practice '.$index,
                'phone' => '+1919555'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'status' => 'outreach',
                'priority' => Lead::PRIORITY_NORMAL,
                'source' => SdrOutreach::SOURCE,
            ]);

            $lead->activities()->create([
                'actor_user_id' => $sdr->id,
                'type' => LeadActivity::TYPE_CALL,
                'summary' => 'SDR call: No answer',
                'occurred_at' => now()->subMinutes($index),
                'metadata' => [
                    'sdr_outcome' => 'no_answer',
                    'sdr_outcome_label' => 'No answer',
                ],
            ]);
        }

        $emailLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Email Material Practice',
            'company' => 'Email Material Practice',
            'phone' => '+19195559999',
            'status' => 'need_to_email_material',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);
        $emailLead->activities()->create([
            'actor_user_id' => $sdr->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: Send one-page resource',
            'occurred_at' => now(),
            'metadata' => [
                'sdr_outcome' => 'resource_requested',
                'sdr_outcome_label' => 'Send one-page resource',
            ],
        ]);

        $dropOffLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Material Drop Practice',
            'company' => 'Material Drop Practice',
            'phone' => '+19195558888',
            'status' => 'need_to_drop_material',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);
        $dropOffLead->activities()->create([
            'actor_user_id' => $sdr->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: Agreed to a material drop-off',
            'occurred_at' => now(),
            'metadata' => [
                'sdr_outcome' => 'material_drop_agreed',
                'sdr_outcome_label' => 'Agreed to a material drop-off',
            ],
        ]);

        $oldLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Old Wrong Number Practice',
            'company' => 'Old Wrong Number Practice',
            'phone' => '+19195557777',
            'status' => 'not_fit',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);
        $oldLead->activities()->create([
            'actor_user_id' => $sdr->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: Wrong number',
            'occurred_at' => now()->subDays(45),
            'metadata' => [
                'sdr_outcome' => 'wrong_number',
                'sdr_outcome_label' => 'Wrong number',
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(SdrOutreachCenter::class)
            ->assertSee('All outcomes')
            ->assertSee('All time')
            ->assertSee('Volume by call outcome')
            ->assertSee('Material drop-offs')
            ->assertViewHas('outcomeStats', fn ($rows): bool => $rows->contains(
                fn (array $row): bool => $row['outcome'] === 'no_answer' && $row['count'] === 13
            ))
            ->assertViewHas('outcomeStats', fn ($rows): bool => $rows->contains(
                fn (array $row): bool => $row['outcome'] === 'wrong_number' && $row['count'] === 0
            ))
            ->assertViewHas('poolStats', fn (array $stats): bool => $stats['material_drop_agreed'] === 1)
            ->assertViewHas('dailyStats', fn ($rows): bool => $rows->contains(
                fn (array $row): bool => $row['sdr'] === 'SDR Caller' && $row['material_drop_agreed'] === 1
            ))
            ->set('metricsWindow', 'all')
            ->assertViewHas('poolStats', fn (array $stats): bool => $stats['calls'] === 16)
            ->assertViewHas('outcomeStats', fn ($rows): bool => $rows->contains(
                fn (array $row): bool => $row['outcome'] === 'wrong_number' && $row['count'] === 1
            ))
            ->set('outcomeFilter', 'no_answer')
            ->assertSet('recentCallsLimit', 12)
            ->assertSee('No Answer Practice 12')
            ->assertDontSee('No Answer Practice 13')
            ->assertSee('Load more')
            ->call('loadMoreRecentCalls')
            ->assertSet('recentCallsLimit', 24)
            ->assertSee('No Answer Practice 13')
            ->set('outcomeFilter', 'resource_requested')
            ->assertSet('recentCallsLimit', 12)
            ->assertSee('Email Material Practice')
            ->assertDontSee('No Answer Practice 1')
            ->assertDontSee('Load more')
            ->set('outcomeFilter', 'material_drop_agreed')
            ->assertSee('Material Drop Practice')
            ->assertDontSee('Email Material Practice');
    }

    public function test_refresh_resumes_uncalled_claim_instead_of_future_follow_up(): void
    {
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        $firstLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'First Practice',
            'company' => 'First Practice',
            'phone' => '+19195550100',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);
        $secondLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Second Practice',
            'company' => 'Second Practice',
            'phone' => '+19195550101',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
        ]);

        Livewire::actingAs($sdr)
            ->test(CallingConsole::class)
            ->call('claimNextLead')
            ->assertSet('activeLeadId', $firstLead->id)
            ->set('outcome', 'resource_requested')
            ->call('logOutcome')
            ->assertSet('activeLeadId', null)
            ->call('claimNextLead')
            ->assertSet('activeLeadId', $secondLead->id);

        $firstLead->refresh();
        $this->assertTrue($firstLead->next_follow_up_at->isFuture());

        Livewire::actingAs($sdr)
            ->test(CallingConsole::class)
            ->assertSet('activeLeadId', $secondLead->id);
    }

    public function test_refresh_resumes_a_due_follow_up_but_not_a_future_one(): void
    {
        $sdr = User::factory()->create([
            'email' => 'caller@example.com',
            'role' => 'sdr',
            'name' => 'SDR Caller',
        ]);

        Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Future Follow Up',
            'phone' => '+19195550100',
            'status' => 'nurturing',
            'assigned_admin_id' => $sdr->id,
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
            'last_contacted_at' => now()->subDay(),
            'next_follow_up_at' => now()->addDay(),
        ]);
        $dueLead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Due Follow Up',
            'phone' => '+19195550101',
            'status' => 'nurturing',
            'assigned_admin_id' => $sdr->id,
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => SdrOutreach::SOURCE,
            'last_contacted_at' => now()->subDays(2),
            'next_follow_up_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($sdr)
            ->test(CallingConsole::class)
            ->assertSet('activeLeadId', $dueLead->id);
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
