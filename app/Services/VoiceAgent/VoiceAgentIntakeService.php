<?php

namespace App\Services\VoiceAgent;

use App\Models\Lead;
use Illuminate\Http\Request;

class VoiceAgentIntakeService
{
    public function createLead(array $payload, Request $request): Lead
    {
        return $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'],
            name: $payload['name'] ?? null,
            email: $payload['email'] ?? null,
            phone: $payload['phone'] ?? null,
            company: $payload['company'] ?? null,
            location: $payload['location'] ?? null,
            zip: $payload['zip'] ?? null,
            sourceUrl: $payload['source_url'] ?? null,
            referrerUrl: $payload['referrer_url'] ?? null,
            data: [
                'source' => 'voice_agent',
                'intent' => $payload['intent'],
                'notes' => $payload['notes'] ?? null,
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );
    }

    public function createCallbackRequest(array $payload, Request $request): Lead
    {
        return $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'] ?? 'general',
            name: $payload['name'] ?? null,
            phone: $payload['phone'],
            data: [
                'source' => 'voice_agent',
                'intent' => 'callback_request',
                'callback_time' => $payload['callback_time'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );
    }

    public function createSignupRequest(array $payload, Request $request, string $signupLink): array
    {
        $lead = $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'],
            name: $payload['name'] ?? null,
            phone: $payload['phone'] ?? null,
            data: [
                'source' => 'voice_agent',
                'intent' => 'signup_link',
                'consent_received' => (bool) $payload['consent_received'],
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'signup_link' => $signupLink,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );

        return [
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'signup_link' => $signupLink,
            'sms_message' => trim(sprintf(
                'Thanks for calling %s. Here is your sign-up link: %s',
                (string) config('voice_agent.brand_name', config('app.name')),
                $signupLink
            )),
        ];
    }

    private function storeLead(
        Request $request,
        string $leadType,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $company = null,
        ?string $location = null,
        ?string $zip = null,
        ?string $sourceUrl = null,
        ?string $referrerUrl = null,
        array $data = [],
    ): Lead {
        return Lead::query()->create([
            'lead_type' => $leadType,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'location' => $location,
            'zip' => $zip,
            'data' => $data,
            'status' => 'new',
            'source_url' => $sourceUrl ?: 'voice-agent://phone-call',
            'referrer_url' => $referrerUrl,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
