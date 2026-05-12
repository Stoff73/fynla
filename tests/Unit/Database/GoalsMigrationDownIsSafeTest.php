<?php

declare(strict_types=1);

/**
 * Regression test for audit MIG-01 (May/May12Updates/review-database.md).
 *
 * Migration 2026_01_18_000003_migrate_existing_goals_data.php previously
 * truncated the entire `goals` table in its down(). Anyone running
 * `php artisan migrate:rollback --step=N` would silently lose every goal
 * created since the migration ran. The fix: down() must be a no-op for
 * data migrations that don't have a safe reverse.
 *
 * This test pins the contract textually so a future "defensive cleanup"
 * doesn't put truncate() back. We don't actually run migrate:rollback
 * here — that's invasive and slow — but verifying the file content is
 * sufficient.
 */
it('goals data migration down() does not truncate', function () {
    $path = base_path('database/migrations/2026_01_18_000003_migrate_existing_goals_data.php');

    expect(file_exists($path))->toBeTrue("Migration file missing at {$path}");

    $contents = file_get_contents($path);

    // Extract the down() method body. Migrations always use the same
    // anonymous-class pattern, so a simple delimited match is enough.
    expect($contents)->toContain('public function down(): void');
    expect($contents)->not->toContain("DB::table('goals')->truncate()");
    expect($contents)->not->toMatch('/->truncate\s*\(\s*\)/');
});
