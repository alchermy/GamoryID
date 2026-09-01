<?php

return [

    'discord' => [
        'application_id' => env('DISCORD_APPLICATION_ID'),
        'public_key' => env('DISCORD_PUBLIC_KEY'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'test_bypass' => env('DISCORD_TEST_BYPASS', false),
    ],

    'slipok' => [
        'endpoint' => env('SLIPOK_ENDPOINT', 'https://api.slipok.com'),
        'api_key' => env('SLIPOK_API_KEY'),
        'branch_id' => env('SLIPOK_BRANCH_ID'),
        'receiver_account' => env('SLIPOK_RECEIVER_ACCOUNT'),
        'test_bypass' => env('SLIPOK_TEST_BYPASS', false),
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

];
