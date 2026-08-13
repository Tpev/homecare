<?php

namespace App\Services\AiSupport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OfflineOpenAiResponsesClient
{
    /**
     * @param  array<string,mixed>  $candidate
     * @return array{response:array<string,mixed>,usage:array<string,int>,latency_ms:int,retries:int,response_hash:string}
     */
    public function evaluate(array $candidate, string $instructions, string $input, array $schema): array
    {
        $this->assertMayRun();

        $apiKey = trim((string) config('services.openai.api_key'));
        $caBundle = trim((string) config('services.openai.ca_bundle'));

        $payload = [
            'model' => $candidate['model'],
            'store' => false,
            'instructions' => $instructions,
            'input' => $input,
            'reasoning' => ['effort' => $candidate['reasoning_effort']],
            'max_output_tokens' => $candidate['max_output_tokens'],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'lolo_ai_support_evaluation_result',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        $started = hrtime(true);
        $response = null;
        $lastException = null;
        $attempts = 0;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $attempts = $attempt;
            try {
                $request = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout(10)
                    ->timeout(max(10, (int) config('services.openai.timeout_seconds', 60)));
                if ($caBundle !== '') {
                    $request = $request->withOptions(['verify' => $caBundle]);
                }
                $response = $request->post('responses', $payload);

                if ($response->successful() || ! $this->mayRetry($response) || $attempt === 3) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
                if ($attempt === 3) {
                    break;
                }
            }

            usleep(250_000 * $attempt);
        }
        $latencyMs = (int) round((hrtime(true) - $started) / 1_000_000);

        if (! $response instanceof Response) {
            throw new RuntimeException('Responses API connection failed after retry.', 0, $lastException);
        }
        if (! $response->successful()) {
            $providerCode = $this->safeProviderToken($response->json('error.code'));
            $providerParam = $this->safeProviderToken($response->json('error.param'));
            throw new RuntimeException(
                'Responses API request failed with HTTP '.$response->status()
                .'; provider_code='.$providerCode
                .'; provider_param='.$providerParam.'.',
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Responses API returned an invalid response envelope.');
        }

        $text = $this->extractOutputText($body);
        try {
            $structured = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Responses API structured output was not valid JSON.', 0, $exception);
        }
        if (! is_array($structured)) {
            throw new RuntimeException('Responses API structured output was not an object.');
        }

        $usage = [
            'input_tokens' => (int) data_get($body, 'usage.input_tokens', 0),
            'cached_input_tokens' => (int) data_get($body, 'usage.input_tokens_details.cached_tokens', 0),
            'output_tokens' => (int) data_get($body, 'usage.output_tokens', 0),
            'reasoning_tokens' => (int) data_get($body, 'usage.output_tokens_details.reasoning_tokens', 0),
        ];

        return [
            'response' => $structured,
            'usage' => $usage,
            'latency_ms' => $latencyMs,
            'retries' => max(0, $attempts - 1),
            'response_hash' => hash('sha256', json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ];
    }

    public function assertMayRun(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Offline AI Support evaluation is prohibited in production.');
        }
        if (! (bool) config('ai_support.offline_evaluation_enabled', false)) {
            throw new RuntimeException('Offline AI Support evaluation is disabled.');
        }
        if ((bool) config('ai_support.runtime_available', false)) {
            throw new RuntimeException('Offline evaluation cannot run while the customer AI runtime is available.');
        }
        if (trim((string) config('services.openai.api_key')) === '') {
            throw new RuntimeException('OPENAI_API_KEY is required for offline AI Support evaluation.');
        }
        $caBundle = trim((string) config('services.openai.ca_bundle'));
        if ($caBundle !== '' && ! is_file($caBundle)) {
            throw new RuntimeException('OPENAI_CA_BUNDLE does not identify a readable certificate bundle.');
        }
    }

    private function mayRetry(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /** @param array<string,mixed> $body */
    private function extractOutputText(array $body): string
    {
        $texts = [];
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('Responses API returned a refusal instead of the required structure.');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $texts[] = $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $texts));
        if ($text === '') {
            throw new RuntimeException('Responses API returned no output text.');
        }

        return $text;
    }

    private function safeProviderToken(mixed $value): string
    {
        $token = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $value);

        return $token === '' ? 'unspecified' : substr($token, 0, 80);
    }
}
