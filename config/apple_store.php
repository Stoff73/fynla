<?php

$configuredAppAppleId = env('APPLE_STORE_APP_APPLE_ID');
$appAppleId = null;

if ($configuredAppAppleId !== null && $configuredAppAppleId !== '') {
    $validatedAppAppleId = filter_var(
        $configuredAppAppleId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    );
    $appAppleId = $validatedAppAppleId === false
        ? $configuredAppAppleId
        : $validatedAppAppleId;
}

return [
    // The app testers and the App Store get is the org.fynla.app.dev record,
    // named "Fynla" (CSJ 2026-09-07: one app), so production sets
    // APPLE_STORE_BUNDLE_ID=org.fynla.app.dev; org.fynla.app is the legacy record.
    'bundle_id' => env('APPLE_STORE_BUNDLE_ID', 'org.fynla.app'),
    'allowed_product_ids' => [
        'org.fynla.premium.monthly',
        'org.fynla.premium.annual',
    ],
    'environment' => env('APPLE_STORE_ENVIRONMENT', 'sandbox'),
    'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
    'app_apple_id' => $appAppleId,
    'online_checks' => true,

    'python_executable' => env(
        'APPLE_STORE_PYTHON_EXECUTABLE',
        base_path('.venv/apple-store/bin/python'),
    ),
    'bridge_cli_path' => base_path('services/apple_store_bridge/cli.py'),
    'process_timeout_seconds' => 40.0,
    'max_request_bytes' => 256 * 1024,
    'max_response_bytes' => 1024 * 1024,

    'key_id' => env('APPLE_STORE_KEY_ID') ?: null,
    'issuer_id' => env('APPLE_STORE_ISSUER_ID') ?: null,
    'private_key_path' => env('APPLE_STORE_PRIVATE_KEY_PATH') ?: null,
];
