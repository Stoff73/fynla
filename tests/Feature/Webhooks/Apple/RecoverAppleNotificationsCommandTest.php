<?php

declare(strict_types=1);

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Data\Billing\Apple\AppleReconciliationRenewalEvidence;
use App\Data\Billing\Apple\AppleReconciliationTransactionEvidence;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\AppleTransaction;
use App\Models\User;
use App\Services\Billing\Apple\AppleStoreServerClient;
use App\Services\Billing\Apple\AppleTransactionStore;
use Carbon\CarbonImmutable;

it('registers and schedules Apple notification evidence recovery', function (): void {
    $this->artisan('apple:notifications:recover')->assertSuccessful();

    $this->artisan('schedule:list')
        ->expectsOutputToContain('apple:notifications:recover')
        ->assertSuccessful();
});

it('reconciles recoverable evidence without rewriting its audit hashes', function (): void {
    config()->set('apple_store.environment', 'sandbox');
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();
    $batch = recoveryAppleBatch($token);
    $transaction = $batch->transactions[0]->transaction;
    app(AppleTransactionStore::class)->store(
        $user,
        $transaction,
        'initial.submission.jws',
    );
    $log = AppleNotificationLog::query()->create([
        'notification_uuid' => '836ef34d-8454-4f06-980a-e85fdf7919d4',
        'environment' => 'sandbox',
        'notification_type' => 'DID_RENEW',
        'subtype' => null,
        'signed_payload_sha256' => str_repeat('c', 64),
        'status' => AppleNotificationLog::STATUS_FAILED,
        'error_code' => 'verifier_unavailable',
    ]);
    $log->refresh();
    $originalAudit = $log->getAttributes();
    $client = new RecoveryAppleStoreServerClientStub($batch);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toBe([['recovery-original-1', $token]])
        ->and($log->fresh()->getAttributes())->toBe($originalAudit)
        ->and(AppleTransaction::query()->sole()->reconciled_at)->not->toBeNull();
});

it('retries failed evidence but skips permanent evidence without changing audit', function (
    AppleVerificationException $failure,
    string $initialStatus,
    int $expectedCalls,
): void {
    config()->set('apple_store.environment', 'sandbox');
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();
    $batch = recoveryAppleBatch($token);
    app(AppleTransactionStore::class)->store(
        $user,
        $batch->transactions[0]->transaction,
        'initial.submission.jws',
    );
    $log = AppleNotificationLog::query()->create([
        'notification_uuid' => '836ef34d-8454-4f06-980a-e85fdf7919d5',
        'environment' => 'sandbox',
        'notification_type' => 'DID_RENEW',
        'subtype' => null,
        'signed_payload_sha256' => str_repeat('c', 64),
        'status' => $initialStatus,
        'error_code' => 'previous_failure',
    ]);
    $log->refresh();
    $originalAudit = $log->getAttributes();
    $client = new RecoveryAppleStoreServerClientStub($batch, $failure);
    app()->instance(
        AppleStoreServerClient::class,
        $client,
    );

    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toHaveCount($expectedCalls)
        ->and($log->fresh()->getAttributes())->toBe($originalAudit);
})->with([
    'retryable failed evidence is retried' => [
        new AppleVerificationException('verifier_unavailable', true),
        AppleNotificationLog::STATUS_FAILED,
        1,
    ],
    'permanent rejected evidence is skipped' => [
        new AppleVerificationException('invalid_signed_data'),
        AppleNotificationLog::STATUS_REJECTED,
        0,
    ],
]);

it('bounds the distinct original transactions reconciled by one recovery signal', function (): void {
    config()->set('apple_store.environment', 'sandbox');
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();

    foreach (range(1, 3) as $sequence) {
        app(AppleTransactionStore::class)->store(
            $user,
            recoveryAppleTransaction($token, $sequence),
            "initial.submission.{$sequence}.jws",
        );
    }

    AppleNotificationLog::query()->create([
        'notification_uuid' => '836ef34d-8454-4f06-980a-e85fdf7919d6',
        'environment' => 'sandbox',
        'notification_type' => 'DID_RENEW',
        'subtype' => null,
        'signed_payload_sha256' => str_repeat('d', 64),
        'status' => AppleNotificationLog::STATUS_FAILED,
        'error_code' => 'verifier_unavailable',
    ]);
    $client = new RecoveryAppleStoreServerClientStub(
        recoveryAppleBatch($token),
        new AppleVerificationException('verifier_unavailable', true),
    );
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover --limit=2')->assertSuccessful();

    expect($client->calls)->toHaveCount(2);
});

function recoveryAppleBatch(string $token): AppleReconciliationBatch
{
    $purchase = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expires = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signed = CarbonImmutable::now('UTC')->startOfSecond();
    $transaction = new VerifiedAppleTransaction(
        'recovery-transaction-1',
        'recovery-original-1',
        'org.fynla.app',
        'sandbox',
        'org.fynla.premium.monthly',
        $token,
        $purchase,
        $expires,
        null,
        'PURCHASED',
        'RENEWAL',
        $signed,
    );
    $renewal = new VerifiedAppleRenewal(
        'recovery-original-1',
        'org.fynla.premium.monthly',
        'org.fynla.premium.monthly',
        1,
        $expires,
        null,
        null,
        false,
        'sandbox',
        $signed,
    );

    return new AppleReconciliationBatch(
        'recovery-original-1',
        [new AppleReconciliationTransactionEvidence(
            hash('sha256', 'initial.submission.jws'),
            $transaction,
        )],
        [new AppleReconciliationRenewalEvidence(str_repeat('b', 64), $renewal)],
    );
}

function recoveryAppleTransaction(string $token, int $sequence): VerifiedAppleTransaction
{
    $purchase = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expires = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signed = CarbonImmutable::now('UTC')->startOfSecond();

    return new VerifiedAppleTransaction(
        "recovery-transaction-{$sequence}",
        "recovery-original-{$sequence}",
        'org.fynla.app',
        'sandbox',
        'org.fynla.premium.monthly',
        $token,
        $purchase,
        $expires,
        null,
        'PURCHASED',
        'RENEWAL',
        $signed,
    );
}

final class RecoveryAppleStoreServerClientStub implements AppleStoreServerClient
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    public function __construct(
        private readonly AppleReconciliationBatch $batch,
        private readonly ?AppleVerificationException $failure = null,
    ) {}

    public function reconcile(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch {
        $this->calls[] = [$originalTransactionId, $expectedAppAccountToken];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->batch;
    }
}
