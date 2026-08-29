<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Models\UserAssumption;
use App\Services\Estate\EstateProjectionService;
use App\Services\Settings\AssumptionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0520 — the projected estate reads the growth method the user actually chose.
 *
 * `Settings → Assumptions` offers "Monte Carlo" or "Custom", and a rate box when Custom is
 * picked (`AssumptionsSettings.vue:329`, validated at `AssumptionsController:65`). The
 * estate projection called `projectInvestmentsMonteCarlo()` directly, straight past
 * `projectInvestments()` — the dispatcher that reads the setting — so the choice was
 * ignored in the figure that decides projected Inheritance Tax.
 *
 * It was not ignored ENTIRELY, which is what hid it: `getFallbackGrowthRate()` reads the
 * custom rate, but only as the fallback for when the simulation fails. The user's explicit
 * choice was reachable solely by an error, which is exactly backwards.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function investorWithCustomRate(float $value, ?float $customRate): User
{
    $user = User::factory()->create([
        'date_of_birth' => '1980-01-01',
        'gender' => 'female',
        'marital_status' => 'single',
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'current_value' => $value,
        'ownership_type' => 'individual',
    ]);

    if ($customRate !== null) {
        UserAssumption::create([
            'user_id' => $user->id,
            'assumption_type' => 'estate_planning',
            'investment_growth_method' => 'custom',
            'custom_investment_rate' => $customRate,
        ]);
    }

    return $user->fresh();
}

it('compounds at the rate the user chose when the method is custom', function () {
    // 10% for ten years on £100,000 is £259,374.25 and nothing else. A Monte Carlo p20
    // cannot land on that figure, so this assertion is only satisfiable by the dispatcher
    // actually reading the setting.
    $user = investorWithCustomRate(100_000, 10.0);

    $projected = app(EstateProjectionService::class)->projectInvestments(
        $user,
        null,
        10,
        app(AssumptionsService::class)->getEstateAssumptions($user),
        false,
    );

    expect($projected)->toEqualWithDelta(100_000 * pow(1.10, 10), 0.01);
});

it('returns today value at a zero-year horizon rather than simulating nothing', function () {
    // The guard that only ever existed on the dispatcher. Reached when the household is
    // already at its modelled horizon.
    $user = investorWithCustomRate(100_000, 10.0);

    $projected = app(EstateProjectionService::class)->projectInvestments(
        $user,
        null,
        0,
        app(AssumptionsService::class)->getEstateAssumptions($user),
        false,
    );

    expect($projected)->toEqualWithDelta(100_000.0, 0.01);
});

it('still simulates for a household that has not chosen a custom rate', function () {
    // The default path must be untouched: no override means Monte Carlo, as before.
    $user = investorWithCustomRate(100_000, null);

    $projected = app(EstateProjectionService::class)->projectInvestments(
        $user,
        null,
        10,
        app(AssumptionsService::class)->getEstateAssumptions($user),
        false,
    );

    // Not the custom-rate answer, and a real projection rather than a passthrough.
    expect($projected)->toBeGreaterThan(0.0)
        ->and($projected)->not->toEqualWithDelta(100_000 * pow(1.10, 10), 0.01);
});

it('saves the setting the user actually submitted, all three columns of it', function () {
    // W-0520, the two layers UNDER the projection, both of which had to be fixed before
    // fixing the projection could reach a real user.
    //
    //  1. `assumption_type` did not accept `estate_planning` — the enum ALTER in
    //     `2026_02_03_100002` landed on no database, so the row could not be written.
    //  2. `UserAssumption::$fillable` omitted all three estate columns, so `fill()`
    //     discarded them without complaint even where the row could be written.
    //
    // This drives `AssumptionsService` rather than `UserAssumption::create()` precisely
    // because the second defect is invisible to a test that sets the attributes directly.
    $user = User::factory()->create();

    app(AssumptionsService::class)->updateAssumptions($user->id, 'estate_planning', [
        'inflation_rate' => 3.10,
        'property_growth_rate' => 4.20,
        'investment_growth_method' => 'custom',
        'custom_investment_rate' => 6.30,
    ]);

    $stored = app(AssumptionsService::class)->getEstateAssumptions($user->fresh());

    expect((float) $stored['inflation_rate'])->toBe(3.10)
        ->and((float) $stored['property_growth_rate'])->toBe(4.20)
        ->and($stored['investment_growth_method'])->toBe('custom')
        ->and((float) $stored['custom_investment_rate'])->toBe(6.30)
        ->and($stored['has_overrides'])->toBeTrue();
});
