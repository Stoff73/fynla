<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CoALA Phase 5 item 8 (FR-M11) — per-action cost attribution ledger.
 *
 * One row per LLM call, tagging the spend to the action it served so cost can be
 * explained to the FCA / board by action_type, stage, session_mode, and
 * procedural_version ("we spent X on advice, of which Y was reasoning"). Sibling
 * to ai_messages (per-message tokens) and ai_advice_logs (legacy advice record);
 * this is the typed-action cost ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cost_attribution', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();

            // The Fyn write state the turn ran in (advice | onboarding).
            $table->string('session_mode', 32)->nullable();
            // The planner-chosen action this call served (reason/retrieve/learn/
            // ground/no_action), null for a deterministic/bypass call.
            $table->string('action_type', 32)->nullable();
            // Where in the loop the call sat: planner | reasoner.
            $table->string('stage', 32);
            // The planning cycle within the turn (1-based).
            $table->unsignedSmallInteger('cycle_id')->default(1);
            // The prompt/procedure version that produced the call (provenance).
            $table->string('procedural_version', 64)->nullable();

            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_hit_tokens')->default(0);
            $table->unsignedInteger('cache_miss_tokens')->default(0);

            // Computed via AiCostCalculator (item 1). `priced` is false when the
            // model was not in the price table (cost is then a 0.0 placeholder).
            $table->decimal('gbp_cost', 12, 6)->default(0);
            $table->boolean('gbp_cost_priced')->default(false);

            $table->timestamps();

            $table->index(['action_type', 'created_at']);
            $table->index(['session_mode', 'created_at']);
            $table->index(['stage', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cost_attribution');
    }
};
