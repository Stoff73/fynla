<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->decimal('projected_cash', 15, 2)->nullable()->after('projected_iht_liability');
            $table->decimal('projected_investments', 15, 2)->nullable()->after('projected_cash');
            $table->decimal('projected_properties', 15, 2)->nullable()->after('projected_investments');
            $table->unsignedSmallInteger('retirement_age')->nullable()->after('projected_properties');
        });
    }

    public function down(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->dropColumn(['projected_cash', 'projected_investments', 'projected_properties', 'retirement_age']);
        });
    }
};
