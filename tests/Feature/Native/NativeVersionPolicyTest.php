<?php

declare(strict_types=1);

use App\Models\NativeDeviceSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'native.environment' => 'staging',
        'native.environments.staging.minimum_version' => '1.2.3',
        'native.environments.staging.minimum_build' => 42,
        'native.environments.production.minimum_version' => '2.0.0',
        'native.environments.production.minimum_build' => 100,
        'native.storekit_purchase_enabled' => false,
        'native.app_store_url' => 'https://apps.apple.com/app/id123456789',
    ]);
});

it('applies version enforcement to every native route and nowhere else', function (): void {
    $nativeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/native'));

    expect($nativeRoutes)->not->toBeEmpty();
    foreach ($nativeRoutes as $route) {
        expect($route->middleware(), $route->uri())->toContain('native.version');
    }

    foreach ([
        ['/api/v1/mobile/dashboard', 'GET'],
        ['/api/auth/user', 'GET'],
        ['/api/webhooks/apple/v2', 'POST'],
        ['/api/webhooks/revolut', 'POST'],
    ] as [$path, $method]) {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route): bool => '/'.$route->uri() === $path
                && in_array($method, $route->methods(), true),
        );

        expect($route, $path)->not->toBeNull();
        expect($route->middleware(), $path)->not->toContain('native.version');
    }
});

it('returns the exact update contract for an old semantic version or build', function (array $headers): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/native/health', $headers)
        ->assertStatus(426)
        ->assertExactJson([
            'success' => false,
            'error' => 'native_update_required',
            'message' => 'A newer version of Fynla is required.',
            'minimum_version' => '1.2.3',
            'minimum_build' => 42,
            'app_store_url' => 'https://apps.apple.com/app/id123456789',
        ]);
})->with([
    'old version' => [[
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.2',
        'X-Fynla-Build' => '42',
    ]],
    'old build' => [[
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '41',
    ]],
]);

it('returns runtime purchase state for an accepted build', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/native/health', nativePolicyHeaders())
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'data' => [
                'api_version' => 'v1',
                'storekit_purchase_enabled' => false,
            ],
        ]);
});

it('selects production minimums independently from staging', function (): void {
    config(['native.environment' => 'production']);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/native/health', nativePolicyHeaders())
        ->assertStatus(426)
        ->assertJsonPath('minimum_version', '2.0.0')
        ->assertJsonPath('minimum_build', 100);
});

it('checks the purchase switch immediately before StoreKit initiation without blocking restore routes', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = nativePolicySession($user);

    $this->withToken($native['plain'])
        ->withHeaders(nativePolicyHeaders())
        ->getJson('/api/v1/native/storekit/purchase-authorization')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'data' => ['enabled' => false],
        ]);

    $reconcile = Route::getRoutes()->match(Request::create(
        '/api/v1/native/storekit/reconcile',
        'POST',
    ));
    $transactions = Route::getRoutes()->match(Request::create(
        '/api/v1/native/storekit/transactions',
        'POST',
    ));
    expect($reconcile->middleware())->not->toContain('storekit.purchase')
        ->and($transactions->middleware())->not->toContain('storekit.purchase');
});

/** @return array<string, string> */
function nativePolicyHeaders(): array
{
    return [
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ];
}

/** @return array{plain: string, token: PersonalAccessToken, session: NativeDeviceSession} */
function nativePolicySession(User $user): array
{
    $issued = $user->createToken('native-policy-test', ['native'], now()->addMinutes(15));
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
