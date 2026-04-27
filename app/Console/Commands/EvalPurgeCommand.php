<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Permanently delete eval users (and their cascaded recording sessions,
 * provider runs, conversations, messages) older than a threshold.
 *
 * Defaults to a dry-run that lists what would go — pass --confirm to
 * actually delete.
 *
 * Usage:
 *   php artisan eval:purge                     # dry-run, default 90d
 *   php artisan eval:purge --older-than=30d
 *   php artisan eval:purge --older-than=90d --confirm
 *   php artisan eval:purge --older-than=0d --confirm     # delete ALL eval users
 */
final class EvalPurgeCommand extends Command
{
    protected $signature = 'eval:purge
        {--older-than=90d : Threshold (e.g. 30d, 90d, 180d). Eval users created before now() minus this are eligible.}
        {--confirm : Actually delete. Without this flag, the command is a dry-run.}';

    protected $description = 'Purge old eval users (is_eval_user=true) and their cascaded recordings.';

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

        $query = User::where('is_eval_user', true)
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();
        $this->info('Eval users older than '.$cutoff->toDateTimeString().": {$count}");

        if ($count === 0) {
            $this->line('Nothing to purge.');

            return self::SUCCESS;
        }

        $sample = (clone $query)->orderBy('created_at')->limit(10)->get(['id', 'email', 'created_at']);
        $this->newLine();
        $this->line('Sample (up to 10):');
        foreach ($sample as $u) {
            $this->line('  id='.$u->id.' email='.$u->email.' created_at='.$u->created_at?->toDateTimeString());
        }

        if (! $confirm) {
            $this->newLine();
            $this->warn('Dry-run — no rows deleted. Pass --confirm to actually delete.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Deleting {$count} eval user(s)...");

        $deleted = 0;
        // Delete in batches so foreign-key cascades aren't a single giant query.
        $query->orderBy('id')->chunkById(100, function ($users) use (&$deleted): void {
            foreach ($users as $user) {
                $user->forceDelete();
                $deleted++;
            }
            $this->line("  deleted {$deleted} so far...");
        });

        $this->newLine();
        $this->info("Purged {$deleted} eval user(s) and their cascaded recordings.");

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
