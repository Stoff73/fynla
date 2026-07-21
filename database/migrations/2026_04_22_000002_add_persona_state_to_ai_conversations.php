<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('persona_state')
                ->nullable()
                ->after('metadata')
                ->comment('FynPersonaOrchestrator state: current mode, pending advice question, capture context.');
        });

        // Backfill existing rows with the default state so the orchestrator
        // can read persona_state unconditionally after the flag flips on.
        DB::table('ai_conversations')
            ->whereNull('persona_state')
            ->update([
                'persona_state' => json_encode([
                    'current' => 'advice',
                    'pending_advice_question' => null,
                    'capture_context' => null,
                    'turns_in_capture' => 0,
                ]),
            ]);
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('persona_state');
        });
    }
};
