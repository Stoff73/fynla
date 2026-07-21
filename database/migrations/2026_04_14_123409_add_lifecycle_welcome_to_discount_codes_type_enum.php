<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum ALTER — Laravel's schema builder doesn't support this directly.
        // Idempotent: checks current enum definition before altering.
        $current = DB::select("SHOW COLUMNS FROM discount_codes WHERE Field = 'type'");
        if (empty($current)) {
            return;
        }

        $columnType = $current[0]->Type;
        if (str_contains($columnType, "'lifecycle_welcome'")) {
            return;  // already added
        }

        DB::statement(
            'ALTER TABLE discount_codes MODIFY COLUMN type '
            ."ENUM('percentage', 'fixed_amount', 'trial_extension', 'lifecycle_welcome') NOT NULL"
        );
    }

    public function down(): void
    {
        // Downgrade: only drop the enum value if no rows use it
        $rowsUsingIt = DB::table('discount_codes')->where('type', 'lifecycle_welcome')->count();
        if ($rowsUsingIt > 0) {
            throw new RuntimeException(
                "Cannot rollback: {$rowsUsingIt} discount_codes rows use type='lifecycle_welcome'. Delete them first."
            );
        }

        DB::statement(
            'ALTER TABLE discount_codes MODIFY COLUMN type '
            ."ENUM('percentage', 'fixed_amount', 'trial_extension') NOT NULL"
        );
    }
};
