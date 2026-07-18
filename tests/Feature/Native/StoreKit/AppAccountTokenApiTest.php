<?php

declare(strict_types=1);

use App\Http\Kernel;
use App\Http\Middleware\EnsureActiveNativeSession;
use App\Http\Middleware\IdentifyNativeClient;
use App\Models\NativeDeviceSession;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Billing\AppAccountTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\PersonalAccessToken;

function storeKitAccountTokenHeaders(array $overrides = []): array
{
    return array_merge([
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ], $overrides);
}

/**
 * @return array{plain: string, token: PersonalAccessToken, session: NativeDeviceSession}
 */
function storeKitActiveNativeSession(User $user, array $abilities = ['native']): array
{
    $issued = $user->createToken('storekit-native-test', $abilities, now()->addMinutes(15));
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

function storeKitAccountTokenPath(): string
{
    return '/api/v1/native/storekit/account-token';
}

function cleanupCommittedStoreKitUser(int $userId): void
{
    User::withTrashed()->whereKey($userId)->forceDelete();
    TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
}

it('registers the account token route with native client authentication and active-session middleware in order', function (): void {
    $route = Route::getRoutes()->match(Request::create(storeKitAccountTokenPath(), 'GET'));

    expect($route->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\V1\\Native\\StoreKit\\AppAccountTokenController')
        ->and($route->gatherMiddleware())->toContain('native.client', 'auth:sanctum', 'native.session')
        ->and(array_search('native.client', $route->gatherMiddleware(), true))
        ->toBeLessThan(array_search('auth:sanctum', $route->gatherMiddleware(), true))
        ->and(array_search('auth:sanctum', $route->gatherMiddleware(), true))
        ->toBeLessThan(array_search('native.session', $route->gatherMiddleware(), true));
});

it('adds native priority around Laravel authentication without changing the default priority order', function (): void {
    $priority = app(Kernel::class)->getMiddlewarePriority();
    $withoutNative = array_values(array_filter($priority, fn (string $middleware): bool => ! in_array($middleware, [
        EnsureActiveNativeSession::class,
        IdentifyNativeClient::class,
    ], true)));

    expect($withoutNative)->toBe([
        HandlePrecognitiveRequests::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        AuthenticatesRequests::class,
        ThrottleRequests::class,
        ThrottleRequestsWithRedis::class,
        AuthenticatesSessions::class,
        SubstituteBindings::class,
        Authorize::class,
    ])->and(array_search(IdentifyNativeClient::class, $priority, true))
        ->toBeLessThan(array_search(AuthenticatesRequests::class, $priority, true))
        ->and(array_search(AuthenticatesRequests::class, $priority, true))
        ->toBeLessThan(array_search(EnsureActiveNativeSession::class, $priority, true));
});

it('issues one persisted UUID v4 and returns the minimal exact response', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = storeKitActiveNativeSession($user);

    $response = $this->withToken($native['plain'])
        ->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertOk();

    $token = $response->json('data.app_account_token');

    expect($response->json())->toBe([
        'success' => true,
        'data' => ['app_account_token' => $token],
    ])->and($token)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($user->fresh()->apple_app_account_token)->toBe($token);
});

it('is stable and ignores client-supplied replacements without echoing them', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = storeKitActiveNativeSession($user);
    $replacement = '75c42f38-62f1-4d0e-94ea-f8270f5d73fd';

    $first = $this->withToken($native['plain'])
        ->withHeaders(storeKitAccountTokenHeaders(['X-App-Account-Token' => $replacement]))
        ->json('GET', storeKitAccountTokenPath().'?app_account_token='.$replacement, [
            'app_account_token' => $replacement,
            'user_id' => 999999,
            'email' => 'attacker@example.test',
        ])
        ->assertOk();

    $second = $this->withToken($native['plain'])
        ->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertOk();

    expect($second->json())->toBe($first->json())
        ->and($first->json('data.app_account_token'))->not->toBe($replacement)
        ->and(json_encode($first->json(), JSON_THROW_ON_ERROR))
        ->not->toContain('attacker@example.test', '999999', 'user_id', 'email', 'session_id');
});

it('keeps users tokens distinct and never exposes another users association', function (): void {
    $firstUser = User::factory()->create(['is_preview_user' => false]);
    $secondUser = User::factory()->create(['is_preview_user' => false]);
    $first = storeKitActiveNativeSession($firstUser);
    $second = storeKitActiveNativeSession($secondUser);

    $firstResponse = $this->withToken($first['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertOk();
    app('auth')->forgetGuards();
    $secondResponse = $this->flushHeaders()->withToken($second['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertOk();

    expect($firstResponse->json('data.app_account_token'))
        ->not->toBe($secondResponse->json('data.app_account_token'))
        ->and($firstUser->fresh()->apple_app_account_token)->toBe($firstResponse->json('data.app_account_token'))
        ->and($secondUser->fresh()->apple_app_account_token)->toBe($secondResponse->json('data.app_account_token'));
});

it('rejects missing bearer, transient cookie, wildcard, bootstrap, and non-native bearer credentials', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $this->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertUnauthorized();

    $this->actingAs($user, 'sanctum')->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertUnauthorized()
        ->assertJsonPath('error', 'native_session_invalid');

    foreach ([['*'], ['bootstrap'], ['browser']] as $abilities) {
        $native = storeKitActiveNativeSession($user, $abilities);

        $this->withToken($native['plain'])->withHeaders(storeKitAccountTokenHeaders())
            ->getJson(storeKitAccountTokenPath())
            ->assertUnauthorized()
            ->assertJsonPath('error', 'native_session_invalid');
    }

    expect($user->fresh()->apple_app_account_token)->toBeNull();
});

it('makes invalid or missing native headers win before missing or invalid bearer authentication', function (): void {
    $this->flushHeaders()
        ->getJson(storeKitAccountTokenPath())
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_native_client');

    $this->flushHeaders()
        ->withToken('not-a-persisted-bearer-token')
        ->withHeaders(storeKitAccountTokenHeaders(['X-Fynla-Client' => 'android']))
        ->getJson(storeKitAccountTokenPath())
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_native_client');

    $user = User::factory()->create(['is_preview_user' => false]);
    $native = storeKitActiveNativeSession($user);

    $this->flushHeaders()->withToken($native['plain'])
        ->withHeaders(storeKitAccountTokenHeaders(['X-Fynla-Client' => 'android']))
        ->getJson(storeKitAccountTokenPath())
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_native_client');

    expect($user->fresh()->apple_app_account_token)->toBeNull();
});

it('rejects expired and deleted personal access tokens including an orphaned session reference', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $expired = $user->createToken('expired-native', ['native'], now()->subSecond());
    NativeDeviceSession::factory()->for($user)->create([
        'current_access_token_id' => $expired->accessToken->id,
        'absolute_expires_at' => now()->addDay(),
    ]);

    $this->withToken($expired->plainTextToken)->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertUnauthorized();

    app('auth')->forgetGuards();
    $orphaned = storeKitActiveNativeSession($user);
    $orphaned['token']->delete();

    $this->flushHeaders()->withToken($orphaned['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertUnauthorized();

    app('auth')->forgetGuards();
    $replacement = $user->createToken('replacement-native', ['native'], now()->addMinutes(15));

    $this->flushHeaders()->withToken($replacement->plainTextToken)->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertUnauthorized()
        ->assertJsonPath('error', 'native_session_invalid');

    expect($orphaned['session']->fresh()->current_access_token_id)->toBeNull()
        ->and($user->fresh()->apple_app_account_token)->toBeNull();
});

it('rejects a deliberate bearer current-token and user mismatch in the native-session middleware', function (): void {
    $requestUser = User::factory()->create(['is_preview_user' => false]);
    $bearerUser = User::factory()->create(['is_preview_user' => false]);
    $requestUserNative = storeKitActiveNativeSession($requestUser);
    $bearerNative = storeKitActiveNativeSession($bearerUser);
    $request = Request::create(storeKitAccountTokenPath(), 'GET', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$bearerNative['plain'],
    ]);
    $request->setUserResolver(fn (): User => $requestUser->fresh()->withAccessToken($requestUserNative['token']->fresh()));

    $response = app(EnsureActiveNativeSession::class)->handle(
        $request,
        fn () => response()->json(['unexpected' => true]),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'success' => false,
            'error' => 'native_session_invalid',
            'message' => 'Native session credentials are invalid.',
        ]);
});

it('fails closed for missing foreign revoked expired rotated and orphan native sessions or tokens', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $orphan = $user->createToken('orphan-native', ['native'], now()->addMinutes(15));
    $this->withToken($orphan->plainTextToken)->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    $foreign = storeKitActiveNativeSession($user);
    $other = User::factory()->create(['is_preview_user' => false]);
    $foreign['session']->forceFill(['user_id' => $other->id])->save();
    $this->withToken($foreign['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    $revoked = storeKitActiveNativeSession($user);
    $revoked['session']->forceFill(['revoked_at' => now()])->save();
    $this->withToken($revoked['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    $expired = storeKitActiveNativeSession($user);
    $expired['session']->forceFill(['absolute_expires_at' => now()])->save();
    $this->withToken($expired['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    $rotated = storeKitActiveNativeSession($user);
    $replacement = $user->createToken('replacement-native', ['native'], now()->addMinutes(15));
    $rotated['session']->forceFill(['current_access_token_id' => $replacement->accessToken->id])->save();
    $this->withToken($rotated['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    expect($user->fresh()->apple_app_account_token)->toBeNull();
});

it('denies preview users at the route and direct service boundaries before mutation', function (): void {
    $preview = User::factory()->preview()->create();
    $native = storeKitActiveNativeSession($preview);

    $this->withToken($native['plain'])->withHeaders(storeKitAccountTokenHeaders())
        ->getJson(storeKitAccountTokenPath())
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'error' => 'native_account_token_forbidden',
            'message' => 'StoreKit account tokens are unavailable for preview users.',
        ]);

    expect(class_exists(AppAccountTokenService::class))->toBeTrue();
    if (! class_exists(AppAccountTokenService::class)) {
        return;
    }

    expect(fn (): string => app(AppAccountTokenService::class)->issue($preview))
        ->toThrow(AuthorizationException::class);
    expect($preview->fresh()->apple_app_account_token)->toBeNull();
});

it('keeps the service idempotent and preserves the database unique invariant', function (): void {
    expect(class_exists(AppAccountTokenService::class))->toBeTrue();
    if (! class_exists(AppAccountTokenService::class)) {
        return;
    }

    $service = app(AppAccountTokenService::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $first = $service->issue($user);
    $second = $service->issue($user->fresh());
    $other = User::factory()->create(['is_preview_user' => false]);

    expect($second)->toBe($first)
        ->and($user->fresh()->apple_app_account_token)->toBe($first);

    expect(fn () => $other->forceFill(['apple_app_account_token' => $first])->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('serializes two concurrent first issues into one durable account token', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    $user = User::factory()->create(['is_preview_user' => false]);
    $userId = $user->id;
    DB::commit();

    $blockerName = 'storekit_account_token_blocker';
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
                app()->forgetInstance(AppAccountTokenService::class);
                fwrite($sockets[1], "ready\n");
                fgets($sockets[1]);
                fwrite($sockets[1], "issuing\n");

                try {
                    $token = app(AppAccountTokenService::class)->issue(User::query()->findOrFail($userId));
                    $result = ['status' => 'success', 'token' => $token];
                } catch (Throwable $exception) {
                    $result = ['status' => 'unexpected', 'type' => $exception::class, 'message' => $exception->getMessage()];
                }

                fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($sockets[1]);
                exit(0);
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0], 'number' => $workerNumber];
        }

        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "go\n");
        }
        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('issuing');
        }
        $blocker->commit();

        $results = [];
        foreach ($workers as $worker) {
            $results[] = json_decode((string) fgets($worker['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        DB::purge();
        DB::connection()->getPdo();
        $tokens = collect($results)->pluck('token')->unique()->values();

        expect(collect($results)->pluck('status')->all())->toBe(['success', 'success'])
            ->and($tokens)->toHaveCount(1)
            ->and($tokens->first())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
            ->and(User::query()->findOrFail($userId)->apple_app_account_token)->toBe($tokens->first());
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        cleanupCommittedStoreKitUser($userId);
        DB::beginTransaction();
    }
});
