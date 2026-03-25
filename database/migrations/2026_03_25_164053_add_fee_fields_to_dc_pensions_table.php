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
        Schema::table('dc_pensions', function (Blueprint $table) {
            if (! Schema::hasColumn('dc_pensions', 'platform_fee_type')) {
                $table->string('platform_fee_type', 20)->default('percentage')->after('platform_fee_percent');
            }
            if (! Schema::hasColumn('dc_pensions', 'platform_fee_amount')) {
                $table->decimal('platform_fee_amount', 15, 2)->nullable()->after('platform_fee_type');
            }
            if (! Schema::hasColumn('dc_pensions', 'platform_fee_frequency')) {
                $table->string('platform_fee_frequency', 20)->default('annually')->after('platform_fee_amount');
            }
            if (! Schema::hasColumn('dc_pensions', 'advisor_fee_percent')) {
                $table->decimal('advisor_fee_percent', 5, 4)->nullable()->after('platform_fee_frequency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_type', 'platform_fee_amount', 'platform_fee_frequency', 'advisor_fee_percent']);
        });
    }
};
