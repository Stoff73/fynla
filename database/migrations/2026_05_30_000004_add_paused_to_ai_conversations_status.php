<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CoALA Phase 5 item 7 (FR-M10) — add `paused` to the ai_conversations.status
 * enum so the inactivity timer can pause idle conversations. Sending reopens
 * them (status → active).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ai_conversations MODIFY COLUMN status ENUM('active', 'archived', 'paused') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ai_conversations MODIFY COLUMN status ENUM('active', 'archived') NOT NULL DEFAULT 'active'");
    }
};
