<?php

declare(strict_types=1);

use App\Agents\SavingsAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * The excess-cash cards (item 15, CSJ 2026-10-08): "if a person has entered a
 * gia or bond account, these cards need to show, if a person has not, then they
 * only show if they have used their allowances up". A holder with ISA room sees
 * both cards; every card still needs cash above the user's own emergency fund
 * target. Before this the pension card covered every case the bond and General
 * Investment Account cards fired on, so neither ever reached a user.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
    $this->seed(InvestmentActionDefinitionSeeder::class);
    $this->seed(EstateActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
});

function excessCashSaver(array $user = [], float $cash = 20000): User
{
    $saver = User::factory()->create(array_merge([
        'employment_status' => 'employed', 'annual_employment_income' => 40000, 'monthly_expenditure' => 1000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ], $user));
    SavingsAccount::factory()->create([
        'user_id' => $saver->id, 'account_name' => 'Easy Saver', 'institution' => 'Nationwide',
        'account_type' => 'easy_access', 'access_type' => 'immediate', 'is_isa' => false, 'is_emergency_fund' => false,
        'current_balance' => $cash, 'interest_rate' => 3.0, 'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    return $saver;
}

function useIsaAllowance(User $user): void
{
    InvestmentAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'current_value' => 30000,
        'isa_subscription_current_year' => 20000, 'contributions_ytd' => 20000,
    ]);
}

/** @return array<string, array<string, mixed>> definition key => item */
function excessCashCards(User $user): array
{
    return collect(app(NextActionsService::class)->buildAll($user->id))
        ->filter(fn (array $i): bool => in_array($i['card']['definition_key'] ?? null, ['excess_cash_isa_available', 'excess_cash_pension', 'excess_cash_bond', 'excess_cash_gia'], true))
        ->keyBy(fn (array $i): string => $i['card']['definition_key'])
        ->all();
}

it('shows no bond or General Investment Account card to someone without one who has ISA allowance left', function () {
    $cards = excessCashCards(excessCashSaver());

    expect($cards)->toHaveKey('excess_cash_isa_available')
        ->and($cards)->not->toHaveKey('excess_cash_gia')
        ->and($cards)->not->toHaveKey('excess_cash_bond');
});

it('shows the General Investment Account card beside the ISA card to someone who holds one', function () {
    $user = excessCashSaver();
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia', 'current_value' => 5000]);

    $cards = excessCashCards($user);

    expect($cards)->toHaveKey('excess_cash_gia')
        ->and($cards)->toHaveKey('excess_cash_isa_available');
});

it('shows the bond card to someone who holds a bond, while the pension card still fires', function () {
    $user = excessCashSaver(cash: 60000);
    useIsaAllowance($user);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'onshore_bond', 'current_value' => 50000]);

    $cards = excessCashCards($user);

    expect($cards)->toHaveKey('excess_cash_bond')
        ->and($cards)->toHaveKey('excess_cash_pension');
});

it('offers the pension, not a General Investment Account, once the ISA is used while relief room remains', function () {
    $user = excessCashSaver();
    useIsaAllowance($user);

    $cards = excessCashCards($user);

    expect($cards)->toHaveKey('excess_cash_pension')
        ->and($cards)->not->toHaveKey('excess_cash_gia');
});

it('offers no pension card from 75, and the General Investment Account card above a retired user\'s own three-month target', function () {
    // FA 2004 s188(3)(a): no relief on contributions paid after reaching 75.
    // EmergencyFundCalculator::getTargetMonths: 3 months for someone retired.
    $user = excessCashSaver(['employment_status' => 'retired', 'annual_employment_income' => 0, 'date_of_birth' => now()->subYears(76)->toDateString()]);
    useIsaAllowance($user);

    $cards = excessCashCards($user);
    $analysis = app(SavingsAgent::class)->analyze($user->id);
    $monthly = (float) (($analysis['data'] ?? $analysis)['summary']['monthly_expenditure']);

    expect($cards)->not->toHaveKey('excess_cash_pension')
        ->and($cards)->toHaveKey('excess_cash_gia')
        ->and($cards['excess_cash_gia']['card']['figures'])->toMatchArray([
            'target_amount' => '£'.number_format(3 * $monthly, 0),
            'surplus_amount' => '£'.number_format(20000 - 3 * $monthly, 0),
        ]);
});

it('gives a holder the approved steps, filled from their own account and tax config', function () {
    $user = excessCashSaver(cash: 60000);
    useIsaAllowance($user);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia', 'provider' => 'Vanguard', 'current_value' => 5000]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'offshore_bond', 'provider' => 'Utmost', 'current_value' => 50000]);

    $cards = excessCashCards($user);
    $gia = app(ActionCardService::class)->for($user, $cards['excess_cash_gia']['id']);
    $bond = app(ActionCardService::class)->for($user, $cards['excess_cash_bond']['id']);

    expect($gia['why'])->toContain('Your ISA allowance is used for this year.')
        ->and($gia['how_to'])->toContain('You can add to your General Investment Account with Vanguard.')
        ->and($gia['how_to'])->toContain('Dividends above your £500 dividend allowance are taxed each year.')
        ->and($bond['how_to'])->toContain('You can add to your bond with Utmost, if it takes further payments.')
        ->and(implode(' ', $bond['how_to']))->not->toContain('Before you buy');
});
