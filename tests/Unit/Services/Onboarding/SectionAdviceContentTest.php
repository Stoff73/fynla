<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Per-section advice is built from the composed tax plan catalogue and
 * Fyn-phrased (numbers from the engine, Rule #2). These tests prove the engine
 * integration produces real advice for the relevant section.
 *
 * Updated in Task 17 (A3): advice is now catalogue-driven via
 * ComposedTaxPlanService rather than the raw TaxStrategyCalculator; the
 * old hardcoded section intros ("Here's where you stand on income tax.") are
 * replaced by strategy titles and descriptions from the plan items.
 */
beforeEach(function () {
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function adviceFor(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'buildSectionAdvice');
    $m->setAccessible(true);

    return $m->invoke($director, $user, $section);
}

it('produces income-tax advice for a 60% tax-trap earner', function () {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'date_of_birth' => '1985-01-12',
        'annual_employment_income' => 120000, // inside the £100k–£125,140 taper
    ]);

    $advice = adviceFor($user, 'income');

    // Catalogue-driven output: strategy title/description from the plan — the
    // old hardcoded intro "Here's where you stand on income tax." is gone.
    // pa_taper_rescue fires for this earner and its description breaks the 60%
    // saving into the two mechanisms with this user's own figures (CSJ):
    // £120k income → £20k contribution → reclaim £10k PA (saving £4k, 20%) +
    // £8k higher-rate relief (40%) = £12k total.
    expect($advice)->toBeString()
        ->and($advice)->toContain('Personal Allowance')
        ->and($advice)->toContain('For your income of £120,000')
        ->and($advice)->toContain('a £20,000 pension contribution')
        ->and($advice)->toContain('Reclaim £10,000 of your Personal Allowance, saving £4,000 (20% of your contribution)')
        ->and($advice)->toContain('Reduce your income tax at 40% by £8,000')
        ->and($advice)->toContain("Together that's £12,000 back this year")
        ->and($advice)->toContain('income between £100,000 and £125,140 is taxed at 60%');
});

it('returns null when a section has no relevant recommendations', function () {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 30000, // basic rate — no income-band rescue
    ]);

    expect(adviceFor($user, 'income'))->toBeNull();
});
