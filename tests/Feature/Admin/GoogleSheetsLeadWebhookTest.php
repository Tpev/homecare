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
