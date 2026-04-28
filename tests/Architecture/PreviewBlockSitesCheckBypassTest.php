<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/../../');

/**
 * Walks the three directories that the canonical eval contract names as
 * legitimate write-block sites and asserts that every file reading
 * `is_preview_user` ALSO consults `bypass-preview-mode` — so the eval
 * flow's Sanctum bypass token reaches every gate, not just the three
 * the audit knew about.
 *
 * The ignore list is for files where `is_preview_user` is consulted in
 * pure read-context (plan-tier lookup, feature-flag, subscription gate)
 * — bypassing those would change product behaviour in ways unrelated
 * to writes. When a new file lands in these directories that reads the
 * flag in a write context, the test fails until either the file gains
 * the bypass check or it's added to this ignore list with a deliberate
 * comment.
 */

it('every preview write-block site checks the bypass-preview-mode token ability', function () use ($root): void {
    $directories = [
        'app/Http/Middleware/',
        'app/Traits/',
        'app/Agents/',
    ];

    /**
     * Files that read `is_preview_user` in a non-write context. Each entry
     * carries a short comment so the next reader knows why it's exempt.
     * If a file's behaviour changes from read-only to write-context, drop
     * it from this list — the test should fail and force the bypass check.
     */
    $ignore = [
        // Subscription tier lookup — preview users skip the paywall, no
        // write effect.
        'app/Http/Middleware/CheckSubscription.php',
        // Feature-access check — same shape as CheckSubscription.
        'app/Http/Middleware/CheckFeatureAccess.php',
        // Plan-tier lookup ('preview' tier vs paid tier). Used for token
        // budget + rate-limit defaults; no write effect.
        'app/Traits/HasAiGuardrails.php',
        // Audit chain — `is_preview_user` skips audit-row writes. NOT a
        // write-block but DOES affect what gets persisted. Listed here
        // because the 10 current mitchell scenarios are non-mutating; a
        // mutating scenario will need this flag to also gate on bypass
        // so audit chains fire for eval writes. See maxAuditEval §5.6.
        'app/Traits/Auditable.php',
    ];

    $failures = [];
    foreach ($directories as $dir) {
        $absDir = $root.'/'.$dir;
        if (! is_dir($absDir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absDir));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if (in_array($relative, $ignore, true)) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (! str_contains($contents, 'is_preview_user')) {
                continue;
            }
            if (! str_contains($contents, 'bypass-preview-mode')) {
                $failures[] = $relative;
            }
        }
    }

    expect($failures)->toBe([], 'These files read is_preview_user without consulting bypass-preview-mode — eval writes will be silently swallowed:'.PHP_EOL.'  - '.implode(PHP_EOL.'  - ', $failures));
});
