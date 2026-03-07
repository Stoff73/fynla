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
            if (! Schema::hasColumn('retirement_profiles', 'care_cost_annual')) {
                $table->decimal('care_cost_annual', 10, 2)->nullable()->after('life_expectancy');
            }
            if (! Schema::hasColumn('retirement_profiles', 'care_start_age')) {
                $table->integer('care_start_age')->nullable()->after('care_cost_annual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('retirement_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('retirement_profiles', 'care_cost_annual')) {
                $table->dropColumn('care_cost_annual');
            }
            if (Schema::hasColumn('retirement_profiles', 'care_start_age')) {
                $table->dropColumn('care_start_age');
            }
        });
    }
};
