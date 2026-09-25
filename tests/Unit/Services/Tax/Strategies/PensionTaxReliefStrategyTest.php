<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function reliefRecs(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->whereIn('type', ['pension_relief_higher_rate', 'pension_relief_basic_rate'])
        ->keyBy('type')->all();
}

function reliefUser(float $income, array $extra = []): User
{
    return User::factory()->create($extra + [
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
}

it('sizes a higher-rate item to the slice taxed at the higher rate', function () {
    $user = reliefUser(60000);
    $math = app(TaxStrategyMath::class);
    $slice = 60000 - $math->bandThresholdsFor($user)['higher'];
    $display = (int) (round($slice / 100) * 100);

    $rec = reliefRecs($user)['pension_relief_higher_rate'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) $display)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($display * $math->bandRateForBand('higher'), 2))
        ->and(reliefRecs($user))->not->toHaveKey('pension_relief_basic_rate');
});

it('gives a basic-rate earner an item sized at a tenth of their earnings', function () {
    $user = reliefUser(30000);
    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');

    $rec = reliefRecs($user)['pension_relief_basic_rate'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe(3000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round(3000 * $basic, 2));
});

it('stays silent for a basic-rate earner already paying in a tenth', function () {
    $user = reliefUser(30000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 8, 'employer_contribution_percent' => 3,
    ]);

    expect(reliefRecs($user))->toBe([]);
});

it('leaves the Personal Allowance taper band to pa_taper_rescue', function () {
    expect(reliefRecs(reliefUser(110000)))->toBe([]);
});

it('stays silent without taxable earnings', function (array $attrs) {
    expect(reliefRecs(reliefUser(...$attrs)))->toBe([]);
})->with([
    'below the Personal Allowance' => [[10000]],
    'no earnings' => [[0, ['employment_status' => 'retired', 'annual_other_income' => 30000]]],
    'aged 75' => [[30000, ['date_of_birth' => now()->subYears(76)->toDateString()]]],
]);
