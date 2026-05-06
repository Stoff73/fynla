<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiDailyUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * S0.11.1 — Backfill ai_daily_usage from existing ai_messages.
 *
 * Run once after deploy to populate the new daily-usage table from the
 * historical token columns on ai_messages. Idempotent: re-runs cleanly
 * via INSERT...ON DUPLICATE KEY UPDATE so any new messages persisted
 * during the backfill window are picked up on the next run.
 */
class BackfillAiDailyUsage extends Command
{
    protected $signature = 'ai:usage:backfill {--days=30 : Look back this many days}';

    protected $description = 'Backfill ai_daily_usage rows from existing ai_messages token totals';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->startOfDay();

        $this->info("Backfilling ai_daily_usage from ai_messages since {$cutoff->toDateString()}...");

        $rows = DB::table('ai_messages')
            ->join('ai_conversations', 'ai_messages.conversation_id', '=', 'ai_conversations.id')
            ->where('ai_messages.created_at', '>=', $cutoff)
            ->groupBy('ai_conversations.user_id', DB::raw('DATE(ai_messages.created_at)'))
            ->select(
                'ai_conversations.user_id',
                DB::raw('DATE(ai_messages.created_at) as usage_date'),
                DB::raw('SUM(COALESCE(ai_messages.input_tokens, 0) + COALESCE(ai_messages.output_tokens, 0)) as tokens_used')
            )
            ->get();

        $written = 0;
        foreach ($rows as $row) {
            AiDailyUsage::updateOrCreate(
                ['user_id' => $row->user_id, 'usage_date' => $row->usage_date],
                ['tokens_used' => (int) $row->tokens_used]
            );
            $written++;
        }

        $this->info("Backfill complete — {$written} ai_daily_usage rows written/updated.");

        return self::SUCCESS;
    }
}
