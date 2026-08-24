<?php

declare(strict_types=1);

/**
 * Rule 9, enforced for the one acronym that keeps coming back.
 *
 * CLAUDE.md Rule 9: every acronym is spelled out in user-facing text, and **ISA is
 * the only exception**. Rule 9 has no grandfather clause — unlike Rule 15, it applies
 * to what is already there.
 *
 * "AIM" has now been caught twice in two days. The caveat was rewritten to spell out
 * "the Alternative Investment Market" and a test asserted the acronym's absence from
 * that one string — and two other live strings kept it, including
 * `"the Alternative Investment Market (AIM)"`, precisely the parenthesised form
 * recorded as needing a Rule 9 AMENDMENT that only CSJ can make:
 *
 *   - `IHTCalculationTable.vue` — a second home for the caveat's own claim
 *   - `TransferRecommendationService` — a recommendation title, body and decision
 *     trace, all rendered to the user
 *
 * A test pinned to one string cannot see a second copy of the sentence, which is the
 * Rule 20 lesson wearing a Rule 9 hat. This one sweeps instead.
 *
 * **Scope, deliberately narrow.** Comments are exempt: they explain the rule and must
 * be able to name the thing. Identifiers are exempt — `scanAIMShareIHT()` is a method
 * name and no user reads it. What is checked is quoted strings and template text.
 */
it('does not put the AIM acronym in front of a user', function () {
    $roots = [
        base_path('app/Services'),
        base_path('app/Http/Controllers'),
        base_path('app/Agents'),
        base_path('resources/js'),
        base_path('resources/mobile'),
        base_path('resources/views/emails'),
    ];

    $offenders = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'vue', 'js'], true)) {
                continue;
            }

            $inBlockComment = false;

            foreach (file($file->getPathname()) as $number => $line) {
                $trimmed = ltrim($line);

                // Comments explain the rule and must be able to name it — including
                // their CONTINUATION lines. The first version of this test checked
                // only whether a line STARTED a comment, and immediately flagged its
                // own explanatory comment three lines in. A guard that cannot be
                // explained without tripping is a guard people delete.
                if ($inBlockComment) {
                    if (str_contains($line, '*/') || str_contains($line, '-->')) {
                        $inBlockComment = false;
                    }

                    continue;
                }

                if (str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '<!--')) {
                    if (! str_contains($line, '*/') && ! str_contains($line, '-->')) {
                        $inBlockComment = true;
                    }

                    continue;
                }

                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '#')) {
                    continue;
                }

                // `AIM` as a standalone word. Identifiers like `scanAIMShareIHT` and
                // `AIM_THRESHOLD` are excluded by the boundaries: a following letter,
                // digit or underscore means it is a name, not prose.
                if (preg_match('/(?<![A-Za-z0-9_])AIM(?![A-Za-z0-9_])/', $line) !== 1) {
                    continue;
                }

                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    // If this fails, Rule 9 has been breached in user-facing text. Spell out "the
    // Alternative Investment Market". Writing "the Alternative Investment Market
    // (AIM)" for recognisability is a Rule 9 AMENDMENT and is CSJ's alone to make —
    // do not settle it in the string, and do not add an exemption here.
    expect($offenders)->toBe([]);
});
