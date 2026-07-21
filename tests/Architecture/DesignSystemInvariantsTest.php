<?php

declare(strict_types=1);

/**
 * Design-system invariant arch tests — Wave 2 commit 3.
 *
 * Locks in the audit-flagged design invariants so they cannot regress:
 *
 *   Rule #5  — no `'sole'` ownership_type. Canonical enums are
 *             `individual`, `joint`, `tenants_in_common`, `trust`.
 *             `sole` is legacy/deprecated and must not appear in code.
 *   Rule #9  — no `amber-*` or `orange-*` Tailwind classes anywhere in
 *             Vue / JS / CSS sources. Use violet for warnings,
 *             raspberry for danger, spring for success.
 *
 * Rules #13 (no score badges) and #14 (every routed view wraps in
 * AppLayout / PublicLayout / MobileLayout) are not yet locked by arch
 * tests — they need a more sophisticated detector than pattern grep
 * (Rule #13 has many false-positive "score" mentions in non-display
 * contexts; Rule #14 needs router/index.js parsing to distinguish
 * routed views from sub-components). Both tracked as follow-ups.
 */

$projectRoot = realpath(__DIR__.'/../../');

/**
 * Return every line that matches the given regex across the given paths,
 * tagged with `path:line:content`. Returns an array of strings.
 *
 * @param  array<int, string>  $excludePathContains  Substrings — if a path contains any,
 *                                                   the file is skipped.
 */
function scanForViolations(array $paths, string $pattern, array $excludePathContains = []): array
{
    $violations = [];
    foreach ($paths as $path) {
        if (! is_dir($path) && ! is_file($path)) {
            continue;
        }

        $entries = is_dir($path)
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
            : [new SplFileInfo($path)];

        foreach ($entries as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            foreach ($excludePathContains as $skip) {
                if (str_contains($filePath, $skip)) {
                    continue 2;
                }
            }

            $contents = @file_get_contents($filePath);
            if ($contents === false) {
                continue;
            }

            $lines = explode("\n", $contents);
            foreach ($lines as $lineNo => $lineContent) {
                if (preg_match($pattern, $lineContent)) {
                    $violations[] = $filePath.':'.($lineNo + 1).': '.trim($lineContent);
                }
            }
        }
    }

    return $violations;
}

it('Rule #5 — no string "sole" used as an ownership_type value', function () use ($projectRoot) {
    // Targets actual ownership-value usage: `'sole'` literal in PHP/JS
    // where the context is ownership-related. We're conservative — match
    // any occurrence of the bare `'sole'` literal inside relevant
    // directories, excluding tests (which may need to assert rejection
    // of legacy values) and vendor.
    $violations = scanForViolations(
        [
            $projectRoot.'/app',
            $projectRoot.'/resources/js',
            $projectRoot.'/database/migrations',
            $projectRoot.'/database/seeders',
        ],
        "/['\"]sole['\"]/",
        excludePathContains: [
            '/node_modules/',
            '/vendor/',
            '/tests/',
            '/storage/',
        ],
    );

    expect($violations)->toBeEmpty();
});

it('Rule #9 — no amber-* / orange-* Tailwind classes in frontend sources', function () use ($projectRoot) {
    // Pattern matches `amber-N` / `orange-N` Tailwind utility names —
    // catches bg-, text-, border-, ring-, etc. in any prefix. Excludes
    // node_modules, build artefacts, and the design-guide doc which may
    // legitimately reference banned colours when documenting the ban.
    $violations = scanForViolations(
        [
            $projectRoot.'/resources/js',
            $projectRoot.'/resources/css',
            $projectRoot.'/resources/views',
        ],
        '/(?<![a-zA-Z])(?:amber|orange)-[0-9]+/',
        excludePathContains: [
            '/node_modules/',
            '/build/',
        ],
    );

    expect($violations)->toBeEmpty();
});
