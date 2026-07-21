<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('source_url')->unique();
            $table->string('slug');
            $table->date('lastmod')->nullable();
            $table->text('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('inferred_topic')->nullable();
            $table->float('topic_confidence')->nullable();
            $table->string('chosen_landing_page_key')->nullable();
            $table->string('final_landing_url')->nullable();
            $table->json('claude_response')->nullable();
            $table->string('heygen_video_id')->nullable();
            $table->string('heygen_video_url')->nullable();
            $table->string('avatar_used')->nullable();
            $table->enum('status', [
                'discovered',
                'scraped',
                'scripted',
                'rendering',
                'rendered',
                'awaiting_approval',
                'approved',
                'rejected',
                'publishing',
                'published',
                'failed',
            ])->default('discovered');
            $table->integer('amend_count')->default(0);
            $table->json('platform_post_ids')->nullable(); // {fb: ..., ig: ..., yt: ...}
            $table->timestamp('published_at')->nullable();
            $table->json('metrics_24h')->nullable();
            $table->json('metrics_48h')->nullable();
            $table->boolean('boost_eligible')->default(false);
            $table->json('boost')->nullable(); // budget, status, campaign id
            $table->timestamps();
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
