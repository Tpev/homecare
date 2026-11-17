<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Sdr\FamilyCallingConsole;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadAdditionalServicesTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'Which additional services would you like more information about? Select all that apply.';

    private const SERVICES = [
        'Pharmacy pickup or delivery',
        'Home safety improvements (ramps, grab bars, stair lifts)',
        'Meal delivery',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_all_imported_service_selections_are_visible_in_each_crm_screen(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), [
            'Lead Id' => 'services-display-test',
            'Full Name' => 'Service interests test',
            'Phone Number' => '9195550180',
            self::QUESTION => self::SERVICES,
        ], ['X-LoLo-Zapier-Token' => 'zapier-secret'])->assertOk();

        $this->assertServicesVisibleInEachScreen(Lead::query()->sole(), self::SERVICES);
    }

    #[DataProvider('legacyLeadData')]
    public function test_existing_leads_show_answers_from_saved_payloads_without_a_backfill(array $data): void
    {
        $lead = Lead::create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Existing imported lead',
            'phone' => '9195550181',
            'status' => 'new',
            'source' => 'facebook_lead_ad',
            'data' => $data,
        ]);
        $originalData = $lead->data;

        $this->assertServicesVisibleInEachScreen($lead, self::SERVICES);
        $this->assertSame($originalData, $lead->fresh()->data);
    }

    public static function legacyLeadData(): array
    {
        return [
            'raw payload' => [['raw_payload' => ['raw' => [self::QUESTION => self::SERVICES]]]],
            'normalized payload' => [['normalized_payload' => [
                'which_additional_services_would_you_like_more_information_about_select_all_that_apply' => self::SERVICES,
            ]]],
        ];
    }

    public function test_service_labels_are_escaped_in_each_crm_screen(): void
    {
        $services = ['Meal delivery <img src=x onerror="alert(1)">'];
        $lead = Lead::create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Escaped service label test',
            'phone' => '9195550182',
            'status' => 'new',
            'data' => ['care_preferences' => ['additional_services' => $services]],
        ]);

        $this->assertServicesVisibleInEachScreen($lead, $services);
    }

    public function test_explicit_current_selections_override_older_raw_answers(): void
    {
        $lead = new Lead(['data' => [
            'care_preferences' => ['additional_services' => ['Meal delivery']],
            'raw_payload' => [self::QUESTION => self::SERVICES],
        ]]);

        $this->assertSame(['Meal delivery'], $lead->additionalServices());
        $this->blade('<x-lead-additional-services :lead="$lead" />', ['lead' => $lead])
            ->assertSee('Meal delivery')
            ->assertDontSee('Pharmacy pickup or delivery')
            ->assertDontSee('Home safety improvements');
    }

    public function test_service_section_is_hidden_when_no_services_were_selected(): void
    {
        foreach ([[], ['care_preferences' => ['additional_services' => []], 'raw_payload' => [self::QUESTION => self::SERVICES]]] as $data) {
            $lead = new Lead(['data' => $data]);
            $this->assertSame([], $lead->additionalServices());
            $this->blade('<x-lead-additional-services :lead="$lead" />', ['lead' => $lead])
                ->assertDontSee('Additional services of interest');
        }
    }

    private function assertServicesVisibleInEachScreen(Lead $lead, array $services): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sdr = User::factory()->create(['role' => 'sdr']);
        $lead->update(['assigned_admin_id' => $sdr->id]);

        foreach ([LeadsIndex::class, FamilyLeadsIndex::class, FamilyCallingConsole::class] as $screen) {
            $component = Livewire::actingAs($screen === LeadsIndex::class ? $admin : $sdr)->test($screen);
            if ($screen === FamilyCallingConsole::class) {
                $component->assertSet('activeLeadId', $lead->id);
            } else {
                $component->call('openLead', $lead->id);
            }
            $component->assertSee('Additional services of interest');

            // Inspect the actual service list, not timeline notes or Livewire's JSON snapshot.
            $document = new \DOMDocument;
            @$document->loadHTML($component->html());
            $xpath = new \DOMXPath($document);
            $sections = $xpath->query('//section[h3[normalize-space(.)="Additional services of interest"]]');
            $this->assertSame(1, $sections->length, $screen);
            $labels = [];
            foreach ($xpath->query('./ul/li', $sections->item(0)) as $item) {
                $labels[] = trim($item->textContent);
            }
            $this->assertSame($services, $labels, $screen);
            $this->assertSame(0, $xpath->query('.//img | .//script', $sections->item(0))->length, $screen);
        }
    }
}
