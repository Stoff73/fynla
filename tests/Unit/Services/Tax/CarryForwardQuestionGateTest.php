<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Memory\HouseViewApplicability;
use App\Services\AI\Memory\SemanticFact;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Past pension payments are asked for only when carry forward could be used
 * (CSJ 2026-10-08): the user can fill ALL of this year's pension Annual
 * Allowance and ALL of this year's ISA allowance and still have spare cash
 * left over. Carry forward starts only once this year's allowance is used
 * (FA 2004 s228A, https://www.legislation.gov.uk/ukpga/2004/12/section/228A),
 * and relief is capped at relevant UK earnings (FA 2004 s190,
 * https://www.legislation.gov.uk/ukpga/2004/12/section/190). One gate,
 * TaxStrategyMath::carryForwardCouldApply, for the plan, the onboarding
 * question and Fyn's knowledge.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->math = app(TaxStrategyMath::class);
    $config = app(TaxConfigService::class);
    $this->taxYear = $config->getTaxYear();
    $this->pensionAllowance = (float) $config->getPensionAllowances()['annual_allowance'];
    $this->isaAllowance = (float) $config->getISAAllowances()['annual_allowance'];
});

function carryForwardSaver(float $earnings, float $cash): User
{
    $user = User::factory()->create([
        'annual_employment_income' => $earnings,
        'employment_status' => 'full_time',
        'marital_status' => 'single',
    ]);
    if ($cash > 0) {
        SavingsAccount::factory()->create([
            'user_id' => $user->id, 'is_isa' => false, 'account_type' => 'easy_access',
            'current_balance' => $cash, 'ownership_type' => 'individual', 'joint_owner_id' => null,
        ]);
    }

    return $user->fresh();
}

it('never applies when spare cash cannot fill both this year\'s pension and ISA allowances', function () {
    // More than this year's pension allowance, less than pension + ISA.
    $cash = $this->pensionAllowance + $this->isaAllowance - 1000;
    $user = carryForwardSaver(150000, $cash);

    expect($this->math->carryForwardCouldApply($user))->toBeFalse();
});

it('applies when spare cash is left after filling both allowances', function () {
    $user = carryForwardSaver(150000, $this->pensionAllowance + $this->isaAllowance + 1000);

    expect($this->math->carryForwardCouldApply($user))->toBeTrue();
});

it('counts only the ISA allowance still unused this tax year', function () {
    $user = carryForwardSaver(150000, $this->pensionAllowance + 5000);
    expect($this->math->carryForwardCouldApply($user))->toBeFalse();

    // The whole ISA allowance already paid in this year: cash above the pension
    // allowance alone is now spare.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'account_type' => 'cash_isa', 'current_balance' => 0,
        'isa_subscription_year' => $this->taxYear, 'isa_subscription_amount' => $this->isaAllowance,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    expect($this->math->carryForwardCouldApply($user->fresh()))->toBeTrue();
});

it('never asks the onboarding history question of a higher-rate earner without the spare cash', function () {
    $user = carryForwardSaver(100000, 30000);

    expect(OnboardingStateMachine::skipIfPensionHistoryNotApplicable($user))->toBeTrue();
});

it('asks the onboarding history question only when carry forward could apply', function () {
    $user = carryForwardSaver(150000, $this->pensionAllowance + $this->isaAllowance + 1000);

    expect(OnboardingStateMachine::skipIfPensionHistoryNotApplicable($user))->toBeFalse();
});

it('keeps the carry forward guide out of Fyn\'s knowledge when carry forward cannot apply', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $fact = fn (string $id): SemanticFact => new SemanticFact($id, 'house_view', $id, '', 1, null, null, 'body');
    $facts = [$fact('hv-pension-aa-carry-forward'), $fact('hv-salary-sacrifice-ni'), new SemanticFact('fca-x', 'fca', 'x', '', 1, null, null, 'body')];

    // Jamie on csjones, 2026-10-08: £26,000 from work, £24,000 cash. Fyn was
    // given the carry forward guide and told her the plan needed her pension
    // history.
    $jamie = carryForwardSaver(26000, 24000);
    $kept = array_map(fn (SemanticFact $f): string => $f->factId, app(HouseViewApplicability::class)->filter($facts, $jamie));
    expect($kept)->toBe(['hv-salary-sacrifice-ni', 'fca-x']);

    $saver = carryForwardSaver(150000, $this->pensionAllowance + $this->isaAllowance + 1000);
    $kept = array_map(fn (SemanticFact $f): string => $f->factId, app(HouseViewApplicability::class)->filter($facts, $saver));
    expect($kept)->toBe(['hv-pension-aa-carry-forward', 'hv-salary-sacrifice-ni', 'fca-x']);
});
