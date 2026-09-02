<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZapierFacebookLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_an_invalid_token(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), [
            'Lead Id' => '1027616976944714',
            'Full Name' => 'Test Lead',
        ], [
            'X-LoLo-Zapier-Token' => 'wrong-secret',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_webhook_maps_facebook_fields_and_preserves_the_payload(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');

        $payload = $this->facebookPayload();

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, [
            'X-LoLo-Zapier-Token' => 'zapier-secret',
        ])->assertOk()->assertJson([
            'ok' => true,
            'created' => true,
            'status' => 'new',
            'external_id' => '1027616976944714',
        ]);

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_FAMILY, $lead->lead_type);
        $this->assertSame('Test Facebook Lead', $lead->name);
        $this->assertSame('test@meta.com', $lead->email);
        $this->assertSame('+19195551212', $lead->phone);
        $this->assertSame('Raleigh, NC 27609', $lead->location);
        $this->assertSame('27609', $lead->zip);
        $this->assertSame('facebook_lead_ad', $lead->source);
        $this->assertSame('First form-copy-copy-copy', $lead->source_detail);
        $this->assertSame('facebook_lead_ads', $lead->external_source);
        $this->assertSame('1027616976944714', $lead->external_id);
        $this->assertSame('2026-09-02 12:47:02', $lead->submitted_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Companionship and meal preparation', data_get($lead->data, 'care_preferences.help_needed'));
        $this->assertSame('1023738677499006', data_get($lead->data, 'facebook.page_id'));
        $this->assertSame('LoLo Care', data_get($lead->data, 'raw_payload.Page Name'));
        $this->assertStringContainsString('Best time to reach: Afternoons', (string) data_get($lead->data, 'notes'));

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Imported from Facebook Lead Ads via Zapier',
        ]);
    }

    public function test_webhook_updates_a_replayed_facebook_lead_without_a_duplicate(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');

        $payload = $this->facebookPayload();
        $headers = ['Authorization' => 'Bearer zapier-secret'];

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)
            ->assertOk()
            ->assertJson(['created' => true]);

        $payload['Phone Number'] = 'tel:+19195559999';
        $payload['2 When Would You Like Help To Start'] = 'As soon as possible';

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)
            ->assertOk()
            ->assertJson(['created' => false]);

        $this->assertDatabaseCount('leads', 1);

        $lead = Lead::query()->sole();

        $this->assertSame('+19195559999', $lead->phone);
        $this->assertSame('As soon as possible', data_get($lead->data, 'care_preferences.start_timing'));
        $this->assertSame(2, LeadActivity::query()->where('lead_id', $lead->id)->count());
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'summary' => 'Facebook Lead Ads lead updated via Zapier',
        ]);
    }

    /** @return array<string, string> */
    private function facebookPayload(): array
    {
        return [
            'Created Time' => '2026-09-02T12:47:02+0000',
            'Form Id' => '2570095570083729',
            'Lead Id' => '1027616976944714',
            'Page Id' => '1023738677499006',
            'Page Name' => 'LoLo Care',
            'Form Name' => 'First form-copy-copy-copy',
            '1 What Kind Of Help Is Needed' => 'Companionship and meal preparation',
            '2 When Would You Like Help To Start' => 'Within two weeks',
            '3 When Is The Best Time To Reach You' => 'Afternoons',
            '4 Where Is Care Needed City Or Zip' => 'Raleigh, NC 27609',
            'Email' => 'test@meta.com',
            'Full Name' => 'Test Facebook Lead',
            'Phone Number' => 'p:+19195551212',
            'Ad Id' => '',
            'Ad Name' => '',
            'Adset Id' => '',
            'Adset Name' => '',
            'Campaign Id' => '',
            'Campaign Name' => '',
            'Platform' => 'facebook',
        ];
    }
}
