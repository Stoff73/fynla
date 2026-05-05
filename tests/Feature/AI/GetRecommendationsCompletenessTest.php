<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.6.2 — handleRecommendations returns the full ranked_recommendations
 * array from orchestrateAnalysis with no summarisation. Every metadata
 * field the engine emits (id, priority_score, timeline, category, impact,
 * recommendation_text, rationale, personalised_context, etc.) round-trips
 * to the model so the LLM can answer the user with full context.
 *
 * The test stubs orchestrateAnalysis with a fixed payload, calls the
 * handler, and asserts every field on every recommendation appears in
 * the handler output unchanged.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

/**
 * Build a CoordinatingAgent subclass that returns a fixed
 * orchestrateAnalysis payload, bypassing the engine and the audit-chain
 * pipeline so the test focuses on the handler's read-completeness
 * contract.
 */
function buildAgentWithFixedAnalysis(array $analysis): CoordinatingAgent
{
    $stub = new class($analysis) extends CoordinatingAgent
    {
        public function __construct(private array $stubAnalysis) {}

        public function orchestrateAnalysis(int $userId, ?array $moduleAgents = null): array
        {
            return $this->stubAnalysis;
        }
    };

    return $stub;
}

it('returns the full ranked_recommendations array verbatim with all metadata fields', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $rankedRecommendations = [
        [
            'id' => 'rec-001',
            'priority_score' => 95,
            'timeline' => 'immediate',
            'category' => 'protection',
            'impact' => 'high',
            'recommendation_text' => 'Increase your life cover to £500,000.',
            'rationale' => 'Your current cover leaves a £200k gap relative to your dependants and outstanding mortgage.',
            'personalised_context' => [
                'gap_amount' => 200000,
                'dependants' => 2,
                'monthly_premium_estimate' => 35.0,
            ],
            'action_definition_id' => 12,
            'expected_value_uplift' => 200000.0,
            'confidence' => 'high',
        ],
        [
            'id' => 'rec-002',
            'priority_score' => 80,
            'timeline' => 'short_term',
            'category' => 'savings',
            'impact' => 'medium',
            'recommendation_text' => 'Build an emergency fund covering 6 months of expenditure.',
            'rationale' => 'Current liquid reserves cover 2 months. The shortfall is the biggest single resilience risk in your plan.',
            'personalised_context' => [
                'months_covered' => 2,
                'target_months' => 6,
                'monthly_expenditure' => 3200.0,
            ],
            'action_definition_id' => 5,
            'expected_value_uplift' => 19200.0,
            'confidence' => 'medium',
        ],
    ];

    $agent = buildAgentWithFixedAnalysis([
        'ranked_recommendations' => $rankedRecommendations,
        'available_surplus' => 1500.0,
    ]);

    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleRecommendations');
    $method->setAccessible(true);

    $result = $method->invoke($agent, $user);

    expect($result)->toBeArray();
    expect($result['total'] ?? null)->toBe(2);
    expect($result['surplus'] ?? null)->toBe(1500.0);

    expect($result['recommendations'] ?? [])->toHaveCount(2);

    // Every field on every recommendation round-trips byte-for-byte —
    // no summariseToolResult / summariseToolAnalysis stripping.
    foreach ($rankedRecommendations as $idx => $expected) {
        $actual = $result['recommendations'][$idx];
        foreach ($expected as $field => $value) {
            expect($actual)->toHaveKey($field);
            expect($actual[$field])->toBe($value, "Field `{$field}` did not round-trip on rec #{$idx}");
        }
    }
});

it('passes through nested personalised_context arrays without flattening or truncating', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $deeplyNested = [
        'breakdowns' => [
            ['label' => 'Pension contribution headroom', 'value' => 24000.0],
            ['label' => 'ISA allowance remaining', 'value' => 12500.0],
            ['label' => 'Capital gains allowance', 'value' => 3000.0],
        ],
        'modelled_outcomes' => [
            'low' => 12000,
            'central' => 18000,
            'high' => 25000,
        ],
        'tags' => ['tax_efficient', 'pre_retirement', 'higher_rate'],
    ];

    $agent = buildAgentWithFixedAnalysis([
        'ranked_recommendations' => [[
            'id' => 'rec-nested',
            'priority_score' => 70,
            'timeline' => 'medium_term',
            'category' => 'tax',
            'impact' => 'high',
            'recommendation_text' => 'Top up your SIPP before the tax year end.',
            'rationale' => 'You have unused annual allowance and are inside the higher-rate band.',
            'personalised_context' => $deeplyNested,
        ]],
        'available_surplus' => 0,
    ]);

    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleRecommendations');
    $method->setAccessible(true);

    $result = $method->invoke($agent, $user);

    expect($result['recommendations'][0]['personalised_context'])->toBe($deeplyNested);
});

it('returns an empty list when orchestrateAnalysis returns no recommendations', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $agent = buildAgentWithFixedAnalysis([
        'ranked_recommendations' => [],
        'available_surplus' => 0,
    ]);

    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleRecommendations');
    $method->setAccessible(true);

    $result = $method->invoke($agent, $user);

    expect($result['total'])->toBe(0);
    expect($result['recommendations'])->toBe([]);
});
