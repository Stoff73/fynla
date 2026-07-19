<?php

declare(strict_types=1);

$defaultEnvironment = env('APP_ENV') === 'production'
    ? 'production'
    : 'staging';

return [
    'environment' => env('NATIVE_POLICY_ENVIRONMENT', $defaultEnvironment),

    'environments' => [
        'staging' => [
            'minimum_version' => env('NATIVE_STAGING_MINIMUM_VERSION', '1.0.0'),
            'minimum_build' => (int) env('NATIVE_STAGING_MINIMUM_BUILD', 1),
        ],
        'production' => [
            'minimum_version' => env('NATIVE_PRODUCTION_MINIMUM_VERSION', '1.0.0'),
            'minimum_build' => (int) env('NATIVE_PRODUCTION_MINIMUM_BUILD', 1),
        ],
    ],

    'storekit_purchase_enabled' => filter_var(
        env('NATIVE_STOREKIT_PURCHASE_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'app_store_url' => env(
        'NATIVE_APP_STORE_URL',
        'https://apps.apple.com/app/fynla',
    ),
];
