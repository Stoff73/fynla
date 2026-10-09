<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\PSACalculator;
use App\Services\Savings\SavingsActionDefinitionService;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * Each joint owner is taxed on their own share of a joint account's interest
 * (ITA 2007 s836, https://www.legislation.gov.uk/ukpga/2007/3/section/836;
 * the Personal Savings Allowance, ITA 2007 s12B). One figure, from
 * IncomeDefinitionsService::estimatedAnnualInterest, on every surface: the
 * Bank Accounts card told Alex (csjones user 502, 2026-10-08) "£960 exceeds
 * your £500 Personal Savings Allowance" while the Tax Strategy page and Fyn
 * said his £480 was covered.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->alex = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 84000, 'marital_status' => 'married',
    ]);
    $this->jamie = User::factory()->create([
        'employment_status' => 'part_time', 'annual_employment_income' => 26000, 'marital_status' => 'married',
        'spouse_id' => $this->alex->id,
    ]);
    $this->alex->update(['spouse_id' => $this->jamie->id]);

    SavingsAccount::factory()->create([
        'user_id' => $this->alex->id, 'joint_owner_id' => $this->jamie->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
        'is_isa' => false, 'account_type' => 'easy_access', 'account_name' => 'Easy access',
        'institution' => 'Santander', 'current_balance' => 24000, 'interest_rate' => 4.0,
    ]);
});

it('counts only the user\'s own share of joint interest', function () {
    $alex = app(PSACalculator::class)->assessPSAPosition($this->alex->fresh());

    expect($alex['annual_interest'])->toBe(480.0)
        ->and($alex['psa_amount'])->toBe(500.0)
        ->and($alex['is_breached'])->toBeFalse()
        ->and($alex['annual_interest'])->toBe(round(app(IncomeDefinitionsService::class)->estimatedAnnualInterest($this->alex->fresh()), 2));
});

it('counts the joint owner\'s share too', function () {
    $jamie = app(PSACalculator::class)->assessPSAPosition($this->jamie->fresh());

    expect($jamie['annual_interest'])->toBe(480.0);
});

it('shows each account\'s share, rate and interest in the card working', function () {
    $this->seed(SavingsActionDefinitionSeeder::class);
    // Alex's share now breaches a £500 allowance: 50% of £30,000 at 4% = £600.
    SavingsAccount::query()->update(['current_balance' => 30000]);

    $recs = app(SavingsActionDefinitionService::class)
        ->evaluateAgentActions([], [], collect(), collect(), $this->alex->id)['recommendations'];
    $card = collect($recs)->firstWhere('definition_key', 'psa_breached');

    expect($card)->not->toBeNull();
    $working = collect($card['decision_trace'])->firstWhere('data_field', 'annual_interest')['explanation'];
    expect($working)->toContain('Easy access at Santander — your 50% of £30,000 × 4.00% = £600')
        ->and($card['description'])->toContain('£600');
});
