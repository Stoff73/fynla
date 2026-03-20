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
            if (! Schema::hasColumn('users', 'retired_budget_overrides')) {
                $table->json('retired_budget_overrides')->nullable()->after('annual_expenditure');
            }
            if (! Schema::hasColumn('users', 'widowed_budget_overrides')) {
                $table->json('widowed_budget_overrides')->nullable()->after('retired_budget_overrides');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['retired_budget_overrides', 'widowed_budget_overrides']);
        });
    }
};
