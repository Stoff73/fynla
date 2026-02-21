<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->boolean('has_flexibly_accessed')->default(false)->after('beneficiary_name');
            $table->date('flexible_access_date')->nullable()->after('has_flexibly_accessed');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn(['has_flexibly_accessed', 'flexible_access_date']);
        });
    }
};
