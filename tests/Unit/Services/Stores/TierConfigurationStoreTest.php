<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Stores\Exceptions\TierConfigValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->store = app(TierConfigurationStore::class);
});

it('reads the active config for a tier', function () {
    $cfg = $this->store->forTier('free');
    expect($cfg)->toBeInstanceOf(TierConfiguration::class)
        ->and($cfg->tier)->toBe('free');
});

it('returns the count cap for an entity/tier pair', function () {
    expect($this->store->capFor('free', 'savings_account'))->toBe(3)
        ->and($this->store->capFor('tier1', 'savings_account'))->toBeNull()
        ->and($this->store->capFor('free', 'unknown_entity'))->toBeNull();
});

it('returns the capability verb for an entity/tier pair', function () {
    expect($this->store->capabilityFor('free', 'estate'))->toBe('teaser')
        ->and($this->store->capabilityFor('tier3', 'estate'))->toBe('full');
});

it('memoises reads within a request', function () {
    $this->store->forTier('free');
    DB::enableQueryLog();
    $this->store->forTier('free');
    expect(DB::getQueryLog())->toBeEmpty();
});

it('admin-updates a tier, audits, and invalidates the cache', function () {
    $admin = User::factory()->create();
    $this->store->forTier('free'); // warm cache

    $updated = $this->store->updateTier('free', ['price_monthly_pence' => 199], $admin, IngestSource::ADMIN);

    expect($updated->price_monthly_pence)->toBe(199)
        ->and($this->store->forTier('free')->price_monthly_pence)->toBe(199); // cache dropped
    $this->assertDatabaseHas('audit_logs', ['model_type' => 'tier_configuration']);
});

it('rejects an invalid tier slug', function () {
    $admin = User::factory()->create();
    expect(fn () => $this->store->updateTier('platinum', [], $admin, IngestSource::ADMIN))
        ->toThrow(TierConfigValidationException::class);
});

it('rejects a non-admin/seeder ingest source', function () {
    $admin = User::factory()->create();
    expect(fn () => $this->store->updateTier('free', ['price_monthly_pence' => 1], $admin, IngestSource::FORM))
        ->toThrow(TierConfigValidationException::class);
});
