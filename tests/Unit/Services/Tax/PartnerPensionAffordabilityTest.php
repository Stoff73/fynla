<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Coordination\StrategyPlanComposer;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\Tax\PensionAffordability;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * A partner pension top-up was suggested with no check that anyone could pay
 * it: a partner earning £20,000 was told to pay £15,200 in (Save Tax matrix
 * S9, E4). CSJ 2026-09-30: "affordability check always"; the user's own card
 * is capped by the same money; a linked partner gets their own card on their
 * own account; with no spending recorded, the top-up waits and asks for it.
 * Spec: docs/superpowers/specs/2026-09-30-partner-pension-affordability-design.md
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

/** A married earner; `monthly_expenditure` null means no spending recorded. */
function affordabilityCouple(string $mode, ?float $monthlySpending, array $household = [], float $income = 60000): User
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => $income,
        'household_calculation_mode' => $mode,
        'expenditure_entry_mode' => 'simple',
        'monthly_expenditure' => $monthlySpending,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return $user->fresh();
}

function affordabilityRecs(User $user): Collection
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)->keyBy('type');
}

/** Monthly spending that leaves roughly £$yearly a year spare, whatever the tax engine takes. */
function spendingLeaving(float $yearly, float $income = 60000, string $mode = 'single_earner_couple'): float
{
    $probe = affordabilityCouple($mode, 1.0, [], $income);
    $money = (float) app(PensionAffordability::class)->moneyThisYear($probe);
    $probe->forceDelete();

    return round(1.0 + ($money - $yearly) / 12, 2);
}

it('has no money figure when no spending is recorded, and a year of surplus when it is', function (): void {
    $unrecorded = affordabilityCouple('single_earner_couple', null);
    $recorded = affordabilityCouple('single_earner_couple', 2000.0);

    expect(app(PensionAffordability::class)->moneyThisYear($unrecorded))->toBeNull()
        ->and(app(PensionAffordability::class)->moneyThisYear($recorded))->toBeGreaterThan(0.0);
});

it('funds a declared non-earner from their recorded cash', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'employment_status' => 'retired', 'annual_employment_income' => 0, 'annual_self_employment_income' => 0]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 1500, 'ownership_type' => 'individual', 'is_isa' => false]);

    expect(app(PensionAffordability::class)->moneyThisYear($user->fresh()))->toBe(1500.0);
});

it('waits for spending before suggesting a partner top-up, and asks for it', function (): void {
    $user = affordabilityCouple('single_earner_couple', null);

    expect(affordabilityRecs($user))->not->toHaveKey('non_earner_spouse_pension')
        ->and(app(HouseholdFinancialContext::class)->availability($user)['expenditure'])->toBeFalse();
});

it('caps a non-earning partner top-up at the money the household has', function (): void {
    $user = affordabilityCouple('single_earner_couple', spendingLeaving(1000.0));
    $money = (float) app(PensionAffordability::class)->moneyThisYear($user);
    $net = app(TaxStrategyMath::class)->nonEarnerPensionContribution()['net'];
    expect($money)->toBeLessThan($net);

    $rec = affordabilityRecs($user)->get('non_earner_spouse_pension');

    expect($rec)->not->toBeNull()
        ->and($rec['net_contribution'])->toBe(floor($money))
        // One figure on the title and the badge (whole pounds, rounded down).
        ->and($rec['estimated_annual_tax_saved'])->toBe(floor(floor($money) * 720 / 2880))
        ->and($rec['title'])->toContain('£'.number_format((int) $rec['estimated_annual_tax_saved']));
});

it('caps a modest-earning partner at what can be paid, not their whole salary (matrix S9)', function (): void {
    $user = affordabilityCouple('dual_earner', spendingLeaving(4000.0, 60000, 'dual_earner'), [
        'spouse_annual_income' => 20000, 'spouse_annual_earnings' => 20000,
    ]);
    $money = (float) app(PensionAffordability::class)->moneyThisYear($user);

    $rec = affordabilityRecs($user)->get('non_earner_spouse_pension');

    expect($rec)->not->toBeNull()
        ->and($rec['net_cost'])->toBeLessThanOrEqual(round($money, 2) + 0.01)
        ->and($rec['gross_capacity'])->toBeLessThan(20000.0);
});

it('suggests no top-up on the user\'s plan when the partner has their own shared account', function (): void {
    $user = affordabilityCouple('single_earner_couple', 2000.0);
    $partner = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    app(SpouseLinkingService::class)->establishAcceptedLink($user, $partner);

    expect(affordabilityRecs($user->fresh()))->not->toHaveKey('non_earner_spouse_pension');
});

it('caps the user\'s own pension card by the same money', function (): void {
    $user = affordabilityCouple('single', spendingLeaving(2000.0, 60000, 'single'));
    $money = (float) app(PensionAffordability::class)->moneyThisYear($user);
    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');

    $rec = affordabilityRecs($user)->get('pension_tax_relief');

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBeLessThanOrEqual($money / (1 - $basic));
});

it('marks the two cards as competing for the money only when it cannot fund both', function (): void {
    $short = affordabilityCouple('single_earner_couple', spendingLeaving(2500.0));
    $ample = affordabilityCouple('single_earner_couple', 500.0);

    $shortRecs = affordabilityRecs($short);
    $ampleRecs = affordabilityRecs($ample);

    expect($shortRecs->get('pension_tax_relief')['competes_for_money_with'] ?? null)->toBe(['non_earner_spouse_pension'])
        ->and($shortRecs->get('non_earner_spouse_pension')['competes_for_money_with'] ?? null)->toBe(['pension_tax_relief'])
        ->and($ampleRecs->get('pension_tax_relief'))->not->toHaveKey('competes_for_money_with')
        ->and($ampleRecs->get('non_earner_spouse_pension'))->not->toHaveKey('competes_for_money_with');
});

it('counts only the larger saving when two cards compete for the money', function (): void {
    $rec = fn (string $type, float $saving, string $other): StrategyRecommendation => new StrategyRecommendation(
        type: $type, category: StrategyCategory::IncomeBand, priority: 'high', title: $type, description: '',
        estimatedAnnualTaxSaved: $saving, extra: ['competes_for_money_with' => [$other]],
    );

    $plan = app(StrategyPlanComposer::class)->compose([
        $rec('pension_tax_relief', 800.0, 'non_earner_spouse_pension'),
        $rec('non_earner_spouse_pension', 500.0, 'pension_tax_relief'),
    ], [], []);
    $items = collect($plan['items'])->keyBy('type');

    expect($items['pension_tax_relief']['counted_in_total'])->toBeTrue()
        ->and($items['non_earner_spouse_pension']['counted_in_total'])->toBeFalse()
        ->and($items['non_earner_spouse_pension']['conflict_note'])->toContain('Not counted in your total');
});
