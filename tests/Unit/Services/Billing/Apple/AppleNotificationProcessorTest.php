<?php

declare(strict_types=1);

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\AppleTransaction;
use App\Models\PremiumEntitlement;
use App\Models\User;
use App\Services\Billing\Apple\AppleNotificationProcessor;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('apple_store.environment', 'sandbox');
});

it('projects a verified DID_RENEW notification from safe nested evidence', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleNotificationUser($token);
    $notification = appleVerifiedNotification('DID_RENEW', null, $token);
    $log = appleNotificationLog($notification);

    app(AppleNotificationProcessor::class)->process($log, $notification);

    $stored = AppleTransaction::query()->sole();
    $entitlement = PremiumEntitlement::query()->sole();

    expect($stored->user_id)->toBe($user->id)
        ->and($stored->signed_payload_sha256)->toBe(str_repeat('a', 64))
        ->and($stored->reconciled_at)->toBeNull()
        ->and($entitlement->provider)->toBe(PremiumEntitlement::PROVIDER_APPLE)
        ->and($entitlement->status)->toBe(PremiumEntitlement::STATUS_ACTIVE)
        ->and($entitlement->will_renew)->toBeTrue()
        ->and($log->fresh()->status)->toBe(AppleNotificationLog::STATUS_PROCESSED)
        ->and(json_encode([
            $stored->getAttributes(),
            $entitlement->getAttributes(),
            $log->fresh()->getAttributes(),
        ], JSON_THROW_ON_ERROR))->not->toContain('nested.transaction.jws');
});

it('accepts a documented notification type and subtype', function (
    string $type,
    ?string $subtype,
): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    appleNotificationUser($token);
    $notification = appleVerifiedNotification($type, $subtype, $token);
    $log = appleNotificationLog($notification);

    app(AppleNotificationProcessor::class)->process($log, $notification);

    expect($log->fresh()->status)->toBe(AppleNotificationLog::STATUS_PROCESSED);
})->with([
    'SUBSCRIBED INITIAL_BUY' => ['SUBSCRIBED', 'INITIAL_BUY'],
    'SUBSCRIBED RESUBSCRIBE' => ['SUBSCRIBED', 'RESUBSCRIBE'],
    'DID_RENEW' => ['DID_RENEW', null],
    'DID_RENEW BILLING_RECOVERY' => ['DID_RENEW', 'BILLING_RECOVERY'],
    'DID_CHANGE_RENEWAL_STATUS AUTO_RENEW_ENABLED' => ['DID_CHANGE_RENEWAL_STATUS', 'AUTO_RENEW_ENABLED'],
    'DID_CHANGE_RENEWAL_STATUS AUTO_RENEW_DISABLED' => ['DID_CHANGE_RENEWAL_STATUS', 'AUTO_RENEW_DISABLED'],
    'DID_FAIL_TO_RENEW' => ['DID_FAIL_TO_RENEW', null],
    'DID_FAIL_TO_RENEW GRACE_PERIOD' => ['DID_FAIL_TO_RENEW', 'GRACE_PERIOD'],
    'GRACE_PERIOD_EXPIRED' => ['GRACE_PERIOD_EXPIRED', null],
    'EXPIRED VOLUNTARY' => ['EXPIRED', 'VOLUNTARY'],
    'EXPIRED BILLING_RETRY' => ['EXPIRED', 'BILLING_RETRY'],
    'EXPIRED PRICE_INCREASE' => ['EXPIRED', 'PRICE_INCREASE'],
    'EXPIRED PRODUCT_NOT_FOR_SALE' => ['EXPIRED', 'PRODUCT_NOT_FOR_SALE'],
    'REFUND' => ['REFUND', null],
    'REFUND_REVERSED' => ['REFUND_REVERSED', null],
    'REVOKE' => ['REVOKE', null],
    'DID_CHANGE_RENEWAL_PREF UPGRADE' => ['DID_CHANGE_RENEWAL_PREF', 'UPGRADE'],
    'DID_CHANGE_RENEWAL_PREF DOWNGRADE' => ['DID_CHANGE_RENEWAL_PREF', 'DOWNGRADE'],
]);

it('projects renewal-state notification transitions', function (
    string $type,
    ?string $subtype,
    array $renewalOverrides,
    string $expectedStatus,
    bool $expectedWillRenew,
): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    appleNotificationUser($token);
    $notification = appleVerifiedNotification(
        $type,
        $subtype,
        $token,
        renewalOverrides: $renewalOverrides,
    );

    app(AppleNotificationProcessor::class)->process(
        appleNotificationLog($notification),
        $notification,
    );

    $entitlement = PremiumEntitlement::query()->sole();

    expect($entitlement->status)->toBe($expectedStatus)
        ->and($entitlement->will_renew)->toBe($expectedWillRenew);
})->with([
    'auto-renew disabled' => [
        'DID_CHANGE_RENEWAL_STATUS',
        'AUTO_RENEW_DISABLED',
        ['autoRenewStatus' => 0],
        PremiumEntitlement::STATUS_CANCELLED,
        false,
    ],
    'billing retry' => [
        'DID_FAIL_TO_RENEW',
        null,
        ['isInBillingRetryPeriod' => true],
        PremiumEntitlement::STATUS_BILLING_RETRY,
        true,
    ],
    'grace period' => [
        'DID_FAIL_TO_RENEW',
        'GRACE_PERIOD',
        [
            'isInBillingRetryPeriod' => true,
            'gracePeriodExpiresDate' => CarbonImmutable::now('UTC')->addDays(3),
        ],
        PremiumEntitlement::STATUS_GRACE_PERIOD,
        true,
    ],
]);

it('does not let an older verified event overwrite newer canonical state', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    appleNotificationUser($token);
    $olderSignedDate = CarbonImmutable::now('UTC')->startOfSecond();
    $newerSignedDate = $olderSignedDate->addHour();
    $newer = appleVerifiedNotification(
        'DID_CHANGE_RENEWAL_STATUS',
        'AUTO_RENEW_DISABLED',
        $token,
        ['signedDate' => $newerSignedDate],
        ['signedDate' => $newerSignedDate, 'autoRenewStatus' => 0],
    );
    $older = appleVerifiedNotification(
        'DID_RENEW',
        null,
        $token,
        ['signedDate' => $olderSignedDate],
        ['signedDate' => $olderSignedDate, 'autoRenewStatus' => 1],
    );

    $processor = app(AppleNotificationProcessor::class);
    $processor->process(appleNotificationLog($newer), $newer);
    $processor->process(appleNotificationLog($older), $older);

    $entitlement = PremiumEntitlement::query()->sole();

    expect($entitlement->status)->toBe(PremiumEntitlement::STATUS_CANCELLED)
        ->and($entitlement->will_renew)->toBeFalse()
        ->and($entitlement->last_verified_at?->toISOString())
        ->toBe($newerSignedDate->toISOString())
        ->and(AppleTransaction::query()->count())->toBe(2);
});

it('preserves millisecond ordering for events within the same second', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    appleNotificationUser($token);
    $newerSignedDate = CarbonImmutable::parse('2026-07-18T12:00:00.900Z');
    $olderSignedDate = CarbonImmutable::parse('2026-07-18T12:00:00.100Z');
    $newer = appleVerifiedNotification(
        'DID_CHANGE_RENEWAL_STATUS',
        'AUTO_RENEW_DISABLED',
        $token,
        ['signedDate' => $newerSignedDate],
        ['signedDate' => $newerSignedDate, 'autoRenewStatus' => 0],
    );
    $older = appleVerifiedNotification(
        'DID_RENEW',
        null,
        $token,
        ['signedDate' => $olderSignedDate],
        ['signedDate' => $olderSignedDate, 'autoRenewStatus' => 1],
    );

    $processor = app(AppleNotificationProcessor::class);
    $processor->process(appleNotificationLog($newer), $newer);
    $processor->process(appleNotificationLog($older), $older);

    $entitlement = PremiumEntitlement::query()->sole();

    expect($entitlement->status)->toBe(PremiumEntitlement::STATUS_CANCELLED)
        ->and($entitlement->will_renew)->toBeFalse()
        ->and($entitlement->last_verified_at?->format('Y-m-d H:i:s.v'))
        ->toBe($newerSignedDate->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s.v'));
});

it('rejects a verified notification whose outer and nested environments differ', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    appleNotificationUser($token);
    $valid = appleVerifiedNotification('DID_RENEW', null, $token);
    $mismatched = new VerifiedAppleNotification(
        notificationUuid: $valid->notificationUuid,
        notificationType: $valid->notificationType,
        subtype: $valid->subtype,
        environment: 'production',
        transaction: $valid->transaction,
        renewal: $valid->renewal,
        transactionSignedPayloadSha256: $valid->transactionSignedPayloadSha256,
        renewalSignedPayloadSha256: $valid->renewalSignedPayloadSha256,
    );
    $log = appleNotificationLog($mismatched);

    expect(fn () => app(AppleNotificationProcessor::class)->process(
        $log,
        $mismatched,
    ))->toThrow(AppleVerificationException::class, 'invalid_signed_data');

    expect(AppleTransaction::query()->count())->toBe(0);
});

function appleNotificationUser(string $token): User
{
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();

    return $user->fresh();
}

function appleNotificationLog(VerifiedAppleNotification $notification): AppleNotificationLog
{
    return AppleNotificationLog::query()->create([
        'notification_uuid' => $notification->notificationUuid,
        'environment' => $notification->environment,
        'notification_type' => $notification->notificationType,
        'subtype' => $notification->subtype,
        'signed_payload_sha256' => str_repeat('c', 64),
        'status' => AppleNotificationLog::STATUS_RECEIVED,
    ]);
}

function appleVerifiedNotification(
    string $type,
    ?string $subtype,
    string $token,
    array $transactionOverrides = [],
    array $renewalOverrides = [],
): VerifiedAppleNotification {
    static $sequence = 0;
    $sequence++;
    $purchaseDate = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $signedDate = CarbonImmutable::now('UTC')->startOfSecond();

    $transaction = new VerifiedAppleTransaction(
        transactionId: $transactionOverrides['transactionId'] ?? 'notification-transaction-'.$sequence,
        originalTransactionId: $transactionOverrides['originalTransactionId'] ?? 'notification-original-1',
        bundleId: $transactionOverrides['bundleId'] ?? 'org.fynla.app',
        environment: $transactionOverrides['environment'] ?? 'sandbox',
        productId: $transactionOverrides['productId'] ?? 'org.fynla.premium.monthly',
        appAccountToken: $transactionOverrides['appAccountToken'] ?? $token,
        purchaseDate: $transactionOverrides['purchaseDate'] ?? $purchaseDate,
        expiresDate: $transactionOverrides['expiresDate'] ?? $purchaseDate->addMonth(),
        revocationDate: $transactionOverrides['revocationDate'] ?? null,
        ownershipType: $transactionOverrides['ownershipType'] ?? 'PURCHASED',
        transactionReason: $transactionOverrides['transactionReason'] ?? 'RENEWAL',
        signedDate: $transactionOverrides['signedDate'] ?? $signedDate,
    );
    $renewal = new VerifiedAppleRenewal(
        originalTransactionId: $renewalOverrides['originalTransactionId'] ?? $transaction->originalTransactionId,
        productId: $renewalOverrides['productId'] ?? $transaction->productId,
        autoRenewProductId: $renewalOverrides['autoRenewProductId'] ?? $transaction->productId,
        autoRenewStatus: $renewalOverrides['autoRenewStatus'] ?? 1,
        renewalDate: $renewalOverrides['renewalDate'] ?? $transaction->expiresDate,
        expirationIntent: $renewalOverrides['expirationIntent'] ?? null,
        gracePeriodExpiresDate: $renewalOverrides['gracePeriodExpiresDate'] ?? null,
        isInBillingRetryPeriod: $renewalOverrides['isInBillingRetryPeriod'] ?? false,
        environment: $renewalOverrides['environment'] ?? $transaction->environment,
        signedDate: $renewalOverrides['signedDate'] ?? $transaction->signedDate,
    );

    return new VerifiedAppleNotification(
        notificationUuid: sprintf('00000000-0000-4000-8000-%012d', $sequence),
        notificationType: $type,
        subtype: $subtype,
        environment: 'sandbox',
        transaction: $transaction,
        renewal: $renewal,
        transactionSignedPayloadSha256: str_repeat('a', 64),
        renewalSignedPayloadSha256: str_repeat('b', 64),
    );
}
