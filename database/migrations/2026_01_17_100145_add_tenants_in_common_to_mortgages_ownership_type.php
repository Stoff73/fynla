<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to include tenants_in_common
        DB::statement("ALTER TABLE mortgages MODIFY COLUMN ownership_type ENUM('individual', 'joint', 'tenants_in_common', 'trust') NOT NULL DEFAULT 'individual'");
    }

    public function down(): void
    {
        // Note: This will fail if any rows have 'tenants_in_common' value
        DB::statement("ALTER TABLE mortgages MODIFY COLUMN ownership_type ENUM('individual', 'joint', 'trust') NOT NULL DEFAULT 'individual'");
    }
};
