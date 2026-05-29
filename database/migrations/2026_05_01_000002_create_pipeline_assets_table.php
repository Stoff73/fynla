<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['image', 'video', 'clip']);
            $table->enum('template_type', ['square_stat_card', 'story_card', 'youtube_thumbnail', 'video_full', 'video_clip'])->nullable();
            $table->string('variant')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('local_path');
            $table->string('public_url')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('destination_url')->nullable();
            $table->timestamps();
            $table->index(['article_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
