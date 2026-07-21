<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SP2 PR5 — Add tier keys (tier1, tier2, tier3) to the billing plan enums.
 *
 * confirmPayment writes the tier key to BOTH subscriptions.plan and the
 * denormalised users.plan column. Both enums must accept tier keys or the
 * confirm transaction 500s on data truncation.
 *
 * Mirrors the sibling enum-modify convention (2026_03_30_000002,
 * 2026_04_09_000001): a bare ALTER ... MODIFY COLUMN with no idempotency
 * guard. Safe because each MODIFY is additive (existing values retained),
 * reversible via down(), and there are no existing tier-key rows to migrate
 * (A9: all legacy cohorts → Free, no payers; tier keys are written only by
 * post-PR5 checkout flows). users.plan keeps its NOT NULL DEFAULT 'free'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student', 'standard', 'family', 'pro', 'free', 'tier1', 'tier2', 'tier3') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('free', 'student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3') NOT NULL DEFAULT 'free'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student', 'standard', 'family', 'pro') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('free', 'student', 'standard', 'family', 'pro') NOT NULL DEFAULT 'free'");
    }
};
