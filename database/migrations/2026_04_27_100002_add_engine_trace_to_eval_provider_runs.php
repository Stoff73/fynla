<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eval_provider_runs', function (Blueprint $table): void {
            $table->json('engine_trace')->nullable()->after('end_state_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('eval_provider_runs', function (Blueprint $table): void {
            $table->dropColumn('engine_trace');
        });
    }
};
