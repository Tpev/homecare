<?php

namespace Tests\Unit\AiCopilot;

use App\Services\AiCopilot\QualityScorer;
use Tests\TestCase;

class QualityScorerTest extends TestCase
{
    public function test_quality_score_increases_when_required_fields_are_completed(): void
    {
        $scorer = new QualityScorer();

        $low = $scorer->score([], ['title', 'address_line1', 'task_ids', 'recipient.full_name']);
        $high = $scorer->score([
            'address_line2' => 'Unit 3',
            'recipient' => ['date_of_birth' => '1940-01-01', 'gender' => 'female'],
        ], []);

        $this->assertTrue($high > $low);
        $this->assertGreaterThanOrEqual(80, $high);
        $this->assertLessThanOrEqual(100, $high);
    }
}
