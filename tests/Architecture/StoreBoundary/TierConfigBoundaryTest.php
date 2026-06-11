<?php

declare(strict_types=1);

use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;

/**
 * SP2 PR9 — Tier config boundary: mutation moat + hardcoded-literal ban (hard).
 *
 * Enforces spec §17 (boundary enforcement): hard CI failure, no soft-warn ramp.
 * tier_configurations is the single source of truth; nothing hardcodes a tier
 * number/slug/budget/cap/quota outside the store + tiers namespace + seeder.
 *
 * Three layers:
 *  1. Mutation boundary — TierConfiguration model is only mutated/read
 *     inside the canonical set (unchanged from PR2-8, now HARD).
 *  2. No-literal leak — no tier-keyed budget/cap map (the DAILY_TOKEN_LIMITS
 *     pattern) appears outside the store/tiers namespace/seeder.
 *  3. Dead-gate cleanup — StaticTierGate is absent; PermissiveTierGate is
 *     deleted; TierGate::class resolves to DbTierGate in the container.
 */

// ──────────────────────────────────────────────────────────────────────────────
// Layer 1 — mutation boundary (carried forward from PR3, now HARD)
// ──────────────────────────────────────────────────────────────────────────────

arch('TierConfiguration is only mutated inside the canonical set')
    ->expect('App\Models\TierConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TierConfigurationStore',
        'App\Services\Tiers\TierResolver',          // read-only (added PR 2)
        'App\Services\Tiers\DbTierGate',            // read-only (added PR 3)
        'App\Services\Tiers\TeaserGate',            // read-only (added PR 7)
        'App\Http\Resources\TierConfigurationResource', // read-only (added PR 4)
        // Read-only type hints on tierFeatureBullets(); instances come from
        // TierConfigurationStore — no query or mutation.
        'App\Http\Controllers\Api\PaymentController',
        'Database\Seeders\TierConfigurationSeeder',
        'Database\Factories\TierConfigurationFactory',
        'App\Models\\',
    ]);

// ──────────────────────────────────────────────────────────────────────────────
// Layer 2 — no hardcoded tier-keyed budget/cap maps outside the allowed set
// ──────────────────────────────────────────────────────────────────────────────

$projectRoot = realpath(__DIR__.'/../../../');
$appDir = $projectRoot.'/app';

/**
 * Count lines in $appDir PHP files that match $pattern, skipping allowed paths.
 */
function countTierLiteralLeaks(string $appDir, string $pattern, array $lineExclusions = [], array $pathExclusions = []): int
{
    if (! is_dir($appDir)) {
        return 0;
    }

    $violations = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        foreach ($pathExclusions as $excl) {
            if (str_contains($path, $excl)) {
                continue 2;
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            if (! preg_match($pattern, $line)) {
                continue;
            }

            $excluded = false;
            foreach ($lineExclusions as $excl) {
                if (str_contains($line, $excl)) {
                    $excluded = true;
                    break;
                }
            }

            if (! $excluded) {
                $violations++;
            }
        }
    }

    return $violations;
}

// Paths where tier-slug literals are structurally necessary:
// store (reads/writes tier rows), tiers namespace (resolvers, gates),
// and Stores subtree (SnapshotPolicies uses tier slug as array key after
// reading from the store). The scan walks app/ only — the seeder lives in
// database/seeders/ and is structurally exempt by being outside the tree.
$allowedPaths = [
    'Services/Stores/',       // entire Stores subtree (SnapshotPolicies, CurrencyDisplayService, etc.)
    'Services/Tiers/',        // resolvers, gates
];

it('DAILY_TOKEN_LIMITS constant does not exist anywhere in app/', function () use ($appDir) {
    // The canonical violation this PR removes. Assert the pattern cannot
    // re-appear in any PHP file under app/.
    $violations = countTierLiteralLeaks(
        $appDir,
        '/DAILY_TOKEN_LIMITS/',
        [],
        [] // no path exclusions — it must be absent everywhere
    );

    expect($violations)->toBe(0, 'DAILY_TOKEN_LIMITS constant found in app/. This array was the hardcoded tier-budget source removed in PR 9. Read Fyn token limits from TierConfigurationStore.');
});

it('no tier-keyed token-budget map literal appears in app/ outside the store/tiers/seeder', function () use ($appDir, $allowedPaths) {
    // Detects the DAILY_TOKEN_LIMITS pattern: an array associating old plan
    // slugs (preview/trial/student/standard/family/pro) or new tier slugs
    // (free/tier1/tier2/tier3) with large token-count integers.
    // Pattern: a quoted plan/tier key followed by => and a six-or-more-digit number.
    $violations = countTierLiteralLeaks(
        $appDir,
        "/['\"](?:preview|trial|student|standard|family|pro|free|tier1|tier2|tier3)['\"]\\s*=>\\s*[0-9_]{6,}/",
        [],
        $allowedPaths
    );

    expect($violations)->toBe(0, 'Hardcoded tier-keyed token-budget map found in app/ outside the store/tiers/seeder. This is the DAILY_TOKEN_LIMITS anti-pattern. Read from TierConfigurationStore::forTier()->fyn_weekly_token_budget / fyn_daily_hard_backstop.');
});

// ──────────────────────────────────────────────────────────────────────────────
// Layer 3 — dead-gate cleanup assertions
// ──────────────────────────────────────────────────────────────────────────────

it('StaticTierGate class file does not exist in app/', function () use ($projectRoot) {
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot.'/app', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' && str_contains($file->getFilename(), 'StaticTierGate')) {
            $found[] = $file->getPathname();
        }
    }

    expect($found)->toBe([], 'StaticTierGate class file found — it was deleted in PR 3 and must not be re-introduced.');
});

it('PermissiveTierGate class file does not exist in app/', function () use ($projectRoot) {
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot.'/app', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' && str_contains($file->getFilename(), 'PermissiveTierGate')) {
            $found[] = $file->getPathname();
        }
    }

    expect($found)->toBe([], 'PermissiveTierGate class file found — it is dead code (no live consumer, no container binding) and was deleted in PR 9.');
});

it('AppServiceProvider does not reference PermissiveTierGate', function () use ($projectRoot) {
    $providerPath = $projectRoot.'/app/Providers/AppServiceProvider.php';
    $source = file_get_contents($providerPath);

    expect(str_contains($source, 'PermissiveTierGate'))->toBeFalse(
        'AppServiceProvider must not reference PermissiveTierGate — the TierGate::class binding must resolve to DbTierGate.'
    );
});

it('AppServiceProvider binds TierGate::class to DbTierGate', function () use ($projectRoot) {
    $providerPath = $projectRoot.'/app/Providers/AppServiceProvider.php';
    $source = file_get_contents($providerPath);

    expect(str_contains($source, 'DbTierGate::class'))->toBeTrue(
        'AppServiceProvider must bind TierGate::class to DbTierGate::class.'
    );
});

// ──────────────────────────────────────────────────────────────────────────────
// Layer 4 — store seam integrity: SavingsStore still calls canCreate()
// ──────────────────────────────────────────────────────────────────────────────

it('SavingsStore calls tierGate->canCreate() before persisting a new account', function () use ($projectRoot) {
    $storePath = $projectRoot.'/app/Services/Stores/SavingsStore.php';
    $source = file_get_contents($storePath);

    expect(str_contains($source, 'canCreate('))->toBeTrue(
        'SavingsStore must call $this->tierGate->canCreate() before persisting. The gate seam has been removed.'
    );
});
