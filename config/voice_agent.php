<?php

return [
    'internal_api_token' => env('VOICE_AGENT_INTERNAL_API_TOKEN'),
    'brand_name' => env('VOICE_AGENT_BRAND_NAME', env('APP_NAME', 'Homecare')),
    'service_summary' => env(
        'VOICE_AGENT_SERVICE_SUMMARY',
        'Homecare helps families understand their options and take the next step toward arranging in-home support for a loved one.'
    ),
    'service_details' => array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode('|', (string) env(
            'VOICE_AGENT_SERVICE_DETAILS',
            'Families can call to understand how the service works, what the next step is, and how to get support for a loved one.|If the family is ready to move forward, the voice agent can text the family signup link.|If the situation is complex or the caller prefers a person, the voice agent can collect a callback request for a human follow-up.'
        ))
    ))),
    'capabilities' => [
        'Answer approved family questions about the service and next steps.',
        'Offer a human callback when the family needs personal follow-up.',
        'Send the family signup link by SMS after caller consent.',
    ],
    'ops_hours' => env('VOICE_AGENT_OPS_HOURS', 'Monday to Friday, 9am to 6pm local time'),
    'signup_links' => [
        'family' => env('VOICE_AGENT_SIGNUP_LINK_FAMILY'),
        'caregiver' => env('VOICE_AGENT_SIGNUP_LINK_CAREGIVER'),
        'agency' => env('VOICE_AGENT_SIGNUP_LINK_AGENCY'),
        'general' => env('VOICE_AGENT_SIGNUP_LINK_GENERAL'),
    ],
    'faqs' => [
        [
            'question' => 'What does Homecare do?',
            'answer' => 'Homecare helps families understand their care options and take the next step toward arranging in-home support for a loved one.',
            'keywords' => ['what do you do', 'service', 'home care', 'support', 'what is homecare'],
        ],
        [
            'question' => 'What happens next if I am interested?',
            'answer' => 'If you are ready, the voice agent can text you the family signup link now. If you would rather talk to someone, it can collect your callback details for a human follow-up.',
            'keywords' => ['next step', 'interested', 'what happens next', 'how do i get started', 'get started'],
        ],
        [
            'question' => 'Can I speak to a human?',
            'answer' => 'Yes. The voice agent can collect your callback details so a team member can follow up.',
            'keywords' => ['human', 'person', 'callback', 'call me back', 'talk to someone'],
        ],
        [
            'question' => 'Can you text me the signup link?',
            'answer' => 'Yes. If you consent, the voice agent can text you the family signup link during the call.',
            'keywords' => ['text me', 'signup link', 'sms', 'register', 'send me the link'],
        ],
        [
            'question' => 'What information should I be ready to share?',
            'answer' => 'It helps to know who needs care, the kind of support they may need, how urgent the situation is, and the city or zip code.',
            'keywords' => ['what information', 'what do i need', 'zip code', 'urgency', 'what should i share'],
        ],
    ],
];
