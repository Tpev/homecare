<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleSheetsLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_sheets_lead_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');

        $this->postJson(route('webhooks.google-sheets.leads'), [
            'row' => ['Name' => 'Don Johnson', 'Phone' => '555-100-2000'],
        ], [
            'X-LoLo-Signature' => 'sha256=bad',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_google_sheets_lead_webhook_creates_family_crm_lead(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');

        $payload = [
            'spreadsheet_id' => 'sheet_123',
            'sheet_name' => 'Facebook Leads',
            'row_number' => 7,
            'import_key' => 'row-uuid-7',
            'row' => [
                'Full Name' => 'Don Johnson',
                'Phone Number' => '555-100-2000',
                'Email' => 'don@example.com',
                'ZIP' => '27703',
                'City' => 'Durham',
                'State' => 'NC',
                'Source' => 'Facebook Lead Ad',
                'Campaign Name' => 'June companionship',
                'Relationship' => 'Self',
                'Care Needs' => 'Companionship twice per week.',
                'Facebook Lead ID' => 'fb-123',
            ],
        ];

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => true,
                'status' => 'new',
            ]);

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_FAMILY, $lead->lead_type);
        $this->assertSame('Don Johnson', $lead->name);
        $this->assertSame('don@example.com', $lead->email);
        $this->assertSame('555-100-2000', $lead->phone);
        $this->assertSame('Durham, NC, 27703', $lead->location);
        $this->assertSame('facebook_lead_ad', $lead->source);
        $this->assertSame('June companionship', $lead->source_detail);
        $this->assertSame('google_sheets', $lead->external_source);
        $this->assertSame('row-uuid-7', $lead->external_id);
        $this->assertSame('Companionship twice per week.', data_get($lead->data, 'notes'));
        $this->assertSame('fb-123', data_get($lead->data, 'facebook.lead_id'));

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Imported from Google Sheet',
        ]);
    }

    public function test_google_sheets_lead_webhook_updates_existing_imported_row_without_duplicate(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');

        $payload = [
            'spreadsheet_id' => 'sheet_123',
            'sheet_name' => 'Leads',
            'row_number' => 12,
            'import_key' => 'stable-row-id',
            'row' => [
                'Name' => 'Caroline Helper',
                'Phone' => '555-100-3000',
                'Email' => 'caroline@example.com',
                'Notes' => 'Original note',
            ],
        ];

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJson(['created' => true]);

        $payload['row']['Notes'] = 'Updated note';
        $payload['row']['Phone'] = '555-100-3999';

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJson(['created' => false]);

        $this->assertDatabaseCount('leads', 1);

        $lead = Lead::query()->sole();

        $this->assertSame('555-100-3999', $lead->phone);
        $this->assertSame('Updated note', data_get($lead->data, 'notes'));
        $this->assertSame(2, LeadActivity::query()->where('lead_id', $lead->id)->count());
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'summary' => 'Google Sheet lead updated',
        ]);
    }

    public function test_new_meta_row_resets_attempts_but_replaying_a_known_row_keeps_progress(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');
        $payload = [
            'import_key' => 'first-row',
            'row' => [
                'Name' => 'Returning contact', 'Phone' => '9195551234',
                'Source' => 'Meta', 'Facebook Lead ID' => 'first-meta-lead',
            ],
        ];
        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))->assertOk();
        $lead = Lead::query()->sole();
        $lead->update(['status' => 'unreachable', 'call_attempt_count' => 7, 'unanswered_attempt_count' => 7]);
        $firstPayload = $payload;
        $payload['import_key'] = 'second-row';
        $payload['row']['Facebook Lead ID'] = 'second-meta-lead';

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()->assertJson(['created' => false, 'lead_id' => $lead->id, 'status' => 'new']);
        $lead->refresh();
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame(0, $lead->call_attempt_count);
        $this->assertSame(0, $lead->unanswered_attempt_count);

        $lead->update(['status' => 'attempting_contact', 'call_attempt_count' => 1, 'unanswered_attempt_count' => 1]);
        foreach ([$payload, $firstPayload] as $replay) {
            $this->postJson(route('webhooks.google-sheets.leads'), $replay, $this->signatureHeaders($replay))
                ->assertOk()->assertJson(['created' => false, 'status' => 'attempting_contact']);
        }
        $lead->refresh();
        $this->assertSame(1, $lead->call_attempt_count);
        $this->assertSame(1, $lead->unanswered_attempt_count);
        $this->assertSame(1, $lead->activities()->where('metadata->source', 'meta_resubmission')->count());
    }

    public function test_new_non_meta_row_keeps_the_existing_status_and_attempts(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');
        $lead = Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Existing contact', 'phone' => '9195551234',
            'status' => 'contacted', 'call_attempt_count' => 3, 'unanswered_attempt_count' => 2,
        ]);
        $payload = ['import_key' => 'new-row', 'row' => ['Name' => 'Contact', 'Phone' => '+19195551234']];

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()->assertJson(['created' => false, 'lead_id' => $lead->id, 'status' => 'contacted']);
        $lead->refresh();
        $this->assertSame(3, $lead->call_attempt_count);
        $this->assertSame(2, $lead->unanswered_attempt_count);
    }

    public function test_google_sheets_lead_webhook_maps_facebook_export_columns(): void
    {
        config()->set('services.google_sheets_leads.webhook_secret', 'sheet-secret');

        $payload = [
            'spreadsheet_id' => 'sheet_123',
            'sheet_name' => 'Facebook Leads',
            'row_number' => 14,
            'import_key' => 'row-14',
            'row' => [
                'id' => 'l:1016701897485241',
                'created_time' => '2026-06-08T17:39:32-05:00',
                'ad_id' => 'ag:120246312371920597',
                'ad_name' => 'New Leads Ad',
                'adset_id' => 'as:120246312371910597',
                'adset_name' => 'New Leads Ad Set',
                'campaign_id' => 'c:120246312371930597',
                'campaign_name' => 'New Leads Campaign',
                'form_id' => 'f:1293671349640626',
                'form_name' => 'First form-copy-copy',
                'platform' => 'fb',
                '1._what_kind_of_help_is_needed?' => 'companionship_or_check-ins',
                '2._when_would_you_like_help_to_start?' => 'planning_ahead',
                '3._when_is_the_best_time_to_reach_you?' => 'tomorrow',
                '4._where_is_care_needed?' => 'Momâ€™s apartment',
                'email' => 'crmnflwr@aim.com',
                'full_name' => "Luna's MeeMee",
                'phone_number' => 'p:+19198510230',
            ],
        ];

        $this->postJson(route('webhooks.google-sheets.leads'), $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => true,
            ]);

        $lead = Lead::query()->sole();

        $this->assertSame("Luna's MeeMee", $lead->name);
        $this->assertSame('+19198510230', $lead->phone);
        $this->assertSame('Momâ€™s apartment', $lead->location);
        $this->assertSame('facebook_lead_ad', $lead->source);
        $this->assertSame('New Leads Campaign', $lead->source_detail);
        $this->assertSame('l:1016701897485241', data_get($lead->data, 'facebook.lead_id'));
        $this->assertSame('companionship_or_check-ins', data_get($lead->data, 'notes'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'X-LoLo-Signature' => 'sha256='.hash_hmac('sha256', $body, 'sheet-secret'),
        ];
    }
}
