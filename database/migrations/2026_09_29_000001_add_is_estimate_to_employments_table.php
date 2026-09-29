<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job someone else told us about. When a spouse's account links, the salary
 * the inviter gave for them is copied across as a job (SpouseHoldingTransfer).
 * That figure is the inviter's estimate: the first job the spouse states
 * themselves replaces it rather than adding to it (production live test
 * 2026-09-29, defect C1 — a £32,000 salary was summed with the same £32,000
 * and taxed as £64,000). Every existing row is a job the user stated, so the
 * default is false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employments', 'is_estimate')) {
            return;
        }

        Schema::table('employments', function (Blueprint $table): void {
            $table->boolean('is_estimate')->default(false)->after('income_type');
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table): void {
            $table->dropColumn('is_estimate');
        });
    }
};
