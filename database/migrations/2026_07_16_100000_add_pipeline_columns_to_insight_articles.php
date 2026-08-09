<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 5 extensions to insight_articles:
 * - Campaign linking (optional foreign key to pipeline_campaigns)
 * - Word-doc provenance (which Drive file created this article, when, with what content hash)
 * - Cross-env sync state (when this article was pushed to dev / prod, by whom)
 *
 * All new columns are nullable — existing articles are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insight_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('insight_articles', 'pipeline_campaign_id')) {
                $table->foreignId('pipeline_campaign_id')
                    ->nullable()
                    ->after('bespoke_component')
                    ->constrained('pipeline_campaigns')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('insight_articles', 'source_docx_drive_file_id')) {
                $table->string('source_docx_drive_file_id')->nullable()->after('pipeline_campaign_id');
            }
            if (! Schema::hasColumn('insight_articles', 'source_docx_drive_url')) {
                $table->string('source_docx_drive_url')->nullable()->after('source_docx_drive_file_id');
            }
            if (! Schema::hasColumn('insight_articles', 'source_docx_hash')) {
                $table->string('source_docx_hash', 64)->nullable()->after('source_docx_drive_url');
            }
            if (! Schema::hasColumn('insight_articles', 'source_docx_imported_at')) {
                $table->timestamp('source_docx_imported_at')->nullable()->after('source_docx_hash');
            }

            if (! Schema::hasColumn('insight_articles', 'dev_synced_at')) {
                $table->timestamp('dev_synced_at')->nullable()->after('source_docx_imported_at');
            }
            if (! Schema::hasColumn('insight_articles', 'prod_synced_at')) {
                $table->timestamp('prod_synced_at')->nullable()->after('dev_synced_at');
            }
            if (! Schema::hasColumn('insight_articles', 'prod_pushed_by')) {
                $table->foreignId('prod_pushed_by')
                    ->nullable()
                    ->after('prod_synced_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index('source_docx_drive_file_id');
            $table->index('pipeline_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('insight_articles', function (Blueprint $table) {
            $table->dropForeign(['pipeline_campaign_id']);
            $table->dropForeign(['prod_pushed_by']);
            $table->dropColumn([
                'pipeline_campaign_id',
                'source_docx_drive_file_id',
                'source_docx_drive_url',
                'source_docx_hash',
                'source_docx_imported_at',
                'dev_synced_at',
                'prod_synced_at',
                'prod_pushed_by',
            ]);
        });
    }
};
