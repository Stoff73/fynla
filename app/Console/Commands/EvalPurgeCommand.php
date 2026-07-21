<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use Illuminate\Console\Command;

/**
 * Permanently delete eval recording sessions (and their cascaded provider
 * runs) older than a threshold. Operates on eval_recording_sessions, NOT on
 * users — per canonical contract 0.2 there are no separate "eval users"; eval
 * runs target preview personas, which we never delete.
 *
 * Defaults to a dry-run that lists what would go — pass --confirm to actually
 * delete.
 *
 * Usage:
 *   php artisan eval:purge                     # dry-run, default 90d
 *   php artisan eval:purge --older-than=30d
 *   php artisan eval:purge --older-than=90d --confirm
 *   php artisan eval:purge --older-than=0d --confirm     # delete ALL recordings
 */
final class EvalPurgeCommand extends Command
{
    protected $signature = 'eval:purge
        {--older-than=90d : Threshold (e.g. 30d, 90d, 180d). Sessions started before now() minus this are eligible.}
        {--confirm : Actually delete. Without this flag, the command is a dry-run.}';

    protected $description = 'Purge old eval_recording_sessions and their cascaded provider runs.';

    public function handle(): int
    {
        $olderThan = (string) $this->option('older-than');
        $confirm = (bool) $this->option('confirm');

        $threshold = $this->parseThreshold($olderThan);
        if ($threshold === null) {
            $this->error("Invalid --older-than '{$olderThan}'. Use a number-of-days suffix like 30d, 90d.");

            return self::INVALID;
        }

        $cutoff = now()->subDays($threshold);

        $query = EvalRecordingSession::query()
            ->where('started_at', '<', $cutoff);

        $count = (clone $query)->count();
        $this->info('Eval recording sessions started before '.$cutoff->toDateTimeString().": {$count}");

        if ($count === 0) {
            $this->line('Nothing to purge.');

            return self::SUCCESS;
        }

        $sample = (clone $query)
            ->orderBy('started_at')
            ->limit(10)
            ->get(['id', 'scenario_id', 'persona', 'started_at']);
        $this->newLine();
        $this->line('Sample (up to 10):');
        foreach ($sample as $s) {
            $this->line('  id='.$s->id.' scenario='.$s->scenario_id.' persona='.($s->persona ?? '?').' started='.$s->started_at?->toDateTimeString());
        }

        if (! $confirm) {
            $this->newLine();
            $this->warn('Dry-run — no rows deleted. Pass --confirm to actually delete.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Deleting {$count} session(s) and their cascaded provider runs...");

        $deletedSessions = 0;
        $deletedRuns = 0;
        $query->orderBy('id')->chunkById(100, function ($sessions) use (&$deletedSessions, &$deletedRuns): void {
            $sessionIds = $sessions->pluck('id')->all();
            $deletedRuns += EvalProviderRun::whereIn('eval_recording_session_id', $sessionIds)->delete();
            $deletedSessions += EvalRecordingSession::whereIn('id', $sessionIds)->delete();
            $this->line("  deleted {$deletedSessions} sessions / {$deletedRuns} runs so far...");
        });

        $this->newLine();
        $this->info("Purged {$deletedSessions} session(s) and {$deletedRuns} provider run(s).");

        return self::SUCCESS;
    }

    private function parseThreshold(string $raw): ?int
    {
        if (preg_match('/^(\d+)d$/i', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
