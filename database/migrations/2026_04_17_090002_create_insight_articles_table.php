<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary');
            $table->enum('category', [
                'tax-changes',
                'pensions',
                'savings-isa',
                'estate-planning',
                'platform-updates',
            ]);
            $table->json('tags')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_image_card_path')->nullable();
            $table->string('hero_image_thumb_path')->nullable();
            $table->json('body_blocks')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('insight_templates')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bespoke')->default(false);
            $table->string('bespoke_component')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_featured');
            $table->index('published_at');
            $table->index('category');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_articles');
    }
};
