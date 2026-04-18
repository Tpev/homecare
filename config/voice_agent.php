<?php

return [
    'internal_api_token' => env('VOICE_AGENT_INTERNAL_API_TOKEN'),
    'brand_name' => env('VOICE_AGENT_BRAND_NAME', env('APP_NAME', 'Homecare')),
    'service_summary' => env(
        'VOICE_AGENT_SERVICE_SUMMARY',
        'Homecare helps families find and coordinate non-medical in-home care support with vetted caregivers.'
    ),
    'service_details' => array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode('|', (string) env(
            'VOICE_AGENT_SERVICE_DETAILS',
            'Families can ask about care options, next steps, and how the service works.|Caregivers can learn how onboarding and profile setup work.|When the caller needs a human, the voice agent can collect a callback request.'
        ))
    ))),
    'capabilities' => [
        'Answer approved questions about the service and signup flow.',
        'Offer a human callback when the request needs a person.',
        'Send a signup link by SMS after caller consent.',
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
            'answer' => 'Homecare helps families connect with in-home care support and move toward the right next step for their situation.',
            'keywords' => ['what do you do', 'service', 'home care', 'support'],
        ],
        [
            'question' => 'Can I speak to a human?',
            'answer' => 'Yes. The voice agent can collect your callback details so a team member can follow up.',
            'keywords' => ['human', 'person', 'callback', 'call me back'],
        ],
        [
            'question' => 'Can you text me a signup link?',
            'answer' => 'Yes. If you consent, the agent can send the right signup link by SMS for families, caregivers, or agencies.',
            'keywords' => ['text me', 'signup link', 'sms', 'register'],
        ],
    ],
];
