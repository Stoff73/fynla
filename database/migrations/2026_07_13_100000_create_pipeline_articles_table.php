<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insight_article_id')
                ->constrained('insight_articles')
                ->cascadeOnDelete();

            $table->enum('status', [
                'detected',
                'scripting',
                'scripted',
                'rendering',
                'rendered',
                'awaiting_approval',
                'approved',
                'rejected',
                'publishing',
                'published',
                'failed',
            ])->default('detected');

            $table->string('script_drive_file_id')->nullable();
            $table->string('script_drive_url')->nullable();
            $table->string('tracking_sheet_row_id')->nullable();

            $table->string('video_provider')->nullable();
            $table->string('video_asset_id')->nullable();
            $table->string('video_url')->nullable();

            $table->json('platform_post_ids')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->json('metrics_24h')->nullable();
            $table->json('metrics_48h')->nullable();
            $table->boolean('boost_eligible')->default(false);
            $table->json('boost')->nullable();

            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique('insight_article_id');
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_articles');
    }
};
