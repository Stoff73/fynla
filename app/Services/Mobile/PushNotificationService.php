<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const GENERIC_TITLE = 'Fynla';

    private const GENERIC_BODY = 'Open Fynla to review an update.';

    private const ALLOWED_ROUTES = [
        'dashboard',
        'achievements',
        'income',
        'expenditure',
        'net_worth',
        'protection',
        'savings',
        'investment',
        'retirement',
        'estate',
        'goals',
        'tax_strategy',
        'holistic_plan',
        'settings',
    ];

    public function __construct(private readonly ApnsClient $apnsClient) {}

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
        $devices = DeviceToken::forUser($userId)
            ->get(['device_token', 'platform']);
        $payload = $this->privacySafePayload($data);

        foreach ($devices as $device) {
            if ($device->platform === 'ios') {
                $this->apnsClient->send($device->device_token, $payload);

                continue;
            }

            $this->sendToToken($device->device_token, $title, $body, $data);
        }
    }

    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');
        $payload = $this->privacySafePayload($data);

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
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                ],
                'data' => ['route' => $payload['route']],
            ]);

            if ($response->failed()) {
                $this->handleFailedResponse($response, $deviceToken);
            }
        } catch (\Exception $e) {
            Log::error('FCM send failed', [
                'exception_class' => $e::class,
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

    private function privacySafePayload(array $data): array
    {
        $requestedRoute = $data['route'] ?? null;
        $route = is_string($requestedRoute) && in_array($requestedRoute, self::ALLOWED_ROUTES, true)
            ? $requestedRoute
            : 'dashboard';

        return [
            'title' => self::GENERIC_TITLE,
            'body' => self::GENERIC_BODY,
            'route' => $route,
        ];
    }
}
