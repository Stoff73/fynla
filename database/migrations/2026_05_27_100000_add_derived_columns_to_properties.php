<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('current_value_gbp', 15, 2)->nullable()->after('current_value');
            $table->timestamp('current_value_gbp_calculated_at')->nullable()->after('current_value_gbp');

            $table->decimal('equity_gbp', 15, 2)->nullable()->after('current_value_gbp_calculated_at');
            $table->timestamp('equity_gbp_calculated_at')->nullable()->after('equity_gbp');

            $table->decimal('loan_to_value_pct', 5, 2)->nullable()->after('equity_gbp_calculated_at');
            $table->timestamp('loan_to_value_pct_calculated_at')->nullable()->after('loan_to_value_pct');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'current_value_gbp', 'current_value_gbp_calculated_at',
                'equity_gbp', 'equity_gbp_calculated_at',
                'loan_to_value_pct', 'loan_to_value_pct_calculated_at',
            ]);
        });
    }
};
