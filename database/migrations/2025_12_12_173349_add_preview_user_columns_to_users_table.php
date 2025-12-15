<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Flag to identify preview/demo users (their data is canonical and never modified)
            $table->boolean('is_preview_user')->default(false)->after('is_admin');

            // Which persona this preview user represents (young_family, peak_earners, widow, entrepreneur)
            $table->string('preview_persona_id', 50)->nullable()->after('is_preview_user');

            // Index for quick lookups when entering preview mode
            $table->index(['is_preview_user', 'preview_persona_id'], 'preview_user_persona_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('preview_user_persona_idx');
            $table->dropColumn(['is_preview_user', 'preview_persona_id']);
        });
    }
};
