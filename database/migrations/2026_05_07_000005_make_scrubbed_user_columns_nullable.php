<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow RetentionPurgeService::purgeUser() to scrub these columns to NULL.
 *
 * RetentionPurgeService (Phase 3) sets first_name and annual_interest_income
 * to NULL during PII scrub. The original schema had them as NOT NULL, which
 * caused 1048 integrity violations during retention purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable()->change();
            $table->decimal('annual_interest_income', 12, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable(false)->change();
            $table->decimal('annual_interest_income', 12, 2)->default(0)->nullable(false)->change();
        });
    }
};
