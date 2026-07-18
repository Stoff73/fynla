<?php

declare(strict_types=1);

use App\Models\PremiumEntitlement;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Carbon::setTestNow(CarbonImmutable::create(2026, 7, 18, 12, 0, 0, config('app.timezone')));
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('shows one Apple Premium entitlement and matching capabilities on every shared surface outside the dashboard cache', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Sanctum::actingAs($user);

    $periodEnd = now()->addMonth();
    $user->premiumEntitlements()->create([
        'provider' => PremiumEntitlement::PROVIDER_APPLE,
        'provider_reference' => 'surface-original-transaction',
        'product_id' => 'org.fynla.premium.monthly',
        'status' => PremiumEntitlement::STATUS_ACTIVE,
        'will_renew' => true,
        'period_start' => now(),
        'period_end' => $periodEnd,
        'last_verified_at' => now(),
        'provider_metadata' => [],
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'billing_cycle' => 'yearly',
        'amount' => 5999,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_end' => now()->addWeek(),
    ]);

    Cache::put("mobile_dashboard_{$user->id}", [
        'modules' => [],
        'net_worth' => ['total' => 0, 'breakdown' => ['assets' => []]],
        'alerts' => [],
        'fyn_insight' => null,
        'cached_at' => '2026-07-17T12:00:00+00:00',
    ], now()->addDay());

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.tier_flags.resolved_tier', 'premium')
        ->assertJsonPath('data.tier_flags.capabilities.dashboard', 'full')
        ->assertJsonPath('data.tier_flags.limits.savings_account', null);

    $this->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('data.tier', 'premium')
        ->assertJsonPath('data.provider', 'apple')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.renews', true)
        ->assertJsonPath('data.current_period_end', $periodEnd->toISOString())
        ->assertJsonPath('data.capabilities.dashboard', 'full')
        ->assertJsonPath('data.limits.savings_account', null)
        ->assertJsonPath('has_subscription', true)
        ->assertJsonPath('subscription_status', 'active')
        ->assertJsonPath('current_period_end', $periodEnd->toISOString())
        ->assertJsonPath('auto_renew', true)
        ->assertJsonPath('next_renewal_date', $periodEnd->toISOString())
        ->assertJsonPath('plan', null)
        ->assertJsonPath('billing_cycle', null)
        ->assertJsonPath('amount', null);

    $premiumDashboard = $this->getJson('/api/v1/mobile/dashboard')
        ->assertOk()
        ->assertJsonPath('data.entitlement.tier', 'premium')
        ->assertJsonPath('data.entitlement.provider', 'apple')
        ->assertJsonPath('data.entitlement.status', 'active')
        ->assertJsonPath('data.entitlement.renews', true)
        ->assertJsonPath('data.entitlement.current_period_end', $periodEnd->toISOString())
        ->assertJsonPath('data.entitlement.capabilities.dashboard', 'full')
        ->assertJsonPath('data.entitlement.limits.savings_account', null);

    expect($premiumDashboard->json('data.cached_at'))->toBe('2026-07-17T12:00:00+00:00');
});

it('uses billing metadata from the exact furthest Revolut entitlement row', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $selectedStart = now()->subMonth();
    $selectedEnd = now()->addMonths(2);

    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'billing_cycle' => 'yearly',
        'amount' => 5999,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_start' => $selectedStart,
        'current_period_end' => $selectedEnd,
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'billing_cycle' => 'monthly',
        'amount' => 699,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addMonth(),
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('data.provider', 'revolut')
        ->assertJsonPath('data.current_period_end', $selectedEnd->toISOString())
        ->assertJsonPath('billing_cycle', 'yearly')
        ->assertJsonPath('amount', '5999.00')
        ->assertJsonPath('current_period_start', $selectedStart->toISOString())
        ->assertJsonPath('current_period_end', $selectedEnd->toISOString())
        ->assertJsonPath('auto_renew', false)
        ->assertJsonPath('next_renewal_date', null);
});

it('leaves Revolut metadata null when identical canonical candidates are ambiguous', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $sharedEnd = now()->addMonth();

    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'billing_cycle' => 'yearly',
        'amount' => 5999,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => $sharedEnd,
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'billing_cycle' => 'monthly',
        'amount' => 699,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_start' => now()->subWeek(),
        'current_period_end' => $sharedEnd,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('data.provider', 'revolut')
        ->assertJsonPath('data.current_period_end', $sharedEnd->toISOString())
        ->assertJsonPath('has_subscription', true)
        ->assertJsonPath('subscription_status', 'active')
        ->assertJsonPath('plan', null)
        ->assertJsonPath('billing_cycle', null)
        ->assertJsonPath('amount', null)
        ->assertJsonPath('current_period_start', null)
        ->assertJsonPath('current_period_end', $sharedEnd->toISOString())
        ->assertJsonPath('auto_renew', true)
        ->assertJsonPath('next_renewal_date', $sharedEnd->toISOString());
});
