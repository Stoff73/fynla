<?php

declare(strict_types=1);

use App\Services\AI\Actions\ActionDispatcher;
use App\Services\AI\Actions\ActionType;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Ground\GroundGate;

/*
 * CoALA Phase 5 item 3 — ActionDispatcher.
 *
 * The decision seam that wraps each emitted tool call in a typed Action and
 * checks it against the unified SurfaceAllowlist. Replaces the bare GroundGate
 * check at the HasAiChat dispatch point (item 2), enforcing the identical
 * write-safety boundary while typing every call. It does NOT execute the tool —
 * execution stays in the HasAiChat tool-use loop until FynLoop lands (item 4).
 */

beforeEach(function () {
    $this->dispatcher = app(ActionDispatcher::class);
});

it('classifies an emitted tool call as a typed ground action', function () {
    $action = $this->dispatcher->classify('create_pension', ['provider' => 'Aviva']);

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->surface())->toBe('create_pension')
        ->and($action->args())->toBe(['provider' => 'Aviva']);
});

it('denies a write surface in advice mode', function () {
    expect($this->dispatcher->isSurfaceAllowed('create_savings_account', 'advice'))->toBeFalse();
});

it('allows a read surface in advice mode', function () {
    expect($this->dispatcher->isSurfaceAllowed('get_module_analysis', 'advice'))->toBeTrue();
});

it('allows a write surface in the onboarding and legacy modes', function () {
    expect($this->dispatcher->isSurfaceAllowed('create_savings_account', 'data_capture'))->toBeTrue()
        ->and($this->dispatcher->isSurfaceAllowed('create_savings_account', null))->toBeTrue();
});

it('subsumes the item-2 GroundGate: a denied surface in advice is exactly a blocked one', function () {
    $gate = app(GroundGate::class);

    foreach (AdviceFyn::WRITE_TOOLS as $surface) {
        expect($this->dispatcher->isSurfaceAllowed($surface, 'advice'))
            ->toBe(! $gate->blocksWriteSurface($surface, 'advice'), "dispatcher/gate mismatch for {$surface}");
    }
});
