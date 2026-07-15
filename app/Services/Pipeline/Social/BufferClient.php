<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Buffer's Publishing API v1
 * (https://buffer.com/developers/api).
 *
 * Chosen over direct Instagram/Facebook/TikTok integration because:
 *   - one OAuth flow (Buffer's) covers all connected platforms
 *   - Buffer handles Meta's chunked-upload rules and TikTok's Content
 *     Posting flow for us
 *   - retrieving per-post metrics is one endpoint, not three
 *
 * If we ever swap to Postiz or direct APIs, this class is the only
 * boundary that changes.
 */
class BufferClient
{
    private const API_ROOT = 'https://api.bufferapp.com/1';

    public function schedule(string $platform, string $text, string $mediaPath, ?\DateTimeInterface $at = null): array
    {
        $profileId = $this->profileId($platform);

        $payload = [
            'text' => $text,
            'profile_ids[]' => $profileId,
            'shorten' => 'false',
            'media[link]' => 'https://fynla.org',
        ];

        if ($at !== null) {
            $payload['scheduled_at'] = $at->getTimestamp();
        } else {
            $payload['now'] = 'true';
        }

        $response = $this->client()
            ->asMultipart()
            ->attach('media[video]', fopen($mediaPath, 'rb'), basename($mediaPath))
            ->post(self::API_ROOT.'/updates/create.json', $payload);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Buffer schedule failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'platform' => $platform,
            ]);
            throw new RuntimeException('Buffer schedule failed: HTTP '.$response->status().' — '.$response->body());
        }

        return $response->json();
    }

    /**
     * Fetch metrics for a previously-scheduled update.
     *
     * @return array<string,mixed>
     */
    public function fetchUpdateMetrics(string $updateId): array
    {
        $response = $this->client()->get(self::API_ROOT.'/updates/'.$updateId.'/interactions.json');

        if (! $response->successful()) {
            Log::channel('pipeline')->warning('Buffer fetchUpdateMetrics failed.', [
                'status' => $response->status(),
                'update_id' => $updateId,
            ]);

            return [];
        }

        return $response->json('interactions') ?? [];
    }

    /**
     * List connected profiles. Used by an admin diagnostic to grab the
     * BUFFER_PROFILE_* env values on first setup.
     */
    public function listProfiles(): array
    {
        $response = $this->client()->get(self::API_ROOT.'/profiles.json');

        if (! $response->successful()) {
            throw new RuntimeException('Buffer listProfiles failed: HTTP '.$response->status());
        }

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->timeout((int) config('pipeline.buffer.timeout_seconds', 60))
            ->retry(2, 1000);
    }

    private function accessToken(): string
    {
        $token = config('pipeline.buffer.access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('BUFFER_ACCESS_TOKEN is not set.');
        }

        return $token;
    }

    private function profileId(string $platform): string
    {
        $id = config('pipeline.buffer.profiles.'.$platform);
        if (! is_string($id) || $id === '') {
            throw new RuntimeException("BUFFER_PROFILE_".strtoupper($platform)." is not set.");
        }

        return $id;
    }
}
