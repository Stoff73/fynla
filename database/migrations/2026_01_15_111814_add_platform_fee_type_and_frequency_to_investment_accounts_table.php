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
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('platform_fee_percent')->comment('Fixed fee amount when fee type is fixed');
            $table->enum('platform_fee_type', ['percentage', 'fixed'])->default('percentage')->after('platform_fee_amount')->comment('Whether fee is percentage or fixed amount');
            $table->enum('platform_fee_frequency', ['monthly', 'quarterly', 'annually'])->default('annually')->after('platform_fee_type')->comment('How often the fee is charged');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_amount', 'platform_fee_type', 'platform_fee_frequency']);
        });
    }
};
