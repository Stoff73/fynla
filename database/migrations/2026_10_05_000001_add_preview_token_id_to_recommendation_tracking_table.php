<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A demo persona is one shared account, and every demo visitor gets their own
 * token (PreviewController::login). "Mark as done" in a demo writes a row
 * stamped with the visitor's token, seen only by that token
 * (RecommendationTracking's preview-session scope), and deleted with the token
 * when the demo is switched or left. Real users' rows keep it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recommendation_tracking', 'preview_token_id')) {
            return;
        }

        Schema::table('recommendation_tracking', function (Blueprint $table): void {
            $table->foreignId('preview_token_id')
                ->nullable()
                ->after('user_id')
                ->constrained('personal_access_tokens')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recommendation_tracking', 'preview_token_id')) {
            return;
        }

        Schema::table('recommendation_tracking', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preview_token_id');
        });
    }
};
