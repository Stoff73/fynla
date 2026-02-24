<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('linked_investment_account_id')
                ->nullable()
                ->after('linked_savings_account_id')
                ->constrained('investment_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['linked_investment_account_id']);
            $table->dropColumn('linked_investment_account_id');
        });
    }
};
