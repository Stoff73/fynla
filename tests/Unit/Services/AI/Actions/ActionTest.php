<?php

declare(strict_types=1);

use App\Services\AI\Actions\Action;
use App\Services\AI\Actions\ActionType;

/*
 * CoALA Phase 5 item 3 — typed Action space (FR-M1, plan §"Action space").
 *
 * Action =
 *   | { type: 'reason',   prompt_template_id, working_memory_fields[] }
 *   | { type: 'retrieve', store: 'episodic' | 'semantic', query, filters }
 *   | { type: 'learn',    store, payload }
 *   | { type: 'ground',   surface, args }
 *   | { type: 'no_action' }
 */

it('builds a ground action carrying surface and args', function () {
    $action = Action::ground('create_savings_account', ['provider' => 'Halifax']);

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->isGround())->toBeTrue()
        ->and($action->surface())->toBe('create_savings_account')
        ->and($action->args())->toBe(['provider' => 'Halifax']);
});

it('builds a retrieve action over a known store', function () {
    $action = Action::retrieve('semantic', 'isa allowance 2026', ['category' => 'tax']);

    expect($action->type)->toBe(ActionType::Retrieve)
        ->and($action->store())->toBe('semantic')
        ->and($action->query())->toBe('isa allowance 2026')
        ->and($action->filters())->toBe(['category' => 'tax']);
});

it('builds a reason action carrying a prompt template id and working-memory fields', function () {
    $action = Action::reason('advice.summarise.v1', ['net_worth', 'risk_profile']);

    expect($action->type)->toBe(ActionType::Reason)
        ->and($action->promptTemplateId())->toBe('advice.summarise.v1')
        ->and($action->workingMemoryFields())->toBe(['net_worth', 'risk_profile']);
});

it('builds a learn action carrying a store and payload', function () {
    $action = Action::learn('episodic', ['observation' => 'user confirmed DOB']);

    expect($action->type)->toBe(ActionType::Learn)
        ->and($action->store())->toBe('episodic')
        ->and($action->payload())->toBe(['observation' => 'user confirmed DOB']);
});

it('builds a no_action terminal action', function () {
    $action = Action::noAction();

    expect($action->type)->toBe(ActionType::NoAction)
        ->and($action->isGround())->toBeFalse();
});

it('rejects a ground action with an empty surface', function () {
    Action::ground('');
})->throws(InvalidArgumentException::class);

it('rejects a retrieve action over an unknown store', function () {
    // The action space pins retrieve targets to episodic | semantic.
    Action::retrieve('relational', 'anything');
})->throws(InvalidArgumentException::class);

it('throws when reading ground fields off a non-ground action', function () {
    Action::retrieve('semantic', 'q')->surface();
})->throws(LogicException::class);

it('throws when reading retrieve fields off a non-retrieve action', function () {
    Action::ground('create_goal')->query();
})->throws(LogicException::class);

it('exposes the five canonical action types as backed enum values', function () {
    expect(ActionType::Reason->value)->toBe('reason')
        ->and(ActionType::Retrieve->value)->toBe('retrieve')
        ->and(ActionType::Learn->value)->toBe('learn')
        ->and(ActionType::Ground->value)->toBe('ground')
        ->and(ActionType::NoAction->value)->toBe('no_action');
});
