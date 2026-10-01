<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Tax\PensionAffordability;
use App\Services\Tax\Strategies\IncomeBandStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
