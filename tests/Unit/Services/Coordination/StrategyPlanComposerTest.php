<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Coordination\StrategyPlanComposer;

it('orders by sequencing, sums savings, and marks conflicts', function () {
    $recs = [
        new StrategyRecommendation('savings_to_spouse', StrategyCategory::Household, StrategyPriority::High,
            'Gift savings to spouse', 'desc', 530.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap cash in ISA', 'desc', 259.0),
        new StrategyRecommendation('joint_savings_psa_split', StrategyCategory::Household, StrategyPriority::High,
            'Split savings', 'desc', 330.0),
    ];

    $metadata = [
        'isa_topup_vs_psa' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => ['savings_to_spouse'], 'conflicts_with' => []]],
        'savings_to_spouse' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa_split']]],
        'joint_savings_psa_split' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['savings_to_spouse']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $types = array_column($plan['items'], 'type');
    // isa_topup must precede savings_to_spouse (do_before).
    expect(array_search('isa_topup_vs_psa', $types))
        ->toBeLessThan(array_search('savings_to_spouse', $types));
    // Conflict pair: the lower-saving one carries the note naming its alternative.
    $joint = collect($plan['items'])->firstWhere('type', 'joint_savings_psa_split');
    expect($joint['conflict_note'])->toContain('savings_to_spouse');
    // The higher-saving member of the pair carries no note.
    $gift = collect($plan['items'])->firstWhere('type', 'savings_to_spouse');
    expect($gift['conflict_note'])->toBeNull();
    // Combined total excludes the lower-saving alternative (joint_savings_psa_split = 330).
    // Realisable total: isa_topup_vs_psa (259) + savings_to_spouse (530) = 789.
    expect($plan['combined_annual_saving'])->toBe(789.0);
    expect($plan['items'][0]['claim_tier'])->toBe('mechanical');
    // sequence_position is 1-based and contiguous.
    expect(array_column($plan['items'], 'sequence_position'))->toBe([1, 2, 3]);
});

it('defaults claim_tier to judgement for types without metadata', function () {
    $recs = [new StrategyRecommendation('mystery_type', StrategyCategory::Allowance, StrategyPriority::Low, 't', 'd', 10.0)];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    expect($plan['items'][0]['claim_tier'])->toBe('judgement');
});

it('lists locked strategies with their missing data points', function () {
    $plan = app(StrategyPlanComposer::class)->compose([], [], lockedStrategies: [
        ['strategy_type' => 'salary_sacrifice_ni', 'missing' => ['workplace_pension']],
    ]);

    expect($plan['locked'][0]['strategy_type'])->toBe('salary_sacrifice_ni')
        ->and($plan['locked'][0]['missing'])->toBe(['workplace_pension'])
        ->and($plan['items'])->toBe([])
        ->and($plan['combined_annual_saving'])->toBe(0.0);
});

it('handles null savings and keeps ordering stable', function () {
    // Recs with null estimatedAnnualTaxSaved sort after valued ones;
    // combined total ignores nulls; sequence_positions remain 1-based and contiguous.
    $recs = [
        new StrategyRecommendation('no_saving_a', StrategyCategory::Allowance, StrategyPriority::Low, 'A', 'desc', null),
        new StrategyRecommendation('has_saving', StrategyCategory::Allowance, StrategyPriority::High, 'B', 'desc', 100.0),
        new StrategyRecommendation('no_saving_b', StrategyCategory::Allowance, StrategyPriority::Low, 'C', 'desc', null),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    $types = array_column($plan['items'], 'type');
    // The valued rec should come first.
    expect($types[0])->toBe('has_saving');
    // Combined total only counts the valued rec (100); nulls are excluded.
    expect($plan['combined_annual_saving'])->toBe(100.0);
    // Positions contiguous 1-based.
    expect(array_column($plan['items'], 'sequence_position'))->toBe([1, 2, 3]);
});
