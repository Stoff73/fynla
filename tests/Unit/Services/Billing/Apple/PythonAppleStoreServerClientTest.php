<?php

declare(strict_types=1);

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Exceptions\Billing\AppleVerificationException;
use App\Services\Billing\Apple\AppleBridgeClient;
use App\Services\Billing\Apple\AppleStoreServerClient;
use App\Services\Billing\Apple\PythonAppleSignedDataVerifier;
use App\Services\Billing\Apple\PythonAppleStoreServerClient;

const RECONCILIATION_ACCOUNT_TOKEN = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
const RECONCILIATION_ORIGINAL_ID = 'apple-original-1';
const RECONCILIATION_MONTHLY_PRODUCT = 'org.fynla.premium.monthly';
const RECONCILIATION_ANNUAL_PRODUCT = 'org.fynla.premium.annual';

beforeEach(function (): void {
    config()->set('apple_store', [
        'bundle_id' => 'org.fynla.app',
        'allowed_product_ids' => [
            RECONCILIATION_MONTHLY_PRODUCT,
            RECONCILIATION_ANNUAL_PRODUCT,
        ],
        'environment' => 'sandbox',
        'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
        'app_apple_id' => null,
        'online_checks' => true,
        'python_executable' => PHP_BINARY,
        'bridge_cli_path' => base_path('tests/fixtures/apple_bridge_stub.php'),
        'process_timeout_seconds' => 40.0,
        'max_request_bytes' => 262_144,
        'max_response_bytes' => 1_048_576,
        'key_id' => 'KEY1234567',
        'issuer_id' => '2efc1b61-4c5f-4cf4-8389-4db4f90044ed',
        'private_key_path' => base_path('tests/fixtures/apple_bridge_stub.php'),
    ]);

    $this->reconciliationBridge = new RecordingAppleReconciliationBridge;
    $mapper = new PythonAppleSignedDataVerifier($this->reconciliationBridge);
    $this->serverClient = new PythonAppleStoreServerClient(
        $this->reconciliationBridge,
        $mapper,
    );
});

it('sends server-only configuration and maps only verified evidence DTOs', function (): void {
    $transactionData = reconciliationTransactionData();
    $renewalData = reconciliationRenewalData();
    $this->reconciliationBridge->response = [
        'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
        'transactions' => [[
            'signed_payload_sha256' => str_repeat('a', 64),
            'data' => $transactionData,
        ]],
        'statuses' => [[
            'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
            'subscription_status' => 4,
            'transaction' => [
                'signed_payload_sha256' => str_repeat('c', 64),
                'data' => $transactionData,
            ],
            'renewal' => [
                'signed_payload_sha256' => str_repeat('b', 64),
                'data' => $renewalData,
            ],
        ]],
    ];

    $batch = $this->serverClient->reconcile(
        RECONCILIATION_ORIGINAL_ID,
        RECONCILIATION_ACCOUNT_TOKEN,
    );

    expect($batch)->toBeInstanceOf(AppleReconciliationBatch::class)
        ->and($batch->originalTransactionId)->toBe(RECONCILIATION_ORIGINAL_ID)
        ->and($batch->transactions)->toHaveCount(1)
        ->and($batch->transactions[0]->signedPayloadSha256)->toBe(str_repeat('a', 64))
        ->and($batch->transactions[0]->transaction->transactionId)->toBe('apple-transaction-1')
        ->and($batch->transactions[0]->transaction->appAccountToken)->toBe(RECONCILIATION_ACCOUNT_TOKEN)
        ->and($batch->statuses)->toHaveCount(1)
        ->and($batch->statuses[0]->subscriptionStatus)->toBe(4)
        ->and($batch->statuses[0]->transaction?->signedPayloadSha256)->toBe(str_repeat('c', 64))
        ->and($batch->statuses[0]->transaction?->transaction->transactionId)->toBe('apple-transaction-1')
        ->and($batch->statuses[0]->renewal?->signedPayloadSha256)->toBe(str_repeat('b', 64))
        ->and($batch->statuses[0]->renewal?->renewal->autoRenewStatus)->toBe(1)
        ->and($this->reconciliationBridge->calls)->toBe([[
            'reconcile',
            [
                'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
                'environment' => 'sandbox',
                'bundle_id' => 'org.fynla.app',
                'app_apple_id' => null,
                'allowed_product_ids' => [
                    RECONCILIATION_MONTHLY_PRODUCT,
                    RECONCILIATION_ANNUAL_PRODUCT,
                ],
                'expected_app_account_token' => RECONCILIATION_ACCOUNT_TOKEN,
                'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
                'private_key_path' => base_path('tests/fixtures/apple_bridge_stub.php'),
                'key_id' => 'KEY1234567',
                'issuer_id' => '2efc1b61-4c5f-4cf4-8389-4db4f90044ed',
            ],
        ]]);

    $serialized = json_encode($batch, JSON_THROW_ON_ERROR);
    expect($serialized)->not->toContain(
        'signed_data',
        'private.signed',
        'x5c',
        'private-key-material',
    );
});

it('fails closed with stable retryability for malformed evidence and identity mismatches', function (Closure $mutate, string $expectedCode, bool $retryable): void {
    $response = [
        'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
        'transactions' => [[
            'signed_payload_sha256' => str_repeat('a', 64),
            'data' => reconciliationTransactionData(),
        ]],
        'statuses' => [[
            'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
            'subscription_status' => 1,
            'transaction' => null,
            'renewal' => [
                'signed_payload_sha256' => str_repeat('b', 64),
                'data' => reconciliationRenewalData(),
            ],
        ]],
    ];
    $this->reconciliationBridge->response = $mutate($response);

    try {
        $this->serverClient->reconcile(
            RECONCILIATION_ORIGINAL_ID,
            RECONCILIATION_ACCOUNT_TOKEN,
        );
        test()->fail('Expected reconciliation evidence to fail closed.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe($expectedCode)
            ->and($exception->retryable)->toBe($retryable)
            ->and($exception->getPrevious())->toBeNull();
    }
})->with([
    'extra batch field' => [function (array $response): array {
        $response['debug'] = 'unsafe';

        return $response;
    }, 'verifier_unavailable', true],
    'wrong original ID' => [function (array $response): array {
        $response['original_transaction_id'] = 'another-original';

        return $response;
    }, 'invalid_signed_data', false],
    'invalid hash' => [function (array $response): array {
        $response['transactions'][0]['signed_payload_sha256'] = 'not-a-hash';

        return $response;
    }, 'verifier_unavailable', true],
    'extra evidence field' => [function (array $response): array {
        $response['transactions'][0]['signed_data'] = 'private.signed.value';

        return $response;
    }, 'verifier_unavailable', true],
    'wrong account token' => [function (array $response): array {
        $response['transactions'][0]['data']['app_account_token'] = '2efc1b61-4c5f-4cf4-8389-4db4f90044ed';

        return $response;
    }, 'invalid_signed_data', false],
    'wrong transaction original' => [function (array $response): array {
        $response['transactions'][0]['data']['original_transaction_id'] = 'another-original';

        return $response;
    }, 'invalid_signed_data', false],
    'wrong transaction product' => [function (array $response): array {
        $response['transactions'][0]['data']['product_id'] = 'org.attacker.product';

        return $response;
    }, 'invalid_signed_data', false],
    'wrong renewal original' => [function (array $response): array {
        $response['statuses'][0]['renewal']['data']['original_transaction_id'] = 'another-original';

        return $response;
    }, 'invalid_signed_data', false],
    'wrong renewal product' => [function (array $response): array {
        $response['statuses'][0]['renewal']['data']['product_id'] = 'org.attacker.product';

        return $response;
    }, 'invalid_signed_data', false],
    'wrong renewal auto-renew product' => [function (array $response): array {
        $response['statuses'][0]['renewal']['data']['auto_renew_product_id'] = 'org.attacker.product';

        return $response;
    }, 'invalid_signed_data', false],
    'list transaction data' => [function (array $response): array {
        $response['transactions'][0]['data'] = ['unexpected'];

        return $response;
    }, 'verifier_unavailable', true],
    'unknown official subscription status' => [function (array $response): array {
        $response['statuses'][0]['subscription_status'] = 6;

        return $response;
    }, 'verifier_unavailable', true],
    'string official subscription status' => [function (array $response): array {
        $response['statuses'][0]['subscription_status'] = '1';

        return $response;
    }, 'verifier_unavailable', true],
    'extra status evidence field' => [function (array $response): array {
        $response['statuses'][0]['signed_data'] = 'private.signed.value';

        return $response;
    }, 'verifier_unavailable', true],
]);

it('rejects missing or untrusted server configuration before calling the bridge', function (string $key, mixed $value): void {
    config()->set("apple_store.{$key}", $value);

    expect(fn () => $this->serverClient->reconcile(
        RECONCILIATION_ORIGINAL_ID,
        RECONCILIATION_ACCOUNT_TOKEN,
    ))->toThrow(AppleVerificationException::class, 'invalid_configuration')
        ->and($this->reconciliationBridge->calls)->toBe([]);
})->with([
    ['key_id', null],
    ['key_id', ''],
    ['issuer_id', 'not-a-uuid'],
    ['private_key_path', 'relative/AuthKey.p8'],
    ['environment', 'xcode'],
]);

it('preserves only a stable bridge failure and resolves the server client contract', function (): void {
    $this->reconciliationBridge->exception = new AppleVerificationException(
        'app_store_api_unavailable',
        retryable: true,
    );

    try {
        $this->serverClient->reconcile(
            RECONCILIATION_ORIGINAL_ID,
            RECONCILIATION_ACCOUNT_TOKEN,
        );
        test()->fail('Expected reconciliation to fail.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe('app_store_api_unavailable')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getPrevious())->toBeNull();
    }

    app()->instance(AppleBridgeClient::class, $this->reconciliationBridge);
    expect(app(AppleStoreServerClient::class))->toBeInstanceOf(
        PythonAppleStoreServerClient::class,
    );
});

/** @return array<string, mixed> */
function reconciliationTransactionData(): array
{
    return [
        'transaction_id' => 'apple-transaction-1',
        'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
        'bundle_id' => 'org.fynla.app',
        'environment' => 'sandbox',
        'product_id' => RECONCILIATION_MONTHLY_PRODUCT,
        'app_account_token' => RECONCILIATION_ACCOUNT_TOKEN,
        'purchase_date' => 1_720_000_000_000,
        'expires_date' => 1_722_678_400_000,
        'revocation_date' => null,
        'ownership_type' => 'PURCHASED',
        'transaction_reason' => 'PURCHASE',
        'signed_date' => 1_720_000_000_100,
    ];
}

/** @return array<string, mixed> */
function reconciliationRenewalData(): array
{
    return [
        'original_transaction_id' => RECONCILIATION_ORIGINAL_ID,
        'product_id' => RECONCILIATION_MONTHLY_PRODUCT,
        'auto_renew_product_id' => RECONCILIATION_MONTHLY_PRODUCT,
        'auto_renew_status' => 1,
        'renewal_date' => 1_722_678_400_000,
        'expiration_intent' => null,
        'grace_period_expires_date' => null,
        'is_in_billing_retry_period' => false,
        'environment' => 'sandbox',
        'signed_date' => 1_720_000_000_200,
    ];
}

final class RecordingAppleReconciliationBridge implements AppleBridgeClient
{
    /** @var array<string, mixed> */
    public array $response = [];

    public ?AppleVerificationException $exception = null;

    /** @var list<array{string, array<string, mixed>}> */
    public array $calls = [];

    public function call(string $operation, array $payload): array
    {
        $this->calls[] = [$operation, $payload];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response;
    }
}
