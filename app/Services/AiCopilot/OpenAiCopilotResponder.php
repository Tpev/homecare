<?php

namespace App\Services\AiCopilot;

use App\Contracts\AiCopilotResponder;
use App\Services\AI\OpenAiClient;
use Throwable;

class OpenAiCopilotResponder implements AiCopilotResponder
{
    public function __construct(
        private readonly OpenAiClient $client,
        private readonly RuleBasedCopilotResponder $fallback,
    ) {
    }

    public function generate(array $conversation, array $draft, array $missingRequired): array
    {
        try {
            $startedAt = microtime(true);
            $response = $this->client->responses([
                'model' => (string) config('services.openai.model'),
                'temperature' => 0.2,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($conversation, $draft, $missingRequired),
                    ],
                ],
            ]);

            $json = json_decode($this->extractOutputText($response), true);
            if (! is_array($json)) {
                return $this->fallback->generate($conversation, $draft, $missingRequired);
            }

            $usage = $response['usage'] ?? [];
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'assistant_message' => (string) ($json['assistant_message'] ?? $json['next_question'] ?? 'Got it. Tell me the next detail.'),
                'field_updates' => is_array($json['field_updates'] ?? null) ? $json['field_updates'] : [],
                'field_confidence' => is_array($json['field_confidence'] ?? null) ? $json['field_confidence'] : [],
                'needs_confirmation' => is_array($json['needs_confirmation'] ?? null) ? array_values($json['needs_confirmation']) : [],
                'next_question' => (string) ($json['next_question'] ?? ''),
                'quick_replies' => is_array($json['quick_replies'] ?? null) ? array_slice(array_values($json['quick_replies']), 0, 4) : [],
                'safety_flags' => is_array($json['safety_flags'] ?? null) ? array_values($json['safety_flags']) : [],
                'quality_hints' => is_array($json['quality_hints'] ?? null) ? array_values($json['quality_hints']) : [],
                'model' => (string) ($response['model'] ?? config('services.openai.model')),
                'prompt_tokens' => is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : null,
                'completion_tokens' => is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : null,
                'latency_ms' => $latencyMs,
            ];
        } catch (Throwable) {
            return $this->fallback->generate($conversation, $draft, $missingRequired);
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an AI copilot that helps families create non-medical home care requests.

Output MUST be valid JSON only (no markdown) with these keys:
- assistant_message: string
- field_updates: object
- field_confidence: object of number 0..1 by field path
- needs_confirmation: array of field paths
- next_question: string
- quick_replies: array of up to 4 short strings
- safety_flags: array (e.g. ["medical_scope"])
- quality_hints: array of short strings

Rules:
1) Ask only one next question.
2) Only capture non-medical caregiving tasks.
3) If user asks for medical procedures, set safety_flags=["medical_scope"] and suggest licensed clinical providers.
4) Prefer extracting structure from user text over asking again.
5) Keep assistant_message concise, warm, and practical.
6) If title is missing but enough context exists (services + schedule or recipient context), generate a clear title automatically in field_updates.title.
7) For services, do not require precise taxonomy. Accept rough wording and map intent (e.g., "help mom in morning" -> companionship/support).
8) Avoid repeating the same question if user already gave partial data; ask a simpler follow-up with examples.
9) Never set title to filler responses like "I don't know", "not really", "test", or "whatever".
PROMPT;
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     * @param  array<string,mixed>  $draft
     * @param  array<int,string>  $missingRequired
     */
    private function userPrompt(array $conversation, array $draft, array $missingRequired): string
    {
        $recentConversation = array_slice($conversation, -8);

        return json_encode([
            'task' => 'Update care-request draft based on conversation and choose the next best question.',
            'draft' => $draft,
            'missing_required' => $missingRequired,
            'conversation' => $recentConversation,
            'allowed_request_types' => ['one_time', 'recurring'],
            'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string,mixed>  $response
     */
    private function extractOutputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $output = $response['output'] ?? [];
        if (is_array($output)) {
            foreach ($output as $item) {
                $content = $item['content'] ?? null;
                if (! is_array($content)) {
                    continue;
                }

                foreach ($content as $piece) {
                    if (isset($piece['text']) && is_string($piece['text'])) {
                        return $piece['text'];
                    }
                    if (isset($piece['output_text']) && is_string($piece['output_text'])) {
                        return $piece['output_text'];
                    }
                }
            }
        }

        return '{}';
    }
}
