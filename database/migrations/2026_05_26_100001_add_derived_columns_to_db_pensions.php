<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->decimal('projected_annual_pension_at_nra_gbp', 14, 2)->nullable()->after('accrued_annual_pension');
            $table->timestamp('projected_annual_pension_at_nra_gbp_calculated_at')->nullable()->after('projected_annual_pension_at_nra_gbp');

            $table->decimal('spouse_pension_projected_gbp', 14, 2)->nullable()->after('spouse_pension_percent');
            $table->timestamp('spouse_pension_projected_gbp_calculated_at')->nullable()->after('spouse_pension_projected_gbp');
        });
    }

    public function down(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'projected_annual_pension_at_nra_gbp', 'projected_annual_pension_at_nra_gbp_calculated_at',
                'spouse_pension_projected_gbp', 'spouse_pension_projected_gbp_calculated_at',
            ]);
        });
    }
};
