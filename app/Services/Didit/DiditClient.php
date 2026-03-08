<?php

namespace App\Services\Didit;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiditClient
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createSession(array $payload): array
    {
        $response = $this->http()
            ->post('/v3/session/', $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Invalid Didit create-session payload.');
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchDecision(string $sessionId): array
    {
        $response = $this->http()
            ->get('/v3/session/'.$sessionId.'/decision/')
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Invalid Didit decision payload.');
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        $key = (string) config('services.didit.api_key');
        if ($key === '') {
            throw new RuntimeException('DIDIT_API_KEY is not configured.');
        }

        return Http::baseUrl((string) config('services.didit.base_url', 'https://verification.didit.me'))
            ->withHeaders(['x-api-key' => $key])
            ->timeout((int) config('services.didit.timeout', 20))
            ->acceptJson()
            ->asJson();
    }
}

