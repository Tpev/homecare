<?php

return [

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT_SECONDS', 25),
    ],

    'didit' => [
        'api_key' => env('DIDIT_API_KEY'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'callback_url' => env('DIDIT_CALLBACK_URL'),
        'base_url' => env('DIDIT_BASE_URL', 'https://verification.didit.me'),
        'timeout' => (int) env('DIDIT_TIMEOUT_SECONDS', 20),
        'bypass' => filter_var(env('DIDIT_BYPASS', false), FILTER_VALIDATE_BOOL),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable_key' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => strtolower((string) env('STRIPE_CURRENCY', 'usd')),
        'bypass' => filter_var(env('STRIPE_BYPASS', env('APP_ENV') === 'local'), FILTER_VALIDATE_BOOL),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
        'voice_from' => env('TWILIO_VOICE_FROM', env('TWILIO_PHONE_NUMBER')),
        'sms_from' => env('TWILIO_SMS_FROM', env('TWILIO_PHONE_NUMBER')),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'webhook_base_url' => env('TWILIO_WEBHOOK_BASE_URL'),
        'voice_agent_callback_url' => env('TWILIO_VOICE_AGENT_CALLBACK_URL'),
        'status_callback_url' => env('TWILIO_SMS_STATUS_CALLBACK_URL'),
        'timeout' => (int) env('TWILIO_TIMEOUT_SECONDS', 15),
        'bypass' => filter_var(env('TWILIO_BYPASS', false), FILTER_VALIDATE_BOOL),
    ],

    'google_sheets_leads' => [
        'webhook_secret' => env('GOOGLE_SHEETS_LEADS_WEBHOOK_SECRET'),
    ],

    'maptiler' => [
        'key' => env('MAPTILER_KEY'),
    ],

];
