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

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID', 'rzp_test_onestall_key'),
        'key_secret' => env('RAZORPAY_KEY_SECRET', 'rzp_test_onestall_secret'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', 'rzp_test_webhook_secret'),
    ],

    'cashfree' => [
        'app_id' => env('CASHFREE_APP_ID', 'cf_test_app_id'),
        'secret_key' => env('CASHFREE_SECRET_KEY', 'cf_test_secret_key'),
        'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),
        'environment' => env('CASHFREE_ENVIRONMENT', 'sandbox'),
    ],

    'cargo' => [
        'base_url' => env('CARGO_API_BASE_URL', 'https://api.onestallcargo.internal/v1'),
        'api_key' => env('CARGO_API_KEY', 'osc_test_api_key_sec123'),
        'api_secret' => env('CARGO_API_SECRET', 'osc_test_secret_abc456'),
        'webhook_secret' => env('CARGO_WEBHOOK_SECRET', 'osc_webhook_secret_xyz789'),
        'fake' => env('CARGO_FAKE', true),
        'timeout' => (int) env('CARGO_TIMEOUT', 15),
        'retry_times' => (int) env('CARGO_RETRY_TIMES', 3),
        'retry_sleep' => (int) env('CARGO_RETRY_SLEEP_MS', 100),
    ],

];
