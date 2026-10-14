<?php

namespace Tests\Feature;

use App\Livewire\Admin\FamilyAcquisitionOverview;
use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Sdr\FamilyCallingConsole;
use App\Models\Lead;
use App\Models\User;
use App\Support\FamilyLeadOutreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyLeadOnboardingVisitStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    public function test_staff_can_save_and_filter_the_new_stage_before_conversion(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead(['status' => 'assessment_scheduled']);
        $other = $this->lead(['name' => 'Unvisited Family', 'status' => 'qualified']);

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->call('openLead', $lead->id)
            ->set('selectedStatus', 'onboarding_visit_completed')
            ->call('saveLead')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('onboarding_visit_completed', $lead->status);
        $this->assertNull($lead->converted_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'stage_change',
            'body' => 'Moved from Assessment scheduled to Onboarding visit completed.',
        ]);

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->set('status', 'onboarding_visit_completed')
            ->assertSee($lead->name)
            ->assertDontSee($other->name)
            ->assertSeeInOrder(['Onboarding visit completed', 'Converted']);
    }

    public function test_family_board_accepts_the_stage_without_applying_it_to_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $referral = $this->lead(['lead_type' => Lead::TYPE_REFERRAL]);

        Livewire::actingAs($admin)->test(LeadsIndex::class)
            ->call('moveLeadToStage', $lead->id, 'onboarding_visit_completed')
            ->assertHasNoErrors()
            ->call('moveLeadToStage', $referral->id, 'onboarding_visit_completed')
            ->assertHasNoErrors();

        $this->assertSame('onboarding_visit_completed', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->converted_at);
        $this->assertSame('new', $referral->fresh()->status);
    }

    public function test_overview_counts_completed_visits_as_progress_without_counting_them_as_customers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->lead(['status' => 'onboarding_visit_completed']);

        Livewire::actingAs($admin)->test(FamilyAcquisitionOverview::class)
            ->assertViewHas('funnel', function (array $funnel): bool {
                $counts = array_column($funnel, 'value', 'label');

                return $counts['Qualified'] === 1
                    && $counts['Assessment booked'] === 1
                    && $counts['Care started'] === 0;
            })
            ->assertViewHas('livePipeline', fn (array $pipeline): bool => $pipeline['onboarding_visit_completed'] === 1
                && $pipeline['assessment'] === 0 && $pipeline['care_started'] === 0)
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['conversion_rate'] === 0)
            ->assertSee('Onboarding visit completed');
    }

    public function test_staff_can_queue_a_follow_up_without_retrying_an_earlier_stage(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead(['status' => 'onboarding_visit_completed', 'assigned_admin_id' => $sdr->id]);
        $this->assertFalse(FamilyLeadOutreach::isCallable($lead));

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->call('openLead', $lead->id)
            ->call('queueFollowUpCall')
            ->assertHasNoErrors();

        Livewire::actingAs($sdr)->test(FamilyCallingConsole::class)
            ->assertSet('activeLeadId', $lead->id)
            ->call('logOutcome', 'no_answer')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('onboarding_visit_completed', $lead->status);
        $this->assertTrue(FamilyLeadOutreach::hasQueuedFollowUpCall($lead));
        $this->assertNull($lead->converted_at);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::withoutEvents(fn () => Lead::query()->create(array_merge([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Visited Family',
            'phone' => '919-555-0100',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'manual_crm',
        ], $attributes)));
    }
}
