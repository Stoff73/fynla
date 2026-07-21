<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->decimal('outstanding_balance_gbp', 15, 2)->nullable()->after('outstanding_balance');
            $table->decimal('monthly_payment_gbp', 15, 2)->nullable()->after('monthly_payment');
            $table->decimal('current_ltv_pct', 8, 4)->nullable()->after('monthly_payment_gbp');
            $table->timestamp('outstanding_balance_gbp_calculated_at')->nullable();
            $table->timestamp('monthly_payment_gbp_calculated_at')->nullable();
            $table->timestamp('current_ltv_pct_calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropColumn([
                'outstanding_balance_gbp',
                'monthly_payment_gbp',
                'current_ltv_pct',
                'outstanding_balance_gbp_calculated_at',
                'monthly_payment_gbp_calculated_at',
                'current_ltv_pct_calculated_at',
            ]);
        });
    }
};
