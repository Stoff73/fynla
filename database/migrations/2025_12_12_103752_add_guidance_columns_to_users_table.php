<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds columns to support:
     * 1. Post-registration guidance system (step-by-step onboarding)
     * 2. Registration source tracking (preview mode, direct, etc.)
     * 3. Preview persona data retention option
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Guidance tracking columns
            $table->boolean('guidance_active')->default(false)->after('remember_token');
            $table->boolean('guidance_completed')->default(false)->after('guidance_active');
            $table->unsignedTinyInteger('guidance_current_step')->default(0)->after('guidance_completed');
            $table->json('guidance_completed_steps')->nullable()->after('guidance_current_step');
            $table->json('guidance_skipped_steps')->nullable()->after('guidance_completed_steps');
            $table->string('guidance_version', 10)->nullable()->after('guidance_skipped_steps');

            // Registration source tracking
            $table->string('registration_source', 50)->nullable()->after('guidance_version');
            $table->string('preview_persona_kept', 50)->nullable()->after('registration_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'guidance_active',
                'guidance_completed',
                'guidance_current_step',
                'guidance_completed_steps',
                'guidance_skipped_steps',
                'guidance_version',
                'registration_source',
                'preview_persona_kept',
            ]);
        });
    }
};
