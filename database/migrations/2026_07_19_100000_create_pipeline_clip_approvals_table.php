<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clip-level approval gate between Stage 3 (clip generation) and Stage 4
 * (post composition + scheduling). One row per clip per article.
 *
 * Flow:
 *   pending → approved (manual, via CMS or magic link)
 *           → rejected (manual, with reason)
 *           → auto_approved (silent, N minutes before scheduled_at if
 *                             still pending)
 *
 * When every clip on an article reaches approved/auto_approved,
 * ComposePostsJob fires. Rejected clips block their article's compose
 * step until they're regenerated.
 *
 * scheduled_at is precomputed at approval-row creation time using
 * PostScheduler::nextSlot — this is the value shown in the approval
 * email so marketing sees the ACTUAL day/time the post will land.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_clip_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_article_id')
                ->constrained('pipeline_articles')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('clip_index');
            $table->string('clip_path');

            $table->enum('status', ['pending', 'approved', 'rejected', 'auto_approved'])
                ->default('pending');

            // 48-char magic tokens for one-click email approve/reject. Single-use.
            $table->string('approve_token', 64)->unique();
            $table->string('reject_token', 64)->unique();
            $table->timestamp('token_expires_at');

            // The earliest scheduled slot across platforms for this clip,
            // computed at row creation. Auto-approve fires
            // config.auto_approve_minutes_before_post ahead of this.
            $table->timestamp('scheduled_at');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('approved_via_email')->default(false);

            $table->timestamps();

            $table->index(['pipeline_article_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_clip_approvals');
    }
};
