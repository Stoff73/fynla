<?php

declare(strict_types=1);

/**
 * Boundary architecture test for InvestmentAccountStore — SOFT TRANSITION (SP1 Pass 6 PR 1).
 *
 * Every mutation of App\Models\Investment\InvestmentAccount MUST go through
 * App\Services\Stores\InvestmentAccountStore. The allowlist below documents
 * every current write site; each entry is annotated with the PR that will
 * remove it. This test will be LOCKED (allowlist cleared) in PR 12.
 */

use PHPUnit\Framework\Assert;
use Tests\TestCase;

uses(TestCase::class);

it('enforces InvestmentAccountStore as the only write path for InvestmentAccount', function () {
    $allowlist = [
        // LOCKED (SP1 Pass 6 PR 4) — PreviewUserSeeder::deleteUserData()
        // uses InvestmentAccount::where('user_id', $userId)->delete() for bulk
        // pre-seed cleanup. This is a seeder-admin operation (spec §14.2 seeder
        // category), mirroring MortgageStoreBoundaryTest's treatment of the same
        // seeder. Migrating to per-record InvestmentAccountStore::delete would be
        // audit-noisy (one audit row per account per persona) and would require a
        // new bulk-cleanup store method out of scope here. The per-record create()
        // at line 894 IS routed through the store; only the bulk-delete remains.
        // Kept allowlisted, documented here permanently.
        'database/seeders/PreviewUserSeeder.php',
    ];

    $patterns = [
        '/\bInvestmentAccount::(create|insert|update|updateOrCreate|save|delete|forceDelete|restore|truncate)\b/',
        '/->investmentAccounts\(\)->(create|insert|save|update|delete|forceDelete)\b/',
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
        "InvestmentAccountStore boundary violations (route through InvestmentAccountStore or add to allowlist): \n".implode("\n", $violations)
    );
});
