<?php

namespace Tests\Unit\AiCopilot;

use App\Models\CareTask;
use App\Services\AiCopilot\DraftNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_task_names_and_state(): void
    {
        $task = CareTask::query()->create(['name' => 'Meal preparation']);
        $normalizer = new DraftNormalizer();

        $draft = $normalizer->merge([], [
            'state' => 'north carolina',
            'tasks' => ['meal prep'],
            'preferred_response_hours' => 0,
        ]);

        $this->assertSame('NC', $draft['state']);
        $this->assertSame(12, $draft['preferred_response_hours']);
        $this->assertSame([$task->id], $draft['task_ids']);
    }
}
