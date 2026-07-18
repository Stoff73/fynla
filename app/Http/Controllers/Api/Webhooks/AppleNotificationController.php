<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Exceptions\Billing\AppleVerificationException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAppleNotification;
use App\Models\AppleNotificationLog;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Throwable;

final class AppleNotificationController extends Controller
{
    private const MAX_SIGNED_PAYLOAD_BYTES = 256 * 1024;

    public function __construct(
        private readonly AppleSignedDataVerifier $verifier,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode(
                $request->getContent(),
                true,
                4,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->invalidResponse();
        }

        $signedPayload = is_array($payload)
            ? ($payload['signedPayload'] ?? null)
            : null;

        if (
            ! is_array($payload)
            || array_is_list($payload)
            || count($payload) !== 1
            || ! array_key_exists('signedPayload', $payload)
            || ! is_string($signedPayload)
            || $signedPayload === ''
            || trim($signedPayload) !== $signedPayload
            || strlen($signedPayload) > self::MAX_SIGNED_PAYLOAD_BYTES
        ) {
            return $this->invalidResponse();
        }

        try {
            $verified = $this->verifier->verifyNotification(
                $signedPayload,
                (string) config('apple_store.environment'),
            );
        } catch (AppleVerificationException $exception) {
            return $exception->retryable
                || $exception->errorCode === 'invalid_configuration'
                ? $this->unavailableResponse()
                : $this->invalidResponse();
        } catch (Throwable) {
            return $this->unavailableResponse();
        }

        $hash = hash('sha256', $signedPayload);
        $now = now();
        AppleNotificationLog::query()->insertOrIgnore([
            'notification_uuid' => $verified->notificationUuid,
            'environment' => $verified->environment,
            'notification_type' => $verified->notificationType,
            'subtype' => $verified->subtype,
            'signed_payload_sha256' => $hash,
            'status' => AppleNotificationLog::STATUS_RECEIVED,
            'error_code' => null,
            'processed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $log = AppleNotificationLog::query()
            ->where('notification_uuid', $verified->notificationUuid)
            ->firstOrFail();

        if (
            $log->signed_payload_sha256 !== $hash
            || $log->environment !== $verified->environment
            || $log->notification_type !== $verified->notificationType
            || $log->subtype !== $verified->subtype
        ) {
            return $this->invalidResponse();
        }

        if ($log->status === AppleNotificationLog::STATUS_PROCESSED) {
            return $this->successResponse(duplicate: true);
        }

        try {
            ProcessAppleNotification::dispatchSync($log->getKey(), $verified);
        } catch (AuthorizationException) {
            $this->markRejected($log, 'invalid_signed_data');

            return $this->invalidResponse();
        } catch (AppleVerificationException $exception) {
            if (
                $exception->retryable
                || $exception->errorCode === 'invalid_configuration'
            ) {
                $this->markFailed($log, $exception->errorCode);

                return $this->unavailableResponse();
            }

            $this->markRejected($log, $exception->errorCode);

            return $this->invalidResponse();
        } catch (Throwable) {
            $this->markFailed($log, 'processing_unavailable');

            return $this->unavailableResponse();
        }

        return $this->successResponse(duplicate: false);
    }

    private function successResponse(bool $duplicate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'acknowledged' => true,
                'duplicate' => $duplicate,
            ],
        ]);
    }

    private function invalidResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_notification_invalid',
            'message' => 'The App Store notification could not be verified.',
        ], 422);
    }

    private function unavailableResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_notification_unavailable',
            'message' => 'App Store notification processing is temporarily unavailable.',
        ], 503);
    }

    private function markFailed(
        AppleNotificationLog $log,
        string $errorCode,
    ): void {
        $log->forceFill([
            'status' => AppleNotificationLog::STATUS_FAILED,
            'error_code' => $errorCode,
            'processed_at' => null,
        ])->save();
    }

    private function markRejected(
        AppleNotificationLog $log,
        string $errorCode,
    ): void {
        $log->forceFill([
            'status' => AppleNotificationLog::STATUS_REJECTED,
            'error_code' => $errorCode,
            'processed_at' => now(),
        ])->save();
    }
}
