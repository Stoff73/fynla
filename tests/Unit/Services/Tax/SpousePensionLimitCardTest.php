<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The partner's pension tile follows the user's rule: relief on their own
 * contributions is capped at relevant UK earnings or the basic amount,
 * whichever is higher (FA 2004 s189-190). A partner living on a pension was
 * shown the £60,000 Annual Allowance (ice-cube, PR 989).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function partnerPensionTile(array $household): array
{
    $user = User::factory()->create(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 45000, 'marital_status' => 'married']);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, ...$household]);

    return collect(app(TaxStrategyCalculator::class)->calculate($user)->spouseAllowances)->firstWhere('key', 'pension_annual_allowance');
}

it('shows a retired partner the basic amount they can get relief on, not the Annual Allowance', function (): void {
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['relevant_earnings_minimum'];
    $tile = partnerPensionTile(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired']);

    expect($tile['label'])->toBe('Pension contribution limit without earnings')
        ->and((float) $tile['amount'])->toBe($basic);
});

it('shows a working partner the limit from their earnings', function (): void {
    $tile = partnerPensionTile(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'full_time']);

    expect($tile['label'])->toBe('Pension contribution limit from their earnings')
        ->and((float) $tile['amount'])->toBe(30000.0);
});

it('uses the earnings given when part of the income is pension or rent', function (): void {
    $tile = partnerPensionTile(['spouse_annual_income' => 30000, 'spouse_annual_earnings' => 10000, 'spouse_employment_status' => 'retired']);

    expect((float) $tile['amount'])->toBe(10000.0);
});

it('keeps the Annual Allowance, unconfirmed, when the partner\'s earnings are not known', function (): void {
    $annualAllowance = (float) app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'];
    $tile = partnerPensionTile(['spouse_annual_income' => 30000]);

    expect($tile['label'])->toBe('Pension Annual Allowance')
        ->and((float) $tile['amount'])->toBe($annualAllowance)
        ->and($tile['known'])->toBeFalse();
});
