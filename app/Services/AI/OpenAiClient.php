<?php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function responses(array $payload): array
    {
        $key = (string) config('services.openai.key');
        if ($key === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = $this->http()
            ->post('/responses', $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Invalid OpenAI response payload.');
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.openai.base_url'))
            ->withToken((string) config('services.openai.key'))
            ->timeout((int) config('services.openai.timeout', 25))
            ->acceptJson()
            ->asJson();
    }
}

