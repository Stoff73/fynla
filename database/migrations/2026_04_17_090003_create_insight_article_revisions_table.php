<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('insight_articles')->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary');
            $table->json('body_blocks')->nullable();
            $table->foreignId('saved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('saved_at');

            $table->index('article_id');
            $table->index('saved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_article_revisions');
    }
};
