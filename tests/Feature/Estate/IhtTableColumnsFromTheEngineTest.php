<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

/*
 * One figure, every surface (CSJ 2026-10-01; audit items 34, 36): the web
 * Inheritance Tax table's "five years earlier / later" columns grew every row
 * at a 4.7% typed into the browser. They now come from the engine itself at a
 * shifted horizon, with each asset projected the same way the at-death column
 * is, and the life events' tax effect from the estate plan's own rule.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    Cache::flush();

    $this->user = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => 'single',
        'date_of_birth' => '1970-03-15',
        'gender' => 'female',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 150000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'account_type' => 'gia',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 600000,
    ]);
    Sanctum::actingAs($this->user);
});

it('serves the earlier and later columns from the engine at a shifted horizon', function () {
    $summary = $this->postJson('/api/estate/calculate-iht')->assertOk()->json('iht_summary');
    $engine = app(IHTCalculationService::class);

    $minus = $engine->calculateAtHorizonOffset($this->user->fresh(), null, false, -5);
    $plus = $engine->calculateAtHorizonOffset($this->user->fresh(), null, false, 5);

    expect((float) $summary['projected_minus_5']['net_estate'])->toEqualWithDelta((float) $minus['projected_net_estate'], 0.01)
        ->and((float) $summary['projected_plus_5']['iht_liability'])->toEqualWithDelta((float) $plus['projected_iht_liability'], 0.01)
        ->and($summary['projected_minus_5']['years_to_death'])->toBe(max(0, $summary['projected']['years_to_death'] - 5))
        ->and($summary['projected_plus_5']['years_to_death'])->toBe($summary['projected']['years_to_death'] + 5)
        ->and((float) $summary['projected_plus_5']['net_estate'])->toBeGreaterThan((float) $summary['projected']['net_estate'])
        ->and((float) $summary['projected']['net_estate'])->toBeGreaterThan((float) $summary['projected_minus_5']['net_estate']);
});

it('leaves the main projection untouched after a shifted run', function () {
    $engine = app(IHTCalculationService::class);
    $before = $engine->calculate($this->user->fresh());
    $engine->calculateAtHorizonOffset($this->user->fresh(), null, false, 5);
    $after = $engine->calculate($this->user->fresh());

    expect($after['years_to_death'])->toBe($before['years_to_death'])
        ->and((float) $after['projected_net_estate'])->toBe((float) $before['projected_net_estate']);
});

it('gives every asset row and group its own earlier and later value', function () {
    $response = $this->postJson('/api/estate/calculate-iht')->assertOk();
    $user = $response->json('assets_breakdown.user');
    $row = $user['assets']['investment'][0];

    expect($row)->toHaveKeys(['projected_value', 'projected_value_minus_5', 'projected_value_plus_5'])
        ->and((float) $row['projected_value_plus_5'])->toBeGreaterThan((float) $row['projected_value'])
        ->and((float) $user['group_totals']['investment']['value'])->toBe(600000.0)
        ->and($user['group_totals']['investment'])->toHaveKeys(['projected', 'minus_5', 'plus_5'])
        ->and($user)->toHaveKeys(['projected_total_minus_5', 'projected_total_plus_5']);
});

it('sends the estate above the nil rate band for each column', function () {
    $summary = $this->postJson('/api/estate/calculate-iht')->assertOk()->json('iht_summary');

    expect((float) $summary['current']['estate_after_nrb'])
        ->toEqualWithDelta(max(0, (float) $summary['current']['net_estate'] - (float) $summary['current']['nrb_available']), 0.01)
        ->and($summary['projected_minus_5'])->toHaveKey('estate_after_nrb');
});
