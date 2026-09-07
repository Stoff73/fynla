<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\TierResolver;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * CSJ, 2026-09-04: David & Sarah Mitchell (`peak_earners`) resolve premium so a
 * visitor to the demo can see what premium looks like. Every other persona stays
 * free, which is the comparison.
 *
 * The lever is `users.tier` read ONLY inside the preview branch of
 * PremiumEntitlementResolver. These three pin that it is exactly that narrow: a
 * REAL user's column still grants nothing (W-0018), which is the decision the
 * resolver's docblock protects.
 */
it('resolves premium for the demo household', function () {
    $user = User::factory()->create(['is_preview_user' => true, 'preview_persona_id' => 'peak_earners', 'tier' => 'premium']);

    expect(app(TierResolver::class)->resolve($user))->toBe('premium');
});

it('leaves every other persona free, so the two can be compared', function () {
    $user = User::factory()->create(['is_preview_user' => true, 'preview_persona_id' => 'young_family', 'tier' => null]);

    expect(app(TierResolver::class)->resolve($user))->toBe('free');
});

it('does not turn the column into a grant for a real user', function () {
    // W-0018. A real user carrying tier='premium' with no subscription and no
    // entitlement is a stale query cache, and must still resolve free.
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'premium']);

    expect(app(TierResolver::class)->resolve($user))->toBe('free');
});
