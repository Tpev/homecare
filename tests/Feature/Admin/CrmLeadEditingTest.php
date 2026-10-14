<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\LeadsIndex;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmLeadEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_imported_lead_details_persists_and_preserves_source_and_submission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['facebook_lead_ad', 'meta_lead_ads', 'facebook_lead_ads', 'google_sheet', 'manual_crm', 'local_partner_campaign'] as $index => $source) {
            $lead = Lead::create([
                'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Original contact', 'email' => 'old'.$index.'@example.test',
                'phone' => '919-555-0100', 'status' => 'new', 'source' => $source,
                'external_source' => 'google_sheets', 'external_id' => 'import-'.$index,
                'data' => ['original_submission' => ['name' => 'Original contact'], 'meta' => ['campaign_id' => 'campaign-123']],
            ]);
            $originalData = $lead->data;

            Livewire::actingAs($admin)->test(LeadsIndex::class)
                ->call('openLead', $lead->id)
                ->set('leadForm.name', 'Corrected contact '.$index)
                ->set('leadForm.email', 'corrected'.$index.'@example.test')
                ->set('leadForm.phone', '919-555-0123')
                ->set('leadForm.location', 'Raleigh, NC')
                ->set('leadForm.contact_role', 'Daughter')
                ->call('saveLead')->assertHasNoErrors()
                ->assertSee('Corrected contact '.$index)
                ->call('closeLead')->call('openLead', $lead->id)
                ->assertSet('leadForm.name', 'Corrected contact '.$index)
                ->assertSet('leadForm.source', $source);

            $lead->refresh();
            $this->assertSame('Corrected contact '.$index, $lead->name);
            $this->assertSame('corrected'.$index.'@example.test', $lead->email);
            $this->assertSame('919-555-0123', $lead->phone);
            $this->assertSame('Raleigh, NC', $lead->location);
            $this->assertSame('Daughter', $lead->contact_role);
            $this->assertSame($source, $lead->source);
            $this->assertSame('import-'.$index, $lead->external_id);
            $this->assertSame($originalData, $lead->data);
            $this->assertNull($lead->converted_at);

            Livewire::actingAs($admin)->test(LeadsIndex::class)->call('openLead', $lead->id)
                ->assertSet('leadForm.name', 'Corrected contact '.$index)
                ->assertSet('leadForm.email', 'corrected'.$index.'@example.test');
        }
    }

    public function test_existing_custom_source_is_displayed_but_a_different_unlisted_source_is_rejected(): void
    {
        $lead = Lead::create(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Imported contact',
            'status' => 'new', 'source' => 'local_partner_campaign']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(LeadsIndex::class)
            ->call('openLead', $lead->id)
            ->assertSee('value="local_partner_campaign"', false)
            ->set('leadForm.name', 'Unsaved edit')->set('leadForm.source', 'unlisted_source')
            ->call('saveLead')->assertHasErrors(['leadForm.source'])
            ->assertSee('Lead changes were not saved.')
            ->assertSet('leadForm.name', 'Unsaved edit');

        $this->assertSame('Imported contact', $lead->fresh()->name);
        $this->assertSame('local_partner_campaign', $lead->fresh()->source);
    }

    public function test_invalid_contact_details_are_visible_and_can_be_corrected_without_losing_edits(): void
    {
        $lead = Lead::create(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Original contact',
            'status' => 'new', 'source' => 'facebook_lead_ad']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(LeadsIndex::class)
            ->call('openLead', $lead->id)->set('leadForm.name', 'Corrected contact')
            ->set('leadForm.email', 'not-an-email')->call('saveLead')
            ->assertHasErrors(['leadForm.email'])->assertSee('Lead changes were not saved.')
            ->assertSet('leadForm.name', 'Corrected contact')
            ->set('leadForm.email', 'valid@example.test')->call('saveLead')->assertHasNoErrors()
            ->assertDontSee('Lead changes were not saved.');

        $this->assertSame('Corrected contact', $lead->fresh()->name);
        $this->assertSame('valid@example.test', $lead->fresh()->email);
    }

    public function test_a_new_lead_cannot_inherit_an_existing_leads_custom_source_permission(): void
    {
        $lead = Lead::create(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Imported contact',
            'status' => 'new', 'source' => 'local_partner_campaign']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(LeadsIndex::class)
            ->call('openLead', $lead->id)->call('toggleCreateForm')
            ->set('leadForm.name', 'New contact')->set('leadForm.source', 'local_partner_campaign')
            ->call('createLead')->assertHasErrors(['leadForm.source']);

        $this->assertDatabaseCount('leads', 1);
    }
}
