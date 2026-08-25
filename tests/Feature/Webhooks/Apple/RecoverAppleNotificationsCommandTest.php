<?php

declare(strict_types=1);

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Data\Billing\Apple\AppleReconciliationRenewalEvidence;
use App\Data\Billing\Apple\AppleReconciliationStatusEvidence;
use App\Data\Billing\Apple\AppleReconciliationTransactionEvidence;
use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Http\Controllers\Api\Webhooks\AppleNotificationController;
use App\Models\AppleNotificationLog;
use App\Models\AppleNotificationRecovery;
use App\Models\AppleTransaction;
use App\Models\User;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use App\Services\Billing\Apple\AppleStoreServerClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('registers and schedules Apple notification evidence recovery', function (): void {
    $this->artisan('apple:notifications:recover')->assertSuccessful();

    $this->artisan('schedule:list')
        ->expectsOutputToContain('apple:notifications:recover')
        ->assertSuccessful();
});

it('durably recovers an initially failed verified notification with no committed transaction', function (): void {
    config()->set('apple_store.environment', 'sandbox');
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = recoveryUser($token);
    $notification = recoveryNotification($token, 'recovery-original-1', 1);
    app()->instance(
        AppleSignedDataVerifier::class,
        new RecoveryNotificationVerifierStub($notification),
    );
    config()->set('app.timezone', 'Invalid/Timezone');

    $request = Request::create(
        '/api/webhooks/apple/v2',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode([
            'signedPayload' => 'outer.recovery.initial.failure',
        ], JSON_THROW_ON_ERROR),
    );
    $response = app(AppleNotificationController::class)($request);

    expect($response->getStatusCode())->toBe(503);

    config()->set('app.timezone', 'Europe/London');
    $log = AppleNotificationLog::query()->sole();
    $context = AppleNotificationRecovery::query()->sole();
    expect(AppleTransaction::query()->count())->toBe(0)
        ->and($log->status)->toBe(AppleNotificationLog::STATUS_FAILED)
        ->and($context->status)->toBe(AppleNotificationRecovery::STATUS_FAILED)
        ->and($context->user_id)->toBe($user->id)
        ->and($context->original_transaction_id)->toBe('recovery-original-1');
    $auditHash = $log->signed_payload_sha256;
    $client = new RecoveryAppleStoreServerClientStub([
        'recovery-original-1' => recoveryAppleBatch($token, 'recovery-original-1', 1),
    ]);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toBe([['recovery-original-1', $token]])
        ->and(AppleTransaction::query()->count())->toBe(1)
        ->and($log->fresh()->status)->toBe(AppleNotificationLog::STATUS_PROCESSED)
        ->and($log->fresh()->signed_payload_sha256)->toBe($auditHash)
        ->and($context->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_PROCESSED)
        ->and($context->fresh()->processed_at)->not->toBeNull();
});

it('reconciles only the original transaction linked to the failed notification', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = recoveryUser($token);
    recoveryContext($user, 'recovery-original-1', 1);
    AppleTransaction::query()->create([
        'user_id' => $user->id,
        'premium_entitlement_id' => recoveryEntitlementId($user, 'unrelated-original'),
        'transaction_id' => 'unrelated-transaction',
        'original_transaction_id' => 'unrelated-original',
        'app_account_token' => $token,
        'product_id' => 'org.fynla.premium.monthly',
        'environment' => 'sandbox',
        'purchased_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
        'ownership_type' => 'PURCHASED',
        'transaction_reason' => 'RENEWAL',
        'signed_payload_sha256' => str_repeat('e', 64),
        'received_at' => now(),
    ]);
    $client = new RecoveryAppleStoreServerClientStub([
        'recovery-original-1' => recoveryAppleBatch($token, 'recovery-original-1', 1),
    ]);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toBe([['recovery-original-1', $token]]);
});

it('rejects a permanent recovery failure and never retries it', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $context = recoveryContext(recoveryUser($token), 'recovery-original-1', 1);
    $client = new RecoveryAppleStoreServerClientStub([], [
        'recovery-original-1' => [
            new AppleVerificationException('invalid_signed_data'),
        ],
    ]);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover')->assertSuccessful();
    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toHaveCount(1)
        ->and($context->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_REJECTED)
        ->and($context->fresh()->notificationLog->status)->toBe(AppleNotificationLog::STATUS_REJECTED)
        ->and($context->fresh()->notificationLog->processed_at)->not->toBeNull();
});

it('keeps retryable recovery due and later resolves the same audit', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $context = recoveryContext(recoveryUser($token), 'recovery-original-1', 1);
    $client = new RecoveryAppleStoreServerClientStub([
        'recovery-original-1' => recoveryAppleBatch($token, 'recovery-original-1', 1),
    ], [
        'recovery-original-1' => [
            new AppleVerificationException('verifier_unavailable', true),
        ],
    ]);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($context->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_FAILED)
        ->and($context->fresh()->notificationLog->status)->toBe(AppleNotificationLog::STATUS_FAILED)
        ->and($context->fresh()->next_attempt_at)->not->toBeNull()
        ->and($context->fresh()->attempt_count)->toBe(1);

    $context->forceFill(['next_attempt_at' => now()->subSecond()])->save();
    $this->artisan('apple:notifications:recover')->assertSuccessful();

    expect($client->calls)->toHaveCount(2)
        ->and($context->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_PROCESSED)
        ->and($context->fresh()->notificationLog->status)->toBe(AppleNotificationLog::STATUS_PROCESSED)
        ->and($context->fresh()->attempt_count)->toBe(2);
});

it('bounds due contexts fairly without touching unrelated audits', function (): void {
    $contexts = collect();
    $batches = [];
    foreach (range(1, 3) as $sequence) {
        $token = sprintf('75c42f38-62f1-4d0e-94ea-f8270f5d7%03d', 300 + $sequence);
        $original = "recovery-original-{$sequence}";
        $contexts->push(recoveryContext(recoveryUser($token), $original, $sequence));
        $batches[$original] = recoveryAppleBatch($token, $original, $sequence);
    }
    $client = new RecoveryAppleStoreServerClientStub($batches);
    app()->instance(AppleStoreServerClient::class, $client);

    $this->artisan('apple:notifications:recover --limit=2')->assertSuccessful();

    expect($client->calls)->toHaveCount(2)
        ->and($contexts->take(2)->every(
            fn (AppleNotificationRecovery $context): bool => $context->fresh()->status
                === AppleNotificationRecovery::STATUS_PROCESSED,
        ))->toBeTrue()
        ->and($contexts->last()->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_FAILED)
        ->and($contexts->last()->fresh()->notificationLog->status)->toBe(AppleNotificationLog::STATUS_FAILED);

    $this->artisan('apple:notifications:recover --limit=2')->assertSuccessful();

    expect($client->calls)->toHaveCount(3)
        ->and($contexts->last()->fresh()->status)->toBe(AppleNotificationRecovery::STATUS_PROCESSED);
});

function recoveryUser(string $token): User
{
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();

    return $user->fresh();
}

function recoveryContext(User $user, string $original, int $sequence): AppleNotificationRecovery
{
    $log = AppleNotificationLog::query()->create([
        'notification_uuid' => sprintf('836ef34d-8454-4f06-980a-e85fdf791%03d', $sequence),
        'environment' => 'sandbox',
        'notification_type' => 'DID_RENEW',
        'subtype' => null,
        'signed_payload_sha256' => hash('sha256', "audit-{$sequence}"),
        'status' => AppleNotificationLog::STATUS_FAILED,
        'error_code' => 'previous_failure',
    ]);

    return AppleNotificationRecovery::query()->create([
        'apple_notification_log_id' => $log->id,
        'user_id' => $user->id,
        'original_transaction_id' => $original,
        'status' => AppleNotificationRecovery::STATUS_FAILED,
        'error_code' => 'previous_failure',
    ]);
}

function recoveryEntitlementId(User $user, string $original): int
{
    return (int) DB::table('premium_entitlements')->insertGetId([
        'user_id' => $user->id,
        'provider' => 'apple',
        'provider_reference' => $original,
        'product_id' => 'org.fynla.premium.monthly',
        'status' => 'active',
        'will_renew' => true,
        'period_start' => now()->subDay(),
        'period_end' => now()->addMonth(),
        'last_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function recoveryNotification(
    string $token,
    string $original,
    int $sequence,
): VerifiedAppleNotification {
    $batch = recoveryAppleBatch($token, $original, $sequence);

    return new VerifiedAppleNotification(
        notificationUuid: sprintf('d9f625d8-ef5a-4ee4-a2b9-19163e2d6%03d', $sequence),
        notificationType: 'DID_RENEW',
        subtype: null,
        environment: 'sandbox',
        transaction: $batch->transactions[0]->transaction,
        renewal: $batch->statuses[0]->renewal?->renewal,
        transactionSignedPayloadSha256: $batch->transactions[0]->signedPayloadSha256,
        renewalSignedPayloadSha256: $batch->statuses[0]->renewal?->signedPayloadSha256,
    );
}

function recoveryAppleBatch(
    string $token,
    string $original,
    int $sequence,
): AppleReconciliationBatch {
    $purchase = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expires = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signed = CarbonImmutable::now('UTC')->startOfSecond();
    $transaction = new VerifiedAppleTransaction(
        "recovery-transaction-{$sequence}",
        $original,
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
        $original,
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
        $original,
        [new AppleReconciliationTransactionEvidence(
            hash('sha256', "recovery-transaction-{$sequence}"),
            $transaction,
        )],
        [new AppleReconciliationStatusEvidence(
            originalTransactionId: $original,
            subscriptionStatus: AppleReconciliationStatusEvidence::ACTIVE,
            transaction: null,
            renewal: new AppleReconciliationRenewalEvidence(str_repeat('b', 64), $renewal),
        )],
    );
}

final class RecoveryNotificationVerifierStub implements AppleSignedDataVerifier
{
    public function __construct(
        private readonly VerifiedAppleNotification $notification,
    ) {}

    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction {
        throw new RuntimeException('Transaction verification was not expected.');
    }

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification {
        return $this->notification;
    }
}

final class RecoveryAppleStoreServerClientStub implements AppleStoreServerClient
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    /**
     * @param  array<string, AppleReconciliationBatch>  $batches
     * @param  array<string, list<AppleVerificationException>>  $failures
     */
    public function __construct(
        private readonly array $batches,
        private array $failures = [],
    ) {}

    public function reconcile(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch {
        $this->calls[] = [$originalTransactionId, $expectedAppAccountToken];

        $queuedFailures = $this->failures[$originalTransactionId] ?? [];
        $failure = array_shift($queuedFailures);
        $this->failures[$originalTransactionId] = $queuedFailures;
        if ($failure instanceof AppleVerificationException) {
            throw $failure;
        }

        return $this->batches[$originalTransactionId]
            ?? throw new RuntimeException('No reconciliation batch configured.');
    }
}
