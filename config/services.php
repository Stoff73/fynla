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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN', ''),
        'secret' => env('MAILGUN_SECRET', ''),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN', ''),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID', ''),
        'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'chat_model_pro' => env('OPENAI_CHAT_MODEL_PRO', 'gpt-5-mini-2025-08-07'),
        'chat_model_standard' => env('OPENAI_CHAT_MODEL_STANDARD', 'gpt-5-mini-2025-08-07'),
    ],

    'getaddress' => [
        'api_key' => env('GETADDRESS_API_KEY', ''),
    ],

    'revolut' => [
        'api_key' => env('REVOLUT_API_KEY', ''),
        'public_key' => env('REVOLUT_PUBLIC_KEY', ''),
        'webhook_secret' => env('REVOLUT_WEBHOOK_SECRET', ''),
        'sandbox' => env('REVOLUT_SANDBOX', true),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

];
