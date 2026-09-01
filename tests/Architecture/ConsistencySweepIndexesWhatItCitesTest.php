<?php

declare(strict_types=1);

/**
 * W-0506. `workforce/ops/sweep.sh` reported 99 broken references and nobody read it —
 * a three-minute check whose output is mostly noise will not be believed the day it
 * catches something real.
 *
 * **The item proposed treating only paths containing a `/` as links.** That rule fails
 * on the item's own example: 25 of the 41 slash-bearing references were
 * `reports/R-01-...`, which the item itself names as citations. Measured before fixing.
 *
 * **The actual cause is the basename index.** The orphan check resolves a reference by
 * looking its basename up in an index built from a fixed list of directories — and that
 * list omitted `tests`, `public`, `ios-native`, `routes` and `fyn-memory`. So every
 * citation of a persona report under `tests/Persona/…/reports/`, a test file, an iOS
 * fixture, a built asset or a Fyn tool schema was unresolvable **by construction**: not
 * broken, invisible. That is all four example shapes the item lists.
 *
 * Widening the index took the count 99 → 34, and the remainder is largely real —
 * genuinely deleted files like `SpouseNRBTrackerService.php` and `VoiceInputButton.vue`.
 * No citation heuristic was needed and none was added: guessing at intent would have
 * hidden the real findings along with the noise.
 */
it('indexes every directory the trunk documents actually cite', function () {
    $sweep = (string) file_get_contents(__DIR__.'/../../workforce/ops/sweep.sh');

    // Each of these was the sole reason for a class of false positive.
    $required = [
        'tests' => 'persona reports and test filenames cited as evidence',
        'public' => 'built assets under m-build/ quoted in deploy notes',
        'ios-native' => 'Swift files and FynlaTests fixtures',
        'routes' => 'route files cited in API notes',
        'fyn-memory' => 'Fyn tool schemas — capture_salary_sacrifice.xai.md and siblings',
    ];

    // Read the whole `find` command, continuation lines included, rather than the file —
    // so an unrelated mention of "tests" elsewhere in the script cannot satisfy this,
    // and so roots split across a line break still count.
    preg_match('/^find\s(.+?)>\s*\/tmp\/sweep_names\.txt/ms', $sweep, $match);

    expect($match)->not->toBeEmpty('sweep.sh no longer builds the basename index with find');

    // Collected and asserted as a set, so a failure names every missing root at once.
    // `toContain()` takes further NEEDLES, not a message — passing the reason as a
    // second argument asserts the script contains the reason text.
    $missing = [];

    foreach (array_keys($required) as $root) {
        if (! str_contains($match[1], $root)) {
            $missing[] = $root.' — '.$required[$root];
        }
    }

    expect($missing)->toBe([]);
});

it('skips illustrative placeholders rather than reporting them as broken', function () {
    $sweep = (string) file_get_contents(__DIR__.'/../../workforce/ops/sweep.sh');

    // `Foo.php`, `branches/fixes/F-....md` and `.php/.blade.php/.html/.js/.vue` are
    // examples in prose, not references. NNNN / YYYY / <slug> were already filtered;
    // the elided form was not.
    expect($sweep)->toContain("*'...'*");
});
