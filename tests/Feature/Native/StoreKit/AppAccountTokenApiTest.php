<?php

declare(strict_types=1);

use App\Models\NativeDeviceSession;
use App\Models\User;
use App\Services\Billing\AppAccountTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

it('requires valid native headers before authentication', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = storeKitActiveNativeSession($user);

    $this->withToken($native['plain'])
        ->withHeaders(storeKitAccountTokenHeaders(['X-Fynla-Client' => 'android']))
        ->getJson(storeKitAccountTokenPath())
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_native_client');

    expect($user->fresh()->apple_app_account_token)->toBeNull();
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

it('uses a locked idempotent service path that preserves one unique durable token', function (): void {
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

    expect(file_get_contents(app_path('Services/Billing/AppAccountTokenService.php')))
        ->toContain('DB::transaction', 'lockForUpdate');
});
