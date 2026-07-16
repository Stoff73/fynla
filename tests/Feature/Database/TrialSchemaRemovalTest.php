<?php

declare(strict_types=1);

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
        DB::table('subscriptions')->where('user_id', $user->id)->delete();
        $migration->up();
    }
});
