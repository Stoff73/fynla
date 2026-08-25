<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;

/**
 * W-0154 R2. The `annual_income` the estate aggregator publishes for a Defined Benefit
 * pension was `$pension->expected_annual_pension ?? 0` — a column that has never existed
 * on `db_pensions`. `?? 0` swallowed the null, so every Defined Benefit pension in the
 * application reported £0 of income to the estate.
 *
 * **Stated plainly: nothing reads this field today.** No consumer of
 * `gatherUserAssets()` touches `annual_income` on a `db_pension` row, because the
 * estate's own retirement income projection
 * (`IHTCalculationService::getRetirementIncome()`) reads no pension at all — the larger
 * defect, tracked as W-0154 R1 and owned elsewhere. This is corrected and pinned so the
 * field is truthful when something does read it, rather than being wrong the day it is
 * wired up.
 *
 * The source is the derived, revalued figure at the scheme's Normal Retirement Age,
 * falling back to the accrued figure the form captures — `PensionDerivedColumnCalculator`
 * only writes the derived column when a write triggers recalculation, so rows predating
 * that are null. A one-word column swap would have replaced "always zero" with "zero
 * until someone happens to re-save the record", which looks fixed and is not.
 */
function aggregatorDbPensionIncome(DBPension $pension): float
{
    $assets = app(EstateAssetAggregatorService::class)
        ->gatherUserAssets($pension->user()->first());

    $row = $assets->firstWhere('asset_type', 'db_pension');

    expect($row)->not->toBeNull();

    return (float) $row->annual_income;
}

it('publishes the revalued figure at the scheme retirement age', function (): void {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 35000,
        'projected_annual_pension_at_nra_gbp' => 41000,
    ]);

    expect(aggregatorDbPensionIncome($pension))->toBe(41000.0);
});

it('falls back to the accrued figure when the derived column has never been written', function (): void {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 35000,
        'projected_annual_pension_at_nra_gbp' => null,
    ]);

    expect(aggregatorDbPensionIncome($pension))->toBe(35000.0);
});

it('reports nothing when the scheme records no pension amount at all', function (): void {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => null,
        'projected_annual_pension_at_nra_gbp' => null,
    ]);

    expect(aggregatorDbPensionIncome($pension))->toBe(0.0);
});

it('keeps the Defined Benefit pension out of the estate itself', function (): void {
    // The income figure is for projections only — a Defined Benefit pension has no
    // transfer value and dies with the member, so it must never carry estate value.
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 35000,
        'projected_annual_pension_at_nra_gbp' => 41000,
    ]);

    $row = app(EstateAssetAggregatorService::class)
        ->gatherUserAssets($pension->user()->first())
        ->firstWhere('asset_type', 'db_pension');

    expect((float) $row->current_value)->toBe(0.0)
        ->and((float) $row->full_value)->toBe(0.0)
        ->and($row->is_iht_exempt)->toBeTrue();
});
