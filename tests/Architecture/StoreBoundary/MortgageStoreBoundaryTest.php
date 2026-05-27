<?php

declare(strict_types=1);

/**
 * Boundary architecture test for MortgageStore.
 *
 * SP1 Pass 5 contract: every mutation of App\Models\Mortgage must go
 * through App\Services\Stores\MortgageStore. This test scans for direct
 * Mortgage::create / ::update / ::save / ::delete / ::forceDelete /
 * ::restore call sites outside the store + an allowlist that shrinks
 * each PR until LOCKED in PR 8.
 *
 * SOFT mode: allowlist contains every existing direct-write site for
 * incremental migration. LOCKED in PR 8.
 */

use PHPUnit\Framework\Assert;
use Tests\TestCase;

uses(TestCase::class);

it('enforces MortgageStore as the only write path for Mortgage', function () {
    $allowlist = [
        // PR 2 trimmed: MortgageController + PreviewController now route through MortgageStore.
        'app/Http/Controllers/Api/PropertyController.php',         // PR 4 will trim (mortgages()->delete() cascade in destroy)
        'app/Agents/CoordinatingAgent.php',                        // PR 3 will trim
        'app/Services/Property/MortgageService.php',               // PR 4 will trim (createFromPropertyData)
        'app/Services/Documents/DocumentProcessor.php',            // PR 4 will trim
        'app/Services/Onboarding/OnboardingService.php',           // PR 4 will trim
        'app/Services/Onboarding/AssetCaptureEntityExtractor.php', // PR 4 will trim
        'database/seeders/PreviewUserSeeder.php',                  // PR 4 will trim
        'database/seeders/ChrisUserSeeder.php',                    // PR 4 will trim
        'database/seeders/LifecycleTestSeeder.php',                // PR 4 will trim if used
        'app/Console/Commands/EncryptExistingData.php',            // PR 8 LOCKED — pre-existing migration command
        'app/Console/Commands/ResetPreviewData.php',               // PR 8 LOCKED — admin reset
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
