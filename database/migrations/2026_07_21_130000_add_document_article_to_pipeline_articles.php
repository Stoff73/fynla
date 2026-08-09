<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let the marketing pipeline source articles from the DocumentArticle CMS
 * (dev's browser-upload CMS) as well as native InsightArticles. A pipeline
 * article now references exactly one of the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipeline_articles')) {
            return;
        }

        if (! Schema::hasColumn('pipeline_articles', 'document_article_id')) {
            Schema::table('pipeline_articles', function (Blueprint $table) {
                $table->foreignId('document_article_id')
                    ->nullable()
                    ->after('insight_article_id')
                    ->constrained('document_articles')
                    ->nullOnDelete();
            });
        }

        // insight_article_id must become nullable so a document-sourced row can
        // omit it. Raw statement avoids a doctrine/dbal dependency for ->change().
        DB::statement('ALTER TABLE pipeline_articles MODIFY insight_article_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('pipeline_articles', 'document_article_id')) {
            Schema::table('pipeline_articles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('document_article_id');
            });
        }
    }
};
