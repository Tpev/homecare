<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ZapierFacebookLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ADDITIONAL_SERVICES_QUESTION = 'Which additional services would you like more information about? Select all that apply.';

    private const ADDITIONAL_SERVICES = [
        'Pharmacy pickup or delivery',
        'Home safety improvements (ramps, grab bars, stair lifts)',
        'Meal delivery',
    ];

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
        $this->assertSame(2, LeadActivity::query()->where('lead_id', $lead->id)
            ->where('metadata->source', 'zapier_facebook_lead_webhook')->count());
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'summary' => 'Facebook Lead Ads lead updated via Zapier',
        ]);
    }

    #[DataProvider('additionalServicePayloads')]
    public function test_webhook_preserves_every_selected_additional_service(array $fields, array $expected): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');
        $payload = $this->facebookPayload() + $fields;

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, [
            'X-LoLo-Zapier-Token' => 'zapier-secret',
        ])->assertOk();

        $lead = Lead::query()->sole();
        $this->assertSame($expected, data_get($lead->data, 'care_preferences.additional_services'));
        $this->assertSame($expected, $lead->additionalServices());
        foreach ($fields as $key => $value) {
            $this->assertSame($value, $lead->data['raw_payload'][$key]);
        }

        $activity = $lead->activities()->where('metadata->source', 'zapier_facebook_lead_webhook')->sole();
        foreach ($expected as $service) {
            $this->assertStringContainsString($service, $activity->body);
            $this->assertStringContainsString($service, $lead->data['notes']);
        }
    }

    public static function additionalServicePayloads(): array
    {
        return [
            'multiple selections' => [[self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES], self::ADDITIONAL_SERVICES],
            'canonical selections override raw answers' => [[
                'additional_services' => ['Meal delivery'],
                'raw' => [self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES],
            ], ['Meal delivery']],
            'explicit empty selections override raw answers' => [[
                'additional_services' => [],
                'raw' => [self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES],
            ], []],
            'single selection' => [[self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES[1]], [self::ADDITIONAL_SERVICES[1]]],
            'JSON array' => [['additional_services' => json_encode(self::ADDITIONAL_SERVICES)], self::ADDITIONAL_SERVICES],
            'semicolon separated' => [[self::ADDITIONAL_SERVICES_QUESTION => implode('; ', self::ADDITIONAL_SERVICES)], self::ADDITIONAL_SERVICES],
            'newline separated' => [[self::ADDITIONAL_SERVICES_QUESTION => implode("\n", self::ADDITIONAL_SERVICES)], self::ADDITIONAL_SERVICES],
            'nested raw answers' => [['raw' => [self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES]], self::ADDITIONAL_SERVICES],
            'repeated named answers' => [['field_data' => array_map(
                fn (string $service): array => ['name' => self::ADDITIONAL_SERVICES_QUESTION, 'values' => [$service]],
                self::ADDITIONAL_SERVICES,
            )], self::ADDITIONAL_SERVICES],
            'numbered flattened answers' => [[
                'Raw '.self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES[0],
                'Raw '.self::ADDITIONAL_SERVICES_QUESTION.' 2' => self::ADDITIONAL_SERVICES[1],
                'Raw '.self::ADDITIONAL_SERVICES_QUESTION.' 3' => self::ADDITIONAL_SERVICES[2],
            ], self::ADDITIONAL_SERVICES],
            'repeated selection' => [[self::ADDITIONAL_SERVICES_QUESTION => [...self::ADDITIONAL_SERVICES, self::ADDITIONAL_SERVICES[0]]], self::ADDITIONAL_SERVICES],
        ];
    }

    public function test_replay_replaces_service_selections_without_stale_array_entries(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');
        $headers = ['X-LoLo-Zapier-Token' => 'zapier-secret'];
        $payload = $this->facebookPayload() + [self::ADDITIONAL_SERVICES_QUESTION => self::ADDITIONAL_SERVICES];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();

        $payload[self::ADDITIONAL_SERVICES_QUESTION] = ['Meal delivery'];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)
            ->assertOk()->assertJson(['created' => false]);

        $lead = Lead::query()->sole();
        $this->assertSame(['Meal delivery'], data_get($lead->data, 'care_preferences.additional_services'));
        $this->assertSame(['Meal delivery'], $lead->additionalServices());

        // Partial replays must not remove an answer that was not included in the request.
        unset($payload[self::ADDITIONAL_SERVICES_QUESTION]);
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        $this->assertSame(['Meal delivery'], $lead->fresh()->additionalServices());

        $payload[self::ADDITIONAL_SERVICES_QUESTION] = [];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        $lead->refresh();
        $this->assertSame([], data_get($lead->data, 'care_preferences.additional_services'));
        $this->assertSame([], $lead->additionalServices());
    }

    public function test_a_second_submission_with_the_same_phone_keeps_services_from_both_submissions(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');
        $headers = ['X-LoLo-Zapier-Token' => 'zapier-secret'];
        $payload = $this->facebookPayload() + ['additional_services' => [self::ADDITIONAL_SERVICES[0]]];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        $lead = Lead::query()->sole();
        $lead->update(['status' => 'qualified']);

        $payload['Lead Id'] = 'second-service-submission';
        $payload['additional_services'] = array_slice(self::ADDITIONAL_SERVICES, 1);
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)
            ->assertOk()->assertJson(['created' => false]);

        $this->assertDatabaseCount('leads', 1);
        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertEqualsCanonicalizing(self::ADDITIONAL_SERVICES, $lead->additionalServices());
        $this->assertEqualsCanonicalizing(self::ADDITIONAL_SERVICES, data_get($lead->data, 'care_preferences.additional_services'));
    }

    public function test_webhook_recognizes_the_updated_home_care_location_question(): void
    {
        config()->set('services.zapier_facebook_leads.webhook_secret', 'zapier-secret');
        $payload = $this->facebookPayload();
        unset($payload['4 Where Is Care Needed City Or Zip']);
        $payload['4. Where is home care needed? (City or Zip)'] = '27587';

        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, [
            'X-LoLo-Zapier-Token' => 'zapier-secret',
        ])->assertOk();

        $lead = Lead::query()->sole();
        $this->assertSame('27587', $lead->location);
        $this->assertSame('27587', $lead->zip);
        $this->assertSame('27587', data_get($lead->data, 'care_preferences.care_location'));
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
