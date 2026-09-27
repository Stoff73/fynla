<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function bandRec(float $income, string $type): ?array
{
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(45)->toDateString(),
    ]);

    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)->firstWhere('type', $type);
}

it('prices Personal Allowance rescue at the higher rate plus the allowance won back', function () {
    // £110,000: £10,000 in the taper band. 40% relief (£4,000) plus £5,000 of
    // allowance restored, which was taxed at 40% (£2,000). ITA 2007 s35.
    $rec = bandRec(110000, 'pa_taper_rescue');

    expect($rec)->not->toBeNull()
        ->and($rec['estimated_annual_tax_saved'])->toBe(6000.0)
        ->and($rec['description'])->toContain('Reduce your income tax at 40% by £4,000');
});

it('prices additional-rate avoidance as the tax actually saved', function () {
    // £140,000 with the £60,000 allowance: tax on £140,000 is £49,203
    // (£7,540 + £34,976 + £6,687, no Personal Allowance) and on £80,000 is
    // £19,432 (£7,540 + £11,892), so the contribution saves £29,771.
    $rec = bandRec(140000, 'additional_rate_avoidance');

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe(60000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBe(29771.0);
});
