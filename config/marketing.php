<?php

return [
    'homepage_faqs' => [
        [
            'question' => 'What types of care does LoLo provide?',
            'answer' => 'LoLo connects families with non-medical home support, including companionship, rides, errands, meal preparation, light housekeeping, and respite support.',
        ],
        [
            'question' => 'Is there a minimum visit length?',
            'answer' => 'You can request flexible help starting from a one-hour visit, subject to caregiver availability.',
        ],
        [
            'question' => 'Can I book care for my parent?',
            'answer' => 'Yes. Family members can arrange and coordinate care for an older parent or another loved one.',
        ],
        [
            'question' => 'Can other family members join the account?',
            'answer' => 'Yes. Invite family members so schedules, visit updates, messages, and care history stay in one shared place.',
        ],
        [
            'question' => 'How are caregivers verified?',
            'answer' => 'Caregiver profiles can include identity verification, background screening status, experience, training, certifications, and family reviews.',
        ],
    ],

    'podcast' => [
        'eyebrow' => env('MARKETING_PODCAST_EYEBROW', 'Podcast for family care decisions'),
        'title' => env('MARKETING_PODCAST_TITLE', 'The HomeCare Family Brief'),
        'description' => env(
            'MARKETING_PODCAST_DESCRIPTION',
            'Short episodes that help families navigate care planning, home support, and harder decisions with more clarity.'
        ),
        'episode_title' => env('MARKETING_PODCAST_EPISODE_TITLE'),
        'episode_summary' => env('MARKETING_PODCAST_EPISODE_SUMMARY'),
        'episode_length' => env('MARKETING_PODCAST_EPISODE_LENGTH'),
        'embed_url' => env('MARKETING_PODCAST_EMBED_URL'),
        'audio_url' => env('MARKETING_PODCAST_AUDIO_URL'),
        'spotify_url' => env('MARKETING_PODCAST_SPOTIFY_URL'),
        'apple_url' => env('MARKETING_PODCAST_APPLE_URL'),
        'youtube_url' => env('MARKETING_PODCAST_YOUTUBE_URL'),
        'transcript_url' => env('MARKETING_PODCAST_TRANSCRIPT_URL'),
    ],
];
