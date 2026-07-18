<?php

declare(strict_types=1);

use App\Data\Billing\ResolvedEntitlement;
use App\Models\PremiumEntitlement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $now = CarbonImmutable::create(2026, 7, 18, 12, 0, 0, config('app.timezone'));
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);
});

afterEach(function (): void {
    DB::disableQueryLog();
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function createTaskThreeAppleGrant(User $user, array $overrides = []): PremiumEntitlement
{
    static $reference = 0;

    return $user->premiumEntitlements()->create(array_merge([
        'provider' => PremiumEntitlement::PROVIDER_APPLE,
        'provider_reference' => 'task-three-apple-'.++$reference,
        'product_id' => 'org.fynla.premium.monthly',
        'status' => PremiumEntitlement::STATUS_ACTIVE,
        'will_renew' => true,
        'period_start' => now()->subMonth(),
        'period_end' => now()->addMonth(),
        'grace_period_ends_at' => null,
        'revoked_at' => null,
        'last_verified_at' => now(),
        'provider_metadata' => [],
    ], $overrides));
}

it('defines the canonical immutable entitlement result', function (): void {
    expect(class_exists(ResolvedEntitlement::class))->toBeTrue();
});

it('returns Free when users tier is stale and no provider grant is live', function (): void {
    $user = User::factory()->create(['tier' => 'premium']);

    $resolved = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($resolved->tier)->toBe('free')
        ->and($resolved->provider)->toBeNull()
        ->and($resolved->status)->toBe('free')
        ->and($resolved->renews)->toBeFalse()
        ->and($resolved->periodEndsAt)->toBeNull();
});

it('resolves an active Revolut Premium subscription', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    $subscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => true,
        'current_period_end' => now()->addDays(10),
    ]);

    $resolved = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($resolved->tier)->toBe('premium')
        ->and($resolved->provider)->toBe('revolut')
        ->and($resolved->status)->toBe('active')
        ->and($resolved->renews)->toBeTrue()
        ->and($resolved->periodEndsAt?->equalTo($subscription->current_period_end))->toBeTrue();
});

it('keeps a cancelled Revolut subscription Premium until its exact period end', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->plan('premium')->cancelled()->create([
        'user_id' => $user->id,
        'auto_renew' => true,
        'current_period_end' => now()->addSecond(),
    ]);

    $beforeExpiry = app(PremiumEntitlementResolver::class)->resolve($user);
    Carbon::setTestNow(now()->addSecond());
    app(PremiumEntitlementResolver::class)->invalidate($user);
    $atExpiry = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($beforeExpiry->tier)->toBe('premium')
        ->and($beforeExpiry->status)->toBe('cancelled')
        ->and($beforeExpiry->renews)->toBeFalse()
        ->and($atExpiry->tier)->toBe('free');
});

it('rejects ended pending and non Premium Revolut rows', function (string $status, string $plan, ?string $periodEnd): void {
    $user = User::factory()->create(['tier' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => $plan,
        'status' => $status,
        'current_period_end' => $periodEnd === null ? null : CarbonImmutable::parse($periodEnd),
    ]);

    expect(app(PremiumEntitlementResolver::class)->resolve($user)->tier)->toBe('free');
})->with([
    'active exactly at expiry' => ['active', 'premium', '2026-07-18 12:00:00'],
    'pending Premium' => ['pending', 'premium', null],
    'active Free plan' => ['active', 'free', '2026-07-19 12:00:00'],
]);

it('resolves an active Apple entitlement only before its exact period end', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    createTaskThreeAppleGrant($user, ['period_end' => now()->addSecond()]);

    $beforeExpiry = app(PremiumEntitlementResolver::class)->resolve($user);
    Carbon::setTestNow(now()->addSecond());
    app(PremiumEntitlementResolver::class)->invalidate($user);
    $atExpiry = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($beforeExpiry->tier)->toBe('premium')
        ->and($beforeExpiry->provider)->toBe('apple')
        ->and($beforeExpiry->renews)->toBeTrue()
        ->and($atExpiry->tier)->toBe('free');
});

it('resolves Apple grace period and billing retry only through a live grace end', function (string $status, ?int $graceSeconds, string $expectedTier): void {
    $user = User::factory()->create(['tier' => 'free']);
    $graceEnd = $graceSeconds === null ? null : now()->addSeconds($graceSeconds);
    createTaskThreeAppleGrant($user, [
        'status' => $status,
        'period_end' => now()->subDay(),
        'grace_period_ends_at' => $graceEnd,
    ]);

    $resolved = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($resolved->tier)->toBe($expectedTier);
    if ($expectedTier === 'premium') {
        expect($resolved->periodEndsAt?->equalTo($graceEnd))->toBeTrue();
    }
})->with([
    'grace period before end' => ['grace_period', 1, 'premium'],
    'grace period exactly at end' => ['grace_period', 0, 'free'],
    'billing retry with live grace' => ['billing_retry', 1, 'premium'],
    'billing retry without grace' => ['billing_retry', null, 'free'],
]);

it('rejects expired and revoked Apple entitlements', function (string $status, ?string $revokedAt): void {
    $user = User::factory()->create(['tier' => 'premium']);
    createTaskThreeAppleGrant($user, [
        'status' => $status,
        'revoked_at' => $revokedAt === null ? null : CarbonImmutable::parse($revokedAt),
    ]);

    expect(app(PremiumEntitlementResolver::class)->resolve($user)->tier)->toBe('free');
})->with([
    'expired status' => ['expired', null],
    'revoked status' => ['revoked', null],
    'active row with revocation timestamp' => ['active', '2026-07-18 11:59:59'],
]);

it('keeps Apple cancellation through its period and reflects renewal only while live', function (int $secondsUntilEnd, string $expectedTier, bool $expectedRenews): void {
    $user = User::factory()->create(['tier' => 'free']);
    createTaskThreeAppleGrant($user, [
        'status' => 'cancelled',
        'will_renew' => true,
        'period_end' => now()->addSeconds($secondsUntilEnd),
    ]);

    $resolved = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($resolved->tier)->toBe($expectedTier)
        ->and($resolved->renews)->toBe($expectedRenews);
})->with([
    'inside period' => [1, 'premium', true],
    'at period end' => [0, 'free', false],
]);

it('chooses the furthest live provider without mutating overlapping audit rows', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(10),
    ]);
    createTaskThreeAppleGrant($user, ['period_end' => now()->addDays(20)]);

    $resolved = app(PremiumEntitlementResolver::class)->resolve($user);

    expect($resolved->provider)->toBe('apple')
        ->and($resolved->periodEndsAt?->equalTo(now()->addDays(20)))->toBeTrue()
        ->and($user->subscriptions()->count())->toBe(1)
        ->and($user->premiumEntitlements()->count())->toBe(1);
});

it('uses a stable provider tie breaker', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'current_period_end' => now()->addDays(10),
    ]);
    createTaskThreeAppleGrant($user, ['period_end' => now()->addDays(10)]);
    $resolver = app(PremiumEntitlementResolver::class);

    $first = $resolver->resolve($user);
    $resolver->invalidate($user);
    $second = $resolver->resolve($user);

    expect($first->provider)->toBe('apple')
        ->and($second->provider)->toBe('apple');
});

it('memoises provider queries and explicitly invalidates one user', function (): void {
    $user = User::factory()->create(['tier' => 'free']);
    createTaskThreeAppleGrant($user, ['period_end' => now()->addDay()]);
    $resolver = app(PremiumEntitlementResolver::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($resolver->resolve($user)->tier)->toBe('premium');
    expect($resolver->resolve($user)->tier)->toBe('premium');

    DB::table('premium_entitlements')->where('user_id', $user->id)->update([
        'status' => PremiumEntitlement::STATUS_REVOKED,
        'revoked_at' => now(),
    ]);

    expect($resolver->resolve($user)->tier)->toBe('premium');
    $resolver->invalidate($user);
    expect($resolver->resolve($user)->tier)->toBe('free');

    $providerSelects = collect(DB::getQueryLog())->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        return str_starts_with($sql, 'select')
            && (str_contains($sql, 'subscriptions') || str_contains($sql, 'premium_entitlements'));
    });

    expect($providerSelects)->toHaveCount(4);
});

it('keeps preview users Free even when provider records and stale tier claim Premium', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'is_preview_user' => true]);
    createTaskThreeAppleGrant($user);
    Subscription::factory()->plan('premium')->create(['user_id' => $user->id]);

    expect(app(PremiumEntitlementResolver::class)->resolve($user)->tier)->toBe('free');
});
