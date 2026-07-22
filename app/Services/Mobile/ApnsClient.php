<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ApnsClient
{
    private const STALE_TOKEN_REASONS = [
        'BadDeviceToken',
        'DeviceTokenNotForTopic',
        'Unregistered',
    ];

    public function send(string $deviceToken, array $payload): void
    {
        if (! preg_match('/\A[0-9a-f]{6,500}\z/', $deviceToken)) {
            Log::warning('APNs device token is invalid');

            return;
        }

        try {
            $bundleId = $this->requiredConfig('bundle_id');
            $response = Http::withToken($this->providerToken(), 'bearer')
                ->withHeaders([
                    'apns-topic' => $bundleId,
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                ])
                ->withOptions(['version' => 2.0])
                ->post($this->baseUrl().'/3/device/'.$deviceToken, [
                    'aps' => [
                        'alert' => [
                            'title' => $payload['title'],
                            'body' => $payload['body'],
                        ],
                        'sound' => 'default',
                    ],
                    'route' => $payload['route'],
                ]);

            if ($response->successful()) {
                return;
            }

            $reason = (string) ($response->json('reason') ?? 'unknown');
            if ($response->status() === 410 || in_array($reason, self::STALE_TOKEN_REASONS, true)) {
                DeviceToken::where('device_token', $deviceToken)->delete();
            }

            Log::warning('APNs send failed', [
                'status' => $response->status(),
                'reason' => $reason,
            ]);
        } catch (Throwable $exception) {
            Log::error('APNs send failed', [
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function providerToken(): string
    {
        $teamId = $this->requiredConfig('team_id');
        $keyId = $this->requiredConfig('key_id');
        $privateKey = str_replace('\\n', "\n", $this->requiredConfig('private_key'));
        $cacheKey = 'apns-provider-token:'.hash('sha256', $teamId.$keyId.$privateKey);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($teamId, $keyId, $privateKey): string {
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'ES256',
                'kid' => $keyId,
            ], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $teamId,
                'iat' => now()->timestamp,
            ], JSON_THROW_ON_ERROR));
            $unsignedToken = $header.'.'.$claims;
            $key = openssl_pkey_get_private($privateKey);

            if ($key === false) {
                throw new RuntimeException('APNs private key is invalid');
            }

            if (! openssl_sign($unsignedToken, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('APNs provider token signing failed');
            }

            return $unsignedToken.'.'.$this->base64UrlEncode($this->derToJose($derSignature));
        });
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.apns.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('APNs configuration is incomplete');
        }

        return $value;
    }

    private function baseUrl(): string
    {
        return config('services.apns.environment', 'sandbox') === 'production'
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function derToJose(string $signature): string
    {
        $offset = 0;
        if ($this->readByte($signature, $offset) !== 0x30) {
            throw new RuntimeException('APNs signature is not a DER sequence');
        }

        $this->readLength($signature, $offset);
        $r = $this->readInteger($signature, $offset);
        $s = $this->readInteger($signature, $offset);

        return $this->normalizeCoordinate($r).$this->normalizeCoordinate($s);
    }

    private function readInteger(string $value, int &$offset): string
    {
        if ($this->readByte($value, $offset) !== 0x02) {
            throw new RuntimeException('APNs signature integer is invalid');
        }

        $length = $this->readLength($value, $offset);
        $integer = substr($value, $offset, $length);
        if (strlen($integer) !== $length) {
            throw new RuntimeException('APNs signature is truncated');
        }

        $offset += $length;

        return $integer;
    }

    private function readLength(string $value, int &$offset): int
    {
        $length = $this->readByte($value, $offset);
        if (($length & 0x80) === 0) {
            return $length;
        }

        $byteCount = $length & 0x7F;
        if ($byteCount < 1 || $byteCount > 4) {
            throw new RuntimeException('APNs signature length is invalid');
        }

        $length = 0;
        for ($index = 0; $index < $byteCount; $index++) {
            $length = ($length << 8) | $this->readByte($value, $offset);
        }

        return $length;
    }

    private function readByte(string $value, int &$offset): int
    {
        if (! isset($value[$offset])) {
            throw new RuntimeException('APNs signature is truncated');
        }

        return ord($value[$offset++]);
    }

    private function normalizeCoordinate(string $coordinate): string
    {
        $coordinate = ltrim($coordinate, "\0");
        if (strlen($coordinate) > 32) {
            throw new RuntimeException('APNs signature coordinate is invalid');
        }

        return str_pad($coordinate, 32, "\0", STR_PAD_LEFT);
    }
}
