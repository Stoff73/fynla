<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\PointAward;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Support\Facades\DB;

/**
 * W-0030 acceptance criterion 3: rows written under the decimal convention are
 * found and migrated, and the derived cache is recalculated with them.
 *
 * The local database had zero affected rows when this was written, which is
 * exactly why the migration must be exercised here rather than assumed correct
 * from a clean run — csjones and production were not surveyed.
 *
 * **These tests commit, whether they like it or not, so they clean up by hand.**
 * `up()` alters the column comment, and MySQL commits implicitly on DDL. The
 * moment it runs, `RefreshDatabase`'s wrapping transaction is gone and teardown's
 * rollback becomes a silent no-op — so every row written before that point is
 * committed for the rest of the run. That is not hypothetical: it leaked four
 * users, four pensions and their gamification rows into a full-suite run and
 * failed two unrelated end-of-file guards in `TrialSchemaRemovalTest` and
 * `AppleTransactionSubmissionApiTest`, which report survivors by name (W-0125).
 *
 * Each test therefore cleans up in a `finally`, so a failing assertion still
 * tidies, and the guard at the bottom of this file fails loudly if it misses
 * something.
 */
function runSpousePercentMigration(): void
{
    $migration = require base_path('database/migrations/2026_08_21_120000_correct_spouse_pension_percent_convention.php');
    $migration->up();
}

/**
 * A user with one defined benefit pension, ready for the migration to find.
 *
 * @param  array<string, mixed>  $attributes
 */
function spousePercentFixture(array $attributes): DBPension
{
    $user = User::factory()->create();

    return DBPension::factory()->create([...$attributes, 'user_id' => $user->id]);
}

/**
 * Remove everything a committed test wrote, children first.
 *
 * Creating a pension fires the gamification observer, so a `point_awards` row and
 * a `user_gamification` row arrive with it — neither follows the user by foreign
 * key, and both outlive a `User` that only soft-deletes. The tax configuration is
 * the `Pest.php` safety-net row, created inside the same doomed transaction.
 */
function cleanupCommittedSpousePercentFixture(int $userId): void
{
    PointAward::query()->where('user_id', $userId)->delete();
    UserGamification::query()->where('user_id', $userId)->delete();
    DBPension::withTrashed()->where('user_id', $userId)->forceDelete();

    $user = User::withTrashed()->find($userId);
    if ($user !== null) {
        $user->tokens()->delete();
        $user->forceDelete();
    }

    TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
}

it('rescales a decimal-convention row and recalculates the projected spouse pension', function (): void {
    $pension = spousePercentFixture([
        'accrued_annual_pension' => 35000,
        'spouse_pension_percent' => 0.50,
        'spouse_pension_projected_gbp' => 175.00,
    ]);

    try {
        runSpousePercentMigration();
        $pension->refresh();

        expect((float) $pension->spouse_pension_percent)->toBe(50.0)
            // 35000 x 50% — the figure the projection should always have used.
            ->and((float) $pension->spouse_pension_projected_gbp)->toBe(17500.0);
    } finally {
        cleanupCommittedSpousePercentFixture($pension->user_id);
    }
});

it('leaves a correctly stored row alone', function (): void {
    $pension = spousePercentFixture([
        'accrued_annual_pension' => 22000,
        'spouse_pension_percent' => 50.00,
        'spouse_pension_projected_gbp' => 11000.00,
    ]);

    try {
        runSpousePercentMigration();
        $pension->refresh();

        expect((float) $pension->spouse_pension_percent)->toBe(50.0)
            ->and((float) $pension->spouse_pension_projected_gbp)->toBe(11000.0);
    } finally {
        cleanupCommittedSpousePercentFixture($pension->user_id);
    }
});

it('is idempotent — a second run changes nothing', function (): void {
    $pension = spousePercentFixture([
        'accrued_annual_pension' => 35000,
        'spouse_pension_percent' => 0.50,
    ]);

    try {
        runSpousePercentMigration();
        runSpousePercentMigration();
        $pension->refresh();

        expect((float) $pension->spouse_pension_percent)->toBe(50.0);
    } finally {
        cleanupCommittedSpousePercentFixture($pension->user_id);
    }
});

it('handles a null annual pension without writing a projection', function (): void {
    $pension = spousePercentFixture([
        'accrued_annual_pension' => null,
        'spouse_pension_percent' => 0.66,
    ]);

    try {
        runSpousePercentMigration();
        $pension->refresh();

        expect((float) $pension->spouse_pension_percent)->toBe(66.0)
            ->and($pension->spouse_pension_projected_gbp)->toBeNull();
    } finally {
        cleanupCommittedSpousePercentFixture($pension->user_id);
    }
});

it('records the convention on the column itself, where the next contributor will find it', function (): void {
    $comment = DB::selectOne(
        "SELECT COLUMN_COMMENT AS column_comment
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'db_pensions'
            AND COLUMN_NAME = 'spouse_pension_percent'"
    );

    expect($comment->column_comment)->toContain('Percentage points');
});

// Runs last on purpose. Every test above commits past RefreshDatabase's transaction
// the moment the migration's DDL executes, so what they wrote is theirs to remove by
// hand. Reported as identifiers rather than counts: when this fails during a
// full-suite run, the survivor's identity is the whole diagnosis, and a count would
// only say that something, somewhere, was left behind.
//
// `tax_configurations` is deliberately absent: the Pest.php safety-net row is created
// fresh for this test too, inside its own live transaction, so it is legitimately
// present here. Its committed twin is removed by the cleanup helper instead.
it('leaves nothing behind once the migration has committed past the transaction', function (): void {
    expect([
        'users' => User::withTrashed()->pluck('email')->all(),
        'db_pensions' => DBPension::withTrashed()->pluck('scheme_name')->all(),
        'point_awards' => PointAward::query()->pluck('dedup_key')->all(),
        'user_gamification' => UserGamification::query()->pluck('user_id')->all(),
    ])->toBe([
        'users' => [],
        'db_pensions' => [],
        'point_awards' => [],
        'user_gamification' => [],
    ]);
});
