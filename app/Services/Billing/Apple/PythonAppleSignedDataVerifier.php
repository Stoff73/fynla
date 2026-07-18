<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use Carbon\CarbonImmutable;
use Throwable;

final class PythonAppleSignedDataVerifier implements AppleSignedDataVerifier
{
    private const BUNDLE_ID = 'org.fynla.app';

    private const PRODUCTS = [
        'org.fynla.premium.monthly',
        'org.fynla.premium.annual',
    ];

    private const TRANSACTION_KEYS = [
        'transaction_id',
        'original_transaction_id',
        'bundle_id',
        'environment',
        'product_id',
        'app_account_token',
        'purchase_date',
        'expires_date',
        'revocation_date',
        'ownership_type',
        'transaction_reason',
        'signed_date',
    ];

    private const RENEWAL_KEYS = [
        'original_transaction_id',
        'product_id',
        'auto_renew_product_id',
        'auto_renew_status',
        'renewal_date',
        'expiration_intent',
        'grace_period_expires_date',
        'is_in_billing_retry_period',
        'environment',
        'signed_date',
    ];

    private const NOTIFICATION_KEYS = [
        'notification_uuid',
        'notification_type',
        'subtype',
        'environment',
        'transaction',
        'renewal',
    ];

    public function __construct(
        private readonly AppleBridgeClient $bridgeClient,
    ) {}

    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction {
        $payload = $this->trustedPayload(
            $jws,
            $expectedEnvironment,
            $expectedAppAccountToken,
        );
        $data = $this->bridgeClient->call('verify_transaction', $payload);

        return $this->mapTransaction($data, $expectedAppAccountToken);
    }

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification {
        $payload = $this->trustedPayload($jws, $expectedEnvironment, null);
        $data = $this->bridgeClient->call('verify_notification', $payload);

        if (! $this->hasExactKeys($data, self::NOTIFICATION_KEYS)) {
            $this->throwUnavailable();
        }

        $notificationUuid = $this->requiredCanonicalUuid(
            $data['notification_uuid'] ?? null,
        );
        $notificationType = $this->requiredString(
            $data['notification_type'] ?? null,
        );
        $subtype = $this->nullableString($data['subtype'] ?? null);
        $environment = $this->requiredString($data['environment'] ?? null);
        if ($environment !== $expectedEnvironment) {
            $this->throwUnavailable();
        }

        $transaction = $data['transaction'];
        if ($transaction !== null && ! is_array($transaction)) {
            $this->throwUnavailable();
        }
        $renewal = $data['renewal'];
        if ($renewal !== null && ! is_array($renewal)) {
            $this->throwUnavailable();
        }

        return new VerifiedAppleNotification(
            notificationUuid: $notificationUuid,
            notificationType: $notificationType,
            subtype: $subtype,
            environment: $environment,
            transaction: $transaction === null
                ? null
                : $this->mapTransaction($transaction, null),
            renewal: $renewal === null
                ? null
                : $this->mapRenewal($renewal),
        );
    }

    /** @return array<string, mixed> */
    private function trustedPayload(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): array {
        $configuredEnvironment = config('apple_store.environment');
        $bundleId = config('apple_store.bundle_id');
        $products = config('apple_store.allowed_product_ids');
        $rootPath = config('apple_store.root_certificate_path');
        $appAppleId = config('apple_store.app_apple_id');
        $onlineChecks = config('apple_store.online_checks');

        if (
            ! in_array($configuredEnvironment, ['sandbox', 'production'], true)
            || $expectedEnvironment !== $configuredEnvironment
            || $bundleId !== self::BUNDLE_ID
            || $products !== self::PRODUCTS
            || ! is_string($rootPath)
            || $rootPath !== base_path('resources/certificates/apple/AppleRootCA-G3.cer')
            || $onlineChecks !== true
            || ($appAppleId !== null && (! is_int($appAppleId) || $appAppleId <= 0))
            || ($configuredEnvironment === 'production' && ! is_int($appAppleId))
        ) {
            throw new AppleVerificationException('invalid_configuration');
        }

        if (trim($jws) === '') {
            throw new AppleVerificationException('invalid_signed_data');
        }

        if (
            $expectedAppAccountToken !== null
            && ! $this->isCanonicalUuid($expectedAppAccountToken)
        ) {
            throw new AppleVerificationException('invalid_configuration');
        }

        return [
            'root_certificate_path' => $rootPath,
            'environment' => $configuredEnvironment,
            'bundle_id' => $bundleId,
            'app_apple_id' => $appAppleId,
            'allowed_product_ids' => $products,
            'expected_app_account_token' => $expectedAppAccountToken,
            'signed_data' => $jws,
        ];
    }

    /** @param array<string, mixed> $data */
    private function mapTransaction(
        array $data,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction {
        if (! $this->hasExactKeys($data, self::TRANSACTION_KEYS)) {
            $this->throwUnavailable();
        }

        $transactionId = $this->requiredString($data['transaction_id'] ?? null);
        $originalTransactionId = $this->requiredString(
            $data['original_transaction_id'] ?? null,
        );
        $bundleId = $this->requiredString($data['bundle_id'] ?? null);
        $environment = $this->requiredString($data['environment'] ?? null);
        $productId = $this->requiredString($data['product_id'] ?? null);
        $appAccountToken = $this->requiredCanonicalUuid(
            $data['app_account_token'] ?? null,
        );
        $purchaseDate = $this->dateFromMilliseconds(
            $data['purchase_date'] ?? null,
            required: true,
        );
        $expiresDate = $this->dateFromMilliseconds($data['expires_date'] ?? null);
        $revocationDate = $this->dateFromMilliseconds(
            $data['revocation_date'] ?? null,
        );
        $signedDate = $this->dateFromMilliseconds(
            $data['signed_date'] ?? null,
            required: true,
        );

        if (
            $bundleId !== self::BUNDLE_ID
            || $environment !== config('apple_store.environment')
            || ! in_array($productId, self::PRODUCTS, true)
            || ($expectedAppAccountToken !== null
                && $appAccountToken !== $expectedAppAccountToken)
            || $purchaseDate === null
            || $signedDate === null
            || $signedDate->lessThan($purchaseDate)
            || ($expiresDate !== null && $expiresDate->lessThan($purchaseDate))
            || ($revocationDate !== null && $revocationDate->lessThan($purchaseDate))
        ) {
            $this->throwUnavailable();
        }

        return new VerifiedAppleTransaction(
            transactionId: $transactionId,
            originalTransactionId: $originalTransactionId,
            bundleId: $bundleId,
            environment: $environment,
            productId: $productId,
            appAccountToken: $appAccountToken,
            purchaseDate: $purchaseDate,
            expiresDate: $expiresDate,
            revocationDate: $revocationDate,
            ownershipType: $this->nullableString($data['ownership_type'] ?? null),
            transactionReason: $this->nullableString(
                $data['transaction_reason'] ?? null,
            ),
            signedDate: $signedDate,
        );
    }

    /** @param array<string, mixed> $data */
    private function mapRenewal(array $data): VerifiedAppleRenewal
    {
        if (! $this->hasExactKeys($data, self::RENEWAL_KEYS)) {
            $this->throwUnavailable();
        }

        $productId = $this->requiredString($data['product_id'] ?? null);
        $autoRenewProductId = $this->requiredString(
            $data['auto_renew_product_id'] ?? null,
        );
        $autoRenewStatus = $data['auto_renew_status'] ?? null;
        $expirationIntent = $data['expiration_intent'] ?? null;
        $billingRetry = $data['is_in_billing_retry_period'] ?? null;
        $environment = $this->requiredString($data['environment'] ?? null);

        if (
            ! is_int($autoRenewStatus)
            || ! in_array($autoRenewStatus, [0, 1], true)
            || ($expirationIntent !== null && ! is_int($expirationIntent))
            || ($billingRetry !== null && ! is_bool($billingRetry))
            || ! in_array($productId, self::PRODUCTS, true)
            || ! in_array($autoRenewProductId, self::PRODUCTS, true)
            || $environment !== config('apple_store.environment')
        ) {
            $this->throwUnavailable();
        }

        return new VerifiedAppleRenewal(
            originalTransactionId: $this->requiredString(
                $data['original_transaction_id'] ?? null,
            ),
            productId: $productId,
            autoRenewProductId: $autoRenewProductId,
            autoRenewStatus: $autoRenewStatus,
            renewalDate: $this->dateFromMilliseconds(
                $data['renewal_date'] ?? null,
            ),
            expirationIntent: $expirationIntent,
            gracePeriodExpiresDate: $this->dateFromMilliseconds(
                $data['grace_period_expires_date'] ?? null,
            ),
            isInBillingRetryPeriod: $billingRetry,
            environment: $environment,
            signedDate: $this->dateFromMilliseconds(
                $data['signed_date'] ?? null,
                required: true,
            ),
        );
    }

    private function dateFromMilliseconds(
        mixed $value,
        bool $required = false,
    ): ?CarbonImmutable {
        if ($value === null) {
            if ($required) {
                $this->throwUnavailable();
            }

            return null;
        }
        if (! is_int($value) || $value <= 0) {
            $this->throwUnavailable();
        }

        try {
            return CarbonImmutable::createFromTimestampMsUTC($value);
        } catch (Throwable) {
            $this->throwUnavailable();
        }
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || $value === '' || trim($value) !== $value) {
            $this->throwUnavailable();
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->requiredString($value);
    }

    private function requiredCanonicalUuid(mixed $value): string
    {
        if (! is_string($value) || ! $this->isCanonicalUuid($value)) {
            $this->throwUnavailable();
        }

        return $value;
    }

    private function isCanonicalUuid(string $value): bool
    {
        return preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
            $value,
        ) === 1;
    }

    /** @param array<string, mixed> $value */
    private function hasExactKeys(array $value, array $expectedKeys): bool
    {
        $actualKeys = array_keys($value);
        sort($actualKeys);
        sort($expectedKeys);

        return $actualKeys === $expectedKeys;
    }

    private function throwUnavailable(): never
    {
        throw new AppleVerificationException(
            'verifier_unavailable',
            retryable: true,
        );
    }
}
