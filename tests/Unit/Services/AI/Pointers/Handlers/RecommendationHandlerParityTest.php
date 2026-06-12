<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

/** §6b parity: skill payload === tool payload === service output, for the same user. */
function composedPlanThreeWays(User $user): array
{
    $res = app(RecommendationHandler::class)->fetch(new FetchContext($user, 'what should i do'));
    $skillPlan = json_decode($res->value, true)['composed_tax_plan'];

    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'handleRecommendations');
    $m->setAccessible(true);
    $toolPlan = $m->invoke($agent, $user)['composed_tax_plan'];

    $direct = app(ComposedTaxPlanService::class)->forUser($user);

    return [$skillPlan, $toolPlan, $direct];
}

it('the fetch-skill payload equals get_recommendations composed_tax_plan', function (): void {
    $user = User::factory()->create();

    [$skillPlan, $toolPlan, $direct] = composedPlanThreeWays($user);

    expect($skillPlan)->toEqual($toolPlan)
        ->and($skillPlan)->toEqual($direct);
});

it('parity holds for a user whose plan has fired items and locked strategies', function (): void {
    $this->seed(TaxActionDefinitionSeeder::class);

    // Mirror of ComposedTaxPlanServiceTest's non-empty recipe: £110k earner with
    // non-ISA interest above the Personal Savings Allowance and ISA headroom →
    // isa_topup_vs_psa fires; no pension records → salary_sacrifice_ni locks.
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'is_isa' => false,
        'current_balance' => 81000.00,
        'interest_rate' => 0.0325,
    ]);

    SavingsAccount::factory()->isa()->create([
        'user_id' => $user->id,
        'current_balance' => 5000.00,
        'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 100.00,
    ]);

    [$skillPlan, $toolPlan, $direct] = composedPlanThreeWays($user->fresh());

    // Parity on an empty plan proves little — pin the non-emptiness first.
    expect($skillPlan['items'])->not->toBe([])
        ->and($skillPlan['locked'])->not->toBe([])
        ->and(array_column($skillPlan['items'], 'type'))->toContain('isa_topup_vs_psa')
        ->and(array_column($skillPlan['locked'], 'strategy_type'))->toContain('salary_sacrifice_ni')
        ->and($skillPlan)->toEqual($toolPlan)
        ->and($skillPlan)->toEqual($direct);
});
