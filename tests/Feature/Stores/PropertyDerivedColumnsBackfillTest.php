<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('properties:backfill-derived populates derived columns on legacy rows', function () {
    $user = User::factory()->create();

    // Insert via factory (bypasses store) with derived columns null.
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 150000,
        'current_value_gbp' => null,
        'equity_gbp' => null,
        'loan_to_value_pct' => null,
    ]);

    // Pass 5 PR 6: canonical source for outstanding mortgage is MortgageStore.
    // Seed a mortgage so the backfill recalculates against the same canonical sum.
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'outstanding_balance' => 150000,
    ]);

    $this->artisan('properties:backfill-derived-columns')
        ->expectsOutput('Backfilled 1 properties.')
        ->assertExitCode(0);

    $fresh = $property->fresh();
    expect((string) $fresh->current_value_gbp)->toBe('500000.00');
    expect((string) $fresh->equity_gbp)->toBe('350000.00');
    expect((string) $fresh->loan_to_value_pct)->toBe('30.00');
});
