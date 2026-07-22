<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Data\Billing\Apple\AppleReconciliationRenewalEvidence;
use App\Data\Billing\Apple\AppleReconciliationStatusEvidence;
use App\Data\Billing\Apple\AppleReconciliationTransactionEvidence;
use App\Exceptions\Billing\AppleVerificationException;
use Throwable;

final class PythonAppleStoreServerClient implements AppleStoreServerClient
{
    private const BUNDLE_ID = 'org.fynla.app';

    private const PRODUCTS = [
        'org.fynla.premium.monthly',
        'org.fynla.premium.annual',
    ];

    private const BATCH_KEYS = [
        'original_transaction_id',
        'transactions',
        'statuses',
    ];

    private const EVIDENCE_KEYS = [
        'signed_payload_sha256',
        'data',
    ];

    private const STATUS_KEYS = [
        'original_transaction_id',
        'subscription_status',
        'transaction',
        'renewal',
    ];

    public function __construct(
        private readonly AppleBridgeClient $bridgeClient,
        private readonly PythonAppleSignedDataVerifier $mapper,
    ) {}

    public function reconcile(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch {
        $payload = $this->trustedPayload(
            $originalTransactionId,
            $expectedAppAccountToken,
        );

        try {
            $data = $this->bridgeClient->call('reconcile', $payload);

            return $this->mapBatch(
                $data,
                $originalTransactionId,
                $expectedAppAccountToken,
            );
        } catch (AppleVerificationException $exception) {
            throw new AppleVerificationException(
                $exception->errorCode,
                $exception->retryable,
            );
        } catch (Throwable) {
            $this->throwUnavailable();
        }
    }

    /** @return array<string, mixed> */
    private function trustedPayload(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): array {
        $environment = config('apple_store.environment');
        $bundleId = config('apple_store.bundle_id');
        $products = config('apple_store.allowed_product_ids');
        $rootPath = config('apple_store.root_certificate_path');
        $appAppleId = config('apple_store.app_apple_id');
        $keyId = config('apple_store.key_id');
        $issuerId = config('apple_store.issuer_id');
        $privateKeyPath = config('apple_store.private_key_path');

        if (
            ! in_array($environment, ['sandbox', 'production'], true)
            || $bundleId !== self::BUNDLE_ID
            || $products !== self::PRODUCTS
            || ! is_string($rootPath)
            || $rootPath !== base_path('resources/certificates/apple/AppleRootCA-G3.cer')
            || ($appAppleId !== null && (! is_int($appAppleId) || $appAppleId <= 0))
            || ($environment === 'production' && ! is_int($appAppleId))
            || ! is_string($keyId)
            || preg_match('/\A[A-Z0-9]{10}\z/D', $keyId) !== 1
            || ! is_string($issuerId)
            || ! $this->isCanonicalUuid($issuerId)
            || ! $this->isTrustedFile($privateKeyPath)
            || ! $this->isBoundedIdentifier($originalTransactionId)
            || ! $this->isCanonicalUuid($expectedAppAccountToken)
        ) {
            throw new AppleVerificationException('invalid_configuration');
        }

        return [
            'root_certificate_path' => $rootPath,
            'environment' => $environment,
            'bundle_id' => $bundleId,
            'app_apple_id' => $appAppleId,
            'allowed_product_ids' => $products,
            'expected_app_account_token' => $expectedAppAccountToken,
            'original_transaction_id' => $originalTransactionId,
            'private_key_path' => $privateKeyPath,
            'key_id' => $keyId,
            'issuer_id' => $issuerId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapBatch(
        array $data,
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch {
        if (
            array_is_list($data)
            || ! $this->hasExactKeys($data, self::BATCH_KEYS)
            || ! is_array($data['transactions'] ?? null)
            || ! array_is_list($data['transactions'])
            || ! is_array($data['statuses'] ?? null)
            || ! array_is_list($data['statuses'])
        ) {
            $this->throwUnavailable();
        }
        if ($data['original_transaction_id'] !== $originalTransactionId) {
            $this->throwInvalidSignedData();
        }

        $transactions = [];
        foreach ($data['transactions'] as $evidence) {
            [$hash, $verified] = $this->evidenceParts($evidence);
            $transaction = $this->mapper->mapTransaction(
                $verified,
                $expectedAppAccountToken,
            );
            if ($transaction->originalTransactionId !== $originalTransactionId) {
                $this->throwInvalidSignedData();
            }
            $transactions[] = new AppleReconciliationTransactionEvidence(
                signedPayloadSha256: $hash,
                transaction: $transaction,
            );
        }

        $statuses = [];
        foreach ($data['statuses'] as $statusEvidence) {
            if (
                ! is_array($statusEvidence)
                || array_is_list($statusEvidence)
                || ! $this->hasExactKeys($statusEvidence, self::STATUS_KEYS)
                || ! is_int($statusEvidence['subscription_status'] ?? null)
                || ! in_array(
                    $statusEvidence['subscription_status'],
                    AppleReconciliationStatusEvidence::VALUES,
                    true,
                )
                || (! is_array($statusEvidence['transaction'] ?? null)
                    && ($statusEvidence['transaction'] ?? null) !== null)
                || (! is_array($statusEvidence['renewal'] ?? null)
                    && ($statusEvidence['renewal'] ?? null) !== null)
            ) {
                $this->throwUnavailable();
            }
            if (
                ! is_string($statusEvidence['original_transaction_id'] ?? null)
                || $statusEvidence['original_transaction_id'] !== $originalTransactionId
            ) {
                $this->throwInvalidSignedData();
            }

            $transactionEvidence = null;
            if (is_array($statusEvidence['transaction'])) {
                [$hash, $verified] = $this->evidenceParts($statusEvidence['transaction']);
                $transaction = $this->mapper->mapTransaction(
                    $verified,
                    $expectedAppAccountToken,
                );
                if ($transaction->originalTransactionId !== $originalTransactionId) {
                    $this->throwInvalidSignedData();
                }
                $transactionEvidence = new AppleReconciliationTransactionEvidence(
                    signedPayloadSha256: $hash,
                    transaction: $transaction,
                );
            }

            $renewalEvidence = null;
            if (is_array($statusEvidence['renewal'])) {
                [$hash, $verified] = $this->evidenceParts($statusEvidence['renewal']);
                $renewal = $this->mapper->mapRenewal($verified);
                if ($renewal->originalTransactionId !== $originalTransactionId) {
                    $this->throwInvalidSignedData();
                }
                $renewalEvidence = new AppleReconciliationRenewalEvidence(
                    signedPayloadSha256: $hash,
                    renewal: $renewal,
                );
            }

            $statuses[] = new AppleReconciliationStatusEvidence(
                originalTransactionId: $originalTransactionId,
                subscriptionStatus: $statusEvidence['subscription_status'],
                transaction: $transactionEvidence,
                renewal: $renewalEvidence,
            );
        }

        return new AppleReconciliationBatch(
            originalTransactionId: $originalTransactionId,
            transactions: $transactions,
            statuses: $statuses,
        );
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function evidenceParts(mixed $evidence): array
    {
        if (
            ! is_array($evidence)
            || array_is_list($evidence)
            || ! $this->hasExactKeys($evidence, self::EVIDENCE_KEYS)
            || ! is_string($evidence['signed_payload_sha256'] ?? null)
            || preg_match(
                '/\A[0-9a-f]{64}\z/D',
                $evidence['signed_payload_sha256'],
            ) !== 1
            || ! is_array($evidence['data'] ?? null)
            || array_is_list($evidence['data'])
        ) {
            $this->throwUnavailable();
        }

        return [
            $evidence['signed_payload_sha256'],
            $evidence['data'],
        ];
    }

    private function isTrustedFile(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && trim($value) === $value
            && str_starts_with($value, DIRECTORY_SEPARATOR)
            && is_file($value)
            && is_readable($value)
            && ($size = filesize($value)) !== false
            && $size > 0
            && $size <= 65_536;
    }

    private function isBoundedIdentifier(string $value): bool
    {
        return $value !== ''
            && trim($value) === $value
            && strlen($value) <= 256;
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

    private function throwInvalidSignedData(): never
    {
        throw new AppleVerificationException('invalid_signed_data');
    }
}
