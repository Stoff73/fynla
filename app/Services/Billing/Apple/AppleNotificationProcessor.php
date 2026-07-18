<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    ): void {
        DB::transaction(function () use ($log, $notification): void {
            $locked = AppleNotificationLog::query()
                ->whereKey($log->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->notification_uuid !== $notification->notificationUuid
                || $locked->environment !== $notification->environment
                || $locked->notification_type !== $notification->notificationType
                || $locked->subtype !== $notification->subtype
                || $notification->environment !== config('apple_store.environment')
                || ($notification->transaction !== null
                    && $notification->transaction->environment !== $notification->environment)
                || ($notification->renewal !== null
                    && $notification->renewal->environment !== $notification->environment)
            ) {
                throw new AppleVerificationException('invalid_signed_data');
            }

            if ($notification->notificationType === 'TEST') {
                if (
                    $notification->subtype !== null
                    || $notification->transaction !== null
                    || $notification->renewal !== null
                    || $notification->transactionSignedPayloadSha256 !== null
                    || $notification->renewalSignedPayloadSha256 !== null
                ) {
                    throw new AppleVerificationException('invalid_signed_data');
                }
            } elseif (
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

            if ($notification->transaction !== null) {
                $user = User::query()
                    ->where(
                        'apple_app_account_token',
                        $notification->transaction->appAccountToken,
                    )
                    ->first();

                if (! $user instanceof User) {
                    throw new AppleVerificationException('invalid_signed_data');
                }

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
            }

            $locked->forceFill([
                'status' => AppleNotificationLog::STATUS_PROCESSED,
                'error_code' => null,
                'processed_at' => now(),
            ])->save();
        }, 3);
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
