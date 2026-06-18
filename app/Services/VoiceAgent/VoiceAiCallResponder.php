<?php

namespace App\Services\VoiceAgent;

use App\Models\VoiceAiCall;

class VoiceAiCallResponder
{
    /**
     * @return array{message: string, hangup: bool}
     */
    public function respond(VoiceAiCall $call, string $speech): array
    {
        $speech = trim($speech);
        $normalized = strtolower($speech);

        if ($speech === '') {
            return [
                'message' => 'I did not catch that. Could you say that one more time?',
                'hangup' => false,
            ];
        }

        $this->extractIntakeFields($call, $speech);
        $call->refresh();

        if ($this->containsAny($normalized, ['bye', 'goodbye', 'that is all', 'nothing else', 'end call'])) {
            $summary = $this->summaryFor($call);
            $call->update([
                'summary' => $summary,
                'current_step' => 'completed',
            ]);

            return [
                'message' => 'Thanks. I saved this test call transcript. '.$summary.' Goodbye.',
                'hangup' => true,
            ];
        }

        if ($this->containsAny($normalized, ['emergency', 'chest pain', 'cannot breathe', 'fall injury', 'stroke'])) {
            $call->update(['current_step' => 'safety']);

            return [
                'message' => 'If this is a medical emergency, please hang up and call 911 now. LoLo can help with non-medical home care planning after urgent safety is handled.',
                'hangup' => false,
            ];
        }

        if ($this->containsAny($normalized, ['human', 'person', 'call me', 'callback', 'call back', 'talk to someone'])) {
            $call->update([
                'callback_requested' => true,
                'current_step' => 'callback',
            ]);

            return [
                'message' => $this->callbackQuestion($call),
                'hangup' => false,
            ];
        }

        if ($this->containsAny($normalized, ['text me', 'signup link', 'sign up link', 'register', 'send me the link', 'get started'])) {
            $call->update([
                'signup_link_requested' => true,
                'current_step' => 'signup_link',
            ]);

            return [
                'message' => 'I noted that you want the sign-up link. For this test call, I will save that request in the transcript for the team to review.',
                'hangup' => false,
            ];
        }

        $faqAnswer = $this->faqAnswer($normalized);
        if ($faqAnswer !== null) {
            return [
                'message' => $faqAnswer.' '.$this->nextIntakeQuestion($call),
                'hangup' => false,
            ];
        }

        return [
            'message' => 'Got it. '.$this->nextIntakeQuestion($call),
            'hangup' => false,
        ];
    }

    public function initialPrompt(): string
    {
        return 'Hi, this is the LoLo test voice assistant. I can answer basic questions and collect care request details for the team. Who needs care, and what kind of help are you looking for?';
    }

    private function callbackQuestion(VoiceAiCall $call): string
    {
        if (! $call->gathered_name) {
            return 'Absolutely. What name should our team ask for when they call back?';
        }

        if (! $call->gathered_callback_time) {
            return 'What is the best time for a team member to call back?';
        }

        return 'I saved your callback request. You can also tell me what kind of care support is needed.';
    }

    private function nextIntakeQuestion(VoiceAiCall $call): string
    {
        if (! $call->gathered_name) {
            return 'What is your name?';
        }

        if (! $call->gathered_relationship) {
            return 'What is your relationship to the person receiving care?';
        }

        if (! $call->gathered_care_needs) {
            return 'What kind of help is needed, for example companionship, errands, meals, or light housekeeping?';
        }

        if (! $call->gathered_location) {
            return 'What city or ZIP code is the care needed in?';
        }

        if (! $call->gathered_urgency) {
            return 'How soon do you need care to start?';
        }

        if ($call->callback_requested && ! $call->gathered_callback_time) {
            return 'What is the best time for a team member to call back?';
        }

        return 'I have the basics. You can ask another question, add more details, or say goodbye to finish.';
    }

    private function faqAnswer(string $normalizedSpeech): ?string
    {
        foreach ((array) config('voice_agent.faqs', []) as $faq) {
            foreach ((array) ($faq['keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($normalizedSpeech, strtolower((string) $keyword))) {
                    return (string) ($faq['answer'] ?? '');
                }
            }
        }

        return null;
    }

    private function extractIntakeFields(VoiceAiCall $call, string $speech): void
    {
        $normalized = strtolower($speech);
        $updates = [];

        if (! $call->gathered_name && preg_match('/(?:my name is|this is|i am|i\'m)\s+([a-z][a-z\s\'-]{1,80})/i', $speech, $matches)) {
            $updates['gathered_name'] = trim($matches[1]);
        }

        if (! $call->gathered_relationship) {
            $relationship = $this->relationshipFrom($normalized);
            if ($relationship !== null) {
                $updates['gathered_relationship'] = $relationship;
            }
        }

        if (! $call->gathered_location) {
            if (preg_match('/\b\d{5}(?:-\d{4})?\b/', $speech, $matches)) {
                $updates['gathered_location'] = $matches[0];
            } elseif (preg_match('/(?:in|near|around)\s+([a-z][a-z\s.-]{2,80})(?:\.|,|$)/i', $speech, $matches)) {
                $updates['gathered_location'] = trim($matches[1]);
            }
        }

        if (! $call->gathered_urgency) {
            if ($this->containsAny($normalized, ['today', 'tonight', 'as soon as possible', 'asap', 'urgent', 'tomorrow', 'this week', 'next week'])) {
                $updates['gathered_urgency'] = $this->urgencyFrom($normalized);
            }
        }

        if (! $call->gathered_callback_time && $this->containsAny($normalized, ['morning', 'afternoon', 'evening', 'tomorrow', 'today', 'after', 'before'])) {
            $updates['gathered_callback_time'] = $speech;
        }

        if (! $call->gathered_care_needs && $this->looksLikeCareNeed($normalized)) {
            $updates['gathered_care_needs'] = $speech;
        }

        if ($updates !== []) {
            $call->update($updates);
        }
    }

    private function relationshipFrom(string $speech): ?string
    {
        return match (true) {
            str_contains($speech, 'my mom'), str_contains($speech, 'mother') => 'Mother',
            str_contains($speech, 'my dad'), str_contains($speech, 'father') => 'Father',
            str_contains($speech, 'wife') => 'Wife',
            str_contains($speech, 'husband') => 'Husband',
            str_contains($speech, 'grandmother'), str_contains($speech, 'grandma') => 'Grandmother',
            str_contains($speech, 'grandfather'), str_contains($speech, 'grandpa') => 'Grandfather',
            str_contains($speech, 'myself'), str_contains($speech, 'for me'), str_contains($speech, 'i need care') => 'Self',
            default => null,
        };
    }

    private function urgencyFrom(string $speech): string
    {
        return match (true) {
            str_contains($speech, 'today') => 'Today',
            str_contains($speech, 'tonight') => 'Tonight',
            str_contains($speech, 'tomorrow') => 'Tomorrow',
            str_contains($speech, 'asap'), str_contains($speech, 'as soon as possible'), str_contains($speech, 'urgent') => 'As soon as possible',
            str_contains($speech, 'next week') => 'Next week',
            str_contains($speech, 'this week') => 'This week',
            default => 'Not specified',
        };
    }

    private function looksLikeCareNeed(string $speech): bool
    {
        return $this->containsAny($speech, [
            'companionship',
            'companion',
            'errand',
            'meal',
            'cook',
            'housekeeping',
            'clean',
            'transport',
            'appointment',
            'laundry',
            'help',
            'care',
            'support',
        ]);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function summaryFor(VoiceAiCall $call): string
    {
        $parts = [];

        if ($call->gathered_name) {
            $parts[] = 'Caller: '.$call->gathered_name;
        }
        if ($call->gathered_relationship) {
            $parts[] = 'Relationship: '.$call->gathered_relationship;
        }
        if ($call->gathered_care_needs) {
            $parts[] = 'Needs: '.$call->gathered_care_needs;
        }
        if ($call->gathered_location) {
            $parts[] = 'Location: '.$call->gathered_location;
        }
        if ($call->gathered_urgency) {
            $parts[] = 'Timing: '.$call->gathered_urgency;
        }
        if ($call->callback_requested) {
            $parts[] = 'Callback requested'.($call->gathered_callback_time ? ': '.$call->gathered_callback_time : '');
        }
        if ($call->signup_link_requested) {
            $parts[] = 'Signup link requested';
        }

        return $parts === []
            ? 'No structured details were captured yet.'
            : implode('. ', $parts).'.';
    }
}
