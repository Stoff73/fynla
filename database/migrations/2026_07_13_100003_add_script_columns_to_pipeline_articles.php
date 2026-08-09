<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('pipeline_articles', 'script_model')) {
                $table->string('script_model')->nullable()->after('tracking_sheet_row_id');
            }
            if (! Schema::hasColumn('pipeline_articles', 'script_prompt_version')) {
                $table->string('script_prompt_version', 32)->nullable()->after('script_model');
            }
            if (! Schema::hasColumn('pipeline_articles', 'script_cost_gbp')) {
                $table->decimal('script_cost_gbp', 8, 4)->nullable()->after('script_prompt_version');
            }
            if (! Schema::hasColumn('pipeline_articles', 'script_generated_at')) {
                $table->timestamp('script_generated_at')->nullable()->after('script_cost_gbp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_articles', function (Blueprint $table) {
            $table->dropColumn([
                'script_model',
                'script_prompt_version',
                'script_cost_gbp',
                'script_generated_at',
            ]);
        });
    }
};
