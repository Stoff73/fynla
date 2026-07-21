<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regression test for audit S-01 (May/May12Updates/review-database.md).
 * The 19 expenditure / aggregate columns on `users` were originally
 * `double` and migration 2026_05_12_000001 converts them to `decimal`.
 * This test pins the storage type so a future migration (or schema
 * dump regeneration) can't silently flip them back to float.
 */
$expenditureColumns = [
    'monthly_expenditure',
    'annual_expenditure',
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

describe('users expenditure columns are decimal, not double', function () use ($expenditureColumns) {
    foreach ($expenditureColumns as $col) {
        it("stores `{$col}` as decimal", function () use ($col) {
            // information_schema is safer than `SHOW COLUMNS LIKE ?` —
            // SHOW COLUMNS does not support bound parameters for the LIKE
            // pattern, only Laravel-style interpolated strings.
            $database = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$database, 'users', $col]
            );

            expect($row)->not->toBeNull("Column users.{$col} not found in information_schema");
            expect($row->COLUMN_TYPE)->toStartWith('decimal(');
        });
    }
});
