<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;

/**
 * Phase 4d HARD GATE.
 *
 * Proves the .md-backed + merged transition table is value-identical to the
 * in-code states() table for the DATA subset (incl. state order + bubble
 * order), and that the PHP-only fields (callable next, callable prompt_text,
 * skip_if array callable) are object-identical between merged and in-code.
 *
 * A drifted .md (state added/removed/renamed, or a DATA value changed) fails
 * here. PHP-only fields are never in the .md and are asserted unchanged from
 * the in-code side.
 */

// Keys that are NEVER read from the corpus — always re-attached from code.
const PHP_ONLY_KEYS = ['skip_if'];

/** A value is a PHP callable reference iff it is a string containing '::'. */
function isCallableRef(mixed $v): bool
{
    return is_string($v) && str_contains($v, '::');
}

/** Strip the PHP-only / callable fields so two states can be compared as DATA. */
function dataSubset(array $state): array
{
    $out = [];
    foreach ($state as $k => $v) {
        if (in_array($k, PHP_ONLY_KEYS, true)) {
            continue;
        }
        if (($k === 'next' || $k === 'prompt_text') && isCallableRef($v)) {
            continue; // callable form — compared separately as a PHP-only field
        }
        $out[$k] = $v;
    }

    return $out;
}

/** Reflectively read the private inCodeStates() so the test sees the authority. */
function inCodeStates(): array
{
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);

    return $m->invoke(null);
}

it('merged corpus-backed table deep-equals the in-code states() table', function (): void {
    $inCode = inCodeStates();
    $merged = OnboardingStateMachine::transitionTable();

    // State-id set + ORDER identical.
    expect(array_keys($merged))->toBe(array_keys($inCode));

    foreach ($inCode as $id => $codeState) {
        // DATA subset value-identical, incl. nested ordering (bubbles etc.).
        expect(dataSubset($merged[$id]))->toEqual(dataSubset($codeState))
            ->and(json_encode(dataSubset($merged[$id])))
            ->toBe(json_encode(dataSubset($codeState)))   // strict ordering
            ->and(array_keys($merged[$id]))->toBe(array_keys($codeState)); // key order per state

        // PHP-only / callable fields: object-identical (kept from code).
        foreach (['next', 'prompt_text'] as $k) {
            if (array_key_exists($k, $codeState) && isCallableRef($codeState[$k])) {
                expect($merged[$id][$k] ?? null)->toBe($codeState[$k]);
            }
        }
        if (array_key_exists('skip_if', $codeState)) {
            expect($merged[$id]['skip_if'] ?? null)->toBe($codeState['skip_if']);
        }
    }
});

it('the corpus state-id set exactly equals the in-code state-id set', function (): void {
    $corpus = app(ProceduralCorpusLoader::class)->load();
    $proc = $corpus->active('onboarding.workflow.fyn-onboarding', asOf: Carbon::now());
    expect($proc)->not->toBeNull();

    $parsed = OnboardingWorkflowTable::fromProcedure($proc);
    expect($parsed)->not->toBeNull()
        ->and(array_keys($parsed))->toBe(array_keys(inCodeStates()));
});
