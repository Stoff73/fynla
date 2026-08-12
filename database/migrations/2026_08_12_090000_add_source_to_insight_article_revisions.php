<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an automated write record a revision.
 *
 * Revisions were only ever written when a logged-in person saved an article, so
 * the Google Drive crawl — which runs from a console command with no actor —
 * silently recorded nothing. That is precisely the change an editor would want
 * to undo, since a re-import overwrites the article body.
 *
 * saved_by therefore becomes nullable (an import has no person behind it) and a
 * source column records what caused the revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insight_article_revisions', function (Blueprint $table) {
            $table->string('source', 32)->default('cms')->after('body_blocks');
        });

        // The foreign key tolerates NULL — it only constrains values that are
        // present — so the column can be relaxed without dropping it. Done in
        // raw SQL because ->change() would need doctrine/dbal, which is not
        // installed.
        DB::statement('ALTER TABLE insight_article_revisions MODIFY saved_by BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Attributed rows have to go before the column can be required again.
        DB::table('insight_article_revisions')->whereNull('saved_by')->delete();
        DB::statement('ALTER TABLE insight_article_revisions MODIFY saved_by BIGINT UNSIGNED NOT NULL');

        Schema::table('insight_article_revisions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
