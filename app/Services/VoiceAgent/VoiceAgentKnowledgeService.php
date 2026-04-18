<?php

namespace App\Services\VoiceAgent;

class VoiceAgentKnowledgeService
{
    public function payload(): array
    {
        return [
            'brand_name' => (string) config('voice_agent.brand_name', config('app.name')),
            'service_summary' => (string) config('voice_agent.service_summary'),
            'service_details' => array_values((array) config('voice_agent.service_details', [])),
            'capabilities' => array_values((array) config('voice_agent.capabilities', [])),
            'intents' => [
                'information',
                'callback_request',
                'signup_link',
            ],
            'human_handoff' => [
                'offer_callback_when_uncertain' => true,
                'ops_hours' => (string) config('voice_agent.ops_hours'),
            ],
            'signup_links' => [
                'family' => $this->signupLinkForAudience('family'),
                'caregiver' => $this->signupLinkForAudience('caregiver'),
                'agency' => $this->signupLinkForAudience('agency'),
                'general' => $this->signupLinkForAudience('general'),
            ],
            'faqs' => array_values((array) config('voice_agent.faqs', [])),
        ];
    }

    public function signupLinkForAudience(string $audience): string
    {
        $configured = trim((string) config("voice_agent.signup_links.{$audience}", ''));
        if ($configured !== '') {
            return $configured;
        }

        return match ($audience) {
            'caregiver' => route('caregiver.register'),
            'agency' => route('landing.agency'),
            'general', 'family' => route('register'),
            default => route('register'),
        };
    }
}
