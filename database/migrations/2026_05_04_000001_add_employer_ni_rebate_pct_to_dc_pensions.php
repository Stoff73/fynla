<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('dc_pensions', 'employer_ni_rebate_pct')) {
            return;
        }

        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->decimal('employer_ni_rebate_pct', 5, 4)
                ->nullable()
                ->after('salary_sacrifice')
                ->comment('Share of employer NI saving rebated back into the pension when salary sacrifice is in use (0.0000-1.0000).');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn('employer_ni_rebate_pct');
        });
    }
};
