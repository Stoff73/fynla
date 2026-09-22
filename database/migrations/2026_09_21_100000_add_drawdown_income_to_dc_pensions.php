<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            // Null means the user has not been asked. Never default to 0: a zero
            // is a stated fact ("I draw nothing") and a null is an open question.
            $table->decimal('annual_drawdown_income', 14, 2)->nullable()->after('flexible_access_date');
            $table->decimal('pcls_taken', 14, 2)->nullable()->after('annual_drawdown_income');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn(['annual_drawdown_income', 'pcls_taken']);
        });
    }
};
