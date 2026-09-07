<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0481 — the factory could not reliably produce a row the column accepts.
 *
 * `AssetFactory` picked `asset_type` at random from EIGHT values while the enum
 * accepts five: `property`, `pension`, `investment`, `business`, `other`. Four of
 * the eight — `cash`, `business_interest`, `personal_possession`,
 * `life_insurance` — were rejected outright, and `business`, which the column
 * does accept, was never generated at all.
 *
 * So a factory call without an explicit `asset_type` failed roughly half the
 * time, at random. **That is worse than a factory that always fails**, because
 * it reads as a flaky test rather than a factory that cannot produce a valid row,
 * and the usual response to a flaky test is to re-run it.
 *
 * Enough iterations that a single rejected value in the list would surface.
 */
uses(RefreshDatabase::class);

it('produces a persistable asset every time, not half the time', function () {
    $user = User::factory()->create();

    // 40 draws over a 5-value list: a reintroduced invalid value would have to
    // dodge every one of them to survive this.
    for ($i = 0; $i < 40; $i++) {
        $asset = Asset::factory()->create(['user_id' => $user->id]);

        expect($asset->exists)->toBeTrue()
            ->and($asset->asset_type)->toBeIn(['property', 'pension', 'investment', 'business', 'other']);
    }
});

it('can still name an asset of every type it generates', function () {
    $user = User::factory()->create();

    foreach (['property', 'pension', 'investment', 'business', 'other'] as $type) {
        $asset = Asset::factory()->create(['user_id' => $user->id, 'asset_type' => $type]);

        // The name arms were keyed on the old vocabulary too, so `business`
        // fell through to the default while `business_interest` had the arm.
        expect($asset->asset_name)->not->toBeEmpty();
    }
});
