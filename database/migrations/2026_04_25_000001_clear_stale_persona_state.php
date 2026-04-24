<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 0 two-Fyn collapse — post-onboarding chat no longer has a
 * capturing sub-state, so ai_conversations.persona_state rows that
 * reference the legacy orchestrator become meaningless. Clear them
 * wholesale so stale state cannot be loaded by any leftover code path.
 *
 * The column itself is retained for future reuse. Down is a no-op —
 * the legacy state is not recoverable and not useful even if it were.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_conversations')->update(['persona_state' => null]);
    }

    public function down(): void {}
};
