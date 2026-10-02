<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Audit item 50 (CSJ 2026-10-01: one figure, every surface). Fyn's ranked
 * recommendations came from each agent's own list (CoordinatingAgent::
 * extractRecommendations), not the actions list the dashboard, /m and the
 * action cards read (NextActionsService::buildAll), so Fyn could name an action
 * the user had marked done, or one the list does not hold.
 */

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);

    $this->user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
        'monthly_expenditure' => 2000,
        'date_of_birth' => '1985-01-01',
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create(['user_id' => $this->user->id, 'current_balance' => 4000, 'interest_rate' => 1.5, 'is_emergency_fund' => false]);
});

function savingsTurn(): array
{
    return ['primary' => QuerySchemas::SAVINGS_EMERGENCY, 'related' => [], 'modules' => ['savings']];
}

it('gives Fyn the savings actions of the actions list, in its order, on a savings question', function (): void {
    $actions = collect(app(NextActionsService::class)->buildAll($this->user->id))
        ->where('module', 'savings')->pluck('id')->values()->all();

    $fyn = app(CoordinatingAgent::class)->analyzeRelevantModules($this->user->id, savingsTurn());

    expect($actions)->not->toBeEmpty()
        ->and(collect($fyn['ranked_recommendations'])->pluck('recommendation_id')->all())->toBe($actions);
});

it('never gives Fyn an action the user has marked done', function (): void {
    $agent = app(CoordinatingAgent::class);
    $first = collect($agent->analyzeRelevantModules($this->user->id, savingsTurn())['ranked_recommendations'])->first();
    expect($first)->not->toBeNull();

    RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => $first['recommendation_id'],
        'module' => 'savings',
        'recommendation_text' => $first['title'],
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $after = collect($agent->analyzeRelevantModules($this->user->id, savingsTurn())['ranked_recommendations'])->pluck('recommendation_id');
    $tool = collect($agent->executeTool('get_recommendations', [], $this->user->fresh())['recommendations'])->pluck('recommendation_id');
    $pointer = collect(json_decode(app(RecommendationHandler::class)->fetch(new FetchContext($this->user->fresh(), 'what should i do'))->value, true)['recommendations'])->pluck('recommendation_id');

    expect($after)->not->toContain($first['recommendation_id'])
        ->and($tool)->not->toContain($first['recommendation_id'])
        ->and($pointer)->not->toContain($first['recommendation_id']);
});

it('gives the get_recommendations tool the whole actions list, in its order', function (): void {
    $actions = collect(app(NextActionsService::class)->buildAll($this->user->id))->pluck('id')->values()->all();

    $tool = app(CoordinatingAgent::class)->executeTool('get_recommendations', [], $this->user);

    expect(collect($tool['recommendations'])->pluck('recommendation_id')->all())->toBe($actions);
});

it('gives the fetch_recommendations pointer the same actions list as get_recommendations', function (): void {
    // csjones 2026-10-02, walk account 405: the pointer returned only the tax
    // plan, all of it locked, and Fyn said the list was empty beside ten actions.
    $tool = app(CoordinatingAgent::class)->executeTool('get_recommendations', [], $this->user);
    $pointer = json_decode(app(RecommendationHandler::class)->fetch(new FetchContext($this->user, 'what should i do'))->value, true);

    expect($pointer['recommendations'])->not->toBeEmpty()
        ->and($pointer['recommendations'])->toEqual($tool['recommendations'])
        ->and($pointer['composed_tax_plan'])->toEqual($tool['composed_tax_plan']);
});
