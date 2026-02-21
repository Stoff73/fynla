<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need joint_owner_id foreign key constraints.
     */
    private array $tables = [
        'properties',
        'mortgages',
        'cash_accounts',
        'goals',
        'investment_accounts',
        'liabilities',
    ];

    /**
     * Check if a foreign key exists on a table.
     */
    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $database = config('database.connections.mysql.database');

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'joint_owner_id')) {
                $constraintName = $tableName.'_joint_owner_id_foreign';

                if (! $this->foreignKeyExists($tableName, $constraintName)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->unsignedBigInteger('joint_owner_id')->nullable()->change();
                        $table->foreign('joint_owner_id')
                            ->references('id')
                            ->on('users')
                            ->onDelete('set null');
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['joint_owner_id']);
                });
            }
        }
    }
};
