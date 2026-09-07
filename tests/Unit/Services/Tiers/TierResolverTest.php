<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Tiers\TierResolver;

beforeEach(fn () => $this->resolver = app(TierResolver::class));

it('does not resolve a stale explicit users tier without a live provider grant', function () {
    $u = User::factory()->create(['tier' => 'premium']);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('resolves a live Revolut Premium grant regardless of the users tier cache', function () {
    $u = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $u->id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);

    expect($this->resolver->resolve($u))->toBe('premium');
});

it('resolves a user with no subscription to free', function () {
    $u = User::factory()->create(['tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('resolves a preview user to free for gating', function () {
    $u = User::factory()->create(['is_preview_user' => true, 'tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('resolves a paid legacy subscriber with null tier to premium and still grandfathers the row caps', function () {
    // CSJ, 2026-09-07 (the per-cohort decision spec §5.2 was waiting for): the
    // plans sold before the tier scheme confer Premium, so the live legacy
    // subscription resolves 'premium' through the provider path. The
    // grandfathering flag stays, so DbTierGate never narrows an existing row.
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    $u->subscription()->create(['plan' => 'pro', 'status' => 'active', 'amount' => 0]);
    expect($this->resolver->resolve($u->fresh()))->toBe('premium')
        ->and($this->resolver->isGrandfatheredLegacyPaid($u->fresh()))->toBeTrue();
});

it('does not flag a free user as grandfathered', function () {
    $u = User::factory()->create(['plan' => 'free', 'tier' => null]);
    expect($this->resolver->isGrandfatheredLegacyPaid($u))->toBeFalse();
});

it('does not grandfather a stale legacy plan slug without a subscription record', function () {
    // The exact bug being fixed: users.plan is NOT reset when a paid
    // subscription lapses/cancels, so a stale 'pro' slug with no live
    // subscription row must NOT be grandfathered forever (spec §4.4/§5.2
    // protect *current* paying subscribers, not stale artefacts).
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    expect($this->resolver->isGrandfatheredLegacyPaid($u))->toBeFalse();
});

it('treats a canonical users tier as a migration-cohort marker, not a grant (W-0018)', function () {
    // The one legitimate read of `users.tier`. CSJ ruled 2026-08-21 that the
    // column never grants entitlement (option (b) on W-0018) — it caches the last
    // provider outcome and marks whether a user has been migrated onto the new
    // tier scheme. This pins BOTH halves of that in one test, because the two
    // together are what the docblock had been contradicting.
    $legacy = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    $legacy->subscription()->create(['plan' => 'pro', 'status' => 'active', 'amount' => 0]);

    // Same user, same live legacy subscription, but already migrated: a canonical
    // value in the column answers "yes, migrated", so grandfathering stops.
    $migrated = User::factory()->create(['plan' => 'pro', 'tier' => 'premium']);
    $migrated->subscription()->create(['plan' => 'pro', 'status' => 'active', 'amount' => 0]);

    expect($this->resolver->isGrandfatheredLegacyPaid($legacy->fresh()))->toBeTrue()
        ->and($this->resolver->isGrandfatheredLegacyPaid($migrated->fresh()))->toBeFalse()
        // ...and the marker still confers nothing on its own: what makes both
        // sides premium is the live legacy subscription (CSJ, 2026-09-07), which
        // the provider path resolves whatever the column says.
        ->and($this->resolver->resolve($migrated->fresh()))->toBe('premium')
        ->and($this->resolver->resolve($legacy->fresh()))->toBe('premium');
});
