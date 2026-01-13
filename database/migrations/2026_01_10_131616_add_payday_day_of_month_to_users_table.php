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
        Schema::table('users', function (Blueprint $table) {
            // Day of month when user receives their salary (1-31)
            // 31 means last day of month, actual date calculated at display time
            $table->unsignedTinyInteger('payday_day_of_month')->nullable()->after('annual_other_income');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payday_day_of_month');
        });
    }
};
