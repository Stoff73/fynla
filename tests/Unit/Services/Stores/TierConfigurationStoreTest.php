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

it('allActiveOrdered returns only active tiers ordered free → tier1 → tier2 → tier3', function () {
    TierConfiguration::where('tier', 'tier2')->update(['is_active' => false]);

    $tiers = $this->store->allActiveOrdered();

    expect($tiers->pluck('tier')->all())->toBe(['free', 'tier1', 'tier3'])
        ->and($tiers->every(fn ($t) => $t->is_active))->toBeTrue();
});

it('allOrdered returns every tier (active and inactive) in canonical order', function () {
    TierConfiguration::where('tier', 'tier2')->update(['is_active' => false]);

    $tiers = $this->store->allOrdered();

    expect($tiers->pluck('tier')->all())->toBe(['free', 'tier1', 'tier2', 'tier3'])
        ->and($tiers->firstWhere('tier', 'tier2')->is_active)->toBeFalse();
});

it('lowestTierWithCapability returns the first tier whose estate capability is full', function () {
    $result = $this->store->lowestTierWithCapability('estate', 'full');

    expect($result)->toBeArray()
        ->and($result['tier'])->toBe('tier2')
        ->and($result)->toHaveKeys(['tier', 'display_name']);
});

it('lowestTierWithCapability returns null when no tier matches the requested verb', function () {
    $result = $this->store->lowestTierWithCapability('estate', 'nonexistent_verb');

    expect($result)->toBeNull();
});

it('lowestTierWithCapability returns null when no tier has the requested capability key', function () {
    $result = $this->store->lowestTierWithCapability('unknown_capability_key', 'full');

    expect($result)->toBeNull();
});
