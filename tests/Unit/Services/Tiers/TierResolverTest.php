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

it('treats a canonical users tier as a cache, not a grant (W-0018)', function () {
    // CSJ ruled 2026-08-21 that the column never grants entitlement (option (b)
    // on W-0018): it caches the last provider outcome. What makes a user premium
    // is the live subscription, whatever the column says. The grandfathering
    // half of this test went with the legacy plans on 2026-09-08 — they are
    // premium in the data now, so there is no cohort left to protect.
    $cached = User::factory()->create(['plan' => 'premium', 'tier' => 'premium']);
    $cached->subscription()->create(['plan' => 'premium', 'status' => 'active', 'amount' => 0]);

    $uncached = User::factory()->create(['plan' => 'premium', 'tier' => null]);
    $uncached->subscription()->create(['plan' => 'premium', 'status' => 'active', 'amount' => 0]);

    expect($this->resolver->resolve($cached->fresh()))->toBe('premium')
        ->and($this->resolver->resolve($uncached->fresh()))->toBe('premium');
});
