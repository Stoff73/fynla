<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;

/**
 * One home for the onboarding transition table (F4, CSJ 2026-09-09).
 *
 * The corpus workflow procedure owns every DATA field (turn_type, prompt text,
 * bubbles, capture_field, static next, extraction tools, retry text, layout…).
 * The in-code table holds only PHP-only fields: skip_if, closures, callable
 * references, and the keys the corpus never carries. Duplicating a DATA value
 * in PHP fails here — that is the Rule 20 guard.
 */

/** Keys that live only in code (never in the corpus). */
const PHP_ONLY_KEYS = ['skip_if', 'reprompt_text', 'record_context', 'record_context_mode', 'capture_focus'];

/** A value is a PHP callable reference iff it is a string containing '::'. */
function isCallableRef(mixed $v): bool
{
    return is_string($v) && str_contains($v, '::');
}

/** Reflectively read the private inCodeStates(). */
function inCodeStates(): array
{
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);

    return $m->invoke(null);
}

it('keeps no corpus-owned DATA value in the in-code table', function (): void {
    foreach (inCodeStates() as $id => $codeState) {
        foreach ($codeState as $key => $value) {
            if (in_array($key, PHP_ONLY_KEYS, true) || $value instanceof Closure || isCallableRef($value)) {
                continue;
            }
            if (is_array($value) && ($value[0] ?? null) === OnboardingStateMachine::class) {
                continue; // [self::class, 'method'] skip_if-style callable
            }

            throw new RuntimeException("In-code state '{$id}' still carries DATA field '{$key}'; the corpus workflow file is its one home.");
        }
    }

    expect(true)->toBeTrue();
});

it('merges every corpus DATA field over the in-code PHP-only fields', function (): void {
    $inCode = inCodeStates();
    $merged = OnboardingStateMachine::transitionTable();

    expect(array_keys($merged))->toBe(array_keys($inCode));

    $corpus = app(ProceduralCorpusLoader::class)->load()->active('onboarding.workflow.fyn-onboarding', asOf: Carbon::now());
    $data = OnboardingWorkflowTable::fromProcedure($corpus);

    foreach ($inCode as $id => $codeState) {
        // Every state has a turn_type, and it came from the corpus.
        expect($merged[$id]['turn_type'] ?? null)->toBe($data[$id]['turn_type']);

        // PHP-only fields survive the merge unchanged.
        foreach (['next', 'prompt_text'] as $k) {
            if (array_key_exists($k, $codeState) && isCallableRef($codeState[$k])) {
                expect($merged[$id][$k] ?? null)->toBe($codeState[$k]);
            }
        }
        if (array_key_exists('skip_if', $codeState)) {
            expect($merged[$id]['skip_if'] ?? null)->toBe($codeState['skip_if']);
        }
        if (array_key_exists('navigate_to', $codeState) && $codeState['navigate_to'] instanceof Closure) {
            expect($merged[$id]['navigate_to'] ?? null)->toBeInstanceOf(Closure::class);
        }

        // Corpus DATA that is not a marker for a code callable lands verbatim.
        foreach ($data[$id] as $key => $value) {
            if (in_array($key, ['next', 'prompt_text'], true) && (is_array($value) || isCallableRef($codeState[$key] ?? null))) {
                continue;
            }
            expect($merged[$id][$key] ?? null)->toBe($value);
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
