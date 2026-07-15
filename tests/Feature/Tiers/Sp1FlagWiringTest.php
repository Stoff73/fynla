<?php

declare(strict_types=1);

use App\Services\Stores\TierConfigurationStore;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('exposes the per-tier doc upload allowance + storage quota', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->document_upload_allowance)->toBe(0)
        ->and($s->forTier('free')->document_storage_gb)->toBeNull()
        ->and((float) $s->forTier('premium')->document_storage_gb)->toBe(1.00)
        ->and($s->forTier('premium')->document_upload_allowance)->toBeNull();
});

it('exposes currency-display mode and snapshot window per tier', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->currency_display_mode)->toBe('gbp_only')
        ->and($s->forTier('premium')->currency_display_mode)->toBe('user_choice')
        ->and($s->forTier('free')->snapshot_surfacing_window_days)->toBe(90)
        ->and($s->forTier('premium')->snapshot_surfacing_window_days)->toBeNull()
        ->and($s->forTier('premium')->open_api_affordance)->toBeTrue();
});
