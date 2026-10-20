<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CaregiverCrm;
use App\Livewire\Admin\LeadsIndex;
use App\Models\CaregiverCrmActivity;
use App\Models\CaregiverProfile;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaregiverCrmPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Caregiver CRM Admin']);
    }

    public function test_admin_opens_caregiver_tab_inside_main_crm_and_can_return_to_existing_pipelines(): void
    {
        $this->caregiver('Approved Caregiver');
        $this->actingAs($this->admin)
            ->get(route('admin.crm.index', ['pipeline' => 'caregivers']))
            ->assertOk()->assertSeeLivewire(CaregiverCrm::class)
            ->assertSee('Approved Caregiver');

        Livewire::actingAs($this->admin)->test(LeadsIndex::class)
            ->call('setPipeline', 'caregivers')->assertSet('pipeline', 'caregivers')
            ->assertSeeLivewire(CaregiverCrm::class)
            ->call('setPipeline', Lead::TYPE_REFERRAL)->assertSet('pipeline', Lead::TYPE_REFERRAL)
            ->assertSee('Add referral source')
            ->call('setPipeline', Lead::TYPE_FAMILY)->assertSet('pipeline', Lead::TYPE_FAMILY)
            ->assertSee('Add family lead');
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
    }

    public function test_sales_keeps_existing_crm_access_without_caregiver_tab(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $this->actingAs($sales)->get(route('admin.crm.index'))->assertOk()
            ->assertDontSeeLivewire(CaregiverCrm::class);
        $this->actingAs($sales)->get(route('admin.crm.index', ['pipeline' => 'caregivers']))->assertForbidden();
        Livewire::actingAs($sales)->test(LeadsIndex::class)
            ->call('setPipeline', Lead::TYPE_REFERRAL)->assertSet('pipeline', Lead::TYPE_REFERRAL);
        Livewire::actingAs($sales)->test(CaregiverCrm::class)->assertForbidden();
    }

    public function test_sales_cannot_forge_the_parent_pipeline_property_to_mount_caregivers(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        Livewire::actingAs($sales)->test(LeadsIndex::class)
            ->set('pipeline', 'caregivers')->assertForbidden();
    }

    public static function restrictedRoles(): array
    {
        return [['sales'], ['sdr'], ['family'], ['caregiver']];
    }

    #[DataProvider('restrictedRoles')]
    public function test_caregiver_component_rejects_non_admin_mount_and_a_stale_admin_session(string $role): void
    {
        $caregiver = $this->caregiver('Protected Caregiver');
        $other = User::factory()->create(['role' => $role]);
        Livewire::actingAs($other)->test(CaregiverCrm::class)->assertForbidden();

        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->set('recordForm.notes', 'Unauthorized change');
        $this->actingAs($other);
        $component->call('saveRecord')->assertForbidden();
        $this->assertDatabaseCount('caregiver_crm_records', 0);
    }

    public function test_guest_cannot_mount_either_crm_component(): void
    {
        $this->get(route('admin.crm.index'))->assertRedirect(route('login'));
        Livewire::test(CaregiverCrm::class)->assertForbidden();
        Livewire::test(LeadsIndex::class)->assertForbidden();
    }

    public function test_parent_rechecks_staff_role_before_a_livewire_update(): void
    {
        $component = Livewire::actingAs($this->admin)->test(LeadsIndex::class);
        $this->admin->update(['role' => 'family']);
        $component->call('setPipeline', 'caregivers')->assertForbidden();
    }

    public function test_locked_selection_cannot_be_replaced_by_a_client_property_update(): void
    {
        $caregiver = $this->caregiver('Locked Selection');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('selectedCaregiverId', $caregiver->id);
    }

    public function test_locked_record_version_cannot_be_forged(): void
    {
        $caregiver = $this->caregiver('Locked Version');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('recordVersion', 900);
    }

    public function test_approved_caregivers_are_searchable_and_ineligible_profiles_are_hidden(): void
    {
        $approved = $this->caregiver('Avery Approved');
        $this->caregiver('Morgan Legacy', ['screening_initial_approved_at' => null, 'screening_legacy_access' => true]);
        $this->caregiver('Pending Person', ['screening_initial_approved_at' => null]);
        $this->caregiver('Suspended Person', ['screening_suspended_at' => now()]);

        Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->assertSee('Avery Approved')->assertSee('Morgan Legacy')
            ->assertDontSee('Pending Person')->assertDontSee('Suspended Person')
            ->set('search', 'Avery')->assertSee('Avery Approved')->assertDontSee('Morgan Legacy')
            ->call('openCaregiver', $approved->id)->assertSet('selectedCaregiverId', $approved->id)
            ->call('closeCaregiver')->assertSet('selectedCaregiverId', null)
            ->call('clearFilters')->assertSet('search', '')->assertSee('Morgan Legacy');
    }

    public function test_selected_caregiver_deep_link_loads_the_correct_record_form(): void
    {
        $caregiver = $this->caregiver('Deep Linked Caregiver');
        Livewire::actingAs($this->admin)->withQueryParams(['caregiver' => $caregiver->id])
            ->test(CaregiverCrm::class)->assertSet('selectedCaregiverId', $caregiver->id)
            ->assertSee('Deep Linked Caregiver')->assertSet('recordVersion', 0)
            ->assertSet('recordForm.priority', 'normal');
    }

    public function test_column_pagination_is_bounded_and_filters_reset_its_position(): void
    {
        foreach (range(1, 9) as $index) {
            $this->caregiver(sprintf('Page Caregiver %02d', $index));
        }
        Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->assertViewHas('board', fn ($board): bool => $board['counts']['new'] === 9 && $board['columns']['new']->count() === 8)
            ->call('loadMore', 'new')
            ->assertViewHas('board', fn ($board): bool => $board['offsets']['new'] === 8 && $board['columns']['new']->count() === 1)
            ->assertSee('Page Caregiver 09')->assertDontSee('Page Caregiver 01')
            ->call('previousPage', 'new')->assertSee('Page Caregiver 01')
            ->call('loadMore', 'new')
            ->set('search', 'Page Caregiver 01')
            ->assertSet('columnOffsets', [])->assertSee('Page Caregiver 01')
            ->assertViewHas('board', fn ($board): bool => $board['counts']['new'] === 1);
    }

    public function test_timeline_paging_keeps_history_accessible_and_new_activity_returns_to_latest_page(): void
    {
        $caregiver = $this->caregiver('History Caregiver');
        foreach (range(1, 21) as $index) {
            CaregiverCrmActivity::create([
                'caregiver_user_id' => $caregiver->id, 'actor_user_id' => $this->admin->id,
                'type' => 'note', 'body' => sprintf('History entry %02d.', $index),
                'occurred_at' => now()->subMinutes(30 - $index),
            ]);
        }
        Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 20)
            ->assertSee('History entry 21.')->assertDontSee('History entry 01.')
            ->call('loadMoreActivity')->assertSet('activityOffset', 20)
            ->assertSee('History entry 01.')->assertDontSee('History entry 21.')
            ->call('previousActivity')->assertSet('activityOffset', 0)
            ->assertSee('History entry 21.')
            ->call('loadMoreActivity')
            ->set('activityForm.body', 'Newest history entry.')
            ->call('logActivity')->assertHasNoErrors()
            ->assertSet('activityOffset', 0)->assertSee('Newest history entry.');
    }

    public function test_column_page_is_clamped_after_caregivers_leave_then_previous_returns_to_first_page(): void
    {
        $profiles = collect(range(1, 17))->map(fn ($index) => $this->caregiver(sprintf('Shrinking Page %02d', $index)));
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('loadMore', 'new')->call('loadMore', 'new')
            ->assertSet('columnOffsets.new', 16)->assertSee('Shrinking Page 17');
        CaregiverProfile::whereIn('user_id', $profiles->slice(9)->pluck('id'))
            ->update(['screening_suspended_at' => now()]);
        $component->call('$refresh')->assertSet('columnOffsets.new', 8)
            ->assertSee('Shrinking Page 09')->assertDontSee('Shrinking Page 17')
            ->call('previousPage', 'new')->assertSet('columnOffsets.new', 0)
            ->assertSee('Shrinking Page 01')->assertDontSee('Shrinking Page 09');
    }

    public function test_logging_an_activity_does_not_discard_unsaved_details_when_completing_an_action(): void
    {
        $caregiver = $this->caregiver('Draft Preservation');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->set('recordForm.notes', 'Saved notes')
            ->set('recordForm.next_action', 'Discuss availability')
            ->call('saveRecord')->assertHasNoErrors()
            ->set('recordForm.notes', 'Unsaved notes from this conversation')
            ->set('activityForm.type', 'call')
            ->set('activityForm.call_outcome', 'connected')
            ->set('activityForm.body', 'Discussed available mornings.')
            ->call('logActivity')->assertHasNoErrors()
            ->call('completeNextAction')->assertHasErrors(['record_draft'])
            ->assertSet('recordForm.notes', 'Unsaved notes from this conversation')
            ->assertSet('recordForm.next_action', 'Discuss availability');
        $this->assertDatabaseHas('caregiver_crm_records', [
            'caregiver_user_id' => $caregiver->id, 'notes' => 'Saved notes', 'next_action' => 'Discuss availability',
        ]);
        $component->call('saveRecord')->assertHasNoErrors()
            ->call('completeNextAction')->assertHasNoErrors();
        $this->assertDatabaseHas('caregiver_crm_records', [
            'caregiver_user_id' => $caregiver->id, 'notes' => 'Unsaved notes from this conversation', 'next_action' => null,
        ]);
    }

    public function test_notes_connected_call_and_follow_up_completion_persist_without_creating_a_lead(): void
    {
        $caregiver = $this->caregiver('Jamie Caregiver');
        $followUp = now()->addDays(2)->setTime(10, 30)->format('Y-m-d\TH:i');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->set('recordForm.assigned_admin_id', (string) $this->admin->id)
            ->set('recordForm.priority', 'high')
            ->set('recordForm.notes', 'Prefers weekday mornings.')
            ->set('recordForm.next_action', 'Discuss the first care request')
            ->set('recordForm.next_follow_up_at', $followUp)
            ->call('saveRecord')->assertHasNoErrors()
            ->assertSet('recordVersion', 1);

        $this->assertDatabaseHas('caregiver_crm_records', [
            'caregiver_user_id' => $caregiver->id, 'assigned_admin_id' => $this->admin->id,
            'notes' => 'Prefers weekday mornings.', 'next_action' => 'Discuss the first care request',
            'priority' => 'high', 'version' => 1,
        ]);
        $component->set('activityForm.type', 'call')
            ->set('activityForm.call_outcome', 'connected')
            ->set('activityForm.body', 'Spoke with Jamie about applying to nearby care requests.')
            ->call('logActivity')->assertHasNoErrors()->assertSee('Spoke on phone')
            ->assertSet('recordVersion', 1);
        $this->assertDatabaseHas('caregiver_crm_activities', [
            'caregiver_user_id' => $caregiver->id, 'actor_user_id' => $this->admin->id,
            'type' => 'call', 'call_outcome' => 'connected',
            'body' => 'Spoke with Jamie about applying to nearby care requests.',
        ]);

        $component->call('completeNextAction')->assertHasNoErrors()
            ->assertSet('recordForm.next_action', '')
            ->assertSet('recordForm.next_follow_up_at', '');
        $this->assertDatabaseHas('caregiver_crm_records', [
            'caregiver_user_id' => $caregiver->id, 'next_action' => null, 'next_follow_up_at' => null,
        ]);
        $this->assertDatabaseCount('leads', 0);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_call_outcome_filters_distinguish_no_answer_from_a_conversation(): void
    {
        $caregiver = $this->caregiver('Unreached Caregiver');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->set('activityForm.type', 'call')
            ->set('activityForm.call_outcome', 'no_answer')
            ->set('activityForm.body', 'No answer; try tomorrow.')
            ->call('logActivity')->assertHasNoErrors()
            ->call('closeCaregiver')
            ->set('contact', 'called')->assertDontSee('Unreached Caregiver')
            ->set('contact', 'not_called')->assertSee('Unreached Caregiver');
        $component->call('openCaregiver', $caregiver->id)
            ->set('activityForm.type', 'call')
            ->set('activityForm.call_outcome', 'connected')
            ->set('activityForm.body', 'Connected on the second call.')
            ->call('logActivity')->assertHasNoErrors()
            ->call('closeCaregiver')
            ->set('contact', 'called')->assertSee('Unreached Caregiver');
    }

    public function test_service_validation_errors_remain_visible_without_losing_form_edits(): void
    {
        $caregiver = $this->caregiver('Validation Caregiver');
        Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)
            ->set('recordForm.notes', 'Keep these notes while correcting the priority.')
            ->set('recordForm.priority', 'invalid')
            ->call('saveRecord')->assertHasErrors(['priority'])
            ->assertSet('recordForm.notes', 'Keep these notes while correcting the priority.')
            ->set('recordForm.priority', 'normal')
            ->call('saveRecord')->assertHasNoErrors()
            ->set('activityForm.type', 'call')
            ->set('activityForm.call_outcome', '')
            ->set('activityForm.body', 'Missing call outcome')
            ->call('logActivity')->assertHasErrors(['call_outcome']);
    }

    public function test_concurrent_record_edit_reports_conflict_until_staff_reload(): void
    {
        $caregiver = $this->caregiver('Concurrent Caregiver');
        $first = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)->call('openCaregiver', $caregiver->id);
        $second = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)->call('openCaregiver', $caregiver->id);
        $first->set('recordForm.notes', 'Newer saved note')->call('saveRecord')->assertHasNoErrors();
        $second->set('recordForm.notes', 'Stale note')->call('saveRecord')->assertHasErrors(['record']);
        $this->assertDatabaseHas('caregiver_crm_records', ['caregiver_user_id' => $caregiver->id, 'notes' => 'Newer saved note']);
        $second->call('reloadCaregiver')->assertSet('recordForm.notes', 'Newer saved note')
            ->set('recordForm.next_action', 'Follow up after reload')
            ->call('saveRecord')->assertHasNoErrors();
    }

    public function test_suspended_caregiver_cannot_be_changed_from_an_already_open_panel(): void
    {
        $caregiver = $this->caregiver('Approval Withdrawn');
        $component = Livewire::actingAs($this->admin)->test(CaregiverCrm::class)
            ->call('openCaregiver', $caregiver->id)->set('recordForm.notes', 'Must not be saved');
        $caregiver->caregiverProfile->update(['screening_suspended_at' => now()]);
        $component->call('saveRecord')->assertHasErrors(['caregiver']);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
    }

    public static function legacyLeadActions(): array
    {
        return [['createLead'], ['saveLead'], ['logActivity'], ['queueFollowUpCall']];
    }

    #[DataProvider('legacyLeadActions')]
    public function test_legacy_lead_mutations_are_blocked_while_caregiver_tab_is_active(string $action): void
    {
        Livewire::actingAs($this->admin)->test(LeadsIndex::class, ['pipeline' => 'caregivers'])
            ->call($action)->assertForbidden();
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_caregiver_tab_cannot_move_or_delete_an_existing_family_lead(): void
    {
        $lead = Lead::create(['name' => 'Family stays untouched', 'lead_type' => Lead::TYPE_FAMILY, 'status' => 'new']);
        Livewire::actingAs($this->admin)->test(LeadsIndex::class, ['pipeline' => 'caregivers'])
            ->call('moveLeadToStage', $lead->id, 'qualified')->assertForbidden();
        Livewire::actingAs($this->admin)->test(LeadsIndex::class, ['pipeline' => 'caregivers'])
            ->call('deleteLead', $lead->id)->assertForbidden();
        $this->assertSame('new', $lead->fresh()->status);
    }

    private function caregiver(string $name, array $profile = []): User
    {
        $caregiver = User::factory()->create(['name' => $name, 'role' => 'caregiver']);
        CaregiverProfile::create([
            'user_id' => $caregiver->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'service_area_zip' => '27601',
            ...$profile,
        ]);

        return $caregiver;
    }
}
