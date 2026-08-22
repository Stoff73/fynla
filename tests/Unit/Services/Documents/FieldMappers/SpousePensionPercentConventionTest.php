<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\DBPensionMapper;
use App\Services\Documents\FieldMappers\DCPensionMapper;

/**
 * W-0030. `spouse_pension_percent` is stored in percentage points. The document
 * importer stored the fraction instead — 50% as 0.50 — so an imported Defined
 * Benefit pension projected the spouse's pension at a hundredth of the real
 * figure, while a form-entered one was correct.
 *
 * The Defined Contribution mapper always had the right convention; the two now
 * share one helper so they cannot disagree again.
 */
it('maps a stated spouse percentage to percentage points', function (mixed $input, ?float $expected): void {
    $mapped = (new DBPensionMapper)->map(['spouse_pension_percent' => $input]);

    expect($mapped['spouse_pension_percent'] ?? null)->toBe($expected);
})->with([
    'whole number as the prompt now asks' => [50, 50.0],
    'numeric string' => ['50', 50.0],
    'with a percent sign' => ['50%', 50.0],
    'two thirds' => [66.67, 66.67],
    'full continuation' => [100, 100.0],
    // The old decimal convention. Rescaled rather than trusted: no scheme pays a
    // spouse half of one percent.
    'legacy decimal fraction' => [0.5, 50.0],
    'legacy two-thirds fraction' => [0.6667, 66.67],
    'absent' => [null, null],
    'empty' => ['', null],
    'not a number' => ['n/a', null],
]);

it('clamps to the range the column and both validators allow', function (): void {
    $mapped = (new DBPensionMapper)->map(['spouse_pension_percent' => 250]);

    expect($mapped['spouse_pension_percent'])->toBe(100.0);
});

it('gives the Defined Contribution and Defined Benefit mappers the same answer', function (): void {
    $db = (new DBPensionMapper)->map(['spouse_pension_percent' => 0.5]);
    $dc = (new DCPensionMapper)->map(['employee_contribution_percent' => 0.5]);

    expect($db['spouse_pension_percent'])->toBe($dc['employee_contribution_percent']);
});
