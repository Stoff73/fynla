<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4 posts — one row per clip × variant × platform.
 * With 2 variants and 3 platforms, a single clip typically produces 6 posts;
 * the scheduler distributes them (A→one platform, B→another) so audiences
 * never see both variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_article_id')
                ->constrained('pipeline_articles')
                ->cascadeOnDelete();
            $table->foreignId('pipeline_campaign_id')
                ->nullable()
                ->constrained('pipeline_campaigns')
                ->nullOnDelete();

            $table->unsignedTinyInteger('clip_index');
            $table->enum('variant', ['A', 'B']);
            $table->enum('platform', ['instagram', 'facebook', 'tiktok']);

            $table->text('caption');
            $table->json('hashtags');

            $table->string('destination_url_default');
            $table->string('destination_url_final')->nullable();
            $table->enum('destination_type', ['article', 'custom', 'campaign'])->default('article');

            $table->enum('status', [
                'awaiting_approval',
                'approved',
                'rejected',
                'scheduled',
                'published',
                'failed',
            ])->default('awaiting_approval');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('buffer_update_id')->nullable();
            $table->string('platform_post_url')->nullable();

            $table->json('metrics_24h')->nullable();
            $table->json('metrics_7d')->nullable();
            $table->json('metrics_30d')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['pipeline_article_id', 'variant', 'platform']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_posts');
    }
};
