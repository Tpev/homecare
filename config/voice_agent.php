<?php

return [
    'internal_api_token' => env('VOICE_AGENT_INTERNAL_API_TOKEN'),
    'brand_name' => 'LoLo Care',
    'service_summary' => env(
        'VOICE_AGENT_SERVICE_SUMMARY',
        'LoLo Care helps families understand their options and take the next step toward arranging non-medical in-home support for a loved one.'
    ),
    'service_details' => array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode('|', (string) env(
            'VOICE_AGENT_SERVICE_DETAILS',
            'Families can call to understand how LoLo Care works, what the next step is, and how to get support for a loved one.|If the family is ready to move forward, the voice agent can text the family signup link.|If the situation is complex or the caller asks for a person or Charles, the voice agent collects their callback information for a prompt follow-up.|People interested in caregiver work should create a caregiver account on the LoLo Care website.'
        ))
    ))),
    'capabilities' => [
        'Answer approved family questions about the service and next steps.',
        'Offer a human callback when the family needs personal follow-up.',
        'Send the family signup link by SMS after caller consent.',
        'Direct caregiver job applicants to create a caregiver account on the LoLo Care website.',
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
            'question' => 'What does LoLo Care do?',
            'answer' => 'LoLo Care helps families understand their care options and take the next step toward arranging non-medical in-home support for a loved one.',
            'keywords' => ['what do you do', 'service', 'home care', 'support', 'what is lolo', 'what is lolo care'],
        ],
        [
            'question' => 'What happens next if I am interested?',
            'answer' => 'If you are ready, the voice agent can text you the family signup link now. If you would rather talk to someone, it can collect your callback details for a human follow-up.',
            'keywords' => ['next step', 'interested', 'what happens next', 'how do i get started', 'get started'],
        ],
        [
            'question' => 'Can I speak to a human or Charles?',
            'answer' => 'Yes. The voice agent will collect your name, best callback number, and reason for calling. Someone from the LoLo Care team, or Charles if requested, will call back as soon as possible.',
            'keywords' => ['human', 'person', 'charles', 'callback', 'call me back', 'talk to someone'],
        ],
        [
            'question' => 'Can you text me the signup link?',
            'answer' => 'Yes. If you consent, the voice agent can text you the family signup link during the call.',
            'keywords' => ['text me', 'signup link', 'sms', 'register', 'send me the link'],
        ],
        [
            'question' => 'How do I apply for a caregiver job?',
            'answer' => 'Visit the LoLo Care website, choose Become a Caregiver, and create a caregiver account to apply for caregiver opportunities.',
            'keywords' => ['caregiver job', 'apply for a job', 'job application', 'work for lolo care', 'work with lolo care', 'become a caregiver', 'caregiver position', 'employment'],
        ],
        [
            'question' => 'What information should I be ready to share?',
            'answer' => 'It helps to know who needs care, the kind of support they may need, how urgent the situation is, and the city or zip code.',
            'keywords' => ['what information', 'what do i need', 'zip code', 'urgency', 'what should i share'],
        ],
        [
            'question' => 'How much does it cost?',
            'answer' => 'For short-term care, the rate is 30 dollars per hour. Longer-term support is priced lower than short-term care.',
            'keywords' => ['price', 'pricing', 'cost', 'how much', 'hourly rate', '30 dollars'],
        ],
    ],
];
