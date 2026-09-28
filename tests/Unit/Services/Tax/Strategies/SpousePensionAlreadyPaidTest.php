<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function spousePensionRec(string $mode, array $household): ?array
{
    $user = User::factory()->create([
        'household_calculation_mode' => $mode,
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'non_earner_spouse_pension');
}

it('stays silent when a non-earning spouse already pays in the maximum', function () {
    $net = app(TaxStrategyMath::class)->nonEarnerPensionContribution()['net'];

    expect(spousePensionRec('single_earner_couple', ['spouse_pension_input_annual' => $net]))->toBeNull();
});

it('suggests only the remainder when a non-earning spouse pays part', function () {
    $figures = app(TaxStrategyMath::class)->nonEarnerPensionContribution();
    $rec = spousePensionRec('single_earner_couple', ['spouse_pension_input_annual' => 1000]);

    $remaining = round($figures['net'] - 1000, 2);
    expect($rec)->not->toBeNull()
        ->and($rec['net_contribution'])->toBe($remaining)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($remaining * $figures['relief'] / $figures['net'], 2));
});

it('sizes a modest earner by the earnings left after what they already contribute', function () {
    $rec = spousePensionRec('dual_earner', ['spouse_annual_income' => 8000, 'spouse_annual_earnings' => 8000, 'spouse_pension_input_annual' => 3000]);

    expect($rec)->not->toBeNull()
        ->and($rec['gross_capacity'])->toBe(5000.0);
});

// CSJ 2026-09-28: relief is capped at relevant UK earnings (FA 2004 s189-190),
// not total income. £9,000 of rent or pension is not earnings.
it('caps the top-up at earnings from work, or the basic amount when there are none', function (?float $earnings, float $gross) {
    $rec = spousePensionRec('dual_earner', ['spouse_annual_income' => 9000, 'spouse_annual_earnings' => $earnings]);
    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');

    expect($rec['gross_capacity'])->toBe($gross)
        ->and($rec['net_cost'])->toBe(round($gross * (1 - $basic), 2))
        ->and($rec['spouse_annual_earnings'])->toBe($earnings);
})->with([
    'all of it earned' => [9000.0, 9000.0],
    'none of it earned' => [0.0, 3600.0],
    'earnings not given' => [null, 3600.0],
]);
