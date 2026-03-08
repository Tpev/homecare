<?php

namespace Tests\Unit\AiCopilot;

use App\Services\AiCopilot\DraftNormalizer;
use App\Services\AiCopilot\MissingFieldsResolver;
use App\Services\AiCopilot\RuleBasedCopilotResponder;
use Tests\TestCase;

class RuleBasedResponderConversationTest extends TestCase
{
    public function test_not_really_is_accepted_for_time_expectations(): void
    {
        $responder = new RuleBasedCopilotResponder(new DraftNormalizer(), new MissingFieldsResolver());

        $result = $responder->generate(
            [
                ['role' => 'assistant', 'content' => 'Any timing expectations?'],
                ['role' => 'user', 'content' => 'not really'],
            ],
            [
                'request_type' => 'one_time',
                'recipient' => [],
                'third_party_contact' => [],
            ],
            ['time_expectations', 'title']
        );

        $this->assertSame('No strict timing expectations.', $result['field_updates']['time_expectations'] ?? null);
    }
}

