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

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-haiku-4-5-20251001'),
        'advanced_chat_model' => env('ANTHROPIC_ADVANCED_CHAT_MODEL', 'claude-sonnet-4-6-20260320'),
    ],

    'xai' => [
        'api_key' => env('XAI_API_KEY', ''),
        'chat_model' => env('XAI_CHAT_MODEL', 'grok-4.3'),
        'advanced_chat_model' => env('XAI_ADVANCED_CHAT_MODEL', 'grok-4.3'),
        // Cheaper tier used when a user's rolling weekly budget is exceeded
        // (HasAiGuardrails soft-degrade). Defaults to the standard chat model so
        // chat stays OPEN on xAI; set to a cheaper xAI model to actually degrade.
        'degrade_chat_model' => env('XAI_DEGRADE_CHAT_MODEL'),
        'vision_model' => env('XAI_VISION_MODEL', 'grok-4.3'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
    ],

    // Active AI provider: 'anthropic' or 'xai'
    // Runtime override via admin panel stored in cache; falls back to .env
    'ai_provider' => env('AI_PROVIDER', 'anthropic'),

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

    'apns' => [
        'team_id' => env('APNS_TEAM_ID'),
        'key_id' => env('APNS_KEY_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID'),
        'private_key' => env('APNS_PRIVATE_KEY'),
        'environment' => env('APNS_ENVIRONMENT', 'sandbox'),
    ],

    // GitHub issue creation for in-app bug reports. The token needs only
    // Issues: write on the target repo, lives on the server .env, and is
    // disabled by default so nothing fires until provisioned.
    'github' => [
        'token' => env('GITHUB_BUG_ISSUE_TOKEN'),
        'repo' => env('GITHUB_BUG_ISSUE_REPO', 'Stoff73/fynla'),
        'enabled' => env('GITHUB_BUG_ISSUE_ENABLED', false),
        'labels' => ['bug', 'from-mobile', 'claude-auto'],
    ],

];
