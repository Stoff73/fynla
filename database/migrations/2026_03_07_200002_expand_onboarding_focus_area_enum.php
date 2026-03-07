<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY onboarding_focus_area ENUM('estate','protection','retirement','investment','tax_optimisation','budgeting','family','business','goals') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE onboarding_progress MODIFY focus_area ENUM('estate','protection','retirement','investment','tax_optimisation','budgeting','family','business','goals') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY onboarding_focus_area ENUM('estate','protection','retirement','investment','tax_optimisation') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE onboarding_progress MODIFY focus_area ENUM('estate','protection','retirement','investment','tax_optimisation') NOT NULL");
    }
};
