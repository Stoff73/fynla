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
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->decimal('employer_matching_limit', 5, 2)->nullable()->after('employer_contribution_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn('employer_matching_limit');
        });
    }
};
