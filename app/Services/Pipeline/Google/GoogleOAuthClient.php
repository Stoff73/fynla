<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use App\Models\Pipeline\OAuthCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OAuth 2.0 client for Google APIs.
 *
 * Flow (one-time, triggered by `pipeline:authorise-google`):
 *   1. issueAuthorisationUrl() — command prints URL, user visits in browser
 *   2. User clicks Allow, Google redirects to /pipeline/oauth/google/callback?code=...
 *   3. exchangeCode() — controller exchanges code for access + refresh tokens
 *   4. Refresh token stored (encrypted) in pipeline_oauth_credentials.
 *
 * Runtime (every pipeline job):
 *   - accessToken() — returns a valid access token, refreshing via the stored
 *     refresh token if needed. Callers use it as a Bearer token.
 *
 * No dependency on google/apiclient — all calls are plain HTTPS via
 * Illuminate\Http.
 */
class GoogleOAuthClient
{
    public const PROVIDER = 'google';

    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    /**
     * Google API scopes we request. Drive scope covers file create + share;
     * Sheets scope covers row read/write. Both are the narrow, file-scoped
     * variants — the app never sees files the user hasn't handed us.
     *
     * @var list<string>
     */
    public const SCOPES = [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/analytics.readonly',
        'openid',
        'email',
    ];

    public function issueAuthorisationUrl(string $state): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ]);
    }

    public function exchangeCode(string $code): OAuthCredential
    {
        $response = Http::asForm()
            ->timeout(30)
            ->retry(2, 500)
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Google token exchange failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to exchange OAuth code with Google: '.$response->status());
        }

        $payload = $response->json();
        $refreshToken = $payload['refresh_token'] ?? null;

        if ($refreshToken === null) {
            throw new RuntimeException(
                'Google did not return a refresh token. Revoke the app at '
                .'myaccount.google.com/permissions and re-run the authorisation '
                .'command with prompt=consent.'
            );
        }

        $email = $this->fetchUserEmail($payload['access_token']);

        return OAuthCredential::updateOrCreate(
            ['provider' => self::PROVIDER],
            [
                'account_email' => $email,
                'access_token' => $payload['access_token'],
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3600)),
                'scopes' => explode(' ', $payload['scope'] ?? implode(' ', self::SCOPES)),
            ]
        );
    }

    /**
     * Return a fresh access token, refreshing via the stored refresh token if
     * the current access token is expired (or about to be).
     */
    public function accessToken(): string
    {
        $credential = $this->credential();

        if (! $credential->isExpired() && $credential->access_token) {
            return $credential->access_token;
        }

        $response = Http::asForm()
            ->timeout(30)
            ->retry(2, 500)
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $credential->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Google token refresh failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to refresh Google OAuth token: '.$response->status());
        }

        $payload = $response->json();

        $credential->update([
            'access_token' => $payload['access_token'],
            'expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3600)),
        ]);

        return $payload['access_token'];
    }

    public function credential(): OAuthCredential
    {
        $credential = OAuthCredential::where('provider', self::PROVIDER)->first();

        if ($credential === null) {
            throw new RuntimeException(
                'No Google OAuth credential stored. Run `php artisan pipeline:authorise-google` first.'
            );
        }

        return $credential;
    }

    private function fetchUserEmail(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get(self::USERINFO_ENDPOINT);

        return $response->successful() ? ($response->json('email') ?? null) : null;
    }

    private function clientId(): string
    {
        $value = config('pipeline.google.oauth_client_id');
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('GOOGLE_OAUTH_CLIENT_ID is not set.');
        }

        return $value;
    }

    private function clientSecret(): string
    {
        $value = config('pipeline.google.oauth_client_secret');
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('GOOGLE_OAUTH_CLIENT_SECRET is not set.');
        }

        return $value;
    }

    private function redirectUri(): string
    {
        return config('pipeline.google.oauth_redirect_uri', url('/pipeline/oauth/google/callback'));
    }
}
