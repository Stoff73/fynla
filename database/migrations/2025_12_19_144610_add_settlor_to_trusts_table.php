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
        Schema::table('trusts', function (Blueprint $table) {
            $table->string('settlor')->nullable()->after('trustees')->comment('Person who created the trust');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusts', function (Blueprint $table) {
            $table->dropColumn('settlor');
        });
    }
};
