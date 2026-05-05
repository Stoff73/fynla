<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen enum to include the old value plus every new value so
        // we can migrate data without violating the constraint.
        DB::statement(
            'ALTER TABLE insight_articles MODIFY category ENUM('
            ."'tax-changes','tax','pensions','savings-isa','estate-planning',"
            ."'financial-planning','ai','fintech','developer','international','platform-updates'"
            .') NOT NULL'
        );

        // Step 2: rename the legacy slug.
        DB::table('insight_articles')
            ->where('category', 'tax-changes')
            ->update(['category' => 'tax']);

        // Step 3: drop the legacy value.
        DB::statement(
            'ALTER TABLE insight_articles MODIFY category ENUM('
            ."'tax','pensions','savings-isa','estate-planning',"
            ."'financial-planning','ai','fintech','developer','international','platform-updates'"
            .') NOT NULL'
        );
    }

    public function down(): void
    {
        // Restore the legacy slug so the enum rollback passes.
        DB::statement(
            'ALTER TABLE insight_articles MODIFY category ENUM('
            ."'tax-changes','tax','pensions','savings-isa','estate-planning',"
            ."'financial-planning','ai','fintech','developer','international','platform-updates'"
            .') NOT NULL'
        );

        DB::table('insight_articles')
            ->where('category', 'tax')
            ->update(['category' => 'tax-changes']);

        // Collapse any rows on the new-only categories back to a safe default.
        DB::table('insight_articles')
            ->whereIn('category', ['financial-planning', 'ai', 'fintech', 'developer', 'international'])
            ->update(['category' => 'platform-updates']);

        DB::statement(
            'ALTER TABLE insight_articles MODIFY category ENUM('
            ."'tax-changes','pensions','savings-isa','estate-planning','platform-updates'"
            .') NOT NULL'
        );
    }
};
