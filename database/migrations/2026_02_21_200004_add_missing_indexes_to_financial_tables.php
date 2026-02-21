<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need a trust_id index.
     */
    private array $trustIdTables = [
        'properties',
        'cash_accounts',
        'investment_accounts',
        'mortgages',
        'business_interests',
        'chattels',
    ];

    /**
     * Tables that need a beneficiary_id index.
     */
    private array $beneficiaryIdTables = [
        'dc_pensions',
        'savings_accounts',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // trust_id indexes
        foreach ($this->trustIdTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'trust_id')) {
                $indexName = "{$tableName}_trust_id_index";
                if (! $this->indexExists($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->index('trust_id');
                    });
                }
            }
        }

        // household_id index on properties
        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'household_id')) {
            $indexName = 'properties_household_id_index';
            if (! $this->indexExists('properties', $indexName)) {
                Schema::table('properties', function (Blueprint $table) {
                    $table->index('household_id');
                });
            }
        }

        // beneficiary_id indexes
        foreach ($this->beneficiaryIdTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'beneficiary_id')) {
                $indexName = "{$tableName}_beneficiary_id_index";
                if (! $this->indexExists($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->index('beneficiary_id');
                    });
                }
            }
        }

        // Composite index (user_id, account_type) on investment_accounts
        if (Schema::hasTable('investment_accounts')
            && Schema::hasColumn('investment_accounts', 'user_id')
            && Schema::hasColumn('investment_accounts', 'account_type')) {
            $indexName = 'investment_accounts_user_id_account_type_index';
            if (! $this->indexExists('investment_accounts', $indexName)) {
                Schema::table('investment_accounts', function (Blueprint $table) {
                    $table->index(['user_id', 'account_type'], 'investment_accounts_user_id_account_type_index');
                });
            }
        }

        // Composite index (user_id, account_type) on savings_accounts
        if (Schema::hasTable('savings_accounts')
            && Schema::hasColumn('savings_accounts', 'user_id')
            && Schema::hasColumn('savings_accounts', 'account_type')) {
            $indexName = 'savings_accounts_user_id_account_type_index';
            if (! $this->indexExists('savings_accounts', $indexName)) {
                Schema::table('savings_accounts', function (Blueprint $table) {
                    $table->index(['user_id', 'account_type'], 'savings_accounts_user_id_account_type_index');
                });
            }
        }

        // Composite index (user_id, account_type) on cash_accounts
        if (Schema::hasTable('cash_accounts')
            && Schema::hasColumn('cash_accounts', 'user_id')
            && Schema::hasColumn('cash_accounts', 'account_type')) {
            $indexName = 'cash_accounts_user_id_account_type_index';
            if (! $this->indexExists('cash_accounts', $indexName)) {
                Schema::table('cash_accounts', function (Blueprint $table) {
                    $table->index(['user_id', 'account_type'], 'cash_accounts_user_id_account_type_index');
                });
            }
        }

        // Composite index (user_id, property_type) on properties
        if (Schema::hasTable('properties')
            && Schema::hasColumn('properties', 'user_id')
            && Schema::hasColumn('properties', 'property_type')) {
            $indexName = 'properties_user_id_property_type_index';
            if (! $this->indexExists('properties', $indexName)) {
                Schema::table('properties', function (Blueprint $table) {
                    $table->index(['user_id', 'property_type'], 'properties_user_id_property_type_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trust_id indexes
        foreach ($this->trustIdTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $indexName = "{$tableName}_trust_id_index";
                if ($this->indexExists($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }

        // Drop household_id index on properties
        if (Schema::hasTable('properties')) {
            $indexName = 'properties_household_id_index';
            if ($this->indexExists('properties', $indexName)) {
                Schema::table('properties', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        // Drop beneficiary_id indexes
        foreach ($this->beneficiaryIdTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $indexName = "{$tableName}_beneficiary_id_index";
                if ($this->indexExists($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }

        // Drop composite indexes
        $compositeIndexes = [
            'investment_accounts' => 'investment_accounts_user_id_account_type_index',
            'savings_accounts' => 'savings_accounts_user_id_account_type_index',
            'cash_accounts' => 'cash_accounts_user_id_account_type_index',
            'properties' => 'properties_user_id_property_type_index',
        ];

        foreach ($compositeIndexes as $tableName => $indexName) {
            if (Schema::hasTable($tableName) && $this->indexExists($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
