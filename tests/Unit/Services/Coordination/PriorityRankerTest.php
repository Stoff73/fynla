<?php

declare(strict_types=1);

use App\Services\Coordination\PriorityRanker;

/**
 * Batch B (F2, F12): one ranking rule, read from the seeded priority, consumed
 * by Fyn and the dashboard alike. See September/September9Updates/fyn-wiring-batch-b-plan.md.
 */
describe('PriorityRanker::priorityLabel', function () {
    it('reads a string priority label first', function () {
        expect(PriorityRanker::priorityLabel(['priority' => 'high']))->toBe('high')
            ->and(PriorityRanker::priorityLabel(['priority' => 'Low', 'impact' => 'High']))->toBe('low');
    });

    it('reads the impact label when priority is the engine int', function () {
        // The DB-driven engines emit priority as an int and the seeded label in impact.
        expect(PriorityRanker::priorityLabel(['priority' => 1, 'impact' => 'Critical']))->toBe('critical')
            ->and(PriorityRanker::priorityLabel(['priority' => 3, 'impact' => 'Medium']))->toBe('medium');
    });

    it('falls back to the int rule the protection adapter already used', function () {
        expect(PriorityRanker::priorityLabel(['priority' => 1]))->toBe('high')
            ->and(PriorityRanker::priorityLabel(['priority' => 2]))->toBe('high')
            ->and(PriorityRanker::priorityLabel(['priority' => 3]))->toBe('medium')
            ->and(PriorityRanker::priorityLabel(['priority' => 5]))->toBe('low');
    });

    it('defaults to medium when nothing is seeded', function () {
        expect(PriorityRanker::priorityLabel(['title' => 'x']))->toBe('medium')
            ->and(PriorityRanker::priorityLabel(['priority' => 'urgent']))->toBe('medium');
    });
});

describe('PriorityRanker::rankRecommendations', function () {
    it('orders by the seeded band: critical, high, medium, low', function () {
        $ranked = (new PriorityRanker)->rankRecommendations([
            'savings' => [
                ['title' => 'low', 'priority' => 4, 'impact' => 'Low'],
                ['title' => 'critical', 'priority' => 1, 'impact' => 'Critical'],
            ],
            'tax_optimisation' => [
                ['title' => 'medium', 'priority' => 'medium'],
                ['title' => 'high', 'priority' => 'high'],
            ],
        ]);

        expect(array_column($ranked, 'title'))->toBe(['critical', 'high', 'medium', 'low'])
            ->and(array_column($ranked, 'urgency_score'))->toBe([95.0, 85.0, 60.0, 45.0])
            ->and(array_column($ranked, 'timeline'))->toBe(['immediate', 'immediate', 'short_term', 'medium_term']);
    });

    it('never lets a benefit or module weight lift a rec across a band', function () {
        $ranked = (new PriorityRanker)->rankRecommendations([
            'estate' => [['title' => 'high, no benefit', 'priority' => 'high']],
            'protection' => [['title' => 'medium, huge benefit', 'priority' => 'medium', 'estimated_impact' => 1_000_000]],
        ]);

        expect(array_column($ranked, 'title'))->toBe(['high, no benefit', 'medium, huge benefit'])
            ->and($ranked[1]['impact_score'])->toBe(95.0)
            ->and($ranked[1]['priority_score'])->toBeLessThan($ranked[0]['priority_score']);
    });

    it('breaks ties inside a band by benefit, then module weight', function () {
        $ranked = (new PriorityRanker)->rankRecommendations([
            'estate' => [['title' => 'estate', 'priority' => 'high']],
            'protection' => [['title' => 'protection', 'priority' => 'high']],
            'investment' => [['title' => 'investment with saving', 'priority' => 'high', 'estimated_saving' => 12_000]],
        ]);

        expect(array_column($ranked, 'title'))->toBe(['investment with saving', 'protection', 'estate']);
    });

    it('tags every rec with its module and the UI impact label', function () {
        $ranked = (new PriorityRanker)->rankRecommendations([
            'savings' => [['title' => 'a', 'priority' => 1, 'impact' => 'Critical']],
            'goals' => [['title' => 'b', 'priority' => 'low']],
        ]);

        expect($ranked[0])->toMatchArray(['module' => 'savings', 'impact_label' => 'high'])
            ->and($ranked[1])->toMatchArray(['module' => 'goals', 'impact_label' => 'low'])
            ->and($ranked[0])->not->toHaveKeys(['ease_score', 'user_priority_score']);
    });

    it('skips the bookkeeping keys the coordinating agent adds', function () {
        $ranked = (new PriorityRanker)->rankRecommendations([
            'module_scores' => ['savings' => ['x' => 1]],
            'available_surplus' => 500,
            'savings' => [['title' => 'only', 'priority' => 'high']],
        ]);

        expect($ranked)->toHaveCount(1);
    });
});
