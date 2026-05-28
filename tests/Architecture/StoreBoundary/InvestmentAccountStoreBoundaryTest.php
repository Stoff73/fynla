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
        // removed in PR 3 (Fyn routing)
        'app/Agents/CoordinatingAgent.php',

        // removed in PR 4 (upload routing)
        'app/Services/Documents/DocumentProcessor.php',

        // removed in PR 4 (onboarding routing)
        'app/Services/Onboarding/OnboardingService.php',

        // removed in PR 4 (seeder routing)
        'database/seeders/PreviewUserSeeder.php',

        // removed in PR 4 (seeder routing)
        'database/seeders/ChrisUserSeeder.php',
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
