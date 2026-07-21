<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0.1 — drop the dead users.is_eval_user column. Per canonical contract 0.2
 * (no mirror user / no EvalUserSeeder / no is_eval_user flag — Sanctum bypass
 * token IS the mechanism), nothing in the codebase ever sets this column to
 * true. EvalPurgeCommand was the only reader and has been repointed to operate
 * on eval_recording_sessions directly. Keeping the column was a forensic-chain
 * landmine: if anything ever flagged a real user, the purge command would
 * forceDelete() them on its next run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_eval_user')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_is_eval_user_idx');
                $table->dropColumn('is_eval_user');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_eval_user')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_eval_user')->default(false)->after('is_preview_user');
                $table->index('is_eval_user', 'users_is_eval_user_idx');
            });
        }
    }
};
