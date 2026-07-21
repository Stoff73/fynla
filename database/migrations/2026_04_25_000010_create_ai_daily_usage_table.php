<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S0.11.1 — Atomic token-budget table.
 *
 * Replaces the 5-minute Cache::remember layer that gated the daily AI
 * token budget. Two parallel requests for the same user could both pass
 * the cached `hasTokenBudget` check before either invalidated the cache,
 * silently exceeding the daily limit.
 *
 * The new table holds one row per (user_id, usage_date). Writes are done
 * inside a DB::transaction with `SELECT ... FOR UPDATE` so concurrent
 * recordTokenUsage calls serialise on the row lock. The unique constraint
 * on (user_id, usage_date) means firstOrCreate is atomic — two parallel
 * processes cannot both create the row.
 *
 * Backfill: populated from existing `ai_messages` via the artisan command
 * `ai:usage:backfill` (run once after deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_daily_usage', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->date('usage_date');
            $table->bigInteger('tokens_used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_usage');
    }
};
