<?php

declare(strict_types=1);

it('scenario directory count meets category minima', function (): void {
    $minima = config('fyn_eval.scenario_minima');

    expect($minima)->toBeArray()->not->toBeEmpty();

    foreach ($minima as $category => $minCount) {
        $pattern = base_path("tests/Feature/Fyn/Eval/scenarios/{$category}/*.yaml");
        $files = glob($pattern) ?: [];
        expect(count($files))->toBeGreaterThanOrEqual(
            $minCount,
            "Category {$category} has ".count($files)." scenarios, needs {$minCount}"
        );
    }
})->skip(
    fn (): bool => ! config('fyn_eval.enforce_minima'),
    'Minima gate inactive — see plan/11-sprint-1-plan.md S1.1; activates at S1.10 verification rollup or via FYN_EVAL_ENFORCE_MINIMA=true.'
);
