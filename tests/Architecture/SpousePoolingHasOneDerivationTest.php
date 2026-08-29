<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * W-0529 — `$dataSharingEnabled` is derived in ONE place.
 *
 * **CSJ, 2026-08-29**, on whether `EstateAgent` should derive it from the permission the
 * way `IHTController` does: *"Yes it should."*
 *
 * Before this there were eight derivations in six shapes. Two pooled on the link alone,
 * so Fyn pooled an estate the screen would not have and quoted a different figure from
 * the one on the user's screen. Three asked for consent without checking a spouse was
 * there to give it. The rest each spelled out "a spouse, and permission" again.
 *
 * The unit test beside this asserts what the rule DECIDES. This asserts that every site
 * still asks it — which is the half that rots, because a ninth derivation costs one line
 * and reads as perfectly reasonable at the point someone writes it.
 */
it('derives the spouse pooling flag only from User::sharesFinancialDataWithSpouse()', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) as $i => $line) {
            // Assignments only. A parameter declaration (`bool $dataSharingEnabled =
            // false`) is a default for a value passed IN, not a derivation.
            if (! preg_match('/\$dataSharingEnabled\s*=\s*(?!.*function)/', $line)) {
                continue;
            }

            if (str_contains($line, 'bool $dataSharingEnabled')) {
                continue;
            }

            if (str_contains($line, 'sharesFinancialDataWithSpouse()')) {
                continue;
            }

            $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($i + 1).' — '.trim($line);
        }
    }

    expect($offenders)->toBe([], "Derive it from User::sharesFinancialDataWithSpouse():\n".implode("\n", $offenders));
});
