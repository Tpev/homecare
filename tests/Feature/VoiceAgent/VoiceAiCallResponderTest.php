<?php

namespace Tests\Feature\VoiceAgent;

use App\Models\VoiceAiCall;
use App\Services\VoiceAgent\VoiceAiCallResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceAiCallResponderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_identifies_as_lolo_care(): void
    {
        $prompt = app(VoiceAiCallResponder::class)->initialPrompt();

        $this->assertStringContainsString('LoLo Care', $prompt);
        $this->assertStringNotContainsString('LoLo test voice assistant', $prompt);
    }

    public function test_charles_request_captures_callback_details_and_confirms_prompt_follow_up(): void
    {
        $call = VoiceAiCall::query()->create([
            'status' => VoiceAiCall::STATUS_IN_PROGRESS,
            'to_phone' => '+15551234567',
            'from_phone' => '+15557654321',
            'gathered_phone' => '+15551234567',
        ]);
        $responder = app(VoiceAiCallResponder::class);

        $first = $responder->respond($call, 'I need to speak with Charles.');

        $call->refresh();
        $this->assertTrue($call->callback_requested);
        $this->assertSame('Charles', $call->metadata['requested_contact']);
        $this->assertStringContainsString('Charles or someone from the LoLo Care team', $first['message']);
        $this->assertStringContainsString('as soon as possible', $first['message']);
        $this->assertStringContainsString('What name', $first['message']);

        $second = $responder->respond($call, 'My name is Jamie Smith.');

        $call->refresh();
        $this->assertSame('Jamie Smith', $call->gathered_name);
        $this->assertStringContainsString('best time', $second['message']);

        $third = $responder->respond($call, 'Tomorrow morning works best.');

        $call->refresh();
        $this->assertSame('Tomorrow morning works best.', $call->gathered_callback_time);
        $this->assertStringContainsString('saved your callback request', $third['message']);
        $this->assertStringContainsString('as soon as possible', $third['message']);
    }

    public function test_caregiver_job_inquiry_is_directed_to_caregiver_account_creation(): void
    {
        $call = VoiceAiCall::query()->create([
            'status' => VoiceAiCall::STATUS_IN_PROGRESS,
            'to_phone' => '+15551234567',
            'from_phone' => '+15557654321',
        ]);

        $result = app(VoiceAiCallResponder::class)->respond($call, 'I want to apply for a caregiver job.');

        $call->refresh();
        $this->assertSame('caregiver_application', $call->current_step);
        $this->assertSame('caregiver_application', $call->metadata['voice_agent_intent']);
        $this->assertTrue($call->signup_link_requested);
        $this->assertStringContainsString('LoLo Care', $result['message']);
        $this->assertStringContainsString(route('caregiver.register'), $result['message']);
        $this->assertStringContainsString('create a caregiver account', $result['message']);
    }
}
