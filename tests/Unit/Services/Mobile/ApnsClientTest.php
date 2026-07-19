<?php

declare(strict_types=1);

use App\Services\Mobile\ApnsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends an authenticated APNs alert without exposing financial detail', function () {
    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    expect($resource)->not->toBeFalse();
    openssl_pkey_export($resource, $privateKey);
    config([
        'services.apns.team_id' => 'TEAM123456',
        'services.apns.key_id' => 'KEY1234567',
        'services.apns.bundle_id' => 'org.fynla.app.dev',
        'services.apns.private_key' => $privateKey,
        'services.apns.environment' => 'sandbox',
    ]);
    Http::fake([
        'https://api.sandbox.push.apple.com/*' => Http::response([], 200),
    ]);

    app(ApnsClient::class)->send('00abff', [
        'title' => 'Fynla',
        'body' => 'Open Fynla to review an update.',
        'route' => 'income',
    ]);

    Http::assertSent(function (Request $request): bool {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://api.sandbox.push.apple.com/3/device/00abff'
            && str_starts_with($authorization, 'bearer ')
            && substr_count(substr($authorization, 7), '.') === 2
            && ($request->header('apns-topic')[0] ?? null) === 'org.fynla.app.dev'
            && ($request->header('apns-push-type')[0] ?? null) === 'alert'
            && $request['aps']['alert'] === [
                'title' => 'Fynla',
                'body' => 'Open Fynla to review an update.',
            ]
            && $request['route'] === 'income'
            && count($request->data()) === 2;
    });
});
