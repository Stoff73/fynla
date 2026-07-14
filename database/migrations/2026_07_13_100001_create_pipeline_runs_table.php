<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_article_id')
                ->nullable()
                ->constrained('pipeline_articles')
                ->cascadeOnDelete();

            $table->string('stage');
            $table->enum('status', ['running', 'success', 'error', 'skipped']);
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index('stage');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
