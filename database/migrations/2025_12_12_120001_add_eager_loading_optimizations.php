<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eager Loading and Query Optimization Migration
 *
 * This migration adds columns that help with eager loading optimization
 * and reduces the need for subqueries in common operations.
 *
 * Optimizations:
 * 1. Add counts cache columns for parent tables (denormalization for performance)
 * 2. Add computed columns for frequently calculated values
 *
 * Note: These are optional optimizations. The application will work without them,
 * but they can significantly reduce query complexity for dashboards.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================================
        // SECTION 1: Add counts cache to users table
        // These can be updated via model events or scheduled jobs
        // ============================================================

        if (! Schema::hasColumn('users', 'properties_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('properties_count')->default(0)->after('household_id');
                $table->unsignedInteger('investment_accounts_count')->default(0)->after('properties_count');
                $table->unsignedInteger('savings_accounts_count')->default(0)->after('investment_accounts_count');
                $table->unsignedInteger('dc_pensions_count')->default(0)->after('savings_accounts_count');
                $table->unsignedInteger('db_pensions_count')->default(0)->after('dc_pensions_count');
            });
        }

        // ============================================================
        // SECTION 2: Add total_holdings_value to investment_accounts
        // This is recalculated when holdings change
        // ============================================================

        if (! Schema::hasColumn('investment_accounts', 'total_holdings_value')) {
            Schema::table('investment_accounts', function (Blueprint $table) {
                $table->decimal('total_holdings_value', 15, 2)->nullable()->after('current_value');
            });
        }

        // ============================================================
        // SECTION 3: Add mortgage_count to properties
        // Useful for quick checks without loading mortgages
        // ============================================================

        if (! Schema::hasColumn('properties', 'mortgages_count')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->unsignedTinyInteger('mortgages_count')->default(0)->after('outstanding_mortgage');
            });
        }

        // ============================================================
        // SECTION 4: Add total_mortgage_balance to properties
        // Computed field for quick equity calculations
        // ============================================================

        if (! Schema::hasColumn('properties', 'total_mortgage_balance')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->decimal('total_mortgage_balance', 15, 2)->default(0)->after('mortgages_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove from users table
        Schema::table('users', function (Blueprint $table) {
            $columns = ['properties_count', 'investment_accounts_count', 'savings_accounts_count', 'dc_pensions_count', 'db_pensions_count'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Remove from investment_accounts
        if (Schema::hasColumn('investment_accounts', 'total_holdings_value')) {
            Schema::table('investment_accounts', function (Blueprint $table) {
                $table->dropColumn('total_holdings_value');
            });
        }

        // Remove from properties
        Schema::table('properties', function (Blueprint $table) {
            $columns = ['mortgages_count', 'total_mortgage_balance'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
