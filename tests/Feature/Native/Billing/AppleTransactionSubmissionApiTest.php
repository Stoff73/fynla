<?php

declare(strict_types=1);

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleTransaction;
use App\Models\NativeDeviceSession;
use App\Models\PremiumEntitlement;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use App\Services\Billing\Apple\AppleTransactionStore;
use App\Services\Billing\PremiumEntitlementResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    config()->set('apple_store.environment', 'sandbox');

    $this->appleVerifier = new SubmissionAppleVerifierStub;
    app()->instance(AppleSignedDataVerifier::class, $this->appleVerifier);
});

function appleSubmissionPath(): string
{
    return '/api/v1/native/storekit/transactions';
}

/** @return array<string, string> */
function appleSubmissionHeaders(): array
{
    return [
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ];
}

/** @return array{plain: string, token: PersonalAccessToken, session: NativeDeviceSession} */
function appleSubmissionNativeSession(User $user): array
{
    $issued = $user->createToken('apple-submission-test', ['native'], now()->addMinutes(15));
    $session = NativeDeviceSession::factory()->for($user)->create([
        'current_access_token_id' => $issued->accessToken->id,
        'absolute_expires_at' => now()->addDay(),
        'revoked_at' => null,
    ]);

    return [
        'plain' => $issued->plainTextToken,
        'token' => $issued->accessToken,
        'session' => $session,
    ];
}

function appleSubmissionUser(string $accountToken): User
{
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $accountToken])->save();

    return $user->fresh();
}

function cleanupCommittedAppleSubmissionUser(int $userId): void
{
    User::withTrashed()->whereKey($userId)->forceDelete();
    TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
}

/** @param array<string, mixed> $overrides */
function verifiedAppleSubmission(array $overrides = []): VerifiedAppleTransaction
{
    $purchaseDate = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expiresDate = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signedDate = CarbonImmutable::now('UTC')->startOfSecond();

    return new VerifiedAppleTransaction(
        transactionId: $overrides['transactionId'] ?? 'apple-transaction-1',
        originalTransactionId: $overrides['originalTransactionId'] ?? 'apple-original-1',
        bundleId: $overrides['bundleId'] ?? 'org.fynla.app',
        environment: $overrides['environment'] ?? 'sandbox',
        productId: $overrides['productId'] ?? 'org.fynla.premium.monthly',
        appAccountToken: $overrides['appAccountToken'] ?? '75c42f38-62f1-4d0e-94ea-f8270f5d73fd',
        purchaseDate: array_key_exists('purchaseDate', $overrides)
            ? $overrides['purchaseDate']
            : $purchaseDate,
        expiresDate: array_key_exists('expiresDate', $overrides)
            ? $overrides['expiresDate']
            : $expiresDate,
        revocationDate: array_key_exists('revocationDate', $overrides)
            ? $overrides['revocationDate']
            : null,
        ownershipType: array_key_exists('ownershipType', $overrides)
            ? $overrides['ownershipType']
            : 'PURCHASED',
        transactionReason: array_key_exists('transactionReason', $overrides)
            ? $overrides['transactionReason']
            : 'PURCHASE',
        signedDate: array_key_exists('signedDate', $overrides)
            ? $overrides['signedDate']
            : $signedDate,
    );
}

it('registers the submission route behind the complete native sensitive boundary', function (): void {
    $route = Route::getRoutes()->match(Request::create(appleSubmissionPath(), 'POST'));
    $middleware = $route->gatherMiddleware();

    expect($route->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\V1\\Native\\StoreKit\\AppleTransactionController')
        ->and($middleware)->toContain(
            'native.client',
            'auth:sanctum',
            'native.session',
            'throttle:sensitive',
        )
        ->and(array_search('native.client', $middleware, true))
        ->toBeLessThan(array_search('auth:sanctum', $middleware, true))
        ->and(array_search('auth:sanctum', $middleware, true))
        ->toBeLessThan(array_search('native.session', $middleware, true));
});

it('verifies before persisting a valid purchase and returns the canonical entitlement', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $verified = verifiedAppleSubmission(['appAccountToken' => $accountToken]);
    $this->appleVerifier->transaction = $verified;
    $signedTransaction = 'header.private-payload.signature';
    $baselineTransactionLevel = DB::transactionLevel();

    $response = $this->withToken($native['plain'])
        ->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => $signedTransaction])
        ->assertOk();

    expect($this->appleVerifier->transactionCalls)->toBe([[
        $signedTransaction,
        'sandbox',
        $accountToken,
    ]])->and($this->appleVerifier->transactionLevels)->toBe([
        $baselineTransactionLevel,
    ])->and($response->json())->toBe([
        'success' => true,
        'data' => [
            'acknowledged' => true,
            'transaction_id' => $verified->transactionId,
            'entitlement' => [
                'tier' => 'premium',
                'provider' => 'apple',
                'status' => 'active',
                'renews' => true,
                'current_period_end' => $verified->expiresDate?->toISOString(),
            ],
        ],
    ]);

    $entitlement = PremiumEntitlement::query()->sole();
    $transaction = AppleTransaction::query()->sole();

    expect($entitlement->user_id)->toBe($user->id)
        ->and($entitlement->provider)->toBe(PremiumEntitlement::PROVIDER_APPLE)
        ->and($entitlement->provider_reference)->toBe($verified->originalTransactionId)
        ->and($entitlement->product_id)->toBe($verified->productId)
        ->and($entitlement->status)->toBe(PremiumEntitlement::STATUS_ACTIVE)
        ->and($entitlement->will_renew)->toBeTrue()
        ->and($entitlement->period_start?->toISOString())->toBe($verified->purchaseDate?->toISOString())
        ->and($entitlement->period_end?->toISOString())->toBe($verified->expiresDate?->toISOString())
        ->and($transaction->user_id)->toBe($user->id)
        ->and($transaction->premium_entitlement_id)->toBe($entitlement->id)
        ->and($transaction->transaction_id)->toBe($verified->transactionId)
        ->and($transaction->original_transaction_id)->toBe($verified->originalTransactionId)
        ->and($transaction->signed_payload_sha256)->toBe(hash('sha256', $signedTransaction))
        ->and(json_encode($entitlement->getAttributes(), JSON_THROW_ON_ERROR))->not->toContain($signedTransaction)
        ->and(json_encode($transaction->getAttributes(), JSON_THROW_ON_ERROR))->not->toContain($signedTransaction);
});

it('re-verifies a duplicate and acknowledges the one durable transaction without duplicated effects', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $this->appleVerifier->transaction = verifiedAppleSubmission(['appAccountToken' => $accountToken]);
    $payload = ['signed_transaction' => 'header.duplicate.signature'];

    $first = $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), $payload)->assertOk();
    $second = $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), $payload)->assertOk();

    expect($second->json())->toBe($first->json())
        ->and($this->appleVerifier->transactionCalls)->toHaveCount(2)
        ->and(AppleTransaction::query()->count())->toBe(1)
        ->and(PremiumEntitlement::query()->count())->toBe(1);
});

it('rejects a verified transaction associated with another users token without granting access', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $otherToken = '2efc1b61-4c5f-4cf4-8389-4db4f90044ed';
    $user = appleSubmissionUser($accountToken);
    appleSubmissionUser($otherToken);
    $native = appleSubmissionNativeSession($user);
    $this->appleVerifier->transaction = verifiedAppleSubmission(['appAccountToken' => $otherToken]);

    $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => 'header.foreign.signature'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'error' => 'apple_transaction_forbidden',
            'message' => 'The verified StoreKit transaction is not associated with this account.',
        ]);

    expect(AppleTransaction::query()->count())->toBe(0)
        ->and(PremiumEntitlement::query()->count())->toBe(0);
});

it('fails closed on verification failure and rejects non-exact or oversized input before verification', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $this->appleVerifier->exception = new AppleVerificationException('invalid_signed_data');

    $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => 'header.invalid.signature'])
        ->assertStatus(422)
        ->assertExactJson([
            'success' => false,
            'error' => 'apple_transaction_invalid',
            'message' => 'The StoreKit transaction could not be verified.',
        ]);

    $callsAfterVerificationFailure = count($this->appleVerifier->transactionCalls);
    $this->appleVerifier->exception = null;

    foreach ([
        ['signed_transaction' => str_repeat('x', 65_537)],
        ['signed_transaction' => 'header.extra.signature', 'unexpected' => true],
        [],
    ] as $payload) {
        app('auth')->forgetGuards();
        $validationUser = appleSubmissionUser((string) Str::uuid());
        $validationNative = appleSubmissionNativeSession($validationUser);

        $this->flushHeaders()->withToken($validationNative['plain'])->withHeaders(appleSubmissionHeaders())
            ->postJson(appleSubmissionPath(), $payload)
            ->assertStatus(422);
    }

    expect($this->appleVerifier->transactionCalls)->toHaveCount($callsAfterVerificationFailure)
        ->and(AppleTransaction::query()->count())->toBe(0)
        ->and(PremiumEntitlement::query()->count())->toBe(0);
});

it('returns a stable retryable failure without persisting verifier source details', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $this->appleVerifier->exception = new AppleVerificationException(
        'verifier_unavailable',
        retryable: true,
    );

    $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), [
            'signed_transaction' => 'header.private-source-detail.signature',
        ])
        ->assertStatus(503)
        ->assertExactJson([
            'success' => false,
            'error' => 'apple_verifier_unavailable',
            'message' => 'StoreKit verification is temporarily unavailable.',
        ]);

    expect(AppleTransaction::query()->count())->toBe(0)
        ->and(PremiumEntitlement::query()->count())->toBe(0);
});

it('denies preview users and accounts without a server-issued token before verification', function (): void {
    $withoutToken = User::factory()->create(['is_preview_user' => false]);
    $withoutTokenNative = appleSubmissionNativeSession($withoutToken);
    $preview = User::factory()->preview()->create();
    $preview->forceFill([
        'apple_app_account_token' => '2efc1b61-4c5f-4cf4-8389-4db4f90044ed',
    ])->save();
    $previewNative = appleSubmissionNativeSession($preview);

    foreach ([
        [$withoutToken, $withoutTokenNative],
        [$preview, $previewNative],
    ] as [$user, $native]) {
        app('auth')->forgetGuards();
        $this->flushHeaders()->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
            ->postJson(appleSubmissionPath(), [
                'signed_transaction' => 'header.unowned.signature',
            ])
            ->assertForbidden();
    }

    expect($this->appleVerifier->transactionCalls)->toBe([])
        ->and(AppleTransaction::query()->count())->toBe(0)
        ->and(PremiumEntitlement::query()->count())->toBe(0);
});

it('fails closed when a verifier double returns invalid subscription semantics', function (array $overrides): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $this->appleVerifier->transaction = verifiedAppleSubmission([
        'appAccountToken' => $accountToken,
        ...$overrides,
    ]);

    $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => 'header.mismatch.signature'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'apple_transaction_invalid');

    expect(AppleTransaction::query()->count())->toBe(0)
        ->and(PremiumEntitlement::query()->count())->toBe(0);
})->with([
    'wrong bundle' => [['bundleId' => 'org.attacker.app']],
    'wrong environment' => [['environment' => 'production']],
    'wrong product' => [['productId' => 'org.fynla.premium.unapproved']],
    'missing subscription expiry' => [['expiresDate' => null]],
]);

it('projects a newer verified revocation and removes canonical premium access', function (): void {
    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $active = verifiedAppleSubmission(['appAccountToken' => $accountToken]);
    $this->appleVerifier->transaction = $active;

    $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => 'header.active.signature'])
        ->assertOk();

    $revokedAt = CarbonImmutable::now('UTC')->addMinute()->startOfSecond();
    $this->appleVerifier->transaction = verifiedAppleSubmission([
        'transactionId' => 'apple-transaction-revoked',
        'originalTransactionId' => $active->originalTransactionId,
        'appAccountToken' => $accountToken,
        'purchaseDate' => $active->purchaseDate,
        'expiresDate' => $active->expiresDate,
        'revocationDate' => $revokedAt,
        'signedDate' => $revokedAt,
    ]);

    $response = $this->withToken($native['plain'])->withHeaders(appleSubmissionHeaders())
        ->postJson(appleSubmissionPath(), ['signed_transaction' => 'header.revoked.signature'])
        ->assertOk();

    expect($response->json('data.entitlement'))->toBe([
        'tier' => 'free',
        'provider' => null,
        'status' => 'free',
        'renews' => false,
        'current_period_end' => null,
    ])->and(AppleTransaction::query()->count())->toBe(2);

    $entitlement = PremiumEntitlement::query()->sole();
    expect($entitlement->status)->toBe(PremiumEntitlement::STATUS_REVOKED)
        ->and($entitlement->will_renew)->toBeFalse()
        ->and($entitlement->revoked_at?->toISOString())->toBe($revokedAt->toISOString());
});

it('serializes two concurrent duplicate posts into one durable transaction and one entitlement', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    $accountToken = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleSubmissionUser($accountToken);
    $native = appleSubmissionNativeSession($user);
    $verified = verifiedAppleSubmission(['appAccountToken' => $accountToken]);
    $signedTransaction = 'header.concurrent.signature';
    $userId = $user->id;
    DB::commit();

    $blockerName = 'apple_submission_blocker';
    config(["database.connections.{$blockerName}" => config('database.connections.mysql')]);
    $blocker = DB::connection($blockerName);
    $blocker->beginTransaction();
    $blocker->table('users')->where('id', $userId)->lockForUpdate()->first();
    $workers = [];

    try {
        foreach ([1, 2] as $workerNumber) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            expect($sockets)->not->toBeFalse();
            $pid = pcntl_fork();
            expect($pid)->toBeGreaterThanOrEqual(0);

            if ($pid === 0) {
                fclose($sockets[0]);
                DB::purge();
                DB::connection()->getPdo();
                app()->forgetInstance(AppleTransactionStore::class);
                app()->forgetInstance(PremiumEntitlementResolver::class);
                $verifier = new SubmissionAppleVerifierStub;
                $verifier->transaction = $verified;
                app()->instance(AppleSignedDataVerifier::class, $verifier);
                fwrite($sockets[1], "ready\n");
                fgets($sockets[1]);
                fwrite($sockets[1], "posting\n");

                try {
                    app('auth')->forgetGuards();
                    $response = $this->flushHeaders()
                        ->withToken($native['plain'])
                        ->withHeaders(appleSubmissionHeaders())
                        ->postJson(appleSubmissionPath(), [
                            'signed_transaction' => $signedTransaction,
                        ]);
                    $result = [
                        'status' => $response->getStatusCode(),
                        'json' => $response->json(),
                    ];
                } catch (Throwable $exception) {
                    $result = [
                        'status' => 0,
                        'type' => $exception::class,
                        'message' => $exception->getMessage(),
                    ];
                }

                fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($sockets[1]);
                exit(0);
            }

            fclose($sockets[1]);
            stream_set_timeout($sockets[0], 15);
            $workers[] = [
                'pid' => $pid,
                'socket' => $sockets[0],
                'number' => $workerNumber,
            ];
        }

        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "go\n");
        }
        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('posting');
        }
        $blocker->commit();

        $results = [];
        foreach ($workers as $worker) {
            $results[] = json_decode(
                (string) fgets($worker['socket']),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        DB::purge();
        DB::connection()->getPdo();

        expect(collect($results)->pluck('status')->all())->toBe([200, 200])
            ->and($results[1]['json'])->toBe($results[0]['json'])
            ->and($results[0]['json']['data']['transaction_id'])->toBe($verified->transactionId)
            ->and(AppleTransaction::query()->count())->toBe(1)
            ->and(PremiumEntitlement::query()->count())->toBe(1);
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        cleanupCommittedAppleSubmissionUser($userId);
        DB::beginTransaction();
    }
});

final class SubmissionAppleVerifierStub implements AppleSignedDataVerifier
{
    public ?VerifiedAppleTransaction $transaction = null;

    public ?AppleVerificationException $exception = null;

    /** @var list<array{string, string, string|null}> */
    public array $transactionCalls = [];

    /** @var list<int> */
    public array $transactionLevels = [];

    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction {
        $this->transactionCalls[] = [$jws, $expectedEnvironment, $expectedAppAccountToken];
        $this->transactionLevels[] = DB::transactionLevel();

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->transaction
            ?? throw new AppleVerificationException('verifier_unavailable', retryable: true);
    }

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification {
        throw new AppleVerificationException('unsupported_operation');
    }
}
