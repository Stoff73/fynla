<?php

declare(strict_types=1);

use App\Services\AI\Actions\ActionType;
use App\Services\AI\Loop\Planner;
use App\Services\AI\Memory\FynMemoryStore;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * Track 2 §6c — the recommendation-routing planner heuristics, authored as the
 * FIRST root-level procedure file (fyn-memory/procedural/recommendation-routing.md).
 * FynMemoryStore::proceduralContext() renders it as a "## Procedures" block that
 * FynLoop::plannerSystemPrompt() layers into every planner call (FR-M2).
 *
 * plannerSystemPrompt() itself is private — the loading test below plus FynLoop's
 * existing plumbing tests cover the chain; no prompt-integration test needed.
 */
it('the recommendation-routing procedure loads into the planner context', function (): void {
    config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);

    $context = app(FynMemoryStore::class)->proceduralContext();

    expect($context)->toContain('Recommendation turns — route to the composed plan')
        ->and($context)->toContain('get_recommendations')
        ->and($context)->toContain('unlock question');
});

it('parses a ground plan routed to get_recommendations into a ground Action', function (): void {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'ground', 'surface' => 'get_recommendations', 'args' => []])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->surface())->toBe('get_recommendations')
        ->and($action->args())->toBe([]);
});
