<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\AppleNotificationRecovery;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AppleNotificationProcessor
{
    private const ALLOWED_SUBTYPES = [
        'SUBSCRIBED' => ['INITIAL_BUY', 'RESUBSCRIBE'],
        'DID_RENEW' => [null, 'BILLING_RECOVERY'],
        'DID_CHANGE_RENEWAL_STATUS' => [
            'AUTO_RENEW_ENABLED',
            'AUTO_RENEW_DISABLED',
        ],
        'DID_FAIL_TO_RENEW' => [null, 'GRACE_PERIOD'],
        'GRACE_PERIOD_EXPIRED' => [null],
        'EXPIRED' => [
            'VOLUNTARY',
            'BILLING_RETRY',
            'PRICE_INCREASE',
            'PRODUCT_NOT_FOR_SALE',
        ],
        'REFUND' => [null],
        'REFUND_REVERSED' => [null],
        'REVOKE' => [null],
        'DID_CHANGE_RENEWAL_PREF' => ['UPGRADE', 'DOWNGRADE'],
    ];

    public function __construct(
        private readonly AppleTransactionStore $transactions,
        private readonly AppleEntitlementProjector $entitlements,
    ) {}

    public function process(
        AppleNotificationLog $log,
        VerifiedAppleNotification $notification,
    ): bool {
        $prepared = DB::transaction(function () use ($log, $notification): array {
            $locked = AppleNotificationLog::query()
                ->whereKey($log->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === AppleNotificationLog::STATUS_PROCESSED) {
                return ['duplicate' => true, 'user_id' => null];
            }

            $this->validateIdentity($locked, $notification);

            if ($notification->notificationType === 'TEST') {
                $this->validateTestNotification($notification);

                $locked->forceFill([
                    'status' => AppleNotificationLog::STATUS_PROCESSED,
                    'error_code' => null,
                    'processed_at' => now(),
                ])->save();

                return ['duplicate' => false, 'user_id' => null];
            }

            if (
                ! $this->isSupportedTypeAndSubtype(
                    $notification->notificationType,
                    $notification->subtype,
                )
                || $notification->transaction === null
                || $notification->renewal === null
                || ! $this->isSha256($notification->transactionSignedPayloadSha256)
                || ! $this->isSha256($notification->renewalSignedPayloadSha256)
                || $notification->transaction->originalTransactionId
                    !== $notification->renewal->originalTransactionId
            ) {
                throw new AppleVerificationException('invalid_signed_data');
            }

            $user = User::query()
                ->where(
                    'apple_app_account_token',
                    $notification->transaction->appAccountToken,
                )
                ->first();

            if (! $user instanceof User || $user->is_preview_user) {
                throw new AppleVerificationException('invalid_signed_data');
            }

            AppleNotificationRecovery::query()->insertOrIgnore([
                'apple_notification_log_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'original_transaction_id' => $notification->transaction->originalTransactionId,
                'status' => AppleNotificationRecovery::STATUS_PENDING,
                'error_code' => null,
                'attempt_count' => 0,
                'next_attempt_at' => null,
                'last_attempted_at' => null,
                'processed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $recovery = AppleNotificationRecovery::query()
                ->where('apple_notification_log_id', $locked->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (string) $recovery->user_id !== (string) $user->getKey()
                || $recovery->original_transaction_id
                    !== $notification->transaction->originalTransactionId
                || $recovery->status === AppleNotificationRecovery::STATUS_REJECTED
            ) {
                throw new AppleVerificationException('invalid_signed_data');
            }

            return ['duplicate' => false, 'user_id' => $user->getKey()];
        }, 3);

        if ($prepared['duplicate'] === true || $prepared['user_id'] === null) {
            return $prepared['duplicate'];
        }

        try {
            return DB::transaction(function () use (
                $log,
                $notification,
                $prepared,
            ): bool {
                $locked = AppleNotificationLog::query()
                    ->whereKey($log->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === AppleNotificationLog::STATUS_PROCESSED) {
                    return true;
                }

                $recovery = AppleNotificationRecovery::query()
                    ->where('apple_notification_log_id', $locked->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $user = User::query()->whereKey($prepared['user_id'])->firstOrFail();

                $this->transactions->storeVerifiedEvidence(
                    $user,
                    $notification->transaction,
                    $notification->transactionSignedPayloadSha256,
                );
                $this->entitlements->projectRenewal(
                    $user,
                    $notification->renewal,
                    $notification->notificationType,
                    $notification->subtype,
                );

                $processedAt = now();
                $recovery->forceFill([
                    'status' => AppleNotificationRecovery::STATUS_PROCESSED,
                    'error_code' => null,
                    'next_attempt_at' => null,
                    'processed_at' => $processedAt,
                ])->save();
                $locked->forceFill([
                    'status' => AppleNotificationLog::STATUS_PROCESSED,
                    'error_code' => null,
                    'processed_at' => $processedAt,
                ])->save();

                return false;
            }, 3);
        } catch (Throwable $exception) {
            $this->recordRecoveryFailure($log->getKey(), $exception);

            throw $exception;
        }
    }

    private function validateIdentity(
        AppleNotificationLog $log,
        VerifiedAppleNotification $notification,
    ): void {
        if (
            $log->notification_uuid !== $notification->notificationUuid
            || $log->environment !== $notification->environment
            || $log->notification_type !== $notification->notificationType
            || $log->subtype !== $notification->subtype
            || $notification->environment !== config('apple_store.environment')
            || ($notification->transaction !== null
                && $notification->transaction->environment !== $notification->environment)
            || ($notification->renewal !== null
                && $notification->renewal->environment !== $notification->environment)
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }
    }

    private function validateTestNotification(
        VerifiedAppleNotification $notification,
    ): void {
        if (
            $notification->subtype !== null
            || $notification->transaction !== null
            || $notification->renewal !== null
            || $notification->transactionSignedPayloadSha256 !== null
            || $notification->renewalSignedPayloadSha256 !== null
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }
    }

    private function recordRecoveryFailure(
        mixed $notificationLogId,
        Throwable $exception,
    ): void {
        $recovery = AppleNotificationRecovery::query()
            ->where('apple_notification_log_id', $notificationLogId)
            ->first();

        if (! $recovery instanceof AppleNotificationRecovery) {
            return;
        }

        $permanent = $exception instanceof AuthorizationException
            || ($exception instanceof AppleVerificationException
                && ! $exception->retryable
                && $exception->errorCode !== 'invalid_configuration');
        $errorCode = $exception instanceof AppleVerificationException
            ? $exception->errorCode
            : 'processing_unavailable';

        $recovery->forceFill([
            'status' => $permanent
                ? AppleNotificationRecovery::STATUS_REJECTED
                : AppleNotificationRecovery::STATUS_FAILED,
            'error_code' => $errorCode,
            'next_attempt_at' => $permanent ? null : now(),
            'processed_at' => $permanent ? now() : null,
        ])->save();
    }

    private function isSha256(?string $value): bool
    {
        return is_string($value)
            && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }

    private function isSupportedTypeAndSubtype(
        string $type,
        ?string $subtype,
    ): bool {
        return array_key_exists($type, self::ALLOWED_SUBTYPES)
            && in_array($subtype, self::ALLOWED_SUBTYPES[$type], true);
    }
}
