<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-env sync (Stage 5) inserts InsightArticle rows on dev/prod that
 * come from another environment. The source user_id doesn't map — so
 * author_id must be nullable on the receiving side.
 *
 * Existing local rows keep their author_id; new sync-inbound rows can be
 * null and are attributed to "The Fynla Team" by the frontend byline logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insight_articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('insight_articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable(false)->change();
        });
    }
};
