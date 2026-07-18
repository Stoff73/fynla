<?php

declare(strict_types=1);

use App\Data\Billing\Apple\AppleReconciliationBatch;
use App\Data\Billing\Apple\AppleReconciliationRenewalEvidence;
use App\Data\Billing\Apple\AppleReconciliationTransactionEvidence;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleTransaction;
use App\Models\NativeDeviceSession;
use App\Models\User;
use App\Services\Billing\Apple\AppleStoreServerClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    config()->set('apple_store.environment', 'sandbox');
    $this->appleStoreServer = new AppleStoreServerClientStub;
    app()->instance(AppleStoreServerClient::class, $this->appleStoreServer);
});

it('registers reconciliation and status behind the complete native sensitive boundary', function (): void {
    $reconcile = Route::getRoutes()->match(
        Request::create('/api/v1/native/storekit/reconcile', 'POST'),
    );
    $status = Route::getRoutes()->match(
        Request::create('/api/v1/native/storekit/status', 'GET'),
    );

    expect($reconcile->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\V1\\Native\\StoreKit\\AppleReconciliationController@reconcile')
        ->and($status->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\V1\\Native\\StoreKit\\AppleReconciliationController@status');

    foreach ([$reconcile, $status] as $route) {
        expect($route->gatherMiddleware())->toContain(
            'native.client',
            'auth:sanctum',
            'native.session',
            'throttle:sensitive',
        );
    }
});

it('reconciles verified server history for the signed-in stable account token and returns canonical status', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = appleReconciliationUser($token);
    $native = appleReconciliationNativeSession($user);
    $this->appleStoreServer->batch = appleReconciliationBatch($token);

    $response = $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->postJson('/api/v1/native/storekit/reconcile', [
            'original_transaction_id' => 'reconcile-original-1',
        ])->assertOk();

    expect($this->appleStoreServer->calls)->toBe([[
        'reconcile-original-1',
        $token,
    ]])->and($response->json('data.entitlement'))->toBe([
        'tier' => 'premium',
        'provider' => 'apple',
        'status' => 'active',
        'renews' => true,
        'current_period_end' => $this->appleStoreServer
            ->batch?->transactions[0]
            ->transaction->expiresDate?->toISOString(),
    ]);

    $stored = AppleTransaction::query()->sole();
    expect($stored->signed_payload_sha256)->toBe(str_repeat('a', 64))
        ->and($stored->reconciled_at)->not->toBeNull();

    $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->getJson('/api/v1/native/storekit/status')
        ->assertOk()
        ->assertJsonPath('data.entitlement.provider', 'apple')
        ->assertJsonPath('data.entitlement.status', 'active');
});

it('rejects client-supplied server credentials before calling Apple', function (): void {
    $user = appleReconciliationUser('75c42f38-62f1-4d0e-94ea-f8270f5d73fd');
    $native = appleReconciliationNativeSession($user);

    $response = $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->postJson('/api/v1/native/storekit/reconcile', [
            'original_transaction_id' => 'reconcile-original-1',
            'private_key' => 'client-private-key-must-not-be-accepted',
        ])->assertStatus(422);

    expect($this->appleStoreServer->calls)->toBe([])
        ->and($response->getContent())->not->toContain('client-private-key');
});

it('denies reconciliation without a server-issued stable account token', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = appleReconciliationNativeSession($user);

    $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->postJson('/api/v1/native/storekit/reconcile', [
            'original_transaction_id' => 'reconcile-original-1',
        ])->assertStatus(403);

    expect($this->appleStoreServer->calls)->toBe([]);
});

it('rejects verified history owned by another stable account token', function (): void {
    $user = appleReconciliationUser('75c42f38-62f1-4d0e-94ea-f8270f5d73fd');
    $native = appleReconciliationNativeSession($user);
    $this->appleStoreServer->batch = appleReconciliationBatch(
        '8e681f74-77f5-429f-96ee-6e56019d97f3',
    );

    $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->postJson('/api/v1/native/storekit/reconcile', [
            'original_transaction_id' => 'reconcile-original-1',
        ])->assertStatus(422);

    expect(AppleTransaction::query()->count())->toBe(0);
});

it('returns a retryable response when the App Store Server API is unavailable', function (): void {
    $user = appleReconciliationUser('75c42f38-62f1-4d0e-94ea-f8270f5d73fd');
    $native = appleReconciliationNativeSession($user);
    $this->appleStoreServer->failure = new AppleVerificationException(
        'verifier_unavailable',
        true,
    );

    $this->withToken($native['plain'])
        ->withHeaders(appleReconciliationHeaders())
        ->postJson('/api/v1/native/storekit/reconcile', [
            'original_transaction_id' => 'reconcile-original-1',
        ])->assertStatus(503);

    expect(AppleTransaction::query()->count())->toBe(0);
});

/** @return array<string, string> */
function appleReconciliationHeaders(): array
{
    return [
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ];
}

/** @return array{plain: string, token: PersonalAccessToken, session: NativeDeviceSession} */
function appleReconciliationNativeSession(User $user): array
{
    $issued = $user->createToken('apple-reconcile-test', ['native'], now()->addMinutes(15));
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

function appleReconciliationUser(string $token): User
{
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();

    return $user->fresh();
}

function appleReconciliationBatch(string $token): AppleReconciliationBatch
{
    $purchase = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expires = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signed = CarbonImmutable::now('UTC')->startOfSecond();
    $transaction = new VerifiedAppleTransaction(
        transactionId: 'reconcile-transaction-1',
        originalTransactionId: 'reconcile-original-1',
        bundleId: 'org.fynla.app',
        environment: 'sandbox',
        productId: 'org.fynla.premium.monthly',
        appAccountToken: $token,
        purchaseDate: $purchase,
        expiresDate: $expires,
        revocationDate: null,
        ownershipType: 'PURCHASED',
        transactionReason: 'RENEWAL',
        signedDate: $signed,
    );
    $renewal = new VerifiedAppleRenewal(
        originalTransactionId: 'reconcile-original-1',
        productId: 'org.fynla.premium.monthly',
        autoRenewProductId: 'org.fynla.premium.monthly',
        autoRenewStatus: 1,
        renewalDate: $expires,
        expirationIntent: null,
        gracePeriodExpiresDate: null,
        isInBillingRetryPeriod: false,
        environment: 'sandbox',
        signedDate: $signed,
    );

    return new AppleReconciliationBatch(
        originalTransactionId: 'reconcile-original-1',
        transactions: [
            new AppleReconciliationTransactionEvidence(str_repeat('a', 64), $transaction),
        ],
        renewals: [
            new AppleReconciliationRenewalEvidence(str_repeat('b', 64), $renewal),
        ],
    );
}

final class AppleStoreServerClientStub implements AppleStoreServerClient
{
    public ?AppleReconciliationBatch $batch = null;

    public ?AppleVerificationException $failure = null;

    /** @var list<array{string, string}> */
    public array $calls = [];

    public function reconcile(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch {
        $this->calls[] = [$originalTransactionId, $expectedAppAccountToken];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->batch
            ?? throw new RuntimeException('No reconciliation batch configured.');
    }
}
