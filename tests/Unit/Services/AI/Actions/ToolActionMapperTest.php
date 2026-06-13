<?php

declare(strict_types=1);

use App\Services\AI\Actions\ActionType;
use App\Services\AI\Actions\ToolActionMapper;
use App\Services\AI\AdviceFyn;

/*
 * CoALA Phase 5 item 3 — ToolActionMapper.
 *
 * Wraps an emitted (tool, args) call into a typed Action. In the CoALA
 * framework `retrieve` is recall from the agent's OWN memory (episodic /
 * semantic); querying the app's engines or writing records is external action,
 * so every tool in AiToolDefinitions maps to a `ground` surface. The write-vs-
 * read SAFETY split lives in SurfaceAllowlist (sourced from WRITE_TOOLS); the
 * mapper only types the call and validates the surface is real.
 */

beforeEach(function () {
    $this->mapper = app(ToolActionMapper::class);
});

it('wraps a write tool call as a ground action carrying surface and args', function () {
    $action = $this->mapper->toAction('create_savings_account', ['provider' => 'Halifax']);

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->surface())->toBe('create_savings_account')
        ->and($action->args())->toBe(['provider' => 'Halifax']);
});

it('wraps a read/engine tool call as a ground action too (external info-gathering, not memory recall)', function () {
    $action = $this->mapper->toAction('get_module_analysis', ['module' => 'savings']);

    expect($action->type)->toBe(ActionType::Ground)
        ->and($action->surface())->toBe('get_module_analysis');
});

it('classifies WRITE_TOOLS members as write surfaces', function () {
    expect($this->mapper->isWriteSurface('create_pension'))->toBeTrue()
        ->and($this->mapper->isWriteSurface('set_expenditure'))->toBeTrue()
        ->and($this->mapper->isWriteSurface('navigate_to_page'))->toBeTrue();
});

it('classifies read/engine tools as non-write surfaces', function () {
    expect($this->mapper->isWriteSurface('get_module_analysis'))->toBeFalse()
        ->and($this->mapper->isWriteSurface('list_records'))->toBeFalse()
        ->and($this->mapper->isWriteSurface('get_tax_information'))->toBeFalse();
});

it('recognises catalogue tools as known surfaces and rejects hallucinated ones', function () {
    expect($this->mapper->isKnownSurface('create_savings_account'))->toBeTrue()
        ->and($this->mapper->isKnownSurface('get_recommendations'))->toBeTrue()
        ->and($this->mapper->isKnownSurface('drop_all_tables'))->toBeFalse();
});

it('drift guard: every WRITE_TOOLS denylist entry is a real catalogue surface', function () {
    // A write surface the catalogue never exposes is an orphaned denylist entry
    // — it would silently protect nothing. (Same orphan-guard discipline as the
    // GroundGate suite.)
    $orphans = array_values(array_filter(
        AdviceFyn::WRITE_TOOLS,
        fn (string $tool) => ! $this->mapper->isKnownSurface($tool),
    ));

    expect($orphans)->toBe([]);
});
