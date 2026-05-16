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
    expect($this->resolver->resolve($u))->toBe('free')
        ->and($this->resolver->isGrandfatheredLegacyPaid($u))->toBeTrue();
});

it('does not flag a free user as grandfathered', function () {
    $u = User::factory()->create(['plan' => 'free', 'tier' => null]);
    expect($this->resolver->isGrandfatheredLegacyPaid($u))->toBeFalse();
});
