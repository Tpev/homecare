<?php

namespace Tests\Feature;

use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Sdr\FamilyCallingConsole;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\FamilyAcquisition\FamilyFollowUpCallService;
use App\Support\FamilyLeadOutreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FamilyFollowUpCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_crm_button_queues_a_call_without_changing_stage_owner_or_contact_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead($sdr, [
            'call_attempt_count' => 3,
            'last_contacted_at' => now()->subDay(),
            'first_connected_at' => now()->subWeek(),
        ]);

        $component = Livewire::actingAs($admin)->test(LeadsIndex::class)
            ->call('openLead', $lead->id)
            ->assertSee('Queue follow-up call')
            ->set('leadForm.name', 'Unsaved name')
            ->call('queueFollowUpCall')
            ->assertHasNoErrors()
            ->assertSee('Call queued')
            ->assertSee('Stage stays Qualified.')
            ->assertSet('leadForm.status', 'qualified')
            ->assertSet('leadForm.name', 'Unsaved name')
            ->assertSet('leadForm.next_follow_up_at', now()->format('Y-m-d\TH:i'));

        // A repeated request must not create another activity or restart the cadence.
        $component->call('queueFollowUpCall')->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame('Qualified Family', $lead->name);
        $this->assertSame($sdr->id, $lead->assigned_admin_id);
        $this->assertSame(3, $lead->call_attempt_count);
        $this->assertTrue($lead->last_contacted_at->equalTo(now()->subDay()));
        $this->assertTrue($lead->first_connected_at->equalTo(now()->subWeek()));
        $this->assertNull($lead->first_call_at);
        $this->assertSame('Mother, 80', data_get($lead->data, 'form_answers.care_for'));
        $this->assertTrue(FamilyLeadOutreach::isCallable($lead));
        $this->assertSame(1, $lead->activities()->where('summary', 'Follow-up call queued')->count());
        $this->assertSame(0, $lead->activities()->whereIn('type', [LeadActivity::TYPE_CALL, LeadActivity::TYPE_STAGE_CHANGE])->count());

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', $lead->id)
            ->assertSee('Qualified')
            ->assertViewHas('dueOwnedCount', 1);
    }

    public function test_sdr_can_queue_an_unassigned_qualified_lead_and_claim_it(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead($sdr, ['assigned_admin_id' => null]);

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->call('openLead', $lead->id)
            ->assertViewHas('stats', fn (array $stats) => $stats['due'] === 0)
            ->call('queueFollowUpCall')
            ->assertHasNoErrors()
            ->assertSet('selectedFollowUpAt', now()->format('Y-m-d\TH:i'))
            ->assertViewHas('stats', fn (array $stats) => $stats['due'] === 1 && $stats['qualified'] === 1)
            ->assertSee('Call queued');

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', null)
            ->assertViewHas('availableCount', 1)
            ->call('claimNextLead')
            ->assertSet('activeLeadId', $lead->id);

        $this->assertSame('qualified', $lead->fresh()->status);
        $this->assertSame($sdr->id, $lead->fresh()->assigned_admin_id);
    }

    public function test_existing_qualified_leads_are_not_automatically_enrolled_by_a_general_follow_up_date(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead($sdr, ['next_follow_up_at' => now()->subHour()]);

        $this->assertFalse(FamilyLeadOutreach::isCallable($lead));
        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', null)
            ->assertViewHas('dueOwnedCount', 0);
        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['due'] === 0);
    }

    #[DataProvider('retryOutcomes')]
    public function test_retries_and_callbacks_preserve_qualified_and_return_at_the_follow_up_time(string $outcome, ?string $requestedAt, string $expectedAt): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->set('followUpAt', $requestedAt ?? '')
            ->set('note', 'Try again to close the family.')
            ->call('logOutcome', $outcome)
            ->assertHasNoErrors()
            ->assertSet('activeLeadId', null);

        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame(1, $lead->call_attempt_count);
        $this->assertSame($expectedAt, $lead->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertTrue(FamilyLeadOutreach::hasQueuedFollowUpCall($lead));
        $this->assertFalse(FamilyLeadOutreach::isCallable($lead));
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'type' => 'call', 'body' => 'Try again to close the family.',
        ]);
        $this->assertSame(0, $lead->activities()->where('type', 'stage_change')->count());

        // Even a stale console cannot record another attempt before it is due.
        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', null)
            ->assertViewHas('dueOwnedCount', 0)
            ->set('activeLeadId', $lead->id)
            ->call('logOutcome', 'no_answer');
        $this->assertSame(1, $lead->fresh()->call_attempt_count);

        Carbon::setTestNow($expectedAt);
        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', $lead->id)
            ->assertViewHas('dueOwnedCount', 1);
        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['due'] === 1);
    }

    public static function retryOutcomes(): array
    {
        return [
            'no answer uses next business day' => ['no_answer', null, '2026-10-12 12:15:00'],
            'voicemail uses next business day' => ['voicemail_left', null, '2026-10-12 12:15:00'],
            'requested callback' => ['callback_requested', '2026-10-13T15:30', '2026-10-13 15:30:00'],
            'explicit retry date' => ['no_answer', '2026-10-14T11:00', '2026-10-14 11:00:00'],
        ];
    }

    public function test_callback_still_requires_a_time(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);
        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->call('logOutcome', 'callback_requested')
            ->assertHasErrors(['followUpAt']);
        $this->assertSame(0, $lead->fresh()->call_attempt_count);
    }

    public function test_a_connected_qualified_call_with_a_follow_up_stays_in_the_queue(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->set('followUpAt', '2026-10-12T15:00')
            ->call('logOutcome', 'connected_qualified')
            ->assertHasNoErrors();

        $this->assertTrue(FamilyLeadOutreach::hasQueuedFollowUpCall($lead->fresh()));
        Carbon::setTestNow('2026-10-12 15:00:00');
        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', $lead->id)
            ->call('logOutcome', 'connected_qualified')
            ->assertHasNoErrors();

        $this->assertSame('qualified', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->next_follow_up_at);
        $this->assertFalse(FamilyLeadOutreach::hasQueuedFollowUpCall($lead->fresh()));
    }

    public function test_seventh_follow_up_attempt_stops_the_sequence_without_disqualifying_the_lead(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);
        $lead->update(['unanswered_attempt_count' => 6, 'call_attempt_count' => 12]);

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->set('followUpAt', '2026-10-12T15:00')
            ->call('logOutcome', 'no_answer')
            ->assertHasNoErrors()
            ->assertSet('activeLeadId', null);

        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame(13, $lead->call_attempt_count);
        $this->assertSame(7, $lead->unanswered_attempt_count);
        $this->assertNull($lead->next_follow_up_at);
        $this->assertNull($lead->closed_reason);
        $this->assertFalse(FamilyLeadOutreach::isCallable($lead));

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->call('openLead', $lead->id)
            ->assertSee('Queue follow-up call')
            ->call('queueFollowUpCall')->assertHasNoErrors();
        $this->assertSame(0, $lead->fresh()->unanswered_attempt_count);
        $this->assertSame(13, $lead->fresh()->call_attempt_count);
        $this->assertTrue(FamilyLeadOutreach::isCallable($lead->fresh()));
    }

    #[DataProvider('progressOutcomes')]
    public function test_explicit_outcomes_still_update_the_stage_and_end_the_qualified_call_request(string $outcome, string $stage): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->call('logOutcome', $outcome)->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame($stage, $lead->status);
        $this->assertNull(data_get($lead->data, 'family_outreach.follow_up_call_requested_at'));
        $this->assertFalse(FamilyLeadOutreach::isCallable($lead));
        if ($outcome === 'not_ready') {
            $this->assertTrue($lead->next_follow_up_at->equalTo(now()->addWeeks(2)));
        } else {
            $this->assertNull($lead->next_follow_up_at);
        }
        if ($outcome === 'do_not_contact') {
            $this->assertNotNull($lead->do_not_contact_at);
        }
    }

    public static function progressOutcomes(): array
    {
        return [
            ['assessment_booked', 'assessment_scheduled'],
            ['not_ready', 'nurture'],
            ['not_eligible', 'not_fit'],
            ['do_not_contact', 'closed'],
        ];
    }

    #[DataProvider('ineligibleLeads')]
    public function test_ineligible_leads_cannot_be_queued_even_by_calling_the_action_directly(array $attributes): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead($admin, $attributes);

        Livewire::actingAs($admin)->test(LeadsIndex::class)
            ->call('openLead', $lead->id)
            ->assertDontSee('Queue follow-up call')
            ->call('queueFollowUpCall')
            ->assertHasErrors(['followUpCall']);

        $this->assertNull($lead->fresh()->next_follow_up_at);
        $this->assertSame(0, $lead->activities()->count());
        $this->assertFalse(FamilyLeadOutreach::isCallable($lead->fresh()));
    }

    public static function ineligibleLeads(): array
    {
        return [
            'do not contact' => [['do_not_contact_at' => '2026-10-01 10:00:00']],
            'missing phone' => [['phone' => null]],
            'blank phone' => [['phone' => '   ']],
            'converted' => [['status' => 'converted']],
            'closed' => [['status' => 'closed']],
            'not fit' => [['status' => 'not_fit']],
            'lost' => [['status' => 'lost']],
            'unreachable' => [['status' => 'unreachable']],
            'referral' => [['lead_type' => Lead::TYPE_REFERRAL]],
        ];
    }

    public function test_stale_console_cannot_call_a_lead_after_do_not_contact_is_set(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->queuedLead($sdr);
        $component = Livewire::actingAs($sdr)->test(FamilyCallingConsole::class);
        $lead->update(['do_not_contact_at' => now()]);

        $component->call('startCall')->call('logOutcome', 'no_answer');
        $this->assertNull($lead->fresh()->first_call_at);
        $this->assertSame(0, $lead->fresh()->call_attempt_count);
        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['due'] === 0);
    }

    public function test_queue_action_rechecks_staff_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead($admin);
        $component = Livewire::actingAs($admin)->test(LeadsIndex::class)->call('openLead', $lead->id);
        $admin->update(['role' => 'family']);

        $component->call('queueFollowUpCall')->assertForbidden();
        $this->assertNull($lead->fresh()->next_follow_up_at);
    }

    private function queuedLead(User $owner): Lead
    {
        return app(FamilyFollowUpCallService::class)->queue($this->lead($owner), $owner);
    }

    private function lead(User $owner, array $attributes = []): Lead
    {
        return Lead::query()->create(array_merge([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Qualified Family',
            'phone' => '919-555-0100',
            'status' => 'qualified',
            'priority' => 'normal',
            'assigned_admin_id' => $owner->id,
            'submitted_at' => now()->subWeek(),
            'call_attempt_count' => 0,
            'unanswered_attempt_count' => 0,
            'data' => ['form_answers' => ['care_for' => 'Mother, 80']],
        ], $attributes));
    }
}
