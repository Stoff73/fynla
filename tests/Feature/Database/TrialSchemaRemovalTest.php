<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('removes the retired trial schema and narrows subscription status', function () {
    expect(Schema::hasColumn('subscriptions', 'trial_started_at'))->toBeFalse()
        ->and(Schema::hasColumn('subscriptions', 'trial_ends_at'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'trial_ends_at'))->toBeFalse()
        ->and(Schema::hasColumn('subscription_plans', 'trial_days'))->toBeFalse()
        ->and(Schema::hasTable('trial_reminder_log'))->toBeFalse();

    if (DB::getDriverName() === 'mysql') {
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'subscriptions', 'status']
        );

        expect(strtolower($column->COLUMN_TYPE ?? ''))->toBe(
            "enum('pending','active','cancelled','expired','past_due')"
        );
    }
});

it('keeps the checked-in schema snapshot aligned with the removed trial schema', function () {
    $schema = (string) file_get_contents(database_path('schema/mysql-schema.sql'));

    expect($schema)->not->toContain('CREATE TABLE `trial_reminder_log`')
        ->not->toContain('`trial_started_at` timestamp')
        ->not->toContain('`trial_ends_at` timestamp')
        ->not->toContain("`status` enum('trialing','active','cancelled','expired','past_due')")
        ->toContain("`status` enum('pending','active','cancelled','expired','past_due')")
        ->toContain("`tier` enum('free','premium')");
});

it('aborts before destructive schema changes while a trialing row remains', function () {
    $path = database_path('migrations/2026_07_15_000005_remove_trial_subscription_schema.php');
    expect(is_file($path))->toBeTrue();

    /** @var Migration $migration */
    $migration = require $path;
    $migration->down();

    $user = User::factory()->create();

    try {
        DB::table('subscriptions')->insert([
            'user_id' => $user->id,
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
            'status' => 'trialing',
            'amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, 'Trial schema removal blocked');

        expect(Schema::hasColumn('subscriptions', 'trial_started_at'))->toBeTrue()
            ->and(Schema::hasColumn('subscriptions', 'trial_ends_at'))->toBeTrue()
            ->and(Schema::hasColumn('users', 'trial_ends_at'))->toBeTrue()
            ->and(Schema::hasColumn('subscription_plans', 'trial_days'))->toBeTrue()
            ->and(Schema::hasTable('trial_reminder_log'))->toBeTrue();
    } finally {
        // $migration->down() above ran DDL, and MySQL implicitly commits on DDL — so
        // RefreshDatabase's transaction is already gone and its rollback will do
        // nothing. Everything this test wrote has to be removed by hand, including
        // the user, which soft-deletes and would otherwise survive a ->delete().
        DB::table('subscriptions')->where('user_id', $user->id)->delete();
        DB::table('users')->where('id', $user->id)->delete();
        TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
        $migration->up();
    }
});

// Runs last on purpose: proves the cleanup above still covers everything the
// committed test wrote. Reported as rows rather than counts, because when this
// fails the survivor's identity is the whole diagnosis.
it('leaves no committed rows behind after the migration test', function (): void {
    // Not tax_configurations: the global beforeEach creates a safety-net row for every
    // test, including this one, inside its own transaction.
    expect([
        'users' => DB::table('users')->pluck('email')->all(),
        'subscriptions' => DB::table('subscriptions')->pluck('id')->all(),
    ])->toBe(['users' => [], 'subscriptions' => []]);
});
