<?php

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
            // Bond-specific fields for onshore and offshore bonds
            $table->date('bond_purchase_date')->nullable()->after('include_in_retirement');
            $table->decimal('bond_withdrawal_taken', 12, 2)->nullable()->after('bond_purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropColumn(['bond_purchase_date', 'bond_withdrawal_taken']);
        });
    }
};
