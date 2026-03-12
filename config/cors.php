<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Restrict to actual HTTP methods used by the API
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Use environment variables for allowed origins (no wildcards in production)
    'allowed_origins' => array_filter(array_unique(array_merge(
        explode(',', env('ALLOWED_ORIGINS', '')),
        [
            env('FRONTEND_URL', ''),
            env('APP_URL', ''),
            'capacitor://localhost',
            'http://localhost',
        ]
    )), fn ($origin) => ! empty($origin) && (env('APP_ENV') === 'local' || ! str_contains($origin, '*'))),

    'allowed_origins_patterns' => [],

    // Restrict to headers actually used by the application
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
    ],

    // Expose rate limit headers so frontend can handle them
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    // Cache preflight requests for 1 hour (3600 seconds)
    'max_age' => 3600,

    'supports_credentials' => true,

];
