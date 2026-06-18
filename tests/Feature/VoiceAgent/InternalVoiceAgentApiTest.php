<?php

namespace Tests\Feature\VoiceAgent;

use App\Mail\Ops\VoiceCallReportOpsAlertMail;
use App\Models\Lead;
use App\Models\VoiceAiCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InternalVoiceAgentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('voice_agent.internal_api_token', 'voice-secret');
    }

    public function test_knowledge_endpoint_requires_valid_token(): void
    {
        $this->getJson('/api/internal/voice/knowledge')
            ->assertUnauthorized();

        $this->withToken('voice-secret')
            ->getJson('/api/internal/voice/knowledge')
            ->assertOk()
            ->assertJsonStructure([
                'brand_name',
                'service_summary',
                'service_details',
                'capabilities',
                'intents',
                'human_handoff',
                'signup_links',
                'faqs',
            ]);
    }

    public function test_voice_lead_endpoint_creates_lead_record(): void
    {
        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/leads', [
                'lead_type' => 'family',
                'intent' => 'information',
                'name' => 'Jane Caller',
                'phone' => '+15551234567',
                'notes' => 'Needs help next week.',
                'call_sid' => 'CA123',
                'metadata' => [
                    'caller_city' => 'Raleigh',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'new');

        $lead = Lead::query()->sole();

        $this->assertSame('family', $lead->lead_type);
        $this->assertSame('Jane Caller', $lead->name);
        $this->assertSame('information', $lead->data['intent']);
        $this->assertSame('CA123', $lead->data['call_sid']);
    }

    public function test_callback_endpoint_records_callback_request(): void
    {
        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/callbacks', [
                'lead_type' => 'family',
                'name' => 'Jane Caller',
                'phone' => '+15551234567',
                'callback_time' => 'Tomorrow afternoon',
                'reason' => 'Wants to confirm how onboarding works.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Callback request captured.');

        $lead = Lead::query()->sole();

        $this->assertSame('callback_request', $lead->data['intent']);
        $this->assertSame('Tomorrow afternoon', $lead->data['callback_time']);
    }

    public function test_signup_link_endpoint_returns_route_backed_link(): void
    {
        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/signup-link', [
                'lead_type' => 'caregiver',
                'phone' => '+15557654321',
                'consent_received' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('signup_link', route('caregiver.register'));

        $lead = Lead::query()->sole();

        $this->assertSame('signup_link', $lead->data['intent']);
        $this->assertSame(route('caregiver.register'), $lead->data['signup_link']);
    }

    public function test_report_endpoint_sends_ops_email(): void
    {
        Mail::fake();
        config()->set('marketplace.ops_alert_recipients', ['ops@example.com']);

        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/reports', [
                'call_sid' => 'CA456',
                'name' => 'Jane Caller',
                'phone' => '+15550001111',
                'relationship' => 'Daughter',
                'care_recipient' => 'Her mother',
                'care_needs' => 'Companionship and daily support',
                'urgency' => 'Needs help next week',
                'address' => '123 Main St',
                'city' => 'Raleigh',
                'zip' => '27601',
                'callback_time' => 'Tomorrow afternoon',
                'lead_type' => 'family',
                'intent' => 'callback_request',
                'outcome' => 'callback_request',
                'call_status' => 'completed',
                'duration_seconds' => 93,
                'summary' => 'Caller wants a callback about arranging care for a parent next week.',
                'transcript' => "assistant: Thanks for calling.\nuser: I need help for my mom.",
                'callback_requested' => true,
                'signup_link_sent' => false,
                'metadata' => [
                    'channel' => 'voice_agent',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Voice call report sent.');

        Mail::assertSent(VoiceCallReportOpsAlertMail::class, function (VoiceCallReportOpsAlertMail $mail): bool {
            return ($mail->report['call_sid'] ?? null) === 'CA456'
                && ($mail->report['outcome'] ?? null) === 'callback_request';
        });
    }

    public function test_report_endpoint_syncs_deepgram_callback_transcript_to_admin_call(): void
    {
        Mail::fake();

        $call = VoiceAiCall::query()->create([
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_IN_PROGRESS,
            'to_phone' => '+15550001111',
            'from_phone' => '+15552223333',
            'twilio_call_sid' => 'CA789',
        ]);

        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/reports', [
                'call_sid' => 'CA789',
                'name' => 'Jane Caller',
                'phone' => '+15550001111',
                'relationship' => 'Daughter',
                'care_needs' => 'Companionship and meals',
                'urgency' => 'This week',
                'city' => 'Durham',
                'zip' => '27703',
                'lead_type' => 'family',
                'intent' => 'callback_request',
                'outcome' => 'callback_request',
                'call_status' => 'completed',
                'duration_seconds' => 121,
                'summary' => 'Discovery call completed and family wants follow-up.',
                'transcript' => "assistant: Hi, this is LoLo.\nuser: My mom needs help this week.",
                'callback_requested' => true,
                'signup_link_sent' => false,
                'metadata' => [
                    'channel' => 'voice_agent',
                    'voice_agent_profile' => 'callback_discovery',
                    'voice_ai_call_id' => (string) $call->id,
                ],
            ])
            ->assertCreated();

        $call->refresh();

        $this->assertSame(VoiceAiCall::STATUS_COMPLETED, $call->status);
        $this->assertSame('reported', $call->current_step);
        $this->assertSame('Jane Caller', $call->gathered_name);
        $this->assertSame('Daughter', $call->gathered_relationship);
        $this->assertSame('Companionship and meals', $call->gathered_care_needs);
        $this->assertSame('Durham, 27703', $call->gathered_location);
        $this->assertTrue($call->callback_requested);
        $this->assertSame(121, $call->duration_seconds);
        $this->assertStringContainsString('user: My mom needs help this week.', (string) $call->transcript_text);
        $this->assertSame('callback_discovery', $call->metadata['voice_agent_profile']);
    }
}
