<?php

namespace Tests\Unit\AiCopilot;

use App\Models\CareRequest;
use App\Models\CareTask;
use App\Services\AiCopilot\DraftNormalizer;
use App\Services\AiCopilot\MissingFieldsResolver;
use App\Services\AiCopilot\RuleBasedCopilotResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleBasedCopilotResponderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_title_and_tasks_from_user_message(): void
    {
        CareTask::query()->create(['name' => 'Meal preparation']);
        CareTask::query()->create(['name' => 'Companionship']);

        $responder = new RuleBasedCopilotResponder(new DraftNormalizer(), new MissingFieldsResolver());

        $result = $responder->generate(
            [
                ['role' => 'assistant', 'content' => 'What short title should I use?'],
                ['role' => 'user', 'content' => 'its for meal prep and companion'],
            ],
            [
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'recipient' => [],
                'third_party_contact' => [],
            ],
            ['title', 'task_ids']
        );

        $this->assertArrayHasKey('title', $result['field_updates']);
        $this->assertArrayHasKey('tasks', $result['field_updates']);
        $this->assertNotSame('What short title should I use for this request?', $result['assistant_message']);
    }

    public function test_it_generates_title_when_user_says_i_do_not_know(): void
    {
        CareTask::query()->create(['name' => 'Meal preparation']);

        $responder = new RuleBasedCopilotResponder(new DraftNormalizer(), new MissingFieldsResolver());

        $result = $responder->generate(
            [
                ['role' => 'assistant', 'content' => 'What short title should I use?'],
                ['role' => 'user', 'content' => "I don't know"],
            ],
            [
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'tasks' => ['Meal preparation'],
                'recipient' => ['relationship_to_family' => 'Mother'],
                'third_party_contact' => [],
            ],
            ['title']
        );

        $this->assertSame('Meal preparation support request for Mother', $result['field_updates']['title']);
    }
}
