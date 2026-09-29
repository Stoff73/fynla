<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Marketing\SaveTaxEstimateService;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The /savetax funnel promises a figure before sign-up; the plan engine
 * delivers one after. For the same person (employed, earning the top of the
 * chosen band, no pension saving yet, no savings) the funnel's pension line
 * must never exceed what the engine finds, and must equal it wherever the
 * engine's sizing is set by the band (the 60% trap and the additional rate).
 * 29 Sep 2026 run: £22,562 promised, about £5,550 delivered (L3-1).
 */
beforeEach(function () {
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
});

function enginePensionSaving(int $income, bool $retired = false): float
{
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'employment_status' => $retired ? 'retired' : 'employed',
        'annual_employment_income' => $retired ? 0 : $income,
        'annual_other_income' => $retired ? $income : 0,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(45)->toDateString(),
    ]);

    return (float) collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->whereIn('type', ['pension_tax_relief', 'pa_taper_rescue', 'additional_rate_avoidance'])
        ->sum('estimated_annual_tax_saved');
}

function funnelPensionSaving(string $band, bool $retired = false): int
{
    $result = app(SaveTaxEstimateService::class)->estimate(['employment' => $retired ? 'retired' : 'full-time', 'income' => $band, 'assets' => []]);

    return (int) collect($result['savings'])->whereIn('key', ['pension', 'tax_trap_60'])->sum('amount');
}

it('never promises more pension relief than the plan engine finds', function (string $band, int $income, bool $equal) {
    $engine = enginePensionSaving($income);
    $funnel = funnelPensionSaving($band);

    expect($funnel)->toBeGreaterThan(0)
        ->and((float) $funnel)->toBeLessThanOrEqual(round($engine) + 1.0);

    if ($equal) {
        expect((float) $funnel)->toEqualWithDelta($engine, 1.0);
    }
})->with([
    // Basic band: both size at a tenth of earnings.
    'basic' => ['upto_50270', 50270, true],
    // Higher band: the funnel keeps a tenth of earnings (agreed 29 Sep), below
    // the engine's whole higher-rate slice.
    'higher' => ['50271_100000', 100000, false],
    'trap' => ['100001_125140', 125140, true],
    'additional' => ['over_125140', 150000, true],
]);

it('promises a retiree exactly the relief the plan finds, in every band', function (string $band, int $income) {
    expect((float) funnelPensionSaving($band, true))->toEqualWithDelta(enginePensionSaving($income, true), 1.0);
})->with([
    'no income' => ['zero', 0],
    'basic' => ['upto_50270', 50270],
    'higher' => ['50271_100000', 100000],
    'trap' => ['100001_125140', 125140],
    'additional' => ['over_125140', 150000],
]);
