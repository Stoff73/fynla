<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes optional risk profile fields nullable to support the simple
     * 5-level self-select risk profile flow (where only risk_level is required).
     */
    public function up(): void
    {
        // Use raw SQL for enum columns since Laravel doesn't handle enum->nullable well
        DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN risk_tolerance ENUM('cautious','balanced','adventurous') NULL");
        DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN capacity_for_loss_percent DECIMAL(5,2) NULL');
        DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN time_horizon_years INT NULL');
        DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN knowledge_level ENUM('novice','intermediate','experienced') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting may fail if there are NULL values in the columns
        DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN risk_tolerance ENUM('cautious','balanced','adventurous') NOT NULL");
        DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN capacity_for_loss_percent DECIMAL(5,2) NOT NULL');
        DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN time_horizon_years INT NOT NULL');
        DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN knowledge_level ENUM('novice','intermediate','experienced') NOT NULL");
    }
};
