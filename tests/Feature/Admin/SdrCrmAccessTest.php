<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\LeadsIndex;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\FamilyLeadOutreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SdrCrmAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $sdr;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->sdr = User::factory()->create(['role' => 'sdr', 'name' => 'CRM SDR']);
    }

    public function test_sdr_can_open_all_crm_pipelines_and_the_leads_alias(): void
    {
        foreach ([Lead::TYPE_FAMILY, Lead::TYPE_REFERRAL, 'caregivers'] as $pipeline) {
            $this->actingAs($this->sdr)->get(route('admin.crm.index', ['pipeline' => $pipeline]))
                ->assertOk()->assertSee('Family / care receiver leads')
                ->assertSee('Referral source recruiting')->assertSee('Caregivers');
        }
        $this->actingAs($this->sdr)->get(route('admin.leads.index'))->assertOk();
    }

    public function test_sdr_navigation_adds_main_crm_without_losing_existing_tools(): void
    {
        foreach (['sdr.family-calling', 'sdr.calling', 'admin.family-acquisition.leads', 'admin.crm.index'] as $route) {
            $response = $this->actingAs($this->sdr)->get(route($route))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            foreach ([
                'admin.crm.index' => 'CRM',
                'admin.family-acquisition.leads' => 'Family leads CRM',
                'sdr.family-calling' => 'Family calling console',
                'sdr.calling' => 'Referral outreach',
            ] as $destination => $label) {
                $links = $xpath->query('//nav//a[@href="'.route($destination).'" and normalize-space(.)="'.$label.'"]');
                $this->assertSame(4, $links->length, $route.' exposes '.$label.' in desktop/mobile navigation and account menus.');
            }
            $response->assertSee('SDR workspace')->assertSee('SDR account')
                ->assertDontSee('Sales account')->assertDontSee('Admin Users')
                ->assertDontSee('Management overview')->assertDontSee('data-admin-all-tools', false);
        }
    }

    #[DataProvider('leadPipelines')]
    public function test_sdr_can_manage_and_export_each_lead_pipeline(string $pipeline, string $stage): void
    {
        $otherSdr = User::factory()->create(['role' => 'sdr']);
        $component = Livewire::actingAs($this->sdr)->test(LeadsIndex::class, ['pipeline' => $pipeline])
            ->call('toggleCreateForm')
            ->set('leadForm.name', 'SDR managed '.$pipeline)
            ->set('leadForm.email', $pipeline.'@example.test')
            ->set('leadForm.phone', '919-555-0123')
            ->set('leadForm.source', 'phone')
            ->set('leadForm.assigned_admin_id', (string) $otherSdr->id)
            ->call('createLead')->assertHasNoErrors();
        $lead = Lead::query()->where('email', $pipeline.'@example.test')->sole();
        $this->assertSame($pipeline, $lead->lead_type);
        $this->assertSame($otherSdr->id, $lead->assigned_admin_id);

        $component->call('closeLead')->call('openLead', $lead->id)
            ->set('leadForm.name', 'Updated '.$pipeline)
            ->set('leadForm.priority', 'high')
            ->set('leadForm.next_follow_up_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('saveLead')->assertHasNoErrors()
            ->call('moveLeadToStage', $lead->id, $stage)
            ->call('assignToMe', $lead->id)
            ->set('activityForm.type', LeadActivity::TYPE_CALL)
            ->set('activityForm.body', 'Spoke with this contact.')
            ->call('logActivity')->assertHasNoErrors()
            ->set('assigned', 'me')
            ->set('q', 'Updated')
            ->call('exportStage', $stage)->assertFileDownloaded();

        $lead->refresh();
        $this->assertSame('Updated '.$pipeline, $lead->name);
        $this->assertSame($stage, $lead->status);
        $this->assertSame('high', $lead->priority);
        $this->assertSame($this->sdr->id, $lead->assigned_admin_id);
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'actor_user_id' => $this->sdr->id,
            'type' => LeadActivity::TYPE_CALL, 'body' => 'Spoke with this contact.',
        ]);
        $component->call('deleteLead', $lead->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public static function leadPipelines(): array
    {
        return [[Lead::TYPE_FAMILY, 'contacted'], [Lead::TYPE_REFERRAL, 'outreach']];
    }

    public function test_sdr_can_queue_a_family_follow_up_from_the_main_crm(): void
    {
        $lead = Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Qualified family',
            'status' => 'qualified', 'phone' => '919-555-0123', 'source' => 'phone',
            'assigned_admin_id' => $this->sdr->id, 'data' => ['form_answers' => ['consent' => true]],
        ]);
        Livewire::actingAs($this->sdr)->test(LeadsIndex::class)
            ->call('openLead', $lead->id)->assertSee('Queue follow-up call')
            ->call('queueFollowUpCall')->assertHasNoErrors()->assertSee('Call queued');
        $this->assertTrue(FamilyLeadOutreach::hasQueuedFollowUpCall($lead->fresh()));
        $this->assertSame('qualified', $lead->fresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'actor_user_id' => $this->sdr->id,
            'summary' => 'Follow-up call queued',
        ]);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_crm_access_does_not_grant_global_administrator_permissions(): void
    {
        $this->assertFalse($this->sdr->isAdministrator());
        foreach (['admin.users.index', 'admin.requests.index', 'admin.payments.ops', 'admin.customer-onboarding.index'] as $route) {
            $this->actingAs($this->sdr)->get(route($route))->assertForbidden();
        }
    }

    #[DataProvider('nonStaffRoles')]
    public function test_nonstaff_cannot_access_either_crm_route_or_the_component(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        foreach (['admin.crm.index', 'admin.leads.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
        Livewire::actingAs($user)->test(LeadsIndex::class)->assertForbidden();
    }

    public static function nonStaffRoles(): array
    {
        return [['family'], ['caregiver']];
    }

    #[DataProvider('protectedActions')]
    public function test_a_persisted_role_downgrade_blocks_livewire_actions(string $action): void
    {
        $lead = Lead::create(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Protected lead', 'status' => 'new']);
        $component = Livewire::actingAs($this->sdr)->test(LeadsIndex::class)
            ->call('openLead', $lead->id)->set('leadForm.name', 'Must not be saved');
        // Update a separate instance so the authenticated model remains stale.
        User::query()->whereKey($this->sdr->id)->update(['role' => 'family']);
        $arguments = match ($action) {
            'deleteLead' => [$lead->id],
            'exportStage' => ['new'],
            default => [],
        };
        $component->call($action, ...$arguments)->assertForbidden();
        $this->assertSame('Protected lead', $lead->fresh()->name);
        $this->assertDatabaseCount('lead_activities', 0);
    }

    public static function protectedActions(): array
    {
        return [['saveLead'], ['deleteLead'], ['exportStage'], ['queueFollowUpCall']];
    }
}
