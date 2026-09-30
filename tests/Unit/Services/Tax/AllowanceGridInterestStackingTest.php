<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Savings interest uses the Personal Allowance left by other income first,
 * then the Starting Rate for Savings, then the Personal Savings Allowance
 * (https://www.gov.uk/apply-tax-free-interest-on-savings). The user's grid
 * counted the same £160 against all three for a person with no income.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function usedOn(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->userAllowances)
        ->mapWithKeys(fn (array $p): array => [$p['key'] => (float) $p['used']])->all();
}

it('counts interest against the Personal Allowance first for someone with no income', function (): void {
    $user = User::factory()->create(['employment_status' => 'unemployed', 'annual_employment_income' => 0, 'marital_status' => 'single']);
    SavingsAccount::factory()->for($user)->create(['is_isa' => false, 'current_balance' => 4000, 'interest_rate' => 4.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    $used = usedOn($user);
    expect($used['personal_allowance'])->toBe(160.0)
        ->and($used['starting_rate_for_savings'])->toBe(0.0)
        ->and($used['savings_allowance'])->toBe(0.0);
});

it('counts interest above the Personal Allowance against the Starting Rate, then the Savings Allowance', function (): void {
    // £12,000 of pay leaves £570 of Personal Allowance; £8,000 of interest
    // uses that, all £5,000 of the Starting Rate, then £1,000 of the Savings Allowance.
    $user = User::factory()->create(['employment_status' => 'part_time', 'annual_employment_income' => 12000, 'marital_status' => 'single']);
    SavingsAccount::factory()->for($user)->create(['is_isa' => false, 'current_balance' => 200000, 'interest_rate' => 4.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    $used = usedOn($user);
    expect($used['starting_rate_for_savings'])->toBe(5000.0)
        ->and($used['savings_allowance'])->toBe(1000.0);
});
