<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'iht_profiles',
            'retirement_profiles',
            'risk_profiles',
            'state_pensions',
            'letters_to_spouse',
            'expenditure_profiles',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $indexName = $tableName.'_user_id_unique';
                // Check if unique index already exists by trying to add it
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->unique('user_id', $indexName);
                    });
                } catch (\Exception $e) {
                    // Index already exists, skip
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'iht_profiles',
            'retirement_profiles',
            'risk_profiles',
            'state_pensions',
            'letters_to_spouse',
            'expenditure_profiles',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $indexName = $tableName.'_user_id_unique';
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropUnique($indexName);
                    });
                } catch (\Exception $e) {
                    // Index doesn't exist, skip
                }
            }
        }
    }
};
