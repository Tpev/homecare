<?php

namespace Tests\Feature\VoiceAgent;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Mail\Ops\VoiceCallReportOpsAlertMail;

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
                'phone' => '+15550001111',
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
}
