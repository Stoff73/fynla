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
    // pa_taper_rescue fires for this earner and its title references pension
    // contribution; the description contains the saving figure in £.
    expect($advice)->toBeString()
        ->and($advice)->toContain('Personal Allowance')  // pa_taper_rescue strategy
        ->and($advice)->toContain('£');                  // engine-derived figure
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
