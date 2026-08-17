<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reject-with-feedback → regenerate (Q1). Adds:
 *  - clip_kind: 'short' (a highlight clip that CAN be regenerated) vs 'full'
 *    (the whole-video clip, which can't be re-picked).
 *  - regen_count: how many times this clip slot has already been regenerated,
 *    so we can cap it (config pipeline.clip_approval.max_regenerations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_clip_approvals', function (Blueprint $table) {
            $table->enum('clip_kind', ['short', 'full'])->default('short')->after('clip_path');
            $table->unsignedTinyInteger('regen_count')->default(0)->after('clip_kind');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_clip_approvals', function (Blueprint $table) {
            $table->dropColumn(['clip_kind', 'regen_count']);
        });
    }
};
