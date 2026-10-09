<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Actions\ActionHowToFacts;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Tax\PensionAffordability;
use App\Services\Tax\Strategies\IncomeBandStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator as UKTaxCalculatorAlias;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * The tax-trap card stopped once the Personal Allowance was back at £100,000;
 * below it every further £1,000 paid in still saves £400 down to the
 * higher-rate threshold (Save Tax matrix E3, S3, S9; L3-4). Carried on only
 * when the money to pay it is known (CSJ 2026-09-30, "We ask for
 * expenditure"). ITA 2007 s35, s58; FA 2004 s192(4).
 * Spec: docs/superpowers/specs/2026-10-01-pension-relief-below-taper-design.md
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function taperEarner(float $income = 110000, ?float $monthlySpending = null): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(45)->toDateString(),
        'expenditure_entry_mode' => 'simple',
        'monthly_expenditure' => $monthlySpending,
    ])->fresh();
}

/** The trap card for $user when $money (net, a year) can go into pensions. */
function trapCard(User $user, ?float $money): ?StrategyRecommendation
{
    return collect(app(IncomeBandStrategy::class)->generate(new TaxStrategyContext($user, null, null, 'single', pensionMoney: $money)))
        ->firstWhere('type', 'pa_taper_rescue');
}

it('carries on at 40% below £100,000 when the money is there', function (): void {
    // £110,000: £10,000 in the taper band, then £59,730 taxed at 40% down to
    // £50,270. £16,000 net buys £20,000 gross (relief at source, FA 2004 s192).
    // Tax at £110,000 is £33,432 and at £90,000 £23,432: £10,000 saved, the
    // £5,000 of allowance won back (£2,000) and 40% on £20,000 (£8,000).
    $card = trapCard(taperEarner(), 16000.0);

    expect($card)->not->toBeNull()
        ->and($card->extra['suggested_contribution'])->toBe(20000.0)
        ->and($card->estimatedAnnualTaxSaved)->toBe(10000.0)
        ->and($card->description)->toContain('Reclaim £5,000 of your Personal Allowance, saving £2,000.')
        ->and($card->description)->toContain('Reduce your income tax at 40% by £8,000.')
        ->and($card->description)->toContain("Together that's £10,000 back this year. Income between £100,000 and £125,140 is taxed at 60%. Below £100,000, each £1,000 you pay in still saves £400, down to £50,270.")
        ->and($card->extra['below_taper'])->toBeTrue();
});

it('stops at the higher-rate threshold, within the Annual Allowance', function (): void {
    // Plenty of money: £10,000 + £49,730 = £59,730, under the £60,000
    // allowance, rounded down to £59,700. Tax at £50,300 is £7,552.
    $card = trapCard(taperEarner(), 1000000.0);

    expect($card->extra['suggested_contribution'])->toBe(59700.0)
        ->and($card->estimatedAnnualTaxSaved)->toBe(25880.0);
});

it('stays at the taper slice when the money is not known', function (): void {
    $card = trapCard(taperEarner(), null);

    expect($card->extra['suggested_contribution'])->toBe(10000.0)
        ->and($card->estimatedAnnualTaxSaved)->toBe(6000.0)
        ->and($card->description)->toContain('(20% of your contribution)')
        ->and($card->description)->not->toContain('Below £100,000')
        ->and($card->extra['below_taper'])->toBeFalse();
});

it('gives short money to the 60% slice first', function (): void {
    // £4,000 net buys £5,000 gross: all of it inside the taper band.
    $card = trapCard(taperEarner(), 4000.0);

    expect($card->extra['suggested_contribution'])->toBe(5000.0)
        ->and($card->estimatedAnnualTaxSaved)->toBe(3000.0)
        ->and($card->description)->toContain('Reclaim £2,500 of your Personal Allowance, saving £1,000 (20% of your contribution).')
        ->and($card->description)->not->toContain('Below £100,000');
});

it('carries on in the full plan once spending is recorded (matrix S3)', function (): void {
    $user = taperEarner(110000, 2500.0);
    $money = (float) app(PensionAffordability::class)->moneyThisYear($user);
    $rec = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)->firstWhere('type', 'pa_taper_rescue');

    // Gross the money buys, rounded down to £100, never more than the slice.
    $gross = min(59700.0, floor($money / 0.8 / 100) * 100);
    $math = app(TaxStrategyMath::class);

    expect($gross)->toBeGreaterThan(10000.0)
        ->and($rec['suggested_contribution'])->toBe($gross)
        ->and($rec['estimated_annual_tax_saved'])->toEqualWithDelta($math->pensionContributionSaving($user, $gross), 0.01);
});

it('shows the how-to step for the part below £100,000 only when the card reaches it', function (): void {
    $this->seed(TaxActionDefinitionSeeder::class);
    (new ActionHowToSeeder)->loadModule('tax');
    $step = 'Below £100,000, each pound you pay in still gets relief at 40%, down to £50,270, where the higher rate starts.';

    $below = app(ActionCardService::class)->for(taperEarner(110000, 2500.0), 'tax_pa_taper_rescue');
    $within = app(ActionCardService::class)->for(taperEarner(110000, null), 'tax_pa_taper_rescue');

    expect(collect($below['how_to'])->flatten()->implode("\n"))->toContain($step)
        ->and(collect($within['how_to'])->flatten()->implode("\n"))->not->toContain($step);
});

it('never claims more allowance back than was lost (tax review F1)', function (): void {
    // £140,000 pay with £10,000 of gross Gift Aid: adjusted net income
    // £130,000, past the end of the band, so the whole £12,570 is gone and
    // that is all that can come back (ITA 2007 s35(2)).
    $user = taperEarner(140000);
    $user->forceFill(['annual_charitable_donations' => 8000, 'is_gift_aid' => true])->save();
    $card = trapCard($user->fresh(), 1000000.0);

    expect($card)->not->toBeNull()
        ->and($card->description)->toContain('Reclaim £12,570 of your Personal Allowance, saving £5,028');
});

it('counts the Blind Person\'s Allowance in where the higher rate starts (tax review F3)', function (): void {
    $bpa = app(TaxConfigService::class)->getBlindPersonsAllowance();
    $user = taperEarner();
    $user->forceFill(['is_registered_blind' => true])->save();
    $user = $user->fresh();
    $card = trapCard($user, 1000000.0);
    $math = app(TaxStrategyMath::class);

    // The higher rate starts £$bpa later, so the card stops there, and every
    // pound of it is still relieved at 40%.
    expect($card->extra['suggested_contribution'])->toBe(floor((59730.0 - $bpa) / 100) * 100)
        ->and($card->estimatedAnnualTaxSaved)->toEqualWithDelta(
            app(UKTaxCalculatorAlias::class)->calculateDetailedNetIncome(employmentIncome: 110000, blindPersonsAllowance: $bpa)['summary']['total_income_tax_before_credits']
            - app(UKTaxCalculatorAlias::class)->calculateDetailedNetIncome(employmentIncome: 110000, pensionContributions: $card->extra['suggested_contribution'], blindPersonsAllowance: $bpa)['summary']['total_income_tax_before_credits'],
            1.0,
        )
        ->and($math->incomeTaxNow($user))->toBeLessThan($math->incomeTaxNow(taperEarner()));
});

it('stops the part below the threshold at the pay taxed at 40%, and leaves out the 40% sentence while interest sits above the threshold (tax review F4, walk R25)', function (): void {
    // Interest sits on top of pay (ITA 2007 s16): sized from pay alone (walk
    // R25), the payment is all relieved at 40% and the engine agrees. The
    // sentence's "down to £50,270" would not hold: below the pay slice the
    // next pounds, as many as the interest the allowance covers, save 20%.
    $user = taperEarner(100000);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'current_balance' => 100000, 'interest_rate' => 4.0, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    $user = $user->fresh();
    $card = trapCard($user, 1000000.0);
    $math = app(TaxStrategyMath::class);

    expect($card)->not->toBeNull()
        ->and($card->extra['suggested_contribution'])->toBeLessThanOrEqual(100000 - $math->bandThresholds()['higher'])
        ->and($card->estimatedAnnualTaxSaved)->toBe((float) round($math->pensionContributionSaving($user, $card->extra['suggested_contribution'])))
        ->and($card->description)->not->toContain('each £1,000 you pay in still saves')
        ->and($card->extra['below_taper'])->toBeFalse();
});

it('puts what pay can carry through payroll and the rest in as a one-off (CSJ 2026-10-01)', function (): void {
    Carbon::setTestNow('2026-10-01');
    $this->seed(TaxActionDefinitionSeeder::class);
    (new ActionHowToSeeder)->loadModule('tax');
    $user = taperEarner(110000, 2500.0);
    DCPension::factory()->create([
        'user_id' => $user->id, 'scheme_type' => 'workplace', 'current_fund_value' => 100000,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 5,
        'monthly_contribution_amount' => 5500 / 12, 'annual_salary' => 110000, 'salary_sacrifice' => false,
    ]);
    $user = $user->fresh();
    $item = collect(app(ComposedTaxPlanService::class)->forUser($user)['items'])->firstWhere('type', 'pa_taper_rescue');
    ['facts' => $facts] = app(ActionHowToFacts::class)->for($user, $item);
    $money = (float) app(PensionAffordability::class)->moneyThisYear($user);
    $gross = (float) $item['suggested_contribution'];
    $saved = floor((float) $item['estimated_annual_tax_saved']);
    $perMonth = floor(min(110000 / 12, $money / 12 / (($gross - $saved) / $gross)));

    expect($facts['payroll_short'])->toBeTrue()
        ->and($facts['contribution_per_month_left'])->toBe($perMonth)
        ->and($facts['payroll_total'] + $facts['payroll_rest'])->toBe($gross)
        ->and($facts['payroll_rest_net'] + $facts['payroll_rest_relief'])->toBe($facts['payroll_rest'])
        // Through pay, added at source and claimed: the card's saving, no more.
        ->and(floor(app(TaxStrategyMath::class)->pensionContributionSaving($user, $facts['payroll_total'])) + $facts['payroll_rest_relief'] + $facts['payroll_rest_claim'])->toBe($saved);

    $steps = collect(app(ActionCardService::class)->for($user, 'tax_pa_taper_rescue')['how_to'])->implode("\n");
    expect($steps)->toContain('a month is as much as your pay can carry after your spending')
        ->and($steps)->not->toContain('claim the other £'.number_format((int) $facts['extra_relief']));
    Carbon::setTestNow();
});
