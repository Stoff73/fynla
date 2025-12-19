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
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->boolean('already_receiving')->default(false)->after('state_pension_age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->dropColumn('already_receiving');
        });
    }
};
