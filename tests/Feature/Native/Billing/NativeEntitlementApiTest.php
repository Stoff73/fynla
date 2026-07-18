<?php

declare(strict_types=1);

use App\Models\NativeDeviceSession;
use App\Models\PremiumEntitlement;
use App\Models\Subscription;
use App\Models\TierConfiguration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    TierConfiguration::query()->create(tierConfigFixture('free'));
    TierConfiguration::query()->create(tierConfigFixture('premium'));
});

it('registers canonical entitlement behind the active native session boundary', function (): void {
    $route = Route::getRoutes()->match(
        Request::create('/api/v1/native/entitlement', 'GET'),
    );

    expect($route->getActionName())
        ->toBe('App\\Http\\Controllers\\Api\\V1\\Native\\NativeEntitlementController')
        ->and($route->gatherMiddleware())->toContain(
            'native.client',
            'auth:sanctum',
            'native.session',
        );
});

it('returns free canonical capabilities and no billing management', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = nativeEntitlementSession($user);

    $this->withToken($native['plain'])
        ->withHeaders(nativeEntitlementHeaders())
        ->getJson('/api/v1/native/entitlement')
        ->assertOk()
        ->assertJsonPath('data.entitlement', [
            'tier' => 'free',
            'provider' => null,
            'status' => 'free',
            'renews' => false,
            'current_period_end' => null,
            'capabilities' => ['dashboard' => 'full'],
            'limits' => ['savings_account' => 2],
            'billing_management' => 'none',
        ]);
});

it('returns apple canonical entitlement and apple billing management', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = nativeEntitlementSession($user);
    $periodEnd = CarbonImmutable::now('UTC')->addMonth()->startOfSecond();

    PremiumEntitlement::query()->create([
        'user_id' => $user->id,
        'provider' => PremiumEntitlement::PROVIDER_APPLE,
        'provider_reference' => 'native-entitlement-apple',
        'product_id' => 'org.fynla.premium.monthly',
        'status' => PremiumEntitlement::STATUS_ACTIVE,
        'will_renew' => true,
        'period_start' => $periodEnd->subMonth(),
        'period_end' => $periodEnd,
        'last_verified_at' => CarbonImmutable::now('UTC'),
    ]);

    $this->withToken($native['plain'])
        ->withHeaders(nativeEntitlementHeaders())
        ->getJson('/api/v1/native/entitlement')
        ->assertOk()
        ->assertJsonPath('data.entitlement.tier', 'premium')
        ->assertJsonPath('data.entitlement.provider', 'apple')
        ->assertJsonPath('data.entitlement.status', 'active')
        ->assertJsonPath('data.entitlement.renews', true)
        ->assertJsonPath(
            'data.entitlement.current_period_end',
            PremiumEntitlement::query()->sole()->period_end->toImmutable()->toISOString(),
        )
        ->assertJsonPath('data.entitlement.capabilities', ['dashboard' => 'full'])
        ->assertJsonPath('data.entitlement.limits', ['savings_account' => null])
        ->assertJsonPath('data.entitlement.billing_management', 'apple')
        ->assertJsonMissingPath('data.entitlement.purchase_url');
});

it('returns web billing management for a Revolut entitlement', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = nativeEntitlementSession($user);
    $periodEnd = now()->addMonth()->startOfSecond();

    Subscription::factory()->for($user)->create([
        'plan' => 'premium',
        'status' => 'active',
        'auto_renew' => true,
        'current_period_end' => $periodEnd,
    ]);

    $this->withToken($native['plain'])
        ->withHeaders(nativeEntitlementHeaders())
        ->getJson('/api/v1/native/entitlement')
        ->assertOk()
        ->assertJsonPath('data.entitlement.provider', 'revolut')
        ->assertJsonPath('data.entitlement.billing_management', 'web')
        ->assertJsonMissingPath('data.entitlement.purchase_url');
});

it('uses the canonical provider selection when apple and Revolut entitlements overlap', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $native = nativeEntitlementSession($user);
    $applePeriodEnd = CarbonImmutable::now('UTC')->addDays(40)->startOfSecond();

    Subscription::factory()->for($user)->create([
        'plan' => 'premium',
        'status' => 'active',
        'auto_renew' => true,
        'current_period_end' => now()->addDays(30)->startOfSecond(),
    ]);
    PremiumEntitlement::query()->create([
        'user_id' => $user->id,
        'provider' => PremiumEntitlement::PROVIDER_APPLE,
        'provider_reference' => 'native-entitlement-overlap',
        'product_id' => 'org.fynla.premium.monthly',
        'status' => PremiumEntitlement::STATUS_ACTIVE,
        'will_renew' => true,
        'period_start' => $applePeriodEnd->subMonth(),
        'period_end' => $applePeriodEnd,
        'last_verified_at' => CarbonImmutable::now('UTC'),
    ]);

    $this->withToken($native['plain'])
        ->withHeaders(nativeEntitlementHeaders())
        ->getJson('/api/v1/native/entitlement')
        ->assertOk()
        ->assertJsonPath('data.entitlement.provider', 'apple')
        ->assertJsonPath('data.entitlement.billing_management', 'apple')
        ->assertJsonPath(
            'data.entitlement.current_period_end',
            PremiumEntitlement::query()->sole()->period_end->toImmutable()->toISOString(),
        );
});

/** @return array<string, string> */
function nativeEntitlementHeaders(): array
{
    return [
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.2.3',
        'X-Fynla-Build' => '42',
    ];
}

/** @return array{plain: string, token: PersonalAccessToken, session: NativeDeviceSession} */
function nativeEntitlementSession(User $user): array
{
    $issued = $user->createToken('native-entitlement-test', ['native'], now()->addMinutes(15));
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
