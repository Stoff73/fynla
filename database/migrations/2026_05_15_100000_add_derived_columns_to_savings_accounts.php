<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->decimal('balance_gbp', 12, 2)->nullable()->after('current_balance');
            $table->timestamp('balance_gbp_calculated_at')->nullable()->after('balance_gbp');

            $table->decimal('annual_interest_projected_gbp', 12, 2)->nullable()->after('interest_rate');
            $table->timestamp('annual_interest_projected_gbp_calculated_at')->nullable()->after('annual_interest_projected_gbp');

            $table->decimal('isa_allowance_used_pct', 5, 2)->nullable()->after('isa_subscription_amount');
            $table->timestamp('isa_allowance_used_pct_calculated_at')->nullable()->after('isa_allowance_used_pct');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'balance_gbp', 'balance_gbp_calculated_at',
                'annual_interest_projected_gbp', 'annual_interest_projected_gbp_calculated_at',
                'isa_allowance_used_pct', 'isa_allowance_used_pct_calculated_at',
            ]);
        });
    }
};
