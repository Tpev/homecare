<?php

namespace Tests\Unit\AiCopilot;

use App\Contracts\AiCopilotResponder;
use App\Models\AiRequestSession;
use App\Models\CareTask;
use App\Models\User;
use App\Services\AiCopilot\CopilotTurnService;
use App\Services\AiCopilot\DraftNormalizer;
use App\Services\AiCopilot\MissingFieldsResolver;
use App\Services\AiCopilot\QualityScorer;
use App\Services\AiCopilot\RuleBasedCopilotResponder;
use App\Services\AiCopilot\SafetyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopilotTurnServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_heuristic_updates_when_ai_updates_are_empty(): void
    {
        CareTask::query()->create(['name' => 'Meal preparation']);
        CareTask::query()->create(['name' => 'Companionship']);

        $family = User::factory()->create(['role' => 'family']);
        $session = AiRequestSession::query()->create([
            'family_user_id' => $family->id,
            'status' => AiRequestSession::STATUS_DRAFTING,
            'draft_json' => [
                'request_type' => 'one_time',
                'recipient' => [],
                'third_party_contact' => [],
                'state' => 'NC',
                'city' => 'Raleigh',
            ],
            'quality_score' => 0,
        ]);

        $session->messages()->create([
            'role' => 'user',
            'content_text' => "it's for meal prep and companion for my mom next week 9am to 12:30pm",
        ]);

        $responder = new class implements AiCopilotResponder
        {
            public function generate(array $conversation, array $draft, array $missingRequired): array
            {
                return [
                    'assistant_message' => 'What short title should I use for this request?',
                    'field_updates' => [],
                    'field_confidence' => [],
                    'needs_confirmation' => [],
                    'next_question' => 'What short title should I use for this request?',
                    'quick_replies' => [],
                    'safety_flags' => [],
                    'quality_hints' => [],
                    'model' => 'fake-ai',
                ];
            }
        };

        $turnService = new CopilotTurnService(
            $responder,
            new RuleBasedCopilotResponder(new DraftNormalizer(), new MissingFieldsResolver()),
            new DraftNormalizer(),
            new MissingFieldsResolver(),
            new QualityScorer(),
            new SafetyGuard()
        );

        $result = $turnService->process($session->fresh());

        $this->assertNotEmpty($result['draft']['title'] ?? null);
        $this->assertNotEmpty($result['draft']['task_ids'] ?? null);
        $this->assertNotSame('What short title should I use for this request?', $result['assistant_message']);
    }
}

