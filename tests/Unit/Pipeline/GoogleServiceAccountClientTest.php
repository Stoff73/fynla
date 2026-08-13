<?php

declare(strict_types=1);

use App\Services\Pipeline\Google\GoogleServiceAccountClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    putenv('RANDFILE='.sys_get_temp_dir().'/fynla-openssl-random-state');

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $privateKey);
    $this->publicKey = openssl_pkey_get_details($key)['key'];
    $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fynla-google-');

    file_put_contents($this->credentialsPath, json_encode([
        'type' => 'service_account',
        'project_id' => 'fynla-marketing-test',
        'private_key_id' => 'test-key-id',
        'private_key' => $privateKey,
        'client_email' => 'pipeline@fynla-marketing-test.iam.gserviceaccount.com',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR));

    Config::set('pipeline.google.service_account_credentials', $this->credentialsPath);
});

afterEach(function () {
    @unlink($this->credentialsPath);
});

it('exchanges a signed service-account assertion for an access token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'service-account-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    expect((new GoogleServiceAccountClient)->accessToken())
        ->toBe('service-account-access-token');

    Http::assertSent(function (Request $request): bool {
        $assertion = $request->data()['assertion'] ?? '';
        $segments = explode('.', $assertion);
        if (count($segments) !== 3) {
            return false;
        }

        $payload = json_decode(base64UrlDecodeForTest($segments[1]), true, flags: JSON_THROW_ON_ERROR);
        $signature = base64UrlDecodeForTest($segments[2]);

        return $request->url() === 'https://oauth2.googleapis.com/token'
            && $request->data()['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $payload['iss'] === 'pipeline@fynla-marketing-test.iam.gserviceaccount.com'
            && $payload['aud'] === 'https://oauth2.googleapis.com/token'
            && $payload['scope'] === implode(' ', [
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/analytics.readonly',
            ])
            && $payload['exp'] - $payload['iat'] === 3600
            && openssl_verify(
                $segments[0].'.'.$segments[1],
                $signature,
                $this->publicKey,
                OPENSSL_ALGO_SHA256,
            ) === 1;
    });
});

it('reuses the cached access token until shortly before it expires', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'cached-service-account-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    $client = new GoogleServiceAccountClient;

    expect($client->accessToken())->toBe('cached-service-account-token')
        ->and($client->accessToken())->toBe('cached-service-account-token');

    Http::assertSentCount(1);
});

it('fails clearly when the credentials path is not configured', function () {
    Config::set('pipeline.google.service_account_credentials');

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'GOOGLE_SERVICE_ACCOUNT_CREDENTIALS is not set.');

it('fails clearly when the configured credentials file is not readable', function () {
    Config::set('pipeline.google.service_account_credentials', '/path/that/does/not/exist.json');

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google service-account credentials file is not readable: /path/that/does/not/exist.json');

it('fails clearly when the credentials file is not valid JSON', function () {
    file_put_contents($this->credentialsPath, 'not-json');

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google service-account credentials file is not valid JSON.');

it('rejects credentials that are not a service-account key', function () {
    file_put_contents($this->credentialsPath, json_encode([
        'web' => [
            'client_id' => 'interactive-client-id',
            'client_secret' => 'interactive-client-secret',
        ],
    ], JSON_THROW_ON_ERROR));

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google credentials file must contain a service-account key.');

it('rejects an incomplete service-account key', function () {
    $credentials = json_decode(file_get_contents($this->credentialsPath), true, flags: JSON_THROW_ON_ERROR);
    unset($credentials['private_key']);
    file_put_contents($this->credentialsPath, json_encode($credentials, JSON_THROW_ON_ERROR));

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google service-account credentials are missing private_key.');

it('rejects a malformed private key with a clear exception', function () {
    $credentials = json_decode(file_get_contents($this->credentialsPath), true, flags: JSON_THROW_ON_ERROR);
    $credentials['private_key'] = 'not-a-private-key';
    file_put_contents($this->credentialsPath, json_encode($credentials, JSON_THROW_ON_ERROR));

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google service-account private key is not a valid RSA key.');

it('reports the Google token endpoint status without exposing the key', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
        ], 401),
    ]);

    (new GoogleServiceAccountClient)->accessToken();
})->throws(RuntimeException::class, 'Google service-account token request failed: HTTP 401');

function base64UrlDecodeForTest(string $value): string
{
    $padding = (4 - strlen($value) % 4) % 4;

    return (string) base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
}
