<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the user last answered the employer benefits question. Null means never
 * asked: `has_employer_pmi` is NOT NULL DEFAULT false, so without this column
 * "my employer gives me nothing" and "never asked" read the same, and the
 * "Record your employer benefits" card could never clear (CSJ 2026-09-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('protection_profiles', 'employer_benefits_recorded_at')) {
            return;
        }

        Schema::table('protection_profiles', function (Blueprint $table): void {
            $table->timestamp('employer_benefits_recorded_at')->nullable()->after('employer_name');
        });

        // A profile that already holds an employer benefit was answered at some
        // point (seeded personas): date it from the row's last update.
        DB::table('protection_profiles')
            ->where(fn ($q) => $q->whereNotNull('death_in_service_multiple')
                ->orWhereNotNull('group_ip_benefit_percent')
                ->orWhereNotNull('group_ci_amount')
                ->orWhere('has_employer_pmi', true))
            ->update(['employer_benefits_recorded_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('protection_profiles', 'employer_benefits_recorded_at')) {
            return;
        }

        Schema::table('protection_profiles', function (Blueprint $table): void {
            $table->dropColumn('employer_benefits_recorded_at');
        });
    }
};
