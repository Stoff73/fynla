<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
});

/**
 * fyn-wiring Batch A, Task 8. ModuleScopedFinancialContextTest proves the prompt
 * builder renders module blocks and the ranked list — against a hand-written
 * analysis in the holistic shape. The real module-scoped path
 * (CoordinatingAgent::analyzeRelevantModules) returned the raw agent arrays and
 * no ranked list, so a live "How is my emergency fund looking?" turn carried a
 * <financial_context> with no savings figures and no recommendations, while the
 * <relevant_triggers> block told Fyn to look for them there. This test drives
 * the builder with the real path.
 */
function lowRunwayHousehold(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
        'monthly_expenditure' => 2000,
        'date_of_birth' => '1985-01-01',
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 4000, 'interest_rate' => 1.5, 'is_emergency_fund' => false]);

    return $user;
}

function savingsEmergencyClassification(): array
{
    return ['primary' => QuerySchemas::SAVINGS_EMERGENCY, 'related' => [], 'modules' => ['savings']];
}

it('returns the mapped savings block and a ranked list on the module-scoped path', function (): void {
    $user = lowRunwayHousehold();

    $result = app(CoordinatingAgent::class)->analyzeRelevantModules($user->id, savingsEmergencyClassification());

    expect((float) data_get($result, 'module_analysis.savings.emergency_fund_months'))->toEqualWithDelta(2.0, 0.01)
        ->and((float) data_get($result, 'module_analysis.savings.total_savings'))->toEqualWithDelta(4000.0, 0.01)
        ->and(collect($result['ranked_recommendations'])->pluck('definition_key'))->toContain('emergency_fund_low')
        ->and(collect($result['ranked_recommendations'])->pluck('module')->unique()->all())->toBe(['savings']);
});

it('puts the fired savings trigger into <financial_context> for a module-scoped turn', function (): void {
    $user = lowRunwayHousehold();
    $classification = savingsEmergencyClassification();
    $agent = app(CoordinatingAgent::class);

    $context = app(AdvicePromptBuilder::class)->buildFinancialContext(
        $user,
        fn (int $userId): array => $agent->analyzeRelevantModules($userId, $classification),
        $classification,
    );

    expect($context)->toContain('Top ranked recommendations')
        ->toContain('Triggered by: emergency_fund_low')
        ->toContain('Emergency fund: 2 months from cash savings');
});
