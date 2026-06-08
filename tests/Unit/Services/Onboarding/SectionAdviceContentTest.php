<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Per-section advice is built from the canonical TaxStrategyCalculator and
 * Fyn-phrased (numbers from the engine, Rule #2). These tests prove the engine
 * integration produces real advice for the relevant section.
 */
beforeEach(function () {
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
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

    expect($advice)->toBeString()
        ->and($advice)->toContain('income tax')   // section intro
        ->and($advice)->toContain('£');            // an engine-derived figure
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
