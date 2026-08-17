<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->json('look_through_allocation')->nullable()->after('sub_type');
            $table->string('look_through_source')->nullable()->after('look_through_allocation');
            $table->date('look_through_effective_at')->nullable()->after('look_through_source');
        });

        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->json('entered_allocation_baseline')->nullable()->after('rebalance_threshold_percent');
            $table->string('entered_allocation_source')->nullable()->after('entered_allocation_baseline');
            $table->date('entered_allocation_effective_at')->nullable()->after('entered_allocation_source');
        });

        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->json('entered_allocation_baseline')->nullable()->after('investment_strategy');
            $table->string('entered_allocation_source')->nullable()->after('entered_allocation_baseline');
            $table->date('entered_allocation_effective_at')->nullable()->after('entered_allocation_source');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn([
                'look_through_allocation',
                'look_through_source',
                'look_through_effective_at',
            ]);
        });

        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'entered_allocation_baseline',
                'entered_allocation_source',
                'entered_allocation_effective_at',
            ]);
        });

        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'entered_allocation_baseline',
                'entered_allocation_source',
                'entered_allocation_effective_at',
            ]);
        });
    }
};
