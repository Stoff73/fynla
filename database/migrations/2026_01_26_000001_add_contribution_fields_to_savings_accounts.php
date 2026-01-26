<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            // ISA regular contribution fields (to track planned ISA usage)
            $table->decimal('regular_contribution_amount', 12, 2)->nullable()->after('isa_subscription_amount');
            $table->string('contribution_frequency')->nullable()->after('regular_contribution_amount');
            $table->decimal('planned_lump_sum_amount', 12, 2)->nullable()->after('contribution_frequency');
            $table->date('planned_lump_sum_date')->nullable()->after('planned_lump_sum_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'regular_contribution_amount',
                'contribution_frequency',
                'planned_lump_sum_amount',
                'planned_lump_sum_date',
            ]);
        });
    }
};
