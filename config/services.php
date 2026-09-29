<?php

return [

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1.5'),
    ],

    // Shared secret of the news agent for api/berita-marga/* (pull-based agent).
    'marga_news' => [
        'agent_token' => env('MARGA_NEWS_AGENT_TOKEN'),
    ],

    // Hermes run/poll API called by Laravel. The bearer token must match the
    // Hermes API server's API_SERVER_KEY.
    'hermes' => [
        'base_url' => env('HERMES_BASE_URL'),
        'token' => env('HERMES_TOKEN'),
        'runs_endpoint' => env('HERMES_RUNS_ENDPOINT', '/runs'),
        'timeout' => env('HERMES_TIMEOUT', 90),
        'run_timeout' => env('HERMES_RUN_TIMEOUT', 300),
        'poll_seconds' => env('HERMES_POLL_SECONDS', 2),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        'analytics_measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID'),
        'analytics_report_embed_url' => env('GOOGLE_ANALYTICS_REPORT_EMBED_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
        'mtproto_session_path' => env('TELEGRAM_MTPROTO_SESSION_PATH', storage_path('app/private/telegram/sessions')),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
