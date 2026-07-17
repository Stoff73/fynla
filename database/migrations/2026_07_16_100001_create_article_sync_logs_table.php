<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for every cross-environment article push.
 *
 * One row per push attempt (successful or not). Lets us answer:
 *   - Who pushed article X to prod?
 *   - What did the payload look like at that moment?
 *   - Why did the push to dev fail?
 *
 * Never soft-deleted — this is the permanent record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insight_article_id')
                ->constrained('insight_articles')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('target_env', ['local', 'dev', 'prod']);
            $table->enum('action', ['publish', 'sync', 'reimport', 'delete', 'unpublish']);
            $table->enum('status', ['success', 'error']);
            $table->string('payload_hash', 64)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['insight_article_id', 'target_env']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_sync_logs');
    }
};
