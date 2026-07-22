<?php

declare(strict_types=1);

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Http\Controllers\Api\Webhooks\AppleNotificationController;
use App\Models\AppleNotificationLog;
use App\Models\AppleTransaction;
use App\Models\PremiumEntitlement;
use App\Models\User;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use App\Services\Billing\Apple\AppleTransactionStore;
use App\Services\Billing\PremiumEntitlementResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('apple_store.environment', 'sandbox');

    $this->appleNotificationVerifier = new AppleNotificationVerifierStub;
    app()->instance(
        AppleSignedDataVerifier::class,
        $this->appleNotificationVerifier,
    );
});

it('registers the public App Store Server Notifications V2 endpoint', function (): void {
    $route = Route::getRoutes()->match(
        Request::create('/api/webhooks/apple/v2', 'POST'),
    );

    expect($route->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\Webhooks\\AppleNotificationController');
});

it('verifies and acknowledges a TEST notification before retaining safe audit evidence', function (): void {
    $signedPayload = 'outer.test.signature';
    $this->appleNotificationVerifier->notification = new VerifiedAppleNotification(
        notificationUuid: 'd9f625d8-ef5a-4ee4-a2b9-19163e2d6f95',
        notificationType: 'TEST',
        subtype: null,
        environment: 'sandbox',
        transaction: null,
        renewal: null,
    );

    $response = $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => $signedPayload,
    ])->assertOk();

    expect($this->appleNotificationVerifier->notificationCalls)->toBe([[
        $signedPayload,
        'sandbox',
    ]])->and($response->json())->toBe([
        'success' => true,
        'data' => [
            'acknowledged' => true,
            'duplicate' => false,
        ],
    ]);

    $log = AppleNotificationLog::query()->sole();

    expect($log->notification_uuid)
        ->toBe('d9f625d8-ef5a-4ee4-a2b9-19163e2d6f95')
        ->and($log->notification_type)->toBe('TEST')
        ->and($log->environment)->toBe('sandbox')
        ->and($log->signed_payload_sha256)->toBe(hash('sha256', $signedPayload))
        ->and($log->status)->toBe(AppleNotificationLog::STATUS_PROCESSED)
        ->and($log->processed_at)->not->toBeNull()
        ->and(json_encode($log->getAttributes(), JSON_THROW_ON_ERROR))
        ->not->toContain($signedPayload);
});

it('rejects malformed webhook bodies before verification', function (array $body): void {
    $this->postJson('/api/webhooks/apple/v2', $body)->assertStatus(422);

    expect($this->appleNotificationVerifier->notificationCalls)->toBe([])
        ->and(AppleNotificationLog::query()->count())->toBe(0);
})->with([
    'missing signedPayload' => [[]],
    'non-string signedPayload' => [['signedPayload' => ['not-a-jws']]],
    'empty signedPayload' => [['signedPayload' => '']],
    'whitespace signedPayload' => [['signedPayload' => ' outer.signature ']],
    'signedPayload over 256 KiB' => [['signedPayload' => str_repeat('a', (256 * 1024) + 1)]],
    'unexpected sibling field' => [[
        'signedPayload' => 'outer.signature',
        'serverCredential' => 'must-not-be-accepted',
    ]],
]);

it('classifies verifier failures without leaking signed data', function (
    AppleVerificationException $failure,
    int $expectedStatus,
): void {
    $signedPayload = 'outer.private-payload.signature';
    $this->appleNotificationVerifier->notificationFailure = $failure;

    $response = $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => $signedPayload,
    ])->assertStatus($expectedStatus);

    expect($response->getContent())->not->toContain($signedPayload)
        ->and(AppleNotificationLog::query()->count())->toBe(0);
})->with([
    'permanently invalid signature' => [
        new AppleVerificationException('invalid_signed_data'),
        422,
    ],
    'retryable verifier failure' => [
        new AppleVerificationException('retryable_verification_failure', true),
        503,
    ],
    'invalid server configuration' => [
        new AppleVerificationException('invalid_configuration'),
        503,
    ],
]);

it('contains an unexpected verifier failure without exposing signed data', function (): void {
    $signedPayload = 'outer.unexpected-private.signature';
    $this->appleNotificationVerifier->unexpectedNotificationFailure = new RuntimeException(
        $signedPayload,
    );

    $response = $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => $signedPayload,
    ])->assertStatus(503);

    expect($response->getContent())->not->toContain($signedPayload)
        ->and(AppleNotificationLog::query()->count())->toBe(0);
});

it('returns 200 for a verified duplicate without rewriting the original audit record', function (): void {
    $signedPayload = 'outer.test.signature';
    $this->appleNotificationVerifier->notification = new VerifiedAppleNotification(
        notificationUuid: 'd9f625d8-ef5a-4ee4-a2b9-19163e2d6f95',
        notificationType: 'TEST',
        subtype: null,
        environment: 'sandbox',
        transaction: null,
        renewal: null,
    );

    $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => $signedPayload,
    ])->assertOk();

    $second = $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => $signedPayload,
    ])->assertOk();

    expect($second->json('data.duplicate'))->toBeTrue()
        ->and($this->appleNotificationVerifier->notificationCalls)->toHaveCount(2)
        ->and(AppleNotificationLog::query()->count())->toBe(1)
        ->and(AppleNotificationLog::query()->sole()->status)
        ->toBe(AppleNotificationLog::STATUS_PROCESSED);
});

it('rejects a verified UUID replay with different signed evidence', function (): void {
    $this->appleNotificationVerifier->notification = new VerifiedAppleNotification(
        notificationUuid: 'd9f625d8-ef5a-4ee4-a2b9-19163e2d6f95',
        notificationType: 'TEST',
        subtype: null,
        environment: 'sandbox',
        transaction: null,
        renewal: null,
    );

    $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => 'first.outer.signature',
    ])->assertOk();

    $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => 'different.outer.signature',
    ])->assertStatus(422);

    $log = AppleNotificationLog::query()->sole();

    expect($log->signed_payload_sha256)
        ->toBe(hash('sha256', 'first.outer.signature'))
        ->and($log->status)->toBe(AppleNotificationLog::STATUS_PROCESSED);
});

it('uses safe nested evidence while keeping notification audit non-personal', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();
    $this->appleNotificationVerifier->notification = appleWebhookSubscriptionNotification($token);

    $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => 'outer.subscription.signature',
    ])->assertOk();

    $log = AppleNotificationLog::query()->sole();
    expect($log->signed_payload_sha256)
        ->toBe(hash('sha256', 'outer.subscription.signature'))
        ->and(array_keys($log->getAttributes()))->not->toContain(
            'user_id',
            'original_transaction_id',
            'transaction_signed_payload_sha256',
            'renewal_signed_payload_sha256',
        )
        ->and(AppleTransaction::query()->sole()->signed_payload_sha256)
        ->toBe(str_repeat('a', 64));
});

it('rejects a verified but unsupported notification and retains its safe audit status', function (): void {
    $this->appleNotificationVerifier->notification = new VerifiedAppleNotification(
        'd9f625d8-ef5a-4ee4-a2b9-19163e2d6f97',
        'UNSUPPORTED_TYPE',
        null,
        'sandbox',
        null,
        null,
    );

    $this->postJson('/api/webhooks/apple/v2', [
        'signedPayload' => 'outer.unsupported.signature',
    ])->assertStatus(422);

    $log = AppleNotificationLog::query()->sole();
    expect($log->status)->toBe(AppleNotificationLog::STATUS_REJECTED)
        ->and($log->error_code)->toBe('invalid_signed_data')
        ->and($log->signed_payload_sha256)
        ->toBe(hash('sha256', 'outer.unsupported.signature'));
});

it('returns 503 and retains failed evidence for a transient processing error', function (): void {
    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();
    $this->appleNotificationVerifier->notification = appleWebhookSubscriptionNotification($token);
    config()->set('app.timezone', 'Invalid/Timezone');

    $request = Request::create(
        '/api/webhooks/apple/v2',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode([
            'signedPayload' => 'outer.transient.signature',
        ], JSON_THROW_ON_ERROR),
    );
    $response = app(AppleNotificationController::class)($request);

    expect($response->getStatusCode())->toBe(503);

    $log = AppleNotificationLog::query()->sole();
    expect($log->status)->toBe(AppleNotificationLog::STATUS_FAILED)
        ->and($log->error_code)->toBe('processing_unavailable')
        ->and($log->signed_payload_sha256)
        ->toBe(hash('sha256', 'outer.transient.signature'));
});

it('serializes concurrent verified duplicates into one audit record and transaction', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    $token = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';
    $user = User::factory()->create(['is_preview_user' => false]);
    $user->forceFill(['apple_app_account_token' => $token])->save();
    $notification = appleWebhookSubscriptionNotification($token);
    $userId = $user->id;
    DB::commit();

    $blockerName = 'apple_notification_blocker';
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
                $verifier = new AppleNotificationVerifierStub;
                $verifier->notification = $notification;
                app()->instance(AppleSignedDataVerifier::class, $verifier);
                fwrite($sockets[1], "ready\n");
                fgets($sockets[1]);

                try {
                    $response = $this->postJson('/api/webhooks/apple/v2', [
                        'signedPayload' => 'outer.concurrent.signature',
                    ]);
                    $result = [
                        'status' => $response->getStatusCode(),
                        'duplicate' => $response->json('data.duplicate'),
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
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0], 'number' => $workerNumber];
        }

        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "go\n");
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
            ->and(collect($results)->pluck('duplicate')->sort()->values()->all())
            ->toBe([false, true])
            ->and(AppleNotificationLog::query()->count())->toBe(1)
            ->and(AppleTransaction::query()->count())->toBe(1)
            ->and(PremiumEntitlement::query()->count())->toBe(1);
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        AppleNotificationLog::query()->delete();
        User::withTrashed()->whereKey($userId)->forceDelete();
        DB::beginTransaction();
    }
});

function appleWebhookSubscriptionNotification(string $token): VerifiedAppleNotification
{
    $purchase = CarbonImmutable::now('UTC')->subDay()->startOfSecond();
    $expires = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();
    $signed = CarbonImmutable::now('UTC')->startOfSecond();
    $transaction = new VerifiedAppleTransaction(
        'webhook-transaction-1',
        'webhook-original-1',
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
        'webhook-original-1',
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

    return new VerifiedAppleNotification(
        'd9f625d8-ef5a-4ee4-a2b9-19163e2d6f96',
        'DID_RENEW',
        null,
        'sandbox',
        $transaction,
        $renewal,
        str_repeat('a', 64),
        str_repeat('b', 64),
    );
}

final class AppleNotificationVerifierStub implements AppleSignedDataVerifier
{
    public ?VerifiedAppleNotification $notification = null;

    public ?AppleVerificationException $notificationFailure = null;

    public ?Throwable $unexpectedNotificationFailure = null;

    /** @var list<array{string, string}> */
    public array $notificationCalls = [];

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
        $this->notificationCalls[] = [$jws, $expectedEnvironment];

        if ($this->notificationFailure !== null) {
            throw $this->notificationFailure;
        }
        if ($this->unexpectedNotificationFailure !== null) {
            throw $this->unexpectedNotificationFailure;
        }

        return $this->notification
            ?? throw new RuntimeException('No notification result configured.');
    }
}
