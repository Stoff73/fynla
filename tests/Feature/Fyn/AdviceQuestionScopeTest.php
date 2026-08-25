<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\DCPension;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\KycGateChecker;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function retirementReadinessClassification(): array
{
    return [
        'primary' => QuerySchemas::RETIREMENT_READINESS,
        'related' => [QuerySchemas::GOALS_PROGRESS],
        'modules' => ['retirement', 'goals'],
    ];
}

function retirementReadyWithoutUniversalFields(): User
{
    $user = User::factory()->create([
        'date_of_birth' => '1985-01-01',
        'annual_employment_income' => 75000,
        'marital_status' => null,
        'employment_status' => null,
        'monthly_expenditure' => null,
        'annual_expenditure' => null,
    ]);
    DCPension::factory()->for($user)->create(['current_fund_value' => 125000]);

    return $user;
}

it('does not reapply full module readiness after the primary KYC matrix passes', function (): void {
    $user = retirementReadyWithoutUniversalFields();
    $classification = retirementReadinessClassification();
    $kycResult = app(KycGateChecker::class)->check($user, $classification);

    expect($kycResult['passed'])->toBeTrue();

    $result = app(CoordinatingAgent::class)->executeTool(
        'get_module_analysis',
        ['module' => 'retirement'],
        $user,
        classification: $classification,
        kycResult: $kycResult,
    );

    expect($result)
        ->not->toHaveKey('blocked')
        ->and($result['can_proceed'])->toBeTrue()
        ->and($result['analysis_scope'])->toBe('question_required_data')
        ->and($result['total_pension_value'])->toBe(125000.0);
});

it('never enables the question-scoped fallback when primary KYC is blocked', function (): void {
    $user = User::factory()->create([
        'date_of_birth' => null,
        'annual_employment_income' => 0,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);
    $classification = retirementReadinessClassification();
    $kycResult = app(KycGateChecker::class)->check($user, $classification);

    expect($kycResult['passed'])->toBeFalse();

    $agent = app(CoordinatingAgent::class);
    $result = $agent->executeTool(
        'get_module_analysis',
        ['module' => 'retirement'],
        $user,
        classification: $classification,
        kycResult: $kycResult,
    );
    $preAnalysis = $agent->analyzeRelevantModules($user->id, $classification, $kycResult);

    expect($result)
        ->toHaveKey('blocked', true)
        ->not->toHaveKey('analysis_scope')
        ->and(data_get($preAnalysis, 'module_analysis.retirement.analysis_scope'))
        ->not->toBe('question_required_data');
});

it('omits unrelated and full-module blocks from assembled advice context', function (): void {
    $user = retirementReadyWithoutUniversalFields();
    $classification = retirementReadinessClassification();
    $kycResult = app(KycGateChecker::class)->check($user, $classification);
    $analysis = fn (int $userId): array => ['module_analysis' => []];

    $legacy = app(AdvicePromptBuilder::class)->build(
        user: $user,
        classification: $classification,
        kycResult: $kycResult,
        orchestrateAnalysis: $analysis,
    );

    $context = FynTurnContext::make(
        user: $user,
        message: 'Am I on track for retirement?',
        currentRoute: null,
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: $classification,
        kycResult: $kycResult,
    );
    $unified = app(FynContextAssembler::class)->build($context, $analysis);

    foreach ([$legacy, $unified] as $assembled) {
        expect($assembled)
            ->toContain('Current question: READY')
            ->not->toContain('Goals: BLOCKED')
            ->not->toContain('marital_status:')
            ->not->toContain('employment_status:')
            ->not->toContain('monthly_expenditure:');
    }
});
