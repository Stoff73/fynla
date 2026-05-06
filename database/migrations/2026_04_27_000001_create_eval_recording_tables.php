<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_eval_user')) {
            Schema::table('users', function (Blueprint $t): void {
                $t->boolean('is_eval_user')->default(false)->after('is_preview_user');
                $t->index('is_eval_user', 'users_is_eval_user_idx');
            });
        }

        if (! Schema::hasTable('eval_recording_sessions')) {
            Schema::create('eval_recording_sessions', function (Blueprint $t): void {
                $t->id();
                $t->string('scenario_id', 100);
                $t->string('scenario_path', 500);
                $t->longText('scenario_yaml');
                $t->foreignId('eval_user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $t->json('start_state_snapshot');
                $t->string('fynla_branch', 100)->nullable();
                $t->string('fynla_sha', 20)->nullable();
                $t->enum('status', ['running', 'completed', 'failed'])->default('running');
                $t->timestamp('started_at');
                $t->timestamp('completed_at')->nullable();
                $t->timestamps();

                $t->index(['scenario_id', 'started_at'], 'eval_sessions_scenario_started_idx');
                $t->index('status', 'eval_sessions_status_idx');
            });
        }

        if (! Schema::hasTable('eval_provider_runs')) {
            Schema::create('eval_provider_runs', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('eval_recording_session_id')
                    ->constrained('eval_recording_sessions')
                    ->cascadeOnDelete();
                $t->string('provider', 20);
                $t->string('model', 100);
                $t->foreignId('conversation_id')
                    ->constrained('ai_conversations')
                    ->restrictOnDelete();
                $t->text('user_message');
                $t->text('assistant_text')->nullable();
                $t->json('tool_calls')->nullable();
                $t->unsignedInteger('sse_event_count')->default(0);
                $t->json('sse_event_types')->nullable();
                $t->json('forbidden_hits')->nullable();
                $t->json('db_writes_made')->nullable();
                $t->json('end_state_snapshot')->nullable();
                $t->string('fixture_path', 500)->nullable();
                $t->unsignedInteger('duration_ms')->nullable();
                $t->timestamp('started_at');
                $t->timestamp('completed_at')->nullable();
                $t->timestamps();

                $t->index(['eval_recording_session_id', 'provider'], 'eval_runs_session_provider_idx');
                $t->index(['provider', 'model', 'started_at'], 'eval_runs_provider_model_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_provider_runs');
        Schema::dropIfExists('eval_recording_sessions');

        if (Schema::hasColumn('users', 'is_eval_user')) {
            Schema::table('users', function (Blueprint $t): void {
                $t->dropIndex('users_is_eval_user_idx');
                $t->dropColumn('is_eval_user');
            });
        }
    }
};
