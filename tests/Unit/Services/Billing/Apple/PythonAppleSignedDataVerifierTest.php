<?php

declare(strict_types=1);

use App\Exceptions\Billing\AppleVerificationException;
use App\Services\Billing\Apple\AppleBridgeClient;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use App\Services\Billing\Apple\PythonAppleSignedDataVerifier;
use App\Services\Billing\Apple\SymfonyAppleBridgeClient;

const APPLE_MONTHLY_PRODUCT = 'org.fynla.premium.monthly';
const APPLE_ANNUAL_PRODUCT = 'org.fynla.premium.annual';
const APPLE_ACCOUNT_TOKEN = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';

beforeEach(function () {
    config()->set('apple_store', [
        'bundle_id' => 'org.fynla.app',
        'allowed_product_ids' => [APPLE_MONTHLY_PRODUCT, APPLE_ANNUAL_PRODUCT],
        'environment' => 'sandbox',
        'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
        'app_apple_id' => null,
        'online_checks' => true,
        'python_executable' => PHP_BINARY,
        'bridge_cli_path' => base_path('tests/fixtures/apple_bridge_stub.php'),
        'process_timeout_seconds' => 40.0,
        'max_request_bytes' => 262_144,
        'max_response_bytes' => 1_048_576,
    ]);

    $this->bridge = new RecordingAppleBridgeClient;
    $this->verifier = new PythonAppleSignedDataVerifier($this->bridge);
});

it('sends signed data only in the trusted bridge payload and maps a transaction DTO', function () {
    $this->bridge->response = validAppleTransactionData();

    $transaction = $this->verifier->verifyTransaction(
        'private.signed.transaction',
        'sandbox',
        APPLE_ACCOUNT_TOKEN,
    );

    expect($this->bridge->calls)->toBe([[
        'verify_transaction',
        [
            'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
            'environment' => 'sandbox',
            'bundle_id' => 'org.fynla.app',
            'app_apple_id' => null,
            'allowed_product_ids' => [APPLE_MONTHLY_PRODUCT, APPLE_ANNUAL_PRODUCT],
            'expected_app_account_token' => APPLE_ACCOUNT_TOKEN,
            'signed_data' => 'private.signed.transaction',
        ],
    ]])
        ->and($transaction->transactionId)->toBe('transaction-1')
        ->and($transaction->originalTransactionId)->toBe('original-1')
        ->and($transaction->bundleId)->toBe('org.fynla.app')
        ->and($transaction->environment)->toBe('sandbox')
        ->and($transaction->productId)->toBe(APPLE_MONTHLY_PRODUCT)
        ->and($transaction->appAccountToken)->toBe(APPLE_ACCOUNT_TOKEN)
        ->and($transaction->purchaseDate?->format('Y-m-d\TH:i:s.v\Z'))->toBe('2024-07-03T09:46:40.000Z')
        ->and($transaction->expiresDate?->format('Y-m-d\TH:i:s.v\Z'))->toBe('2024-08-03T09:46:40.000Z')
        ->and($transaction->revocationDate)->toBeNull()
        ->and($transaction->ownershipType)->toBe('PURCHASED')
        ->and($transaction->transactionReason)->toBe('PURCHASE')
        ->and($transaction->signedDate?->format('Y-m-d\TH:i:s.v\Z'))->toBe('2024-07-03T09:46:40.100Z')
        ->and(array_keys(get_object_vars($transaction)))->not->toContain('signed_data', 'x5c');
});

it('maps nested notification transaction and renewal DTOs without raw signed values', function () {
    $this->bridge->response = [
        'notification_uuid' => '9ad56bd2-0bc6-42e0-af24-fd996d87a1e6',
        'notification_type' => 'DID_RENEW',
        'subtype' => 'BILLING_RECOVERY',
        'environment' => 'sandbox',
        'transaction' => validAppleTransactionData(),
        'renewal' => validAppleRenewalData(),
    ];

    $notification = $this->verifier->verifyNotification(
        'private.signed.notification',
        'sandbox',
    );

    expect($this->bridge->calls[0])->toBe([
        'verify_notification',
        [
            'root_certificate_path' => base_path('resources/certificates/apple/AppleRootCA-G3.cer'),
            'environment' => 'sandbox',
            'bundle_id' => 'org.fynla.app',
            'app_apple_id' => null,
            'allowed_product_ids' => [APPLE_MONTHLY_PRODUCT, APPLE_ANNUAL_PRODUCT],
            'expected_app_account_token' => null,
            'signed_data' => 'private.signed.notification',
        ],
    ])
        ->and($notification->notificationUuid)->toBe('9ad56bd2-0bc6-42e0-af24-fd996d87a1e6')
        ->and($notification->notificationType)->toBe('DID_RENEW')
        ->and($notification->subtype)->toBe('BILLING_RECOVERY')
        ->and($notification->environment)->toBe('sandbox')
        ->and($notification->transaction?->transactionId)->toBe('transaction-1')
        ->and($notification->renewal?->originalTransactionId)->toBe('original-1')
        ->and($notification->renewal?->autoRenewStatus)->toBe(1)
        ->and($notification->renewal?->isInBillingRetryPeriod)->toBeFalse()
        ->and($notification->renewal?->renewalDate?->format('Y-m-d\TH:i:s.v\Z'))->toBe('2024-08-03T09:46:40.000Z')
        ->and(array_keys(get_object_vars($notification)))->not->toContain('signed_data', 'x5c');
});

it('maps a notification with no nested subscription data', function () {
    $this->bridge->response = [
        'notification_uuid' => '9ad56bd2-0bc6-42e0-af24-fd996d87a1e6',
        'notification_type' => 'TEST',
        'subtype' => null,
        'environment' => 'sandbox',
        'transaction' => null,
        'renewal' => null,
    ];

    $notification = $this->verifier->verifyNotification('signed', 'sandbox');

    expect($notification->notificationType)->toBe('TEST')
        ->and($notification->subtype)->toBeNull()
        ->and($notification->transaction)->toBeNull()
        ->and($notification->renewal)->toBeNull();
});

it('rejects a caller environment that differs from trusted runtime configuration', function () {
    expect(fn () => $this->verifier->verifyTransaction(
        'private.signed.transaction',
        'production',
        APPLE_ACCOUNT_TOKEN,
    ))->toThrow(AppleVerificationException::class, 'invalid_configuration');

    expect($this->bridge->calls)->toBe([]);
});

it('rejects malformed or invalid transaction fields without exposing the signed value', function (Closure $mutate, string $expectedCode, bool $retryable) {
    $data = validAppleTransactionData();
    $mutate($data);
    $this->bridge->response = $data;

    try {
        $this->verifier->verifyTransaction(
            'private.signed.transaction',
            'sandbox',
            APPLE_ACCOUNT_TOKEN,
        );
        test()->fail('Expected malformed bridge response to fail closed.');
    } catch (AppleVerificationException $exception) {
        expect($exception->errorCode)->toBe($expectedCode)
            ->and($exception->retryable)->toBe($retryable)
            ->and($exception->getMessage())->toBe($expectedCode)
            ->and($exception->getMessage())->not->toContain('private.signed.transaction')
            ->and($exception->getPrevious())->toBeNull();
    }
})->with([
    'missing transaction ID' => [function (array &$data): void {
        unset($data['transaction_id']);
    }, 'verifier_unavailable', true],
    'extra raw field' => [function (array &$data): void {
        $data['signed_data'] = 'raw';
    }, 'verifier_unavailable', true],
    'bool date' => [function (array &$data): void {
        $data['purchase_date'] = true;
    }, 'verifier_unavailable', true],
    'wrong bundle' => [function (array &$data): void {
        $data['bundle_id'] = 'org.attacker.app';
    }, 'invalid_signed_data', false],
    'wrong environment' => [function (array &$data): void {
        $data['environment'] = 'production';
    }, 'invalid_signed_data', false],
    'wrong product' => [function (array &$data): void {
        $data['product_id'] = 'org.attacker.product';
    }, 'invalid_signed_data', false],
    'wrong token' => [function (array &$data): void {
        $data['app_account_token'] = '8e681f74-77f5-429f-96ee-6e56019d97f3';
    }, 'invalid_signed_data', false],
    'expiry before purchase' => [function (array &$data): void {
        $data['expires_date'] = 1_719_999_999_999;
    }, 'invalid_signed_data', false],
]);

it('preserves only a stable bridge failure without adding source detail', function () {
    $this->bridge->error = new AppleVerificationException(
        'retryable_verification_failure',
        true,
    );

    expect(fn () => $this->verifier->verifyTransaction(
        'private.signed.transaction',
        'sandbox',
        APPLE_ACCOUNT_TOKEN,
    ))->toThrow(AppleVerificationException::class, 'retryable_verification_failure');
});

it('resolves the bridge and verifier contracts through the Laravel container', function () {
    expect(app(AppleBridgeClient::class))->toBeInstanceOf(SymfonyAppleBridgeClient::class)
        ->and(app(AppleSignedDataVerifier::class))->toBeInstanceOf(PythonAppleSignedDataVerifier::class);
});

function validAppleTransactionData(): array
{
    return [
        'transaction_id' => 'transaction-1',
        'original_transaction_id' => 'original-1',
        'bundle_id' => 'org.fynla.app',
        'environment' => 'sandbox',
        'product_id' => APPLE_MONTHLY_PRODUCT,
        'app_account_token' => APPLE_ACCOUNT_TOKEN,
        'purchase_date' => 1_720_000_000_000,
        'expires_date' => 1_722_678_400_000,
        'revocation_date' => null,
        'ownership_type' => 'PURCHASED',
        'transaction_reason' => 'PURCHASE',
        'signed_date' => 1_720_000_000_100,
    ];
}

function validAppleRenewalData(): array
{
    return [
        'original_transaction_id' => 'original-1',
        'product_id' => APPLE_MONTHLY_PRODUCT,
        'auto_renew_product_id' => APPLE_ANNUAL_PRODUCT,
        'auto_renew_status' => 1,
        'renewal_date' => 1_722_678_400_000,
        'expiration_intent' => null,
        'grace_period_expires_date' => null,
        'is_in_billing_retry_period' => false,
        'environment' => 'sandbox',
        'signed_date' => 1_720_000_000_100,
    ];
}

final class RecordingAppleBridgeClient implements AppleBridgeClient
{
    public array $calls = [];

    public array $response = [];

    public ?AppleVerificationException $error = null;

    public function call(string $operation, array $payload): array
    {
        $this->calls[] = [$operation, $payload];

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->response;
    }
}
