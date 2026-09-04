<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * W-0540. A component could lose its last importer and nothing failed.
 *
 * It happened twice before anyone noticed: W-0376 (four dead sites, two of them
 * carrying their own copy of a rule) and W-0538 (`TrustsOverviewCard.vue`, which
 * had a design fix made to it, reviewed and signed off, while no page rendered
 * it). Both were found by a person looking, months apart.
 *
 * A component nothing renders is worse than a spare file: it accepts fixes. The
 * palette work on W-0045's fourth surface went into one of these and reached no
 * screen, and would have kept doing so.
 *
 * **The detector.** A component is reachable if its basename appears in any other
 * tracked file. That is deliberately generous — a lazy `import()` by path, a
 * router string, a test, a comment — because the cost of a false positive here is
 * deleting live code, and the cost of a false negative is one more dead file that
 * the next run catches. Verified before the 2026-09-04 sweep that nothing resolves
 * a component by any other route: no global registration in `app.js`, no
 * `import.meta.glob` over components, and every `<component :is>` binding resolves
 * to a string literal or a locally imported component.
 *
 * **No allowlist, deliberately.** One was offered and withdrawn: an allowlist here
 * would record a dead component as permanently acceptable, which is the state this
 * test exists to end.
 */
it('renders every component it ships', function () {
    $root = dirname(__DIR__, 2);
    $roots = [$root.'/resources/js', $root.'/resources/mobile'];

    // One pass. A grep per component is minutes; indexing every file once and
    // asking which files mention each basename is seconds, and a guard nobody
    // waits for is a guard that gets skipped.
    $files = [];
    foreach ($roots as $tree) {
        foreach ((new Finder)->files()->in($tree)->name(['*.vue', '*.js', '*.ts', '*.json'])->getIterator() as $file) {
            $files[$file->getRealPath()] = (string) file_get_contents($file->getRealPath());
        }
    }

    foreach ([$root.'/tests/frontend', $root.'/resources/views', $root.'/public/pages'] as $extra) {
        if (! is_dir($extra)) {
            continue;
        }
        foreach ((new Finder)->files()->in($extra)->name(['*.vue', '*.js', '*.php', '*.html'])->getIterator() as $file) {
            $files[$file->getRealPath()] = (string) file_get_contents($file->getRealPath());
        }
    }

    $components = [];
    foreach ($roots as $tree) {
        $dir = $tree.'/components';
        if (! is_dir($dir)) {
            continue;
        }
        foreach ((new Finder)->files()->in($dir)->name('*.vue')->getIterator() as $file) {
            $components[$file->getRealPath()] = $file->getBasename('.vue');
        }
    }

    expect($components)->not->toBeEmpty();

    $orphans = [];
    foreach ($components as $path => $basename) {
        $referenced = false;

        foreach ($files as $otherPath => $contents) {
            if ($otherPath === $path) {
                continue;
            }
            if (str_contains($contents, $basename)) {
                $referenced = true;
                break;
            }
        }

        if (! $referenced) {
            $orphans[] = str_replace($root.'/', '', $path);
        }
    }

    sort($orphans);

    expect($orphans)->toBe([], implode("\n", [
        'Components nothing renders:',
        '  '.implode("\n  ", $orphans),
        '',
        'A component no page renders still accepts fixes, and they reach no screen —',
        'which is what W-0376 and W-0538 both were. Either wire it in or delete it.',
        'Do not add an allowlist: that records the dead file as acceptable, which is',
        'the state this test exists to end.',
    ]));
});
