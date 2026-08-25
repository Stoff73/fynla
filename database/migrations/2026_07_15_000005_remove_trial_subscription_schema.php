<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the retired trial schema after the cross-environment audit gate.
 *
 * The down migration restores nullable compatibility fields only. It does
 * not and cannot reconstruct historical trial data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('subscriptions')->where('status', 'trialing')->exists()) {
            throw new RuntimeException(
                'Trial schema removal blocked: subscriptions.status still contains trialing rows.'
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','active','cancelled','expired','past_due') NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::dropIfExists('trial_reminder_log');
        $this->dropColumns('subscriptions', ['trial_started_at', 'trial_ends_at']);
        $this->dropColumns('users', ['trial_ends_at']);
        $this->dropColumns('subscription_plans', ['trial_days']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','trialing','active','cancelled','expired','past_due') NOT NULL DEFAULT 'pending'"
            );
        }

        if (! Schema::hasColumn('subscriptions', 'trial_started_at')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->timestamp('trial_started_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('subscriptions', 'trial_ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            });
        }

        if (! Schema::hasColumn('users', 'trial_ends_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dateTime('trial_ends_at')->nullable()->after('plan');
            });
        }

        if (! Schema::hasColumn('subscription_plans', 'trial_days')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->integer('trial_days')->nullable()->after('yearly_price');
            });
        }

        if (! Schema::hasTable('trial_reminder_log')) {
            Schema::create('trial_reminder_log', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('days_remaining');
                $table->timestamp('sent_at');
                $table->unique(['user_id', 'days_remaining']);
            });
        }
    }

    /** @param array<int, string> $columns */
    private function dropColumns(string $tableName, array $columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};
