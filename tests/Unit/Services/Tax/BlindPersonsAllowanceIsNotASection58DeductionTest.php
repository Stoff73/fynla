<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
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
    // one is not a fix (Rule 20). This asserts the agreement by CONSTRUCTING the
    // calculator and reading the Personal Allowance it publishes — the earlier version
    // asserted against a hand-written `110000.00` literal, which agrees with a service
    // that has stopped running (the tax-compliance gate, finding F2).
    $user = registeredBlindUser(110_000);

    $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);
    $calculator = app(UKTaxCalculator::class)->calculateDetailedNetIncome(
        employmentIncome: 110_000,
        blindPersonsAllowance: app(TaxConfigService::class)->blindPersonsAllowanceFor($user),
    );

    // The calculator tapers from total income less net-pay relief; this user has no
    // pension and no Gift Aid, so its taper base is the gross figure — and the
    // definitions service must land on that same adjusted net income.
    expect($definitions['adjusted_net_income'])->toBe(110000.00)
        // The two services on ONE Personal Allowance, neither of them a literal.
        ->and($definitions['adjusted_allowances']['personal_allowance'])
        ->toBe($calculator['summary']['personal_allowance'])
        // And the allowance the definitions panel shows is the one the calculator gave.
        ->and($calculator['summary']['blind_persons_allowance'])
        ->toBe($definitions['deductions']['blind_persons_allowance'])
        ->toBeGreaterThan(0.0);
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

/**
 * W-0511 — the allowance is not only excluded from the wrong place, it is GIVEN in the
 * right one.
 *
 * `is_registered_blind` was read in exactly one line of the application: the s58
 * deduction W-0485 removed. Nothing computed tax with it — the app asked the question,
 * stored the answer, let an administrator maintain the rate and printed the amount, then
 * taxed the household as though they had no allowance at all. ITA 2007 s38 gives it;
 * s23 Step 3 deducts it.
 *
 * Every case below is a BEFORE/AFTER on income tax, and the figure moves DOWN in each.
 */
it('gives the allowance to a basic rate taxpayer', function () {
    $blind = registeredBlindUser(30_000);
    $calculator = app(UKTaxCalculator::class);
    $allowance = app(TaxConfigService::class)->getBlindPersonsAllowance();

    $with = $calculator->calculateNetIncome(
        employmentIncome: 30_000,
        blindPersonsAllowance: app(TaxConfigService::class)->blindPersonsAllowanceFor($blind),
    );
    $without = $calculator->calculateNetIncome(employmentIncome: 30_000);

    // £3,250 that was taxed at 20% is not taxed at all: £650 of relief.
    expect($with['income_tax'])->toBeLessThan($without['income_tax'])
        ->and($without['income_tax'] - $with['income_tax'])
        ->toEqualWithDelta($allowance * 0.20, 0.01);
});

it('gives the allowance to a higher rate taxpayer', function () {
    $blind = registeredBlindUser(70_000);
    $calculator = app(UKTaxCalculator::class);
    $allowance = app(TaxConfigService::class)->getBlindPersonsAllowance();

    $with = $calculator->calculateNetIncome(
        employmentIncome: 70_000,
        blindPersonsAllowance: app(TaxConfigService::class)->blindPersonsAllowanceFor($blind),
    );
    $without = $calculator->calculateNetIncome(employmentIncome: 70_000);

    // The allowance and BOTH limits move up together, so the relief is the whole
    // allowance at 40% — not 20%, which is what would happen if only the allowance
    // moved and the basic-rate band were left to widen into the gap.
    expect($without['income_tax'] - $with['income_tax'])
        ->toEqualWithDelta($allowance * 0.40, 0.01);
});

it('gives the allowance to an additional rate taxpayer', function () {
    $blind = registeredBlindUser(200_000);
    $calculator = app(UKTaxCalculator::class);
    $allowance = app(TaxConfigService::class)->getBlindPersonsAllowance();

    $with = $calculator->calculateNetIncome(
        employmentIncome: 200_000,
        blindPersonsAllowance: app(TaxConfigService::class)->blindPersonsAllowanceFor($blind),
    );
    $without = $calculator->calculateNetIncome(employmentIncome: 200_000);

    // The additional-rate threshold moves with the allowance (ITA 2007 s10 states it
    // against taxable income), so the relief is the whole allowance at 45%. Leaving
    // that threshold still would relieve at 45% but claw part of it back at the band
    // edge, and the figure below would not land.
    expect($without['income_tax'] - $with['income_tax'])
        ->toEqualWithDelta($allowance * 0.45, 0.01);
});

it('does not let the allowance rescue any Personal Allowance from the taper', function () {
    // The W-0485 error in the other direction. The allowance is given at s23 Step 3, so
    // it must not reach the taper base — if it did, this user's Personal Allowance would
    // come back above £7,570 and the relief would be counted twice.
    $blind = registeredBlindUser(110_000);

    $detailed = app(UKTaxCalculator::class)->calculateDetailedNetIncome(
        employmentIncome: 110_000,
        blindPersonsAllowance: app(TaxConfigService::class)->blindPersonsAllowanceFor($blind),
    );

    expect($detailed['summary']['personal_allowance'])->toBe(7570.00);
});

it('gives nothing to a user who is not registered blind', function () {
    $sighted = User::factory()->create([
        'annual_employment_income' => 30_000,
        'is_registered_blind' => false,
        'marital_status' => 'single',
    ]);

    expect(app(TaxConfigService::class)->blindPersonsAllowanceFor($sighted))->toBe(0.0);
});

it('reads the rate from configuration rather than a fallback literal', function () {
    // Rule 2, and W-0511 acceptance 2: the `?? 2870` fallback was a stale year's figure,
    // so an unconfigured year granted the wrong allowance silently. The active year
    // configures one, and it is what the service returns.
    $configured = app(TaxConfigService::class)->get('income_tax.blind_persons_allowance');

    expect(app(TaxConfigService::class)->getBlindPersonsAllowance())
        ->toBe((float) $configured)
        ->toBeGreaterThan(0.0);
});
