<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Creates short-lived Google API access tokens from a service-account key.
 */
class GoogleServiceAccountClient
{
    private const CACHE_KEY_PREFIX = 'pipeline.google.service_account_access_token.';

    /** @var list<string> */
    private const SCOPES = [
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public function accessToken(): string
    {
        $credentials = $this->credentials();
        $privateKey = $this->privateKey($credentials['private_key']);
        $cacheKey = $this->cacheKey($credentials, $privateKey);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUri = $credentials['token_uri'];

        $response = Http::asForm()
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedAssertion($credentials, $privateKey),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google service-account token request failed: HTTP '.$response->status());
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google service-account token response did not contain an access token.');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600));
        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    /**
     * @return array{client_email:string,private_key:string,private_key_id?:string,token_uri:string}
     */
    private function credentials(): array
    {
        $path = config('pipeline.google.service_account_credentials');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS is not set.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Google service-account credentials file is not readable: '.$path);
        }

        try {
            $credentials = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Google service-account credentials file is not valid JSON.', previous: $e);
        }

        if (($credentials['type'] ?? null) !== 'service_account') {
            throw new RuntimeException('Google credentials file must contain a service-account key.');
        }

        foreach (['client_email', 'private_key', 'token_uri'] as $field) {
            if (! isset($credentials[$field]) || ! is_string($credentials[$field]) || $credentials[$field] === '') {
                throw new RuntimeException("Google service-account credentials are missing {$field}.");
            }
        }

        return $credentials;
    }

    /**
     * @param  array{client_email:string,private_key:string,private_key_id?:string,token_uri:string}  $credentials
     */
    private function signedAssertion(array $credentials, OpenSSLAsymmetricKey $privateKey): string
    {
        $issuedAt = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        if (isset($credentials['private_key_id']) && is_string($credentials['private_key_id'])) {
            $header['kid'] = $credentials['private_key_id'];
        }

        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => implode(' ', self::SCOPES),
            'aud' => $credentials['token_uri'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $unsigned = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Google service-account assertion.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function privateKey(string $value): OpenSSLAsymmetricKey
    {
        $privateKey = @openssl_pkey_get_private($value);
        $keyDetails = $privateKey === false ? false : openssl_pkey_get_details($privateKey);
        if ($privateKey === false || $keyDetails === false || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('Google service-account private key is not a valid RSA key.');
        }

        return $privateKey;
    }

    /**
     * @param  array{client_email:string,private_key:string,private_key_id?:string,token_uri:string}  $credentials
     */
    private function cacheKey(array $credentials, OpenSSLAsymmetricKey $privateKey): string
    {
        $keyDetails = openssl_pkey_get_details($privateKey);
        if ($keyDetails === false) {
            throw new RuntimeException('Google service-account private key is not a valid RSA key.');
        }

        $identity = implode("\0", [
            $credentials['client_email'],
            $credentials['token_uri'],
            $credentials['private_key_id'] ?? '',
            $keyDetails['key'],
        ]);

        return self::CACHE_KEY_PREFIX.hash('sha256', $identity);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
