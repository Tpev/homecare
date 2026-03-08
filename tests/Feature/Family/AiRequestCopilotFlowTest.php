<?php

namespace Tests\Feature\Family;

use App\Contracts\AiCopilotResponder;
use App\Livewire\Family\AiRequestCopilot;
use App\Models\AiRequestSession;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiRequestCopilotFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_publish_request_via_ai_copilot(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);

        $this->app->bind(AiCopilotResponder::class, function () use ($task) {
            return new class($task->id) implements AiCopilotResponder
            {
                public function __construct(private readonly int $taskId)
                {
                }

                public function generate(array $conversation, array $draft, array $missingRequired): array
                {
                    return [
                        'assistant_message' => 'Perfect. I captured everything and your request is ready to publish.',
                        'field_updates' => [
                            'request_type' => CareRequest::TYPE_ONE_TIME,
                            'title' => 'Need caregiver tomorrow afternoon',
                            'additional_info' => 'Need reliable, calm support for parent at home.',
                            'scope_of_work' => 'Companionship, meal setup, and mobility standby support.',
                            'time_expectations' => 'Please arrive 10 minutes early.',
                            'home_access_notes' => 'Keypad entry, code sent after hire.',
                            'preferred_response_hours' => 8,
                            'address_line1' => '123 Main St',
                            'city' => 'Raleigh',
                            'state' => 'NC',
                            'zip' => '27601',
                            'task_ids' => [$this->taskId],
                            'requested_start_at' => now()->addDay()->setTime(15, 0)->format('Y-m-d H:i:s'),
                            'requested_end_at' => now()->addDay()->setTime(19, 0)->format('Y-m-d H:i:s'),
                            'recipient' => [
                                'full_name' => 'Margaret Doe',
                                'relationship_to_family' => 'Mother',
                                'care_notes' => 'Needs reminders and support for short walks.',
                            ],
                        ],
                        'field_confidence' => [],
                        'needs_confirmation' => [],
                        'next_question' => '',
                        'quick_replies' => [],
                        'safety_flags' => [],
                        'quality_hints' => [],
                        'model' => 'fake-test-model',
                    ];
                }
            };
        });

        Livewire::actingAs($family)
            ->test(AiRequestCopilot::class)
            ->set('input', 'Need help for my mom tomorrow afternoon in Raleigh.')
            ->call('send')
            ->assertSet('status', AiRequestSession::STATUS_READY_FOR_REVIEW)
            ->call('publish');

        $careRequest = CareRequest::query()->first();
        $this->assertNotNull($careRequest);
        $this->assertDatabaseHas('care_requests', [
            'id' => $careRequest->id,
            'family_user_id' => $family->id,
            'status' => CareRequest::STATUS_OPEN,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->assertDatabaseHas('ai_request_sessions', [
            'family_user_id' => $family->id,
            'status' => AiRequestSession::STATUS_PUBLISHED,
            'published_care_request_id' => $careRequest->id,
        ]);
    }

    public function test_non_family_user_cannot_access_ai_copilot_route(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $this->actingAs($caregiver)
            ->get(route('family.requests.create_ai'))
            ->assertForbidden();
    }
}
