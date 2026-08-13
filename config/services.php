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

    'mikrotik' => [
        'enabled' => env('MIKROTIK_ENABLED', env('APP_ENV', 'production') === 'production'),
        'host' => env('MIKROTIK_HOST'),
        'user' => env('MIKROTIK_USER'),
        'pass' => env('MIKROTIK_PASS'),
        'port' => (int) env('MIKROTIK_PORT', 8728),
        'connect_timeout' => (int) env('MIKROTIK_CONNECT_TIMEOUT', 2),
        'socket_timeout' => (int) env('MIKROTIK_SOCKET_TIMEOUT', 2),
        'attempts' => (int) env('MIKROTIK_ATTEMPTS', 1),
    ],

    'selcom' => [
        'base_url' => env('SELCOM_BASE_URL'),
        'api_secret' => env('SELCOM_API_SECRET'),
        'api_key' => env('SELCOM_API_KEY'),
        'vendor_till' => env('SELCOM_VENDOR_TILL'),
        'verify_webhook' => env('SELCOM_VERIFY_WEBHOOK', false),
    ],

    'azampay' => [
        'app_name' => env('AZAMPAY_APP_NAME'),
        'client_id' => env('AZAMPAY_CLIENT_ID'),
        'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
        'base_url' => env('AZAMPAY_BASE_URL', 'https://checkout.azampay.co.tz'),
        'auth_url' => env('AZAMPAY_AUTH_URL', 'https://authenticator.azampay.co.tz'),
        'ca_bundle' => env('AZAMPAY_CA_BUNDLE'),
        'mno_checkout_path' => env('AZAMPAY_MNO_CHECKOUT_PATH', '/azampay/mno/checkout'),
    ],

];
