<?php

declare(strict_types=1);

use App\Services\AI\Actions\ActionType;
use App\Services\AI\Loop\Planner;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CoALA Phase 5 item 5 — planner LLM call (FR-M6), increment 1.
 *
 * The planner makes ONE forced `plan` tool_use call and parses the result into
 * a typed Action. These tests pin the parse contract against the stream-mock
 * harness; the cycle loop / budget / dispatch land in increment 2 (FynLoop).
 */
it('parses a reason plan tool_use into a reason Action', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Reason)
        ->and($action->promptTemplateId())->toBe('advice_default');
});

it('parses a ground plan tool_use into a ground Action', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'ground', 'surface' => 'create_savings_account', 'args' => ['provider' => 'Halifax']])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->surface())->toBe('create_savings_account')
        ->and($action->args())->toBe(['provider' => 'Halifax']);
});

it('parses a retrieve plan tool_use into a retrieve Action', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'retrieve', 'store' => 'episodic', 'query' => 'last pension chat'])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Retrieve)
        ->and($action->store())->toBe('episodic')
        ->and($action->query())->toBe('last pension chat');
});

it('parses an explicit no_action plan tool_use', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::NoAction);
});

it('defaults a reason action with no template to the default template (no throw)', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason'])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Reason)
        ->and($action->promptTemplateId())->toBe(Planner::DEFAULT_REASON_TEMPLATE);
});

it('degrades an unknown action_type to a default reason action (graceful planner-hiccup fallback)', function () {
    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'wat'])
        ->bind();

    $action = app(Planner::class)->plan('system', [], 'test-model');

    expect($action->type)->toBe(ActionType::Reason)
        ->and($action->promptTemplateId())->toBe(Planner::DEFAULT_REASON_TEMPLATE);
});
