<?php

namespace Tests\Unit\AiCopilot;

use App\Models\CareRequest;
use App\Services\AiCopilot\MissingFieldsResolver;
use Tests\TestCase;

class MissingFieldsResolverTest extends TestCase
{
    public function test_it_detects_missing_required_fields_for_one_time_request(): void
    {
        $resolver = new MissingFieldsResolver();

        $missing = $resolver->requiredMissing([
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'title' => 'Help needed',
            'recipient' => ['full_name' => 'Jane'],
        ]);

        $this->assertContains('task_ids', $missing);
        $this->assertContains('requested_start_at', $missing);
        $this->assertContains('requested_end_at', $missing);
        $this->assertContains('recipient.relationship_to_family', $missing);
    }
}

