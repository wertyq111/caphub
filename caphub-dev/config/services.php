<?php

return [

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

    'openclaw' => [
        'base_url' => env('OPENCLAW_BASE_URL'),
        'api_key' => env('OPENCLAW_API_KEY'),
        'translation_agent' => env('OPENCLAW_TRANSLATION_AGENT', 'chemical-news-translator'),
        'timeout' => (int) env('OPENCLAW_TIMEOUT', 45),
        'retry_times' => (int) env('OPENCLAW_RETRY_TIMES', 1),
        'html_parallelism' => (int) env('OPENCLAW_HTML_PARALLELISM', 2),
    ],

    'hermes' => [
        'base_url' => env('HERMES_BASE_URL'),
        'api_key' => env('HERMES_API_KEY'),
        'profile' => env('HERMES_PROFILE', 'chemical-news-translator'),
        'model' => env('HERMES_MODEL', 'gpt-5-mini'),
        'timeout' => (int) env('HERMES_TIMEOUT', 120),
        'html_parallelism' => (int) env('HERMES_HTML_PARALLELISM', 2),
        'chat_base_url' => env('HERMES_CHAT_BASE_URL', env('HERMES_BASE_URL')),
        'chat_api_key' => env('HERMES_CHAT_API_KEY', env('HERMES_API_KEY')),
        'chat_profile' => env('HERMES_CHAT_PROFILE', 'caphub-assistant'),
    ],

    'github_models' => [
        'base_url' => env('GITHUB_MODELS_BASE_URL', 'https://api.githubcopilot.com'),
        'api_key' => env('GITHUB_COPILOT_API_KEY'),
        'model' => env('GITHUB_MODELS_MODEL', 'gpt-4o'),
        'timeout' => (int) env('GITHUB_MODELS_TIMEOUT', 120),
        'retry_times' => (int) env('GITHUB_MODELS_RETRY_TIMES', 2),
        'html_parallelism' => (int) env('GITHUB_MODELS_HTML_PARALLELISM', 1),
        'html_segment_batch_text_limit' => (int) env('GITHUB_MODELS_HTML_SEGMENT_BATCH_TEXT_LIMIT', 900),
        'html_max_batch_segments' => (int) env('GITHUB_MODELS_HTML_MAX_BATCH_SEGMENTS', 6),
        'html_retry_batch_text_limit' => (int) env('GITHUB_MODELS_HTML_RETRY_BATCH_TEXT_LIMIT', 450),
        'html_retry_max_batch_segments' => (int) env('GITHUB_MODELS_HTML_RETRY_MAX_BATCH_SEGMENTS', 3),
    ],

];
