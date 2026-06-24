<?php

namespace Tests\Feature\VoiceAgent;

use App\Mail\Ops\VoiceCallReportOpsAlertMail;
use App\Models\Lead;
use App\Models\LeadActivity;
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
                    'recording_url' => '/storage/voice-agent-recordings/call-42.wav',
                    'recording_path' => '../storage/app/public/voice-agent-recordings/call-42.wav',
                    'recording_mime_type' => 'audio/wav',
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
        $this->assertSame('/storage/voice-agent-recordings/call-42.wav', $call->metadata['recording_url']);
        $this->assertSame('../storage/app/public/voice-agent-recordings/call-42.wav', $call->metadata['recording_path']);
    }

    public function test_provider_outreach_result_creates_referral_lead_activity(): void
    {
        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/provider-outreach-results', [
                'call_sid' => 'CA_PROVIDER_1',
                'target_name' => 'Office Manager',
                'target_organization' => 'Triangle Primary Care',
                'target_role' => 'office_manager',
                'target_phone' => '+19195554444',
                'target_location' => 'Raleigh, NC',
                'outcome' => 'resource_requested',
                'summary' => 'Office manager asked for the one-page resource.',
                'contact_name' => 'Megan',
                'contact_role' => 'Office manager',
                'email' => 'office@example.com',
                'resource_requested' => true,
                'follow_up_needed' => true,
                'best_follow_up' => 'Email first, then call next week.',
                'metadata' => [
                    'voice_agent_profile' => 'provider_outreach',
                    'voice_ai_call_id' => '44',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Provider outreach result captured.');

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::TYPE_REFERRAL, $lead->lead_type);
        $this->assertSame('Megan', $lead->name);
        $this->assertSame('Triangle Primary Care', $lead->company);
        $this->assertSame('nurturing', $lead->status);
        $this->assertSame('ai_provider_outreach', $lead->source);
        $this->assertTrue($lead->data['provider_outreach']['resource_requested']);
        $this->assertTrue($lead->data['provider_outreach']['follow_up_needed']);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'Julie AI provider outreach: resource requested',
        ]);
    }

    public function test_provider_outreach_report_syncs_call_and_marks_do_not_call(): void
    {
        Mail::fake();

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
            'status' => VoiceAiCall::STATUS_IN_PROGRESS,
            'to_phone' => '+19195554444',
            'from_phone' => '+19844004008',
            'twilio_call_sid' => 'CA_PROVIDER_2',
            'metadata' => [
                'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                'referral_lead_id' => $lead->id,
                'target_organization' => 'Triangle Primary Care',
                'target_name' => 'Office Manager',
                'target_role' => 'office_manager',
            ],
        ]);

        $this->withToken('voice-secret')
            ->postJson('/api/internal/voice/reports', [
                'call_sid' => 'CA_PROVIDER_2',
                'phone' => '+19195554444',
                'lead_type' => 'referral',
                'intent' => 'provider_outreach',
                'outcome' => 'do_not_call',
                'call_status' => 'completed',
                'duration_seconds' => 22,
                'summary' => 'Front desk asked not to receive future calls.',
                'transcript' => "assistant: Hi, this is Julie.\nuser: Please remove us.",
                'callback_requested' => false,
                'signup_link_sent' => false,
                'metadata' => [
                    'channel' => 'voice_agent',
                    'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                    'voice_ai_call_id' => (string) $call->id,
                    'referral_lead_id' => (string) $lead->id,
                    'target_organization' => 'Triangle Primary Care',
                    'target_name' => 'Office Manager',
                    'target_role' => 'office_manager',
                    'target_phone' => '+19195554444',
                    'provider_outreach' => [
                        'outcome' => 'do_not_call',
                        'summary' => 'Front desk asked not to receive future calls.',
                        'do_not_call' => true,
                    ],
                ],
            ])
            ->assertCreated();

        $call->refresh();
        $lead->refresh();

        $this->assertSame(VoiceAiCall::STATUS_COMPLETED, $call->status);
        $this->assertSame(22, $call->duration_seconds);
        $this->assertSame('do_not_call', $call->metadata['provider_outreach']['outcome']);
        $this->assertSame('closed', $lead->status);
        $this->assertTrue($lead->data['provider_outreach']['do_not_call']);
        $this->assertSame('Do-not-call requested during Julie AI provider outreach.', $lead->closed_reason);
    }
}
