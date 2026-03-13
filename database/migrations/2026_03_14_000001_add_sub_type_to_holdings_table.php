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
        Schema::table('holdings', function (Blueprint $table) {
            $table->string('sub_type')->nullable()->after('asset_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('sub_type');
        });
    }
};
