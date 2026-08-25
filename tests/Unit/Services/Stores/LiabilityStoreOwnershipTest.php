<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\LiabilityStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0161: every joint liability Fyn created was stored at 100/0.
 *
 * `CoordinatingAgent::handleCreateLiability` set `ownership_percentage` to 100
 * whenever the tool call omitted it, and this store's own default was `??=` —
 * which cannot fire on a key that is already set. The two defaults between them
 * put the whole debt on the primary owner and nothing on the co-owner.
 *
 * The store's copy had a second gap of its own: it knew only `'joint'`, so a
 * tenants-in-common liability defaulted to 100 — the shape W-0025 closed for
 * chattels. Both now read `SharedOwnership`.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create();
    $this->store = app(LiabilityStore::class);
});

it('gives a joint liability a 50/50 split when no share is stated', function () {
    $liability = $this->store->create([
        'liability_name' => 'Joint car loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 12000,
        'ownership_type' => 'joint',
    ], $this->user, IngestSource::FYN_AI);

    expect((float) $liability->ownership_percentage)->toBe(50.0);
});

it('gives a tenants-in-common liability a 50/50 split too', function () {
    // The copy that used to live in this store knew only 'joint', so this case
    // fell through to 100.
    $liability = $this->store->create([
        'liability_name' => 'Shared improvement loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 8000,
        'ownership_type' => 'tenants_in_common',
    ], $this->user, IngestSource::FYN_AI);

    expect((float) $liability->ownership_percentage)->toBe(50.0);
});

it('leaves an individually-owned liability at 100', function () {
    $liability = $this->store->create([
        'liability_name' => 'Credit card',
        'liability_type' => 'credit_card',
        'current_balance' => 2400,
        'ownership_type' => 'individual',
    ], $this->user, IngestSource::FYN_AI);

    expect((float) $liability->ownership_percentage)->toBe(100.0);
});

it('keeps an uneven share the caller did state', function () {
    $liability = $this->store->create([
        'liability_name' => 'Joint loan, uneven split',
        'liability_type' => 'personal_loan',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
    ], $this->user, IngestSource::FYN_AI);

    expect((float) $liability->ownership_percentage)->toBe(70.0);
});
