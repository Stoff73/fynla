<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\IncomeTaxBands;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0485 — the Blind Person's Allowance is not a deduction on the way to adjusted net
 * income, and two services must not answer one statutory question differently.
 *
 * ITA 2007 s58 has four steps and none of them is the Blind Person's Allowance. It is an
 * **s38 allowance given at s23 Step 3** — downstream of net income — so it cannot reduce
 * adjusted net income by construction. `IncomeDefinitionsService` subtracted it anyway,
 * while `UKTaxCalculator` never did, and `ChildBenefitService`'s docblock asserted the two
 * agreed.
 *
 * **Why it survived.** The persona suite has no registered-blind household, so every test
 * built on the personas is blind to this axis (`tests/CLAUDE.md` §4, the persona-gaps
 * corollary). The fixture below is the answer to that: it exists so the NEXT defect on
 * this axis is visible.
 *
 * Two live money errors, both re-measured here.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * A registered-blind user on a stated employment income.
 *
 * The fixture W-0485 acceptance 5 asks for. Kept deliberately plain — one income source,
 * no donations, no pension — so the only thing distinguishing it from any other user is
 * the axis under test.
 */
function registeredBlindUser(int $employmentIncome): User
{
    return User::factory()->create([
        'annual_employment_income' => $employmentIncome,
        'is_registered_blind' => true,
        'marital_status' => 'single',
    ])->fresh();
}

it('does not taper away more Personal Allowance than the statute takes', function () {
    // £110,000 is £10,000 over the £100,000 taper threshold, so the Personal Allowance
    // is reduced by £5,000 to £7,570. Deducting the £3,250 allowance first pulled
    // adjusted net income to £106,750, reduced the taper to £3,375 and showed £9,195 —
    // £1,625 of Personal Allowance the household is not entitled to.
    $result = app(IncomeDefinitionsService::class)->calculate(registeredBlindUser(110_000)->id);

    expect($result['adjusted_net_income'])->toBe(110000.00)
        ->and($result['adjusted_allowances']['personal_allowance'])->toBe(7570.00);
});

it('does not suppress the High Income Child Benefit Charge', function () {
    // £63,000 is over the £60,000 threshold, so the charge is due. Deducting the £3,250
    // allowance pulled adjusted net income to £59,750 — under the threshold — and the
    // charge disappeared entirely. A statutory charge suppressed is the more serious of
    // the two errors: the household is not told about a liability it has.
    $user = registeredBlindUser(63_000);

    // `receives_child_benefit` is what makes a child count here — without it the
    // service returns a zero benefit and no charge, and the test would pass for the
    // wrong reason (tests/CLAUDE.md §4, Decoy).
    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'date_of_birth' => now()->subYears(8)->toDateString(),
        'receives_child_benefit' => true,
    ]);

    $position = app(ChildBenefitService::class)->calculateChildBenefitPosition($user->fresh());

    expect($position['hicbc']['applies'])->toBeTrue()
        ->and($position['hicbc']['charge'])->toBeGreaterThan(0.0);
});

it('tapers the Personal Allowance from the same figure the tax calculator uses', function () {
    // Acceptance 2, and the whole point of the item: one correct service beside one wrong
    // one is not a fix (Rule 20). `UKTaxCalculator::calculateDetailedNetIncome()` tapers
    // from total income less net-pay pension relief, via `IncomeTaxBands` — it has never
    // deducted the Blind Person's Allowance. This asserts the definitions service now
    // lands on the same adjusted net income AND that the shared taper helper turns it
    // into the same Personal Allowance, for the user who used to divide them.
    $user = registeredBlindUser(110_000);

    $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);
    $config = app(TaxConfigService::class)->getIncomeTax();

    // What the calculator would taper from for this user: no pension, no Gift Aid, so
    // its base is the gross figure.
    $calculatorBase = 110000.00;

    expect($definitions['adjusted_net_income'])->toBe($calculatorBase)
        ->and($definitions['adjusted_allowances']['personal_allowance'])
        ->toBe(IncomeTaxBands::taperedPersonalAllowance($config, $calculatorBase));
});

it('leaves a user who is not registered blind exactly where they were', function () {
    // The control. If this moves, the change did more than remove one deduction.
    $sighted = User::factory()->create([
        'annual_employment_income' => 110_000,
        'is_registered_blind' => false,
        'marital_status' => 'single',
    ]);

    $blind = registeredBlindUser(110_000);

    $service = app(IncomeDefinitionsService::class);

    expect($service->calculate($blind->id)['adjusted_net_income'])
        ->toBe($service->calculate($sighted->id)['adjusted_net_income']);
});
