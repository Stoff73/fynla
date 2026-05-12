<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert remaining `double` expenditure / income columns on `users` to
 * `decimal`. Float arithmetic in financial calculations is the cardinal
 * sin of fintech databases — `WHERE annual_expenditure = 12345.67`
 * against a `double` column will sometimes miss matches, and SUM(...)
 * computed in MySQL drifts from PHP-computed sums.
 *
 * The User model already casts these to `decimal:2` (see
 * app/Models/User.php), so no PHP-layer change is required — this
 * aligns the storage layer with the application contract.
 *
 * Audit reference: May/May12Updates/review-database.md §S-01.
 *
 * Production rollout note: this is an `ALTER TABLE` on the largest
 * user-facing table. Use `pt-online-schema-change` or `ALGORITHM=INPLACE`
 * if running on a heavily-loaded DB. `double → decimal` may force a
 * COPY algorithm holding a metadata lock — verify with EXPLAIN first.
 */
return new class extends Migration
{
    /**
     * Aggregate columns: nullable, allow up to ~999bn (precision 12).
     */
    private const AGGREGATE_COLUMNS = [
        'monthly_expenditure',
        'annual_expenditure',
    ];

    /**
     * Per-category items: NOT NULL DEFAULT 0, max realistic value
     * < £100k/yr (precision 10).
     */
    private const CATEGORY_COLUMNS = [
        'food_groceries',
        'transport_fuel',
        'healthcare_medical',
        'insurance',
        'mobile_phones',
        'internet_tv',
        'subscriptions',
        'clothing_personal_care',
        'entertainment_dining',
        'holidays_travel',
        'pets',
        'childcare',
        'school_fees',
        'children_activities',
        'gifts_charity',
        'regular_savings',
        'other_expenditure',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::AGGREGATE_COLUMNS as $col) {
                $table->decimal($col, 12, 2)->nullable()->change();
            }
            foreach (self::CATEGORY_COLUMNS as $col) {
                $table->decimal($col, 10, 2)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::AGGREGATE_COLUMNS as $col) {
                $table->double($col)->nullable()->change();
            }
            foreach (self::CATEGORY_COLUMNS as $col) {
                $table->double($col)->default(0)->change();
            }
        });
    }
};
