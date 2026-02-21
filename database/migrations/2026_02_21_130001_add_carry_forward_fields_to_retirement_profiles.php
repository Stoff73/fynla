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
            $table->json('prior_year_unused_allowance')->nullable()->after('life_expectancy');
        });
    }

    public function down(): void
    {
        Schema::table('retirement_profiles', function (Blueprint $table) {
            $table->dropColumn('prior_year_unused_allowance');
        });
    }
};
