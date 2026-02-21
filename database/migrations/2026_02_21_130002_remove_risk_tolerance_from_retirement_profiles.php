<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retirement_profiles', function (Blueprint $table) {
            $table->dropColumn('risk_tolerance');
        });
    }

    public function down(): void
    {
        Schema::table('retirement_profiles', function (Blueprint $table) {
            $table->string('risk_tolerance')->nullable()->after('spouse_life_expectancy');
        });
    }
};
