<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'life_expectancy_override')) {
                $table->integer('life_expectancy_override')->nullable()->after('date_of_birth');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'life_expectancy_override')) {
                $table->dropColumn('life_expectancy_override');
            }
        });
    }
};
