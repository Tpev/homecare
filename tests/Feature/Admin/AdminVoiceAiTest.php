<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\VoiceAiTest;
use App\Livewire\Admin\ProviderOutreachAi;
use App\Models\Lead;
use App\Models\User;
use App\Models\VoiceAiCall;
use App\Services\VoiceAgent\TwilioVoiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdminVoiceAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_voice_ai_test_call_in_bypass_mode(): void
    {
        config()->set('services.twilio.bypass', true);
        config()->set('services.twilio.voice_from', '+19195550000');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.voice-ai.index'))
            ->assertOk()
            ->assertSee('Voice AI Test Calls')
            ->assertSee('Voice AI Test');

        Livewire::actingAs($admin)
            ->test(VoiceAiTest::class)
            ->set('phoneNumber', '(919) 555-1234')
            ->call('startCall')
            ->assertHasNoErrors()
            ->assertSee('+19195551234');

        $call = VoiceAiCall::query()->sole();

        $this->assertSame($admin->id, $call->admin_user_id);
        $this->assertSame(VoiceAiCall::STATUS_QUEUED, $call->status);
        $this->assertSame('+19195551234', $call->to_phone);
        $this->assertSame('+19195550000', $call->from_phone);
        $this->assertStringStartsWith('CA_bypass_', (string) $call->twilio_call_sid);
    }

    public function test_admin_can_start_provider_outreach_call_in_bypass_mode(): void
    {
        config()->set('services.twilio.bypass', true);
        config()->set('services.twilio.voice_from', '+19195550000');
        config()->set('services.twilio.voice_agent_callback_url', 'https://voice.carelolo.com/twilio/voice?prompt_profile=callback_discovery');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.provider-outreach-ai.index'))
            ->assertOk()
            ->assertSee('Julie AI provider outreach')
            ->assertSee('Provider AI Outreach');

        Livewire::actingAs($admin)
            ->test(ProviderOutreachAi::class)
            ->set('targetForm.practice_name', 'Triangle Primary Care')
            ->set('targetForm.contact_name', 'Office Manager')
            ->set('targetForm.contact_role', 'office_manager')
            ->set('targetForm.phone', '(919) 555-4444')
            ->set('targetForm.email', 'office@example.com')
            ->set('targetForm.location', 'Raleigh, NC')
            ->call('startCall')
            ->assertHasNoErrors()
            ->assertSee('Triangle Primary Care');

        $lead = Lead::query()->sole();
        $call = VoiceAiCall::query()->sole();

        $this->assertSame(Lead::TYPE_REFERRAL, $lead->lead_type);
        $this->assertSame('Triangle Primary Care', $lead->company);
        $this->assertSame('+19195554444', $lead->phone);
        $this->assertSame('ai_provider_outreach', $lead->source);
        $this->assertSame($lead->id, $call->metadata['referral_lead_id']);
        $this->assertSame(VoiceAiCall::PROFILE_PROVIDER_OUTREACH, $call->metadata['voice_agent_profile']);
        $this->assertStringContainsString('prompt_profile=provider_outreach', (string) data_get($call->raw_payload, 'voice_agent_callback_url'));
    }

    public function test_admin_can_upload_provider_outreach_csv_and_start_next_call(): void
    {
        config()->set('services.twilio.bypass', true);
        config()->set('services.twilio.voice_from', '+19195550000');
        config()->set('services.twilio.voice_agent_callback_url', 'https://voice.carelolo.com/twilio/voice?prompt_profile=callback_discovery');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $csv = implode("\n", [
            'practice,contact,role,phone,email,location',
            'Triangle Primary Care,Megan,Office manager,919-555-4444,office@example.com,Raleigh NC',
            'Wake Senior Center,Steve,Director,919-555-5555,steve@example.com,Wake County',
        ]);

        Livewire::actingAs($admin)
            ->test(ProviderOutreachAi::class)
            ->set('batchLabel', 'Wake County resources')
            ->set('csvFile', UploadedFile::fake()->createWithContent('providers.csv', $csv))
            ->call('uploadCsvBatch')
            ->assertHasNoErrors()
            ->assertSee('CSV batch ready')
            ->call('startNextBatchCall')
            ->assertHasNoErrors()
            ->assertSee('Started next Julie call');

        $this->assertSame(2, VoiceAiCall::query()->count());

        $queued = VoiceAiCall::query()->where('status', VoiceAiCall::STATUS_QUEUED)->sole();
        $waiting = VoiceAiCall::query()->where('status', VoiceAiCall::STATUS_DRAFT)->sole();

        $this->assertSame('Wake County resources', $queued->metadata['provider_outreach_batch_label']);
        $this->assertSame($queued->metadata['provider_outreach_batch_id'], $waiting->metadata['provider_outreach_batch_id']);
        $this->assertSame('calling', $queued->metadata['provider_outreach_batch_status']);
        $this->assertStringContainsString('prompt_profile=provider_outreach', (string) data_get($queued->raw_payload, 'voice_agent_callback_url'));
    }

    public function test_twilio_voice_client_sends_call_with_voice_webhooks(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.account_sid', 'AC123');
        config()->set('services.twilio.auth_token', 'twilio-secret');
        config()->set('services.twilio.voice_from', '+19844004008');
        config()->set('services.twilio.voice_agent_callback_url', 'https://voice.carelolo.com/twilio/voice?prompt_profile=callback_discovery');
        config()->set('services.twilio.webhook_base_url', 'https://carelolo.com');

        Http::fake([
            'api.twilio.com/*' => Http::response([
                'sid' => 'CA_real_test',
                'status' => 'queued',
                'account_sid' => 'AC123',
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $call = app(TwilioVoiceClient::class)->startTestCall('919-555-1234', $admin);

        Http::assertSent(function ($request) use ($call): bool {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Calls.json'
                && $request['To'] === '+19195551234'
                && $request['From'] === '+19844004008'
                && $request['Url'] === 'https://voice.carelolo.com/twilio/voice?prompt_profile=callback_discovery&voice_ai_call_id='.$call->id
                && $request['Method'] === 'POST'
                && $request['StatusCallback'] === 'https://carelolo.com/webhooks/twilio/voice/'.$call->id.'/status'
                && $request['StatusCallbackMethod'] === 'POST';
        });

        $this->assertSame('CA_real_test', $call->twilio_call_sid);
        $this->assertSame(VoiceAiCall::STATUS_QUEUED, $call->status);
    }

    public function test_twilio_voice_client_sends_provider_outreach_context_to_voice_agent(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.account_sid', 'AC123');
        config()->set('services.twilio.auth_token', 'twilio-secret');
        config()->set('services.twilio.voice_from', '+19844004008');
        config()->set('services.twilio.voice_agent_callback_url', 'https://voice.carelolo.com/twilio/voice?prompt_profile=callback_discovery');
        config()->set('services.twilio.webhook_base_url', 'https://carelolo.com');

        Http::fake([
            'api.twilio.com/*' => Http::response([
                'sid' => 'CA_provider_test',
                'status' => 'queued',
                'account_sid' => 'AC123',
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $call = app(TwilioVoiceClient::class)->startProviderOutreachCall([
            'practice_name' => 'Triangle Primary Care',
            'contact_name' => 'Office Manager',
            'contact_role' => 'office_manager',
            'phone' => '919-555-4444',
            'email' => 'office@example.com',
            'fax' => '919-555-4445',
            'location' => 'Raleigh, NC',
        ], $admin);

        $lead = Lead::query()->sole();

        Http::assertSent(function ($request) use ($call, $lead): bool {
            $url = (string) $request['Url'];

            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Calls.json'
                && $request['To'] === '+19195554444'
                && $request['From'] === '+19844004008'
                && str_contains($url, 'prompt_profile=provider_outreach')
                && str_contains($url, 'voice_ai_call_id='.$call->id)
                && str_contains($url, 'referral_lead_id='.$lead->id)
                && str_contains($url, 'target_organization=Triangle+Primary+Care')
                && $request['StatusCallback'] === 'https://carelolo.com/webhooks/twilio/voice/'.$call->id.'/status';
        });

        $this->assertSame('CA_provider_test', $call->twilio_call_sid);
        $this->assertSame(VoiceAiCall::STATUS_QUEUED, $call->status);
    }

    public function test_twilio_voice_client_requires_deepgram_voice_agent_callback_url(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.account_sid', 'AC123');
        config()->set('services.twilio.auth_token', 'twilio-secret');
        config()->set('services.twilio.voice_from', '+19844004008');
        config()->set('services.twilio.voice_agent_callback_url', '');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->expectExceptionMessage('Configure TWILIO_VOICE_AGENT_CALLBACK_URL');

        app(TwilioVoiceClient::class)->startTestCall('919-555-1234', $admin);
    }

    public function test_twilio_voice_webhooks_collect_transcript_and_status(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.auth_token', 'twilio-secret');

        $call = VoiceAiCall::query()->create([
            'status' => VoiceAiCall::STATUS_QUEUED,
            'to_phone' => '+19195551234',
            'from_phone' => '+19195550000',
        ]);

        $answerPayload = [
            'AccountSid' => 'AC123',
            'CallSid' => 'CA123',
            'CallStatus' => 'in-progress',
            'From' => '+19195550000',
            'To' => '+19195551234',
        ];

        $this->post(route('webhooks.twilio.voice.answer', $call), $answerPayload, [
            'X-Twilio-Signature' => 'bad-signature',
        ])->assertUnauthorized();

        $this->post(route('webhooks.twilio.voice.answer', $call), $answerPayload, [
            'X-Twilio-Signature' => $this->twilioSignature(route('webhooks.twilio.voice.answer', $call), $answerPayload, 'twilio-secret'),
        ])
            ->assertOk()
            ->assertSee('<Gather', false)
            ->assertSee('LoLo test voice assistant');

        $call->refresh();
        $this->assertSame('CA123', $call->twilio_call_sid);
        $this->assertSame(VoiceAiCall::STATUS_IN_PROGRESS, $call->status);
        $this->assertStringContainsString('Assistant: Hi, this is the LoLo test voice assistant.', (string) $call->transcript_text);

        $gatherPayload = [
            'AccountSid' => 'AC123',
            'CallSid' => 'CA123',
            'CallStatus' => 'in-progress',
            'From' => '+19195550000',
            'SpeechResult' => 'My name is Don. My mom needs companionship in Durham 27703 tomorrow. How much does it cost?',
            'To' => '+19195551234',
        ];

        $this->post(route('webhooks.twilio.voice.gather', $call), $gatherPayload, [
            'X-Twilio-Signature' => $this->twilioSignature(route('webhooks.twilio.voice.gather', $call), $gatherPayload, 'twilio-secret'),
        ])
            ->assertOk()
            ->assertSee('30 dollars per hour');

        $call->refresh();
        $this->assertSame('Don', $call->gathered_name);
        $this->assertSame('Mother', $call->gathered_relationship);
        $this->assertSame('27703', $call->gathered_location);
        $this->assertSame('Tomorrow', $call->gathered_urgency);
        $this->assertStringContainsString('companionship', (string) $call->gathered_care_needs);
        $this->assertStringContainsString('Caller: My name is Don.', (string) $call->transcript_text);
        $this->assertStringContainsString('Assistant: For short-term care, the rate is 30 dollars per hour.', (string) $call->transcript_text);

        $statusPayload = [
            'AccountSid' => 'AC123',
            'CallDuration' => '93',
            'CallSid' => 'CA123',
            'CallStatus' => 'completed',
            'From' => '+19195550000',
            'To' => '+19195551234',
        ];

        $this->post(route('webhooks.twilio.voice.status', $call), $statusPayload, [
            'X-Twilio-Signature' => $this->twilioSignature(route('webhooks.twilio.voice.status', $call), $statusPayload, 'twilio-secret'),
        ])
            ->assertOk()
            ->assertSee('<Response></Response>', false);

        $this->assertDatabaseHas('voice_ai_calls', [
            'id' => $call->id,
            'status' => VoiceAiCall::STATUS_COMPLETED,
            'duration_seconds' => 93,
        ]);
    }

    public function test_provider_outreach_no_answer_status_marks_referral_lead_lost(): void
    {
        config()->set('services.twilio.bypass', false);
        config()->set('services.twilio.auth_token', 'twilio-secret');

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => 'Triangle Primary Care',
            'company' => 'Triangle Primary Care',
            'phone' => '+19195554444',
            'status' => 'outreach',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'ai_provider_outreach',
            'data' => ['source' => 'test'],
        ]);

        $call = VoiceAiCall::query()->create([
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_RINGING,
            'to_phone' => '+19195554444',
            'from_phone' => '+19844004008',
            'twilio_call_sid' => 'CA_PROVIDER_NO_ANSWER',
            'metadata' => [
                'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                'referral_lead_id' => $lead->id,
                'target_organization' => 'Triangle Primary Care',
                'target_name' => 'Office Manager',
                'target_phone' => '+19195554444',
                'provider_outreach_batch_id' => 'batch_1',
            ],
        ]);

        $payload = [
            'AccountSid' => 'AC123',
            'CallDuration' => '0',
            'CallSid' => 'CA_PROVIDER_NO_ANSWER',
            'CallStatus' => 'no-answer',
            'From' => '+19844004008',
            'To' => '+19195554444',
        ];

        $this->post(route('webhooks.twilio.voice.status', $call), $payload, [
            'X-Twilio-Signature' => $this->twilioSignature(route('webhooks.twilio.voice.status', $call), $payload, 'twilio-secret'),
        ])->assertOk();

        $call->refresh();
        $lead->refresh();

        $this->assertSame(VoiceAiCall::STATUS_NO_ANSWER, $call->status);
        $this->assertSame('error', $call->metadata['provider_outreach_interaction_rating']);
        $this->assertSame('completed', $call->metadata['provider_outreach_batch_status']);
        $this->assertSame('lost', $lead->status);
        $this->assertSame('error', $lead->data['provider_outreach']['last_interaction_rating']);
    }

    public function test_non_admin_cannot_access_voice_ai_console(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('admin.voice-ai.index'))
            ->assertForbidden();
    }

    public function test_admin_voice_log_shows_local_recording_player(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        VoiceAiCall::query()->create([
            'admin_user_id' => $admin->id,
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_COMPLETED,
            'to_phone' => '+19195551234',
            'from_phone' => '+19844004008',
            'metadata' => [
                'recording_url' => '/storage/voice-agent-recordings/call-42.wav',
                'recording_mime_type' => 'audio/wav',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.voice-ai.index'))
            ->assertOk()
            ->assertSee('Local audio recording')
            ->assertSee('/storage/voice-agent-recordings/call-42.wav', false);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function twilioSignature(string $url, array $payload, string $token): string
    {
        ksort($payload, SORT_STRING);

        $base = $url;
        foreach ($payload as $key => $value) {
            $base .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $base, $token, true));
    }
}
