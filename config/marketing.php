<?php

return [
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
