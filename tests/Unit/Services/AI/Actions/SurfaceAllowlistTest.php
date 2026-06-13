<?php

declare(strict_types=1);

use App\Services\AI\Actions\SurfaceAllowlist;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Ground\GroundGate;

/*
 * CoALA Phase 5 item 3 — SurfaceAllowlist.
 *
 * The unified session_mode → allowed-surfaces matrix. Single source for the
 * read/write boundary, subsuming the AdviceFyn catalogue strip and the
 * GroundGate predicate. Preserves item-2 behaviour exactly:
 *   - advice (read-only): every WRITE_TOOLS surface denied, everything else allowed.
 *   - data_capture (onboarding write state): pass-through (the per-focus
 *     narrowing stays with OnboardingPromptBuilder::toolsForFocus / the director).
 *   - null / legacy: pass-through — the canonical contract forbids disturbing
 *     legitimate onboarding or legacy writes at the gate.
 */

beforeEach(function () {
    $this->allowlist = app(SurfaceAllowlist::class);
});

it('denies every write surface in advice mode', function () {
    foreach (AdviceFyn::WRITE_TOOLS as $tool) {
        expect($this->allowlist->allows('advice', $tool))
            ->toBeFalse("expected advice mode to deny write surface {$tool}");
    }
});

it('allows read/engine surfaces in advice mode', function () {
    expect($this->allowlist->allows('advice', 'get_module_analysis'))->toBeTrue()
        ->and($this->allowlist->allows('advice', 'list_records'))->toBeTrue()
        ->and($this->allowlist->allows('advice', 'get_recommendations'))->toBeTrue()
        ->and($this->allowlist->allows('advice', 'get_tax_information'))->toBeTrue();
});

it('passes through write surfaces in the data_capture (onboarding) mode', function () {
    expect($this->allowlist->allows('data_capture', 'create_savings_account'))->toBeTrue()
        ->and($this->allowlist->allows('data_capture', 'capture_personal_details'))->toBeTrue()
        ->and($this->allowlist->allows('data_capture', 'set_expenditure'))->toBeTrue();
});

it('passes through every surface for a null / legacy mode (no gate disturbance)', function () {
    expect($this->allowlist->allows(null, 'create_savings_account'))->toBeTrue()
        ->and($this->allowlist->allows(null, 'get_module_analysis'))->toBeTrue();
});

it('is parity-equivalent to the GroundGate predicate for every known surface', function () {
    // The matrix must subsume the item-2 gate: blocking in the gate iff the
    // matrix denies, for both the read-only and write personas.
    $gate = app(GroundGate::class);
    $surfaces = array_merge(
        AdviceFyn::WRITE_TOOLS,
        ['get_module_analysis', 'list_records', 'get_tax_information', 'get_recommendations'],
    );

    foreach ($surfaces as $surface) {
        expect($gate->blocksWriteSurface($surface, 'advice'))
            ->toBe(! $this->allowlist->allows('advice', $surface), "advice parity mismatch for {$surface}")
            ->and($gate->blocksWriteSurface($surface, 'data_capture'))
            ->toBe(! $this->allowlist->allows('data_capture', $surface), "data_capture parity mismatch for {$surface}");
    }
});
