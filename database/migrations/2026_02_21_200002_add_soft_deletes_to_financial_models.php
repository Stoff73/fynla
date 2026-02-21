<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need soft deletes added.
     */
    private array $tables = [
        'properties',
        'mortgages',
        'cash_accounts',
        'investment_accounts',
        'holdings',
        'dc_pensions',
        'db_pensions',
        'life_insurance_policies',
        'critical_illness_policies',
        'disability_policies',
        'income_protection_policies',
        'sickness_illness_policies',
        'wills',
        'bequests',
        'assets',
        'liabilities',
        'gifts',
        'iht_calculations',
        'retirement_profiles',
        'expenditure_profiles',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
