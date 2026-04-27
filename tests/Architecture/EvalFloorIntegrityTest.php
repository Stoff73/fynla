<?php

declare(strict_types=1);

it('hard-fail floors are non-tunable per fyn-rubrics.md §B', function (): void {
    $floors = config('fyn_eval.hard_fail_floors');

    expect($floors)->toBeArray();
    expect($floors['entity_validity'] ?? null)->toBe(100,
        'entity_validity hard-fail floor must remain 100% — never tunable.');
    expect($floors['value_accuracy'] ?? null)->toBe(100,
        'value_accuracy hard-fail floor must remain 100% — financial app, no £ drift.');
    expect($floors['cross_entity_consistency'] ?? null)->toBe(100,
        'cross_entity_consistency hard-fail floor must remain 100% — no field-bleed.');
    expect($floors['fabrication_rate'] ?? null)->toBe(0,
        'fabrication_rate hard-fail floor must remain 0% — no invented fields.');
});

it('recall and precision baselines are present and within band', function (): void {
    $recall = config('fyn_eval.recall_floor');
    $precision = config('fyn_eval.precision_floor');

    expect($recall)->toBeArray();
    expect($recall['default'] ?? null)
        ->toBeGreaterThanOrEqual(95)
        ->toBeLessThanOrEqual(100,
            'recall_floor.default must sit in [95, 100] — Sprint 1 baseline.');

    expect($precision)->toBeArray();
    expect($precision['default'] ?? null)
        ->toBeGreaterThanOrEqual(95)
        ->toBeLessThanOrEqual(100,
            'precision_floor.default must sit in [95, 100] — Sprint 1 baseline.');
});
