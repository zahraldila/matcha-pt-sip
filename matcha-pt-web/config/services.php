<?php

return [

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

    'supabase' => [
        'url' => env('SUPABASE_URL', 'https://xkyneehswdqkdgzodwdc.supabase.co'),
        // Public key strictly for browser/client-side usage.
        // MUST NEVER fallback to SUPABASE_SERVICE_ROLE_KEY or backend secrets.
        'public_key' => env('SUPABASE_PUBLIC_KEY', env('SUPABASE_ANON_KEY')),
        // Backward-compatible alias for frontend templates (strictly public/anon key only):
        'key' => env('SUPABASE_PUBLIC_KEY', env('SUPABASE_ANON_KEY')),
        // Backend service key for server-to-server broadcast and storage:
        'service_key' => env('SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_KEY')),
        'bucket' => env('SUPABASE_BUCKET', 'venues'),
        'community_bucket' => env('SUPABASE_COMMUNITY_BUCKET', 'community-logos'),
        'avatar_bucket' => env('SUPABASE_AVATAR_BUCKET', 'avatars'),
    ],

];
