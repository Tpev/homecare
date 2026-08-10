<?php

namespace App\Services\VoiceAgent;

class VoiceAgentKnowledgeService
{
    public function payload(): array
    {
        return [
            'brand_name' => (string) config('voice_agent.brand_name', config('app.name')),
            'service_summary' => $this->canonicalBrandText((string) config('voice_agent.service_summary')),
            'service_details' => $this->canonicalBrandList((array) config('voice_agent.service_details', [])),
            'capabilities' => $this->canonicalBrandList((array) config('voice_agent.capabilities', [])),
            'intents' => [
                'information',
                'callback_request',
                'signup_link',
                'caregiver_application',
            ],
            'human_handoff' => [
                'offer_callback_when_uncertain' => true,
                'capture' => ['name', 'phone', 'reason', 'callback_time_if_useful'],
                'requested_contacts' => ['LoLo Care team', 'Charles'],
                'follow_up_expectation' => 'Someone from the LoLo Care team, or Charles when specifically requested, will call back as soon as possible.',
                'ops_hours' => (string) config('voice_agent.ops_hours'),
            ],
            'signup_links' => [
                'family' => $this->signupLinkForAudience('family'),
                'caregiver' => $this->signupLinkForAudience('caregiver'),
                'general' => $this->signupLinkForAudience('general'),
            ],
            'faqs' => array_map(function (array $faq): array {
                $faq['question'] = $this->canonicalBrandText((string) ($faq['question'] ?? ''));
                $faq['answer'] = $this->canonicalBrandText((string) ($faq['answer'] ?? ''));

                return $faq;
            }, array_values((array) config('voice_agent.faqs', []))),
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
            'general', 'family' => route('register'),
            default => route('register'),
        };
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private function canonicalBrandList(array $items): array
    {
        return array_values(array_map(
            fn (mixed $item): string => $this->canonicalBrandText((string) $item),
            $items,
        ));
    }

    private function canonicalBrandText(string $value): string
    {
        $value = str_ireplace(['Homecare', 'Laravel'], 'LoLo Care', $value);

        return (string) preg_replace('/\bLoLo\b(?!\s+Care\b)/i', 'LoLo Care', $value);
    }
}
