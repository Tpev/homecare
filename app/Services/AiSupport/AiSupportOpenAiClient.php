<?php

namespace App\Services\AiSupport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiSupportOpenAiClient
{
    /** @return array{result:array<string,mixed>,usage:array<string,int>,latency_ms:int,retries:int,cost_microunits:int} */
    public function respond(string $instructions, string $input): array
    {
        $this->assertMayRun();
        $payload = [
            'model' => (string) config('ai_support.model'),
            'store' => false,
            'instructions' => $instructions,
            'input' => $input,
            'reasoning' => ['effort' => (string) config('ai_support.reasoning_effort', 'low')],
            'max_output_tokens' => (int) config('ai_support.max_output_tokens', 900),
            'parallel_tool_calls' => false,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'lolo_interactive_support_turn',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        $started = hrtime(true);
        $response = null;
        $exception = null;
        $attempts = 0;
        $maximum = max(1, min(2, (int) config('ai_support.provider_retry_attempts', 2)));
        for ($attempt = 1; $attempt <= $maximum; $attempt++) {
            $attempts = $attempt;
            try {
                $request = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                    ->withToken((string) config('services.openai.api_key'))
                    ->acceptJson()->asJson()->connectTimeout(5)
                    ->timeout(max(10, (int) config('services.openai.timeout_seconds', 60)));
                $caBundle = trim((string) config('services.openai.ca_bundle'));
                if ($caBundle !== '') {
                    $request = $request->withOptions(['verify' => $caBundle]);
                }
                $response = $request->post('responses', $payload);
                if ($response->successful() || ! $this->mayRetry($response) || $attempt === $maximum) {
                    break;
                }
            } catch (ConnectionException $caught) {
                $exception = $caught;
                if ($attempt === $maximum) {
                    break;
                }
            }
        }
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        if (! $response instanceof Response) {
            throw new RuntimeException('AI Support provider connection failed.', 0, $exception);
        }
        if (! $response->successful()) {
            throw new RuntimeException('AI Support provider failed with HTTP '.$response->status().'.');
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('AI Support provider returned an invalid response.');
        }

        try {
            $result = json_decode($this->outputText($body), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $caught) {
            throw new RuntimeException('AI Support provider returned invalid structured output.', 0, $caught);
        }
        if (! is_array($result)) {
            throw new RuntimeException('AI Support provider returned no structured result.');
        }

        $usage = [
            'input_tokens' => (int) data_get($body, 'usage.input_tokens', 0),
            'cached_input_tokens' => (int) data_get($body, 'usage.input_tokens_details.cached_tokens', 0),
            'output_tokens' => (int) data_get($body, 'usage.output_tokens', 0),
        ];
        $uncached = max(0, $usage['input_tokens'] - $usage['cached_input_tokens']);
        $inputCost = ($uncached * (float) config('ai_support.provider_input_usd_per_million', 1.0))
            + ($usage['cached_input_tokens'] * (float) config('ai_support.provider_input_usd_per_million', 1.0) * 0.1);
        $outputCost = $usage['output_tokens'] * (float) config('ai_support.provider_output_usd_per_million', 6.0);

        return [
            'result' => $result,
            'usage' => $usage,
            'latency_ms' => $latency,
            'retries' => max(0, $attempts - 1),
            'cost_microunits' => (int) ceil($inputCost + $outputCost),
        ];
    }

    public function assertMayRun(): void
    {
        if (! config('ai_support.runtime_available', false)
            || ! config('ai_support.provider_enabled', false)) {
            throw new RuntimeException('AI Support provider runtime is disabled.');
        }
        if (trim((string) config('services.openai.api_key')) === '') {
            throw new RuntimeException('AI Support provider credentials are unavailable.');
        }
        $caBundle = trim((string) config('services.openai.ca_bundle'));
        if ($caBundle !== '' && ! is_file($caBundle)) {
            throw new RuntimeException('AI Support provider certificate configuration is invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];
        $nullableBoolean = ['type' => ['boolean', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['operation', 'message', 'navigation_target_id', 'care_path', 'clarifying_question', 'confidence_band', 'kb_stable_ids', 'draft_patch'],
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => ['answer', 'navigate', 'handoff', 'care_path', 'draft_patch']],
                'message' => ['type' => 'string'],
                'navigation_target_id' => $nullableString,
                'care_path' => ['type' => ['string', 'null'], 'enum' => ['one_time', 'recurring', 'human_24_7', 'clarify', null]],
                'clarifying_question' => $nullableString,
                'confidence_band' => ['type' => 'string', 'enum' => ['clear', 'ambiguous']],
                'kb_stable_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
                'draft_patch' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => [
                        'patch_fields', 'recipient_is_requester', 'recipient_profile_id', 'recipient_full_name',
                        'recipient_relationship', 'task_ids', 'task_notes', 'requested_start_date',
                        'requested_start_time', 'duration_minutes', 'recurring_days', 'recurring_schedule',
                        'recurring_starts_on', 'recurring_ends_on', 'address_line1', 'address_line2',
                        'city', 'state', 'zip', 'additional_info', 'home_access_notes', 'preferred_response_hours',
                    ],
                    'properties' => [
                        'patch_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'recipient_is_requester' => $nullableBoolean,
                        'recipient_profile_id' => $nullableInteger,
                        'recipient_full_name' => $nullableString,
                        'recipient_relationship' => $nullableString,
                        'task_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'task_notes' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['task_id', 'note'],
                                'properties' => [
                                    'task_id' => ['type' => 'integer'],
                                    'note' => ['type' => ['string', 'null']],
                                ],
                            ],
                        ],
                        'requested_start_date' => $nullableString,
                        'requested_start_time' => $nullableString,
                        'duration_minutes' => $nullableInteger,
                        'recurring_days' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'recurring_schedule' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['day', 'start_time', 'duration_minutes'],
                                'properties' => [
                                    'day' => ['type' => 'integer'],
                                    'start_time' => ['type' => 'string'],
                                    'duration_minutes' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                        'recurring_starts_on' => $nullableString,
                        'recurring_ends_on' => $nullableString,
                        'address_line1' => $nullableString,
                        'address_line2' => $nullableString,
                        'city' => $nullableString,
                        'state' => $nullableString,
                        'zip' => $nullableString,
                        'additional_info' => $nullableString,
                        'home_access_notes' => $nullableString,
                        'preferred_response_hours' => $nullableInteger,
                    ],
                ],
            ],
        ];
    }

    private function mayRetry(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /** @param array<string,mixed> $body */
    private function outputText(array $body): string
    {
        $texts = [];
        foreach ((array) ($body['output'] ?? []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('AI Support provider refused the structured request.');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $texts[] = $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $texts));
        if ($text === '') {
            throw new RuntimeException('AI Support provider returned no output text.');
        }

        return $text;
    }
}
