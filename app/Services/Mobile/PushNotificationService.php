<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function shouldSend(int $userId, string $preferenceKey): bool
    {
        $hasDevices = DeviceToken::forUser($userId)->exists();
        if (! $hasDevices) {
            return false;
        }

        $prefs = NotificationPreference::getOrCreateForUser($userId);

        return (bool) ($prefs->{$preferenceKey} ?? false);
    }

    public function getDeviceTokens(int $userId): array
    {
        return DeviceToken::forUser($userId)
            ->pluck('device_token')
            ->toArray();
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = $this->getDeviceTokens($userId);

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');

        if (! $serverKey) {
            Log::warning('FCM server key not configured, skipping push notification');

            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]);

            if ($response->failed()) {
                $this->handleFailedResponse($response, $deviceToken);
            }
        } catch (\Exception $e) {
            Log::error('FCM send failed', [
                'error' => $e->getMessage(),
                'device_token_prefix' => substr($deviceToken, 0, 10).'...',
            ]);
        }
    }

    public function removeStaleToken(string $deviceToken): void
    {
        DeviceToken::where('device_token', $deviceToken)->delete();
    }

    private function handleFailedResponse($response, string $deviceToken): void
    {
        $body = $response->json();
        $error = $body['results'][0]['error'] ?? $body['error'] ?? 'unknown';

        if (in_array($error, ['NotRegistered', 'InvalidRegistration'])) {
            $this->removeStaleToken($deviceToken);
            Log::info('Removed stale FCM token', ['error' => $error]);
        } else {
            Log::warning('FCM send error', ['error' => $error]);
        }
    }
}
