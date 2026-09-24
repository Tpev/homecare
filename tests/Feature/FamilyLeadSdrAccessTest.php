<?php

namespace Tests\Feature;

use App\Livewire\Admin\FamilyLeadsIndex;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyLeadSdrAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_leads_remain_restricted_to_staff(): void
    {
        $this->get(route('admin.family-acquisition.leads'))->assertRedirect(route('login'));
        Livewire::test(FamilyLeadsIndex::class)->assertForbidden();

        foreach (['family', 'caregiver'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.family-acquisition.leads'))->assertForbidden();
            Livewire::actingAs($user)->test(FamilyLeadsIndex::class)->assertForbidden();
        }

        foreach (['admin', 'sales', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.family-acquisition.leads'))->assertOk();
        }
    }

    public function test_sdr_navigation_links_to_family_leads_on_desktop_and_mobile(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $leadsUrl = route('admin.family-acquisition.leads');

        foreach (['sdr.family-calling', 'sdr.calling', 'admin.family-acquisition.leads'] as $route) {
            $response = $this->actingAs($sdr)->get(route($route))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//nav//a[@href="'.$leadsUrl.'" and normalize-space(.)="Family leads CRM"]');

            // Main desktop/mobile navigation and both account menus expose the page.
            $this->assertSame(4, $links->length);

            if ($route === 'admin.family-acquisition.leads') {
                $activeLinks = $xpath->query('//nav//a[@href="'.$leadsUrl.'" and contains(@class, "bg-[#23483F]")]');
                $this->assertSame(2, $activeLinks->length);
            }

            $response->assertSee('Family calling console')
                ->assertSee('Referral outreach')
                ->assertDontSee('Management overview');
        }
    }

    public function test_sdr_can_create_update_and_add_notes_to_family_leads(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);

        $component = Livewire::actingAs($sdr)
            ->test(FamilyLeadsIndex::class)
            ->call('toggleCreateForm')
            ->assertSee('Add a family enquiry')
            ->set('leadForm.name', 'SDR Family')
            ->set('leadForm.phone', '919-555-0199')
            ->set('leadForm.email', 'sdr-family@example.test')
            ->set('leadForm.care_for', 'Mother, 82')
            ->call('createLead')
            ->assertHasNoErrors();

        $lead = Lead::query()->where('email', 'sdr-family@example.test')->sole();
        $this->assertSame(Lead::TYPE_FAMILY, $lead->lead_type);
        $this->assertSame($sdr->id, data_get($lead->data, 'original_submission.entered_by'));

        $component->call('closeLead')
            ->call('openLead', $lead->id)
            ->assertSee('Mother, 82')
            ->set('selectedStatus', 'callback_scheduled')
            ->set('selectedPriority', 'high')
            ->set('selectedAssignee', (string) $sdr->id)
            ->set('selectedFollowUpAt', '2026-10-01T14:00')
            ->call('saveLead')
            ->assertHasNoErrors()
            ->assertSee('Assigned to '.$sdr->name.'.')
            ->set('note', 'Call the daughter on Thursday afternoon.')
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertSee('Call the daughter on Thursday afternoon.');

        $lead->refresh();
        $this->assertSame('callback_scheduled', $lead->status);
        $this->assertSame('high', $lead->priority);
        $this->assertSame($sdr->id, $lead->assigned_admin_id);
        $this->assertSame('2026-10-01 14:00', $lead->next_follow_up_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'actor_user_id' => $sdr->id,
            'type' => 'note',
            'body' => 'Call the daughter on Thursday afternoon.',
        ]);

        $this->actingAs($sdr)
            ->get(route('admin.family-acquisition.leads', ['lead' => $lead->id]))
            ->assertOk()
            ->assertSee('Family lead detail')
            ->assertSee('Mother, 82');
    }

    public function test_sdr_cannot_delete_leads_even_by_calling_the_action_directly(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Protected Family',
            'status' => 'new',
        ]);

        Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->assertSee('Protected Family')
            ->assertDontSee('Delete')
            ->call('deleteLead', $lead->id)
            ->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_sales_retains_lead_deletion_access(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Sales Family',
            'status' => 'new',
        ]);

        Livewire::actingAs($sales)->test(FamilyLeadsIndex::class)
            ->assertSee('Delete')
            ->call('deleteLead', $lead->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_access_is_checked_again_before_livewire_updates(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $component = Livewire::actingAs($sdr)->test(FamilyLeadsIndex::class)
            ->set('leadForm.name', 'Unauthorized Family')
            ->set('leadForm.phone', '919-555-0199');

        $sdr->update(['role' => 'family']);

        $component->call('createLead')->assertForbidden();

        $this->assertDatabaseMissing('leads', ['name' => 'Unauthorized Family']);
    }
}
