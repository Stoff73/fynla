<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('exposes the per-tier doc upload allowance + storage quota', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->document_upload_allowance)->toBe(3)
        ->and($s->forTier('free')->document_storage_gb)->toBeNull()
        ->and((float) $s->forTier('tier2')->document_storage_gb)->toBe(5.00)
        ->and($s->forTier('tier3')->document_upload_allowance)->toBe(6);
});

it('exposes currency-display mode and snapshot window per tier', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->currency_display_mode)->toBe('gbp_only')
        ->and($s->forTier('tier2')->currency_display_mode)->toBe('user_choice')
        ->and($s->forTier('free')->snapshot_surfacing_window_days)->toBe(90)
        ->and($s->forTier('tier3')->snapshot_surfacing_window_days)->toBe(2555)
        ->and($s->forTier('tier2')->open_api_affordance)->toBeTrue();
});
