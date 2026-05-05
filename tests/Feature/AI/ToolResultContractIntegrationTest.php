<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Agents\GoalsAgent;
use App\Agents\SavingsAgent;
use App\Models\Goal;
use App\Models\Household;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\AI\ToolResultContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S1.6.b — end-to-end integration of the per-agent output contract.
 *
 * Exercises the round-trip: real agent analyze() → ToolResultContract::validate
 * → CoordinatingAgent::summariseToolAnalysis. Confirms the LLM receives
 * the full agent payload (no key-stripping), that `missing_for_quality_advice`
 * is populated against real data, and that the contract violation path
 * yields a structured error tool result rather than a malformed shape.
 */
beforeEach(function () {
    TaxConfiguration::factory()->create(['is_active' => true]);
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(40),
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'monthly_expenditure' => 2500,
    ]);
    $this->contract = app(ToolResultContract::class);
});

describe('GoalsAgent → contract round-trip', function () {
    it('passes contract validation when no goals exist (has_goals=false branch)', function () {
        $agent = app(GoalsAgent::class);
        $raw = $agent->analyze($this->user->id);

        expect($raw['has_goals'])->toBeFalse();

        $validated = $this->contract->validate('goals', $raw);

        expect($validated)->toHaveKey('module', 'goals')
            ->and($validated)->toHaveKey('has_goals', false)
            ->and($validated)->toHaveKey('summary');
    });

    it('passes contract validation and emits missing_for_quality_advice when goals lack contribution data', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'House deposit',
            'target_amount' => 50000,
            'current_amount' => 5000,
            'monthly_contribution' => 0,
            'target_date' => now()->addYears(5),
            'status' => 'active',
            'priority' => 'high',
            'assigned_module' => 'savings',
        ]);

        $agent = app(GoalsAgent::class);
        $raw = $agent->analyze($this->user->id);

        $validated = $this->contract->validate('goals', $raw);

        expect($validated)->toHaveKey('missing_for_quality_advice')
            ->and($validated['missing_for_quality_advice'])->toBeArray()
            ->and($validated['missing_for_quality_advice'])->not->toBeEmpty();

        $fields = collect($validated['missing_for_quality_advice'])->pluck('field')->all();
        expect($fields)->toContain('goals.monthly_contribution');
    });
});

describe('SavingsAgent → contract round-trip', function () {
    it('emits a blocking gap when monthly_expenditure is unresolved', function () {
        // Create user with no expenditure so the runway calculation has no input
        $userWithoutExpenditure = User::factory()->create([
            'household_id' => $this->household->id,
            'date_of_birth' => now()->subYears(35),
            'marital_status' => 'single',
            'monthly_expenditure' => null,
            'annual_employment_income' => 40000,
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $userWithoutExpenditure->id,
            'current_balance' => 5000,
            'institution' => 'Test Bank',
        ]);

        $agent = app(SavingsAgent::class);
        $raw = $agent->analyze($userWithoutExpenditure->id);

        // If readiness gates blocked it, validate the gated shape; otherwise validate the happy path.
        if (($raw['can_proceed'] ?? null) === false) {
            $validated = $this->contract->validate('savings', $raw);
            expect($validated)->toHaveKey('can_proceed', false);

            return;
        }

        $validated = $this->contract->validate('savings', $raw);

        expect($validated)->toHaveKey('missing_for_quality_advice');

        $blockers = collect($validated['missing_for_quality_advice'])
            ->where('severity', 'blocking')
            ->pluck('field')
            ->all();
        expect($blockers)->toContain('monthly_expenditure');
    });
});

describe('CoordinatingAgent::summariseToolAnalysis verbatim contract', function () {
    it('returns the agent payload verbatim — no 15-key whitelist truncation', function () {
        $coordinator = app(CoordinatingAgent::class);
        $reflection = new ReflectionClass($coordinator);
        $method = $reflection->getMethod('summariseToolAnalysis');
        $method->setAccessible(true);

        $rawAnalysis = [
            'success' => true,
            'message' => 'Protection analysis completed successfully.',
            'data' => [
                'profile' => ['monthly_expenditure' => 2000.0],
                'needs' => ['life_cover_need' => 250000],
                'coverage' => ['life_cover_total' => 100000],
                'gaps' => ['life_cover_gap' => 150000],
                'adequacy_score' => ['score' => 60],
                'recommendations' => [],
                'scenarios' => [],
                'debt_breakdown' => ['mortgage' => 200000, 'other' => 0, 'total' => 200000],
                'policies' => ['life_insurance' => []],
                'profile_completeness' => ['complete' => false],
                'missing_for_quality_advice' => [
                    ['field' => 'spouse_link', 'why' => '...', 'severity' => 'soft'],
                ],
            ],
        ];

        $result = $method->invoke($coordinator, 'protection', $rawAnalysis);

        expect($result)->toHaveKey('module', 'protection')
            ->and($result['gaps']['life_cover_gap'])->toBe(150000)
            ->and($result['needs']['life_cover_need'])->toBe(250000)
            ->and($result['debt_breakdown']['mortgage'])->toBe(200000)
            ->and($result['profile']['monthly_expenditure'])->toBe(2000.0)
            ->and($result['missing_for_quality_advice'])->toBeArray();
    });

    it('returns a structured error tool result when the agent payload drifts from the contract', function () {
        $coordinator = app(CoordinatingAgent::class);
        $reflection = new ReflectionClass($coordinator);
        $method = $reflection->getMethod('summariseToolAnalysis');
        $method->setAccessible(true);

        // Drift: missing every required key
        $malformed = ['success' => true, 'data' => ['profile' => []]];

        $result = $method->invoke($coordinator, 'protection', $malformed);

        expect($result)->toHaveKey('error', 'module_analysis_contract_violation')
            ->and($result)->toHaveKey('module', 'protection')
            ->and($result)->toHaveKey('detail');
    });

    it('returns the goals empty-state result verbatim through summariseToolAnalysis', function () {
        $coordinator = app(CoordinatingAgent::class);
        $reflection = new ReflectionClass($coordinator);
        $method = $reflection->getMethod('summariseToolAnalysis');
        $method->setAccessible(true);

        $emptyGoals = [
            'has_goals' => false,
            'message' => 'No goals found. Set your first financial goal to start tracking progress.',
            'summary' => [
                'total_goals' => 0,
                'on_track_percentage' => 0,
            ],
        ];

        $result = $method->invoke($coordinator, 'goals', $emptyGoals);

        expect($result)->toHaveKey('module', 'goals')
            ->and($result)->toHaveKey('has_goals', false)
            ->and($result['summary']['total_goals'])->toBe(0)
            ->and($result['message'])->toContain('No goals found');
    });
});
