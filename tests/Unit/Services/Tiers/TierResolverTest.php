<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\TierResolver;

beforeEach(fn () => $this->resolver = app(TierResolver::class));

it('resolves an explicit users.tier value', function () {
    $u = User::factory()->create(['tier' => 'tier2']);
    expect($this->resolver->resolve($u))->toBe('tier2');
});

it('resolves a user with no subscription to free', function () {
    $u = User::factory()->create(['tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('resolves a preview user to free for gating', function () {
    $u = User::factory()->create(['is_preview_user' => true, 'tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('grandfathers a paid legacy subscriber with null tier to free-gating-but-never-narrower (no mechanical map)', function () {
    // Spec §5.2: legacy paid sub, tier still null pending per-cohort CSJ
    // conversion decision. Resolver returns 'free' for *gating arithmetic*
    // but isGrandfathered() flags them so DbTierGate never blocks an
    // existing-row create (PR 3 consumes this).
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    $u->subscription()->create(['plan' => 'pro', 'status' => 'active', 'amount' => 0]);
    expect($this->resolver->resolve($u->fresh()))->toBe('free')
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
