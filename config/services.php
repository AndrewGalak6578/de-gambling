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

    'payment_provider' => [
        'name' => env('PAYMENT_PROVIDER_NAME', 'settlane'),
        'base_url' => env('PAYMENT_PROVIDER_BASE_URL', 'https://settlane.tech/api/v1'),
        'api_key' => env('PAYMENT_PROVIDER_API_KEY'),
        'api_key_header' => env('PAYMENT_PROVIDER_API_KEY_HEADER', 'Authorization'),
        'api_key_prefix' => env('PAYMENT_PROVIDER_API_KEY_PREFIX', 'Bearer '),
        'deposit_invoice_path' => env('PAYMENT_PROVIDER_DEPOSIT_INVOICE_PATH', '/invoices'),
        'timeout' => env('PAYMENT_PROVIDER_TIMEOUT', 10),
        'webhook_secret' => env('PAYMENT_PROVIDER_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('PAYMENT_PROVIDER_WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),
    ],

];
