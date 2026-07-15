<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_configurations', function (Blueprint $table) {
            $table->unsignedInteger('document_upload_allowance')->nullable()->default(null)->change();
            $table->unsignedInteger('snapshot_surfacing_window_days')->nullable()->default(null)->change();
        });

        DB::table('tier_configurations')->where('tier', 'premium')->update([
            'document_upload_allowance' => null,
            'snapshot_surfacing_window_days' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('tier_configurations')
            ->whereNull('document_upload_allowance')
            ->update(['document_upload_allowance' => 0]);
        DB::table('tier_configurations')
            ->whereNull('snapshot_surfacing_window_days')
            ->update(['snapshot_surfacing_window_days' => 2555]);

        Schema::table('tier_configurations', function (Blueprint $table) {
            $table->unsignedInteger('document_upload_allowance')->nullable(false)->default(0)->change();
            $table->unsignedInteger('snapshot_surfacing_window_days')->nullable(false)->default(90)->change();
        });
    }
};
