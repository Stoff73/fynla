<?php

declare(strict_types=1);

/**
 * Rule 9, enforced for the one acronym that keeps coming back.
 *
 * **Rule 9 was amended by CSJ on 2026-08-24** and this guard was rewritten with it.
 * The rule is no longer "never AIM". It is: an acronym may be used **once it has been
 * spelled out to that user**, on the surface they are looking at. Write "the
 * Alternative Investment Market (AIM)" and then plain "AIM". What stays banned is an
 * acronym a reader meets **cold**.
 *
 * The previous version banned the acronym outright, and its own failure message told
 * whoever hit it that the parenthesised form needed a CSJ amendment. That amendment
 * has now been made, so the guard enforces the new shape rather than the old one.
 *
 * **Why it sweeps rather than pinning one string.** "AIM" was caught twice in two
 * days: the caveat was rewritten and a test pinned to that ONE string, while two other
 * live strings kept the acronym — `IHTCalculationTable.vue` (a second home for the
 * caveat's own claim) and `TransferRecommendationService` (a recommendation title,
 * body and decision trace, all rendered). A test pinned to one string cannot see a
 * second copy of the sentence. That is the Rule 20 lesson wearing a Rule 9 hat.
 *
 * **Scope, deliberately narrow.** Comments are exempt as USES — they explain the rule
 * and must be able to name the thing. Comments do NOT count as the EXPANSION, because
 * no user reads them; Rule 9's amendment says the expansion must be on the surface the
 * reader sees. Identifiers are exempt — `scanAIMShareIHT()` is a method name.
 *
 * **The file is the proxy for "the surface".** Not perfect — a string at the top of a
 * long service is not necessarily on the same screen as one at the bottom — but it is
 * the closest thing a static sweep can check, and it catches the case that matters: an
 * acronym in a file that never spells it out at all.
 */
it('does not put the AIM acronym in front of a user who has not been told what it means', function () {
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
            $firstExpansionLine = null;
            $acronymLines = [];

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

                if ($firstExpansionLine === null && str_contains($line, 'Alternative Investment Market')) {
                    $firstExpansionLine = $number;
                }

                // `AIM` as a standalone word. Identifiers like `scanAIMShareIHT` and
                // `AIM_THRESHOLD` are excluded by the boundaries: a following letter,
                // digit or underscore means it is a name, not prose.
                if (preg_match('/(?<![A-Za-z0-9_])AIM(?![A-Za-z0-9_])/', $line) === 1) {
                    $acronymLines[] = $number;
                }
            }

            foreach ($acronymLines as $number) {
                // Spelled out at or before this line, on the same surface: allowed
                // under the amended rule. `(AIM)` sits on the same line as its own
                // expansion, which is why this is `<=` and not `<`.
                if ($firstExpansionLine !== null && $firstExpansionLine <= $number) {
                    continue;
                }

                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    // If this fails, a user is being shown "AIM" on a surface that never tells them
    // what it stands for. Spell it out at or before its first use on that surface —
    // "the Alternative Investment Market (AIM)" — rather than adding an exemption
    // here. A comment does not count: no user reads it.
    expect($offenders)->toBe([]);
});
