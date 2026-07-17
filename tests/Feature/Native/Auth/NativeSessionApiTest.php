<?php

declare(strict_types=1);

use App\Data\Auth\NativeDeviceContext;
use App\Data\Auth\NativeSessionCredentials;
use App\Http\Controllers\Api\V1\Native\Auth\NativeSessionController;
use App\Models\NativeDeviceSession;
use App\Models\NativeRefreshToken;
use App\Models\User;
use App\Services\Auth\NativeSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

function nativeSessionApiHeaders(array $overrides = []): array
{
    return array_merge([
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ], $overrides);
}

function nativeSessionApiContext(
    string $label = 'CJ’s iPhone',
    string $version = '1.0.0',
    string $build = '1',
): NativeDeviceContext {
    return new NativeDeviceContext(
        platform: 'ios',
        deviceLabel: $label,
        appVersion: $version,
        appBuild: $build,
    );
}

/**
 * @return array{0: string, 1: PersonalAccessToken}
 */
function nativeSessionApiBootstrap(User $user, array $abilities = ['*']): array
{
    $issued = $user->createToken('native-bootstrap-test', $abilities);
    $issued->accessToken->forceFill([
        'created_at' => CarbonImmutable::now(),
        'updated_at' => CarbonImmutable::now(),
    ])->save();

    return [$issued->plainTextToken, $issued->accessToken];
}

function nativeSessionApiCredentials(User $user): NativeSessionCredentials
{
    [, $bootstrap] = nativeSessionApiBootstrap($user);

    return app(NativeSessionService::class)->exchange(
        $user,
        $bootstrap,
        nativeSessionApiContext(),
    );
}

/**
 * @return array{0: NativeDeviceSession, 1: string, 2: string}
 */
function nativeSessionApiPreviewFamily(User $user): array
{
    $access = $user->createToken('preview-native-access', ['native'], now()->addMinutes(15));
    $session = NativeDeviceSession::factory()->for($user)->create([
        'current_access_token_id' => $access->accessToken->id,
    ]);
    $plainRefresh = 'preview-refresh-credential';
    NativeRefreshToken::factory()->for($session, 'deviceSession')->create([
        'token_hash' => hash('sha256', $plainRefresh),
    ]);

    return [$session, $access->plainTextToken, $plainRefresh];
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-17 10:00:00 UTC');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('registers exactly the four native session routes with the required middleware', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/native/auth'))
        ->values();

    expect($routes)->toHaveCount(4);

    $signatures = $routes
        ->flatMap(fn ($route): array => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): string => $method.' '.$route->uri())
            ->all())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'DELETE api/v1/native/auth/session',
        'GET api/v1/native/auth/session',
        'POST api/v1/native/auth/session/exchange',
        'POST api/v1/native/auth/session/refresh',
    ]);

    $refresh = Route::getRoutes()->match(Request::create('/api/v1/native/auth/session/refresh', 'POST'));
    $exchange = Route::getRoutes()->match(Request::create('/api/v1/native/auth/session/exchange', 'POST'));
    $show = Route::getRoutes()->match(Request::create('/api/v1/native/auth/session', 'GET'));
    $destroy = Route::getRoutes()->match(Request::create('/api/v1/native/auth/session', 'DELETE'));

    expect($refresh->getActionName())->toBe(NativeSessionController::class.'@refresh')
        ->and($refresh->gatherMiddleware())->toContain('native.client', 'throttle:native-session')
        ->and($refresh->gatherMiddleware())->not->toContain('auth:sanctum')
        ->and($exchange->getActionName())->toBe(NativeSessionController::class.'@exchange')
        ->and($exchange->gatherMiddleware())->toContain('native.client', 'auth:sanctum', 'throttle:native-session')
        ->and(array_search('native.client', $exchange->gatherMiddleware(), true))
        ->toBeLessThan(array_search('auth:sanctum', $exchange->gatherMiddleware(), true))
        ->and($show->getActionName())->toBe(NativeSessionController::class.'@show')
        ->and($show->gatherMiddleware())->toContain('native.client', 'auth:sanctum')
        ->and($destroy->getActionName())->toBe(NativeSessionController::class.'@destroy')
        ->and($destroy->gatherMiddleware())->toContain('native.client', 'auth:sanctum');
});

it('exchanges a bearer bootstrap for the exact credential response using only native header metadata', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainBootstrap] = nativeSessionApiBootstrap($user);

    $response = $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', [
            'device_label' => '  CJ’s iPhone  ',
            'platform' => 'android',
            'app_version' => '99.99.99',
            'app_build' => '99999',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(array_keys($response->json()))->toBe(['success', 'data'])
        ->and(array_keys($response->json('data')))->toBe([
            'access_token',
            'access_expires_at',
            'refresh_token',
            'refresh_expires_at',
            'absolute_expires_at',
            'session_id',
        ])
        ->and($response->json('data.access_token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.refresh_token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.access_expires_at'))->toBe('2026-07-17T10:15:00Z')
        ->and($response->json('data.refresh_expires_at'))->toBe('2026-08-16T10:00:00Z')
        ->and($response->json('data.absolute_expires_at'))->toBe('2026-10-15T10:00:00Z');

    $session = NativeDeviceSession::query()->findOrFail($response->json('data.session_id'));
    expect($session->user_id)->toBe($user->id)
        ->and($session->device_label)->toBe('CJ’s iPhone')
        ->and($session->platform)->toBe('ios')
        ->and($session->app_version)->toBe('1.2.3')
        ->and($session->app_build)->toBe('42');
});

it('requires a trimmed non-empty device label with at most eighty characters', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainBootstrap] = nativeSessionApiBootstrap($user);

    $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_label');

    $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_label');

    $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => str_repeat('a', 81)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_label');
});

it('rejects every control-character class in a device label', function (string $label): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainBootstrap] = nativeSessionApiBootstrap($user);

    $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => $label])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_label');
})->with([
    'null byte' => ["My\0iPhone"],
    'leading horizontal tab' => ["\tiPhone"],
    'line feed' => ["My\niPhone"],
    'horizontal tab' => ["My\tiPhone"],
    'delete' => ['My'.chr(0x7F).'iPhone'],
    'unicode next line' => ["My\u{0085}iPhone"],
]);

it('rejects control characters in a refresh device label before rotation', function (): void {
    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => 'unknown-refresh',
            'device_label' => "\tiPhone",
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_label');
});

it('rejects controls before global normalization for non-json input', function (string $source, string $label): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainBootstrap] = nativeSessionApiBootstrap($user);
    $request = $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders() + ['Accept' => 'application/json']);

    $response = $source === 'query'
        ? $request->post('/api/v1/native/auth/session/exchange?device_label='.rawurlencode($label))
        : $request->post('/api/v1/native/auth/session/exchange', ['device_label' => $label]);

    $response->assertUnprocessable()->assertJsonValidationErrors('device_label');
})->with([
    'form leading tab' => ['form', "\tiPhone"],
    'form null byte' => ['form', "My\0iPhone"],
    'query trailing carriage return' => ['query', "iPhone\r"],
    'query line feed' => ['query', "My\niPhone"],
]);

it('requires a string refresh credential no longer than 512 characters', function (): void {
    foreach ([[], ['refresh_token' => ['not-a-string']], ['refresh_token' => str_repeat('r', 513)]] as $payload) {
        $this->withHeaders(nativeSessionApiHeaders())
            ->postJson('/api/v1/native/auth/session/refresh', $payload + ['device_label' => 'iPhone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refresh_token');
    }
});

it('rotates credentials in the exact response shape and refreshes metadata from headers only', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $original = nativeSessionApiCredentials($user);

    CarbonImmutable::setTestNow('2026-07-17 11:00:00 UTC');
    $response = $this->withHeaders(nativeSessionApiHeaders([
        'X-Fynla-Version' => '2.0.0',
        'X-Fynla-Build' => '84',
    ]))->postJson('/api/v1/native/auth/session/refresh', [
        'refresh_token' => $original->refreshToken,
        'device_label' => '  Renamed iPhone  ',
        'platform' => 'android',
        'app_version' => '88.0.0',
        'app_build' => '999',
    ])->assertOk()->assertJsonPath('success', true);

    expect(array_keys($response->json()))->toBe(['success', 'data'])
        ->and(array_keys($response->json('data')))->toBe([
            'access_token',
            'access_expires_at',
            'refresh_token',
            'refresh_expires_at',
            'absolute_expires_at',
            'session_id',
        ])
        ->and($response->json('data.session_id'))->toBe($original->sessionId)
        ->and($response->json('data.access_token'))->not->toBe($original->accessToken)
        ->and($response->json('data.refresh_token'))->not->toBe($original->refreshToken)
        ->and($response->json('data.access_expires_at'))->toBe('2026-07-17T11:15:00Z')
        ->and($response->json('data.refresh_expires_at'))->toBe('2026-08-16T11:00:00Z')
        ->and($response->json('data.absolute_expires_at'))->toBe('2026-10-15T10:00:00Z');

    $session = NativeDeviceSession::query()->findOrFail($original->sessionId);
    expect($session->device_label)->toBe('Renamed iPhone')
        ->and($session->platform)->toBe('ios')
        ->and($session->app_version)->toBe('2.0.0')
        ->and($session->app_build)->toBe('84');
});

it('rejects unauthenticated cookie and missing-current-token exchanges', function (): void {
    $payload = ['device_label' => 'iPhone'];

    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', $payload)
        ->assertUnauthorized();

    $cookieUser = User::factory()->create(['is_preview_user' => false]);
    $this->actingAs($cookieUser)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', $payload)
        ->assertUnauthorized()
        ->assertJsonPath('error', 'native_session_invalid');

    $missingTokenUser = User::factory()->create(['is_preview_user' => false]);
    $this->actingAs($missingTokenUser, 'sanctum')
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', $payload)
        ->assertUnauthorized()
        ->assertJsonPath('error', 'native_session_invalid');

    expect(NativeDeviceSession::query()->count())->toBe(0);
});

it('does not exchange an existing native-only access token into another family', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainNative] = nativeSessionApiBootstrap($user, ['native']);

    $this->withToken($plainNative)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => 'iPhone'])
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'error' => 'native_session_invalid',
            'message' => 'Native session credentials are invalid.',
        ]);

    expect(NativeDeviceSession::query()->count())->toBe(0);
});

it('maps unknown and revoked refresh credentials to one indistinguishable invalid response', function (): void {
    $unknown = $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => 'unknown-refresh',
            'device_label' => 'iPhone',
        ])->assertUnauthorized();

    $user = User::factory()->create(['is_preview_user' => false]);
    $credentials = nativeSessionApiCredentials($user);
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    app(NativeSessionService::class)->revoke($session, 'test_revoked');

    $revoked = $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $credentials->refreshToken,
            'device_label' => 'iPhone',
        ])->assertUnauthorized();

    expect($unknown->json())->toBe([
        'success' => false,
        'error' => 'native_session_invalid',
        'message' => 'Native session credentials are invalid.',
    ])->and($revoked->json())->toBe($unknown->json());
});

it('maps expired replayed and absolute-expired refresh errors to stable 401 codes', function (): void {
    $expiredUser = User::factory()->create(['is_preview_user' => false]);
    $expired = nativeSessionApiCredentials($expiredUser);
    NativeRefreshToken::query()
        ->where('native_device_session_id', $expired->sessionId)
        ->update(['expires_at' => now()->subSecond()]);
    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $expired->refreshToken,
            'device_label' => 'iPhone',
        ])->assertUnauthorized()->assertJsonPath('error', 'native_session_expired');

    $replayUser = User::factory()->create(['is_preview_user' => false]);
    $replayed = nativeSessionApiCredentials($replayUser);
    app(NativeSessionService::class)->rotate($replayed->refreshToken, nativeSessionApiContext());
    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $replayed->refreshToken,
            'device_label' => 'iPhone',
        ])->assertUnauthorized()->assertJsonPath('error', 'native_session_replayed');

    $absoluteUser = User::factory()->create(['is_preview_user' => false]);
    $absolute = nativeSessionApiCredentials($absoluteUser);
    NativeDeviceSession::query()->whereKey($absolute->sessionId)->update(['absolute_expires_at' => now()]);
    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $absolute->refreshToken,
            'device_label' => 'iPhone',
        ])->assertUnauthorized()->assertJsonPath('error', 'native_full_login_required');
});

it('shows only the current users session bound to the current personal access token', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $credentials = nativeSessionApiCredentials($user);

    $response = $this->withToken($credentials->accessToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->getJson('/api/v1/native/auth/session?session_id=client-controlled-id')
        ->assertOk();

    expect($response->json())->toBe([
        'success' => true,
        'data' => [
            'session_id' => $credentials->sessionId,
            'platform' => 'ios',
            'device_label' => 'CJ’s iPhone',
            'app_version' => '1.0.0',
            'app_build' => '1',
            'authenticated_at' => '2026-07-17T10:00:00Z',
            'absolute_expires_at' => '2026-10-15T10:00:00Z',
            'last_used_at' => '2026-07-17T10:00:00Z',
        ],
    ]);

    expect(json_encode($response->json(), JSON_THROW_ON_ERROR))
        ->not->toContain('current_access_token_id', 'refresh_token', 'token_hash', 'user_id');

    [$otherToken] = nativeSessionApiBootstrap($user);
    $this->withToken($otherToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->getJson('/api/v1/native/auth/session?session_id='.$credentials->sessionId)
        ->assertNotFound()
        ->assertJsonPath('error', 'native_session_not_found');

    $otherUser = User::factory()->create(['is_preview_user' => false]);
    [$otherUserToken] = nativeSessionApiBootstrap($otherUser);
    $this->withToken($otherUserToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->getJson('/api/v1/native/auth/session?session_id='.$credentials->sessionId)
        ->assertNotFound()
        ->assertJsonPath('error', 'native_session_not_found');
});

it('revokes only the current users token-bound family without accepting a client session id', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $credentials = nativeSessionApiCredentials($user);
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    $accessId = $session->current_access_token_id;

    $this->withToken($credentials->accessToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->deleteJson('/api/v1/native/auth/session?session_id=client-controlled-id', [
            'session_id' => 'also-client-controlled',
            'refresh_token' => 'must-not-be-echoed',
        ])->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Native session revoked.',
        ]);

    expect($session->fresh()?->revoked_at)->not->toBeNull()
        ->and($session->fresh()?->revoke_reason)->toBe('user_logout')
        ->and($session->fresh()?->current_access_token_id)->toBeNull()
        ->and(PersonalAccessToken::query()->find($accessId))->toBeNull()
        ->and(NativeRefreshToken::query()
            ->where('native_device_session_id', $session->id)
            ->whereNull('revoked_at')
            ->exists())->toBeFalse();
});

it('keeps native session writes intercepted for ordinary preview users without persisting credentials', function (): void {
    $preview = User::factory()->create(['is_preview_user' => true]);
    [$plainBootstrap, $bootstrap] = nativeSessionApiBootstrap($preview);

    $response = $this->withToken($plainBootstrap)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => 'Preview iPhone'])
        ->assertOk()
        ->assertJsonPath('preview_mode', true);

    expect(NativeDeviceSession::query()->where('user_id', $preview->id)->exists())->toBeFalse()
        ->and(NativeRefreshToken::query()->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->find($bootstrap->id))->not->toBeNull()
        ->and(json_encode($response->json(), JSON_THROW_ON_ERROR))
        ->not->toContain('access_token', 'refresh_token', 'absolute_expires_at', 'session_id');

    $source = File::get(app_path('Http/Middleware/PreviewWriteInterceptor.php'));
    expect($source)->not->toContain(
        "'api/v1/native/auth/session/exchange'",
        "'api/v1/native/auth/session/refresh'",
        "'api/v1/native/auth/session'",
    );
});

it('fails preview native authentication closed even when interception is bypassed or no bearer identifies refresh ownership', function (): void {
    $preview = User::factory()->create(['is_preview_user' => true]);
    [$bypassToken] = nativeSessionApiBootstrap($preview, ['bypass-preview-mode']);

    $this->withToken($bypassToken)
        ->withHeaders(nativeSessionApiHeaders() + ['X-Eval-Run-Id' => 'native-preview-test'])
        ->postJson('/api/v1/native/auth/session/exchange', ['device_label' => 'Preview iPhone'])
        ->assertUnauthorized()
        ->assertJsonPath('error', 'native_session_invalid');

    [$session, , $plainRefresh] = nativeSessionApiPreviewFamily($preview);
    $refresh = NativeRefreshToken::query()->where('native_device_session_id', $session->id)->sole();
    $tokenCount = PersonalAccessToken::query()->where('tokenable_id', $preview->id)->count();

    $this->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $plainRefresh,
            'device_label' => 'Preview iPhone',
        ])->assertUnauthorized()->assertJsonPath('error', 'native_session_invalid');

    expect($refresh->fresh()?->used_at)->toBeNull()
        ->and($session->fresh()?->current_access_token_id)->toBe($session->current_access_token_id)
        ->and(PersonalAccessToken::query()->where('tokenable_id', $preview->id)->count())->toBe($tokenCount)
        ->and(NativeRefreshToken::query()->where('native_device_session_id', $session->id)->count())->toBe(1);
});

it('does not mutate a seeded preview family through intercepted destroy or refresh writes', function (): void {
    $preview = User::factory()->create(['is_preview_user' => true]);
    [$session, $accessToken, $plainRefresh] = nativeSessionApiPreviewFamily($preview);
    $refresh = NativeRefreshToken::query()->where('native_device_session_id', $session->id)->sole();

    $this->withToken($accessToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->deleteJson('/api/v1/native/auth/session')
        ->assertOk()
        ->assertJsonPath('preview_mode', true);

    $refreshResponse = $this->withToken($accessToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', [
            'refresh_token' => $plainRefresh,
            'device_label' => 'Preview iPhone',
        ])->assertOk()->assertJsonPath('preview_mode', true);

    expect($session->fresh()?->revoked_at)->toBeNull()
        ->and($session->fresh()?->current_access_token_id)->toBe($session->current_access_token_id)
        ->and($refresh->fresh()?->used_at)->toBeNull()
        ->and(NativeRefreshToken::query()->where('native_device_session_id', $session->id)->count())->toBe(1)
        ->and(json_encode($refreshResponse->json(), JSON_THROW_ON_ERROR))
        ->not->toContain('refresh_token', $plainRefresh);
});

it('limits exchange to ten attempts per authenticated user independently of IP', function (): void {
    $first = User::factory()->create(['is_preview_user' => false]);
    $second = User::factory()->create(['is_preview_user' => false]);
    [$firstToken] = nativeSessionApiBootstrap($first);
    [$secondToken] = nativeSessionApiBootstrap($second);
    $server = ['REMOTE_ADDR' => '203.0.113.40'];

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->withServerVariables($server)
            ->withToken($firstToken)
            ->withHeaders(nativeSessionApiHeaders())
            ->postJson('/api/v1/native/auth/session/exchange', [])
            ->assertUnprocessable();
    }

    $this->withServerVariables($server)
        ->withToken($firstToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', [])
        ->assertStatus(429)
        ->assertExactJson([
            'success' => false,
            'error' => 'native_session_rate_limited',
            'message' => 'Too many native session attempts. Please try again later.',
        ]);

    app('auth')->forgetGuards();

    $this->withServerVariables($server)
        ->withToken($secondToken)
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/exchange', [])
        ->assertUnprocessable();
});

it('limits unauthenticated refresh to ten attempts per IP independently of other IPs', function (): void {
    $payload = ['refresh_token' => 'unknown-refresh', 'device_label' => 'iPhone'];

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeaders(nativeSessionApiHeaders())
            ->postJson('/api/v1/native/auth/session/refresh', $payload)
            ->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', $payload)
        ->assertStatus(429)
        ->assertJsonPath('error', 'native_session_rate_limited');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.51'])
        ->withHeaders(nativeSessionApiHeaders())
        ->postJson('/api/v1/native/auth/session/refresh', $payload)
        ->assertUnauthorized();
});

it('registers a stable named limiter keyed by authenticated user or IP', function (): void {
    $limiter = RateLimiter::limiter('native-session');
    expect($limiter)->not->toBeNull();

    $user = User::factory()->create();
    $authenticated = Request::create('/api/v1/native/auth/session/exchange', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.60',
    ]);
    $authenticated->setUserResolver(fn (): User => $user);
    $userLimit = $limiter($authenticated);

    $anonymous = Request::create('/api/v1/native/auth/session/refresh', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.60',
    ]);
    $ipLimit = $limiter($anonymous);

    expect($userLimit->maxAttempts)->toBe(10)
        ->and($userLimit->decayMinutes)->toBe(1)
        ->and($userLimit->key)->toBe('user:'.$user->id)
        ->and($ipLimit->key)->toBe('ip:203.0.113.60');

    $response = ($userLimit->responseCallback)();
    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'error' => 'native_session_rate_limited',
            'message' => 'Too many native session attempts. Please try again later.',
        ]);
});

it('leaves the existing mobile refresh-token contract unchanged', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    [$plainToken] = nativeSessionApiBootstrap($user);

    $response = $this->withToken($plainToken)
        ->postJson('/api/v1/auth/refresh-token')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data' => ['token', 'expires_at', 'token_age_days']]);

    expect(array_keys($response->json('data')))->toBe(['token', 'expires_at', 'token_age_days'])
        ->and($response->json('data'))->not->toHaveKeys([
            'access_token',
            'refresh_token',
            'absolute_expires_at',
            'session_id',
        ]);
});
