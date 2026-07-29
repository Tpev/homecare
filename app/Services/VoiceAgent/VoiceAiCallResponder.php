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
                'message' => 'If this is a medical emergency, please hang up and call 911 now. LoLo Care can help with non-medical home care planning after urgent safety is handled.',
                'hangup' => false,
            ];
        }

        if ($this->isCaregiverApplicationInquiry($normalized)) {
            $metadata = is_array($call->metadata) ? $call->metadata : [];
            $metadata['voice_agent_intent'] = 'caregiver_application';

            $call->update([
                'signup_link_requested' => true,
                'current_step' => 'caregiver_application',
                'metadata' => $metadata,
            ]);

            return [
                'message' => 'To apply for caregiver opportunities with LoLo Care, please visit '.route('caregiver.register').' and create a caregiver account.',
                'hangup' => false,
            ];
        }

        if ($call->callback_requested) {
            return [
                'message' => $this->callbackQuestion($call),
                'hangup' => false,
            ];
        }

        $askedForCharles = str_contains($normalized, 'charles') && $this->containsAny($normalized, ['speak', 'talk', 'call', 'connect', 'reach', 'need charles', 'charles please']);
        $requestedContact = $askedForCharles ? 'Charles' : 'LoLo Care team';
        if ($askedForCharles || $this->containsAny($normalized, ['human', 'person', 'call me', 'callback', 'call back', 'talk to someone', 'someone from the team'])) {
            $metadata = is_array($call->metadata) ? $call->metadata : [];
            $metadata['requested_contact'] = $requestedContact;

            $call->update([
                'callback_requested' => true,
                'current_step' => 'callback',
                'metadata' => $metadata,
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
        return 'Hi, this is the LoLo Care test voice assistant. I can answer basic questions, arrange a callback, or direct caregiver applicants to create an account. How can I help?';
    }

    private function callbackQuestion(VoiceAiCall $call): string
    {
        $requestedContact = data_get($call->metadata, 'requested_contact') === 'Charles'
            ? 'Charles or someone from the LoLo Care team'
            : 'Someone from the LoLo Care team';

        if (! $call->gathered_name) {
            return 'Absolutely. '.$requestedContact.' will call you back as soon as possible. What name should we ask for?';
        }

        if (! $call->gathered_phone) {
            return 'What is the best phone number for the callback?';
        }

        if (! $call->gathered_callback_time) {
            return 'What is the best time for '.$requestedContact.' to call you back?';
        }

        return 'I saved your callback request. '.$requestedContact.' will call you back as soon as possible.';
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

        if ($call->callback_requested) {
            return $this->callbackQuestion($call);
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

        if (! $call->gathered_phone && preg_match('/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $speech, $matches)) {
            $updates['gathered_phone'] = trim($matches[0]);
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

    private function isCaregiverApplicationInquiry(string $speech): bool
    {
        $matchesApplicationPhrase = $this->containsAny($speech, [
            'apply for a job',
            'apply for caregiver',
            'caregiver job',
            'caregiver position',
            'caregiver work',
            'caregiving job',
            'become a caregiver',
            'register as a caregiver',
            'caregiver account',
            'job application',
            'looking for a job',
            'looking for work',
            'work as a caregiver',
            'work as caregiver',
            'work for lolo',
            'work with lolo',
            'employment',
        ]);

        return $matchesApplicationPhrase
            || (str_contains($speech, 'apply') && $this->containsAny($speech, ['job', 'work', 'caregiver']));
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
            if (data_get($call->metadata, 'requested_contact') === 'Charles') {
                $parts[] = 'Charles requested';
            }
        }
        if ($call->signup_link_requested) {
            $parts[] = 'Signup link requested';
        }

        return $parts === []
            ? 'No structured details were captured yet.'
            : implode('. ', $parts).'.';
    }
}
