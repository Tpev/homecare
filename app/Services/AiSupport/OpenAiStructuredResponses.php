<?php

namespace App\Services\AiSupport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Shared transport; each caller owns its feature authorization and JSON contract. */
class OpenAiStructuredResponses
{
    public function send(array $payload, ?int $timeoutSeconds = null, ?int $maximumAttempts = null): array
    {
        if (trim((string) config('services.openai.api_key')) === '') {
            throw new RuntimeException('The existing OpenAI connection is unavailable.');
        }
        $ca = trim((string) config('services.openai.ca_bundle'));
        if ($ca !== '' && ! is_file($ca)) {
            throw new RuntimeException('The OpenAI certificate configuration is invalid.');
        }
        $started = hrtime(true);
        $response = null;
        $connectionCode = 'unknown';
        $maximum = max(1, min(2, $maximumAttempts ?? (int) config('ai_support.provider_retry_attempts', 2)));
        for ($attempt = 1; $attempt <= $maximum; $attempt++) {
            try {
                $http = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                    ->withToken((string) config('services.openai.api_key'))->acceptJson()->asJson()
                    ->connectTimeout(5)->timeout(max(10, $timeoutSeconds ?? (int) config('services.openai.timeout_seconds', 60)));
                if ($ca !== '') {
                    $http = $http->withOptions(['verify' => $ca]);
                }
                $response = $http->post('responses', $payload);
                if ($response->successful() || ($response->status() !== 429 && ! $response->serverError())) {
                    break;
                }
            } catch (ConnectionException $exception) {
                // Do not include provider request bodies or credentials in errors.
                if (preg_match('/cURL error ([0-9]+)/', $exception->getMessage(), $matches)) {
                    $connectionCode = $matches[1];
                }
            }
        }
        if (! $response instanceof Response) {
            throw new RuntimeException('The OpenAI connection failed (transport '.$connectionCode.'). Please try again.');
        }
        if (! $response->successful()) {
            $code = $response->json('error.code');
            $code = is_string($code) && preg_match('/\A[a-z_]{1,64}\z/', $code) ? $code : 'unavailable';
            throw new RuntimeException('OpenAI request failed with HTTP '.$response->status().' ('.$code.').');
        }
        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? 'completed') !== 'completed') {
            throw new RuntimeException('OpenAI returned an incomplete response.');
        }
        $texts = [];
        foreach (($body['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI could not assess this request.');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $texts[] = $content['text'];
                }
            }
        }
        try {
            $result = json_decode(implode("\n", $texts), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('OpenAI returned invalid structured output.');
        }
        if (! is_array($result)) {
            throw new RuntimeException('OpenAI returned no structured result.');
        }

        return ['result' => $result, 'usage' => [
            'input_tokens' => (int) data_get($body, 'usage.input_tokens', 0),
            'cached_input_tokens' => (int) data_get($body, 'usage.input_tokens_details.cached_tokens', 0),
            'output_tokens' => (int) data_get($body, 'usage.output_tokens', 0),
        ], 'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000), 'retries' => min($maximum - 1, $attempt - 1)];
    }
}
