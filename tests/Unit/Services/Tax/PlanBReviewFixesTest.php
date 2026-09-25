<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\DCPension;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\StrategyPlanComposer;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Retirement\SalarySacrificeAnalyzer;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function reviewMaRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'marriage_allowance_transfer');
}

it('offers Marriage Allowance when personal pension payments bring the recipient into the basic-rate band', function () {
    // £52,000 pay with £2,400 a year paid net into a personal pension: the
    // £3,000 gross contribution extends the basic-rate band past £52,000.
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 52000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal',
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
        'salary_sacrifice' => false,
    ]);

    expect(reviewMaRec($user))->not->toBeNull();
});

it('nets off the tax the transferring spouse pays when their income sits just under the Personal Allowance', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $math = app(TaxStrategyMath::class);
    $ma = $math->marriageAllowanceAmount();
    $spouseIncome = $pa - $ma + 690; // £690 of the transferred slice was in use

    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 35000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => $spouseIncome]);

    expect(reviewMaRec($user)['estimated_annual_tax_saved'])
        ->toBe(round(($ma - 690) * $math->bandRateForBand('basic'), 2));
});

it('treats a form-captured workplace pension as workplace in the Retirement salary sacrifice analysis too', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational',
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'salary_sacrifice' => false,
    ]);

    expect(app(SalarySacrificeAnalyzer::class)->analyze($user->fresh())['is_available'])->toBeTrue();
});

it('marks which composed items count toward the combined total', function () {
    $recs = [
        new StrategyRecommendation('a', StrategyCategory::Household, StrategyPriority::High, 'A', 'd', 300.0),
        new StrategyRecommendation('b', StrategyCategory::Household, StrategyPriority::High, 'B', 'd', 200.0),
    ];
    $metadata = [
        'a' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['b']]],
        'b' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['a']]],
    ];

    $items = collect(app(StrategyPlanComposer::class)->compose($recs, $metadata, [])['items'])->keyBy('type');

    expect($items['a']['counted_in_total'])->toBeTrue()
        ->and($items['b']['counted_in_total'])->toBeFalse();
});

it('voices only the spouse savings that count toward the plan total', function () {
    $director = app(OnboardingChatDirector::class);
    $reflection = new ReflectionMethod($director, 'buildSpouseAdvice');
    $reflection->setAccessible(true);

    $advice = $reflection->invoke($director, ['items' => [
        ['type' => 'savings_to_spouse', 'estimated_annual_tax_saved' => 400.0, 'counted_in_total' => false],
        ['type' => 'marriage_allowance_transfer', 'estimated_annual_tax_saved' => 252.0, 'counted_in_total' => true],
    ]], ['savings_to_spouse', 'marriage_allowance_transfer']);

    expect($advice)->toContain('£252')->and($advice)->not->toContain('£652');
});

it('points Fyn at the pension relief items on every tax path', function () {
    $sections = (new ReflectionClassConstant(OnboardingChatDirector::class, 'SECTION_STRATEGY_TYPES'))->getValue();

    foreach (['pension_relief_higher_rate', 'pension_relief_basic_rate'] as $type) {
        expect($sections['pensions'])->toContain($type)
            ->and(QuerySchemas::RELEVANT_TRIGGERS[QuerySchemas::TAX_OPTIMISATION])->toContain('strategy_'.$type);
    }
});
