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

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-haiku-4-5-20251001'),
        'advanced_chat_model' => env('ANTHROPIC_ADVANCED_CHAT_MODEL', 'claude-sonnet-4-6-20260320'),
        'agent_internal_token' => env('AGENT_INTERNAL_TOKEN', ''),
    ],

    'xai' => [
        'api_key' => env('XAI_API_KEY', ''),
        'chat_model' => env('XAI_CHAT_MODEL', 'grok-4-1-fast-reasoning'),
        'advanced_chat_model' => env('XAI_ADVANCED_CHAT_MODEL', 'grok-4-1-fast-reasoning'),
        'vision_model' => env('XAI_VISION_MODEL', 'grok-4-1-fast-non-reasoning'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
        'agent_internal_token' => env('AGENT_INTERNAL_TOKEN', ''),
    ],

    // Active AI provider: 'anthropic' or 'xai'
    // Runtime override via admin panel stored in cache; falls back to .env
    'ai_provider' => env('AI_PROVIDER', 'anthropic'),
    'ai_provider_runtime' => true, // Flag to check cache at runtime

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
