<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F14 — "never asked" and "nothing" were the same value: the twenty expenditure
 * category columns on users were NOT NULL DEFAULT 0.00. They become nullable
 * with no default; NULL means the category was never captured, 0.00 means the
 * user declared nothing. Rows where every category is 0.00 are backfilled to
 * NULL (a category-mode user with a real total cannot be all zeros).
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'childcare', 'children_activities', 'clothing_personal_care', 'entertainment_dining',
        'food_groceries', 'gifts_charity', 'healthcare_medical', 'holidays_travel', 'insurance',
        'internet_tv', 'mobile_phones', 'other_expenditure', 'pets', 'regular_savings',
        'school_extras', 'school_fees', 'school_lunches', 'subscriptions', 'transport_fuel',
        'university_fees',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::CATEGORIES as $column) {
                $table->decimal($column, 10, 2)->nullable()->default(null)->change();
            }
        });

        $allZero = implode(' AND ', array_map(fn (string $c): string => "`{$c}` = 0", self::CATEGORIES));
        $setNull = implode(', ', array_map(fn (string $c): string => "`{$c}` = NULL", self::CATEGORIES));
        DB::statement("UPDATE `users` SET {$setNull} WHERE {$allZero}");
    }

    public function down(): void
    {
        $setZero = implode(', ', array_map(fn (string $c): string => "`{$c}` = COALESCE(`{$c}`, 0)", self::CATEGORIES));
        DB::statement("UPDATE `users` SET {$setZero}");

        Schema::table('users', function (Blueprint $table): void {
            foreach (self::CATEGORIES as $column) {
                $table->decimal($column, 10, 2)->default(0)->change();
            }
        });
    }
};
