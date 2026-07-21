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
            $table->boolean('salary_sacrifice')
                ->nullable()
                ->after('employer_matching_limit')
                ->comment('true if pension contributions are made via salary sacrifice');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn('salary_sacrifice');
        });
    }
};
