<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W-0049: cookie-banner consent has to be recorded before an account exists —
 * the visitor who accepts or declines on the landing page has no user row, and
 * most never get one.
 *
 * Rather than stand up a second consent store beside user_consents (Rule 20),
 * the existing table gains an anonymous subject: user_id becomes nullable and
 * a random per-browser subject_token identifies the visitor. On registration
 * the anonymous row is claimed onto the new user_id and the token is cleared,
 * so a consent given before sign-up still shows in that user's consent history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_consents', function (Blueprint $table) {
            $table->char('subject_token', 64)->nullable()->after('user_id');
            $table->unique(
                ['subject_token', 'consent_type', 'version'],
                'user_consents_subject_type_version_unique'
            );
        });

        // The foreign key stays in place — MySQL permits NULL in a constrained
        // column. Done as raw SQL because ->change() on a constrained column
        // needs doctrine/dbal to round-trip the FK and drops it on some
        // MySQL 8 builds.
        DB::statement('ALTER TABLE `user_consents` MODIFY `user_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Anonymous rows cannot survive a NOT NULL user_id.
        DB::table('user_consents')->whereNull('user_id')->delete();

        DB::statement('ALTER TABLE `user_consents` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('user_consents', function (Blueprint $table) {
            $table->dropUnique('user_consents_subject_type_version_unique');
            $table->dropColumn('subject_token');
        });
    }
};
