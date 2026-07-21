<?php

declare(strict_types=1);

/**
 * Boundary architecture test for MortgageStore — LOCKED (SP1 Pass 5 PR 8).
 *
 * Every mutation of App\Models\Mortgage MUST go through App\Services\Stores\MortgageStore.
 * The allowlist below documents the only legitimate exceptions, each with a justification.
 */

use PHPUnit\Framework\Assert;
use Tests\TestCase;

uses(TestCase::class);

it('enforces MortgageStore as the only write path for Mortgage', function () {
    $allowlist = [
        // LOCKED (SP1 Pass 5 PR 8) — pre-existing migration command; uses
        // Mortgage::chunkById + forceFill/saveQuietly for encryption backfill
        // (spec §14.2 console-command category). Never a runtime write path.
        'app/Console/Commands/EncryptExistingData.php',

        // LOCKED (SP1 Pass 5 PR 8) — admin-only reset command; resets all
        // mortgage records to a known state. Controlled console command
        // (spec §14.2 console-command category). Never a runtime write path.
        'app/Console/Commands/ResetPreviewData.php',

        // LOCKED (SP1 Pass 5 PR 8) — PreviewUserSeeder::deleteUserData()
        // uses Mortgage::where('user_id', $userId)->delete() for bulk
        // pre-seed cleanup. This is a seeder-admin operation (spec §14.2
        // seeder category) equivalent to the Property store's treatment of the
        // same seeder: PropertyStoreBoundaryTest allowlists PreviewUserSeeder
        // with the same rationale ("resetPersonaData() retains a bulk-delete
        // path outside the ingest boundary"). Migrating to per-record
        // MortgageStore::delete would be audit-noisy (one audit row per
        // mortgage per persona) and would require a new bulk-cleanup store
        // method out of scope for PR 8. Mirroring the Property precedent:
        // kept allowlisted, documented here permanently.
        'database/seeders/PreviewUserSeeder.php',
    ];

    $patterns = [
        '/\bMortgage::(create|insert|update|updateOrCreate|save|delete|forceDelete|restore|truncate)\b/',
        '/->mortgages\(\)->(create|insert|save|update|delete|forceDelete)\b/',
    ];

    $violations = [];
    $base = base_path();

    $scanDirs = [
        ['dir' => $base.'/app', 'prefix' => 'app/'],
        ['dir' => $base.'/database/seeders', 'prefix' => 'database/seeders/'],
    ];

    foreach ($scanDirs as ['dir' => $dir, 'prefix' => $prefix]) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = $prefix.ltrim(str_replace($dir, '', $file->getRealPath()), DIRECTORY_SEPARATOR);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            // Store implementations are exempt.
            if (str_starts_with($relativePath, 'app/Services/Stores/')) {
                continue;
            }
            if (in_array($relativePath, $allowlist, true)) {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $violations[] = $relativePath;
                    break;
                }
            }
        }
    }

    Assert::assertEmpty(
        $violations,
        "MortgageStore boundary violations (route through MortgageStore or add to allowlist): \n".implode("\n", $violations)
    );
});
