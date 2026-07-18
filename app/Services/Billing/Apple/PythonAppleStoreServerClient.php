<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Data\Billing\Apple\AppleReconciliationRenewalEvidence;
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
        'renewals',
    ];

    private const EVIDENCE_KEYS = [
        'signed_payload_sha256',
        'data',
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
            || ($data['original_transaction_id'] ?? null) !== $originalTransactionId
            || ! is_array($data['transactions'] ?? null)
            || ! array_is_list($data['transactions'])
            || ! is_array($data['renewals'] ?? null)
            || ! array_is_list($data['renewals'])
        ) {
            $this->throwUnavailable();
        }

        $transactions = [];
        foreach ($data['transactions'] as $evidence) {
            [$hash, $verified] = $this->evidenceParts($evidence);
            $transaction = $this->mapper->mapTransaction(
                $verified,
                $expectedAppAccountToken,
            );
            if ($transaction->originalTransactionId !== $originalTransactionId) {
                $this->throwUnavailable();
            }
            $transactions[] = new AppleReconciliationTransactionEvidence(
                signedPayloadSha256: $hash,
                transaction: $transaction,
            );
        }

        $renewals = [];
        foreach ($data['renewals'] as $evidence) {
            [$hash, $verified] = $this->evidenceParts($evidence);
            $renewal = $this->mapper->mapRenewal($verified);
            if ($renewal->originalTransactionId !== $originalTransactionId) {
                $this->throwUnavailable();
            }
            $renewals[] = new AppleReconciliationRenewalEvidence(
                signedPayloadSha256: $hash,
                renewal: $renewal,
            );
        }

        return new AppleReconciliationBatch(
            originalTransactionId: $originalTransactionId,
            transactions: $transactions,
            renewals: $renewals,
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
}
