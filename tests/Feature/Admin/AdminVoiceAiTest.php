<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\VoiceAiTest;
use App\Models\User;
use App\Models\VoiceAiCall;
use App\Services\VoiceAgent\TwilioVoiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
