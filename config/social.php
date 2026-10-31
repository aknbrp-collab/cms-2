<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social Media Publishing
    |--------------------------------------------------------------------------
    |
    | driver: "live" uses the official APIs (Meta Graph API, TikTok Content
    |         Posting API) for providers that have credentials. "fake" makes
    |         every provider pretend to connect and publish (development, demos).
    |
    | Meta (Facebook Pages + Instagram Business) uses one Meta app. TikTok
    | needs its own app with Login Kit and Content Posting API.
    | Review checklist: docs/social-review.md
    |
    */

    'driver' => env('SOCIAL_DRIVER', 'fake'),

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        // Only what publishing needs (see docs/social-review.md).
        'scopes' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'business_management',
            'instagram_basic',
            'instagram_content_publish',
        ],
    ],

    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'scopes' => ['user.info.basic', 'video.publish'],
    ],

    /*
    | Caption limits per platform (characters / hashtags).
    */

    'limits' => [
        'facebook' => ['caption' => 63206, 'hashtags' => null],
        'instagram' => ['caption' => 2200, 'hashtags' => 30],
        'tiktok' => ['caption' => 2200, 'hashtags' => null],
    ],

    'timeout' => (int) env('SOCIAL_TIMEOUT', 30),

];
