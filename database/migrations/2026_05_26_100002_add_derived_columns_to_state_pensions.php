<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->decimal('state_pension_forecast_annual_gbp', 12, 2)->nullable()->after('state_pension_forecast_annual');
            $table->timestamp('state_pension_forecast_annual_gbp_calculated_at')->nullable()->after('state_pension_forecast_annual_gbp');

            $table->decimal('ni_completion_pct', 5, 2)->nullable()->after('ni_years_required');
            $table->timestamp('ni_completion_pct_calculated_at')->nullable()->after('ni_completion_pct');

            $table->integer('years_to_state_pension_age')->nullable()->after('state_pension_age');
            $table->timestamp('years_to_state_pension_age_calculated_at')->nullable()->after('years_to_state_pension_age');
        });
    }

    public function down(): void
    {
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'state_pension_forecast_annual_gbp', 'state_pension_forecast_annual_gbp_calculated_at',
                'ni_completion_pct', 'ni_completion_pct_calculated_at',
                'years_to_state_pension_age', 'years_to_state_pension_age_calculated_at',
            ]);
        });
    }
};
