<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the orphan users.ai_chat_enabled column.
 *
 * Per the consent-no-toggle rule (memory feedback_ai_chat_consent_no_toggle.md),
 * AI chat consent is granted at registration via the privacy policy. There is no
 * UI toggle and the column is unused — the 2026-05-23 tech-debt audit confirmed
 * zero references across app/, resources/, tests/. The only writer was a single
 * seeder line in ChrisUserSeeder which is removed in the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'ai_chat_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_chat_enabled');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'ai_chat_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ai_chat_enabled')->default(true);
        });
    }
};
