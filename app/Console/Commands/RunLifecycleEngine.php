<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunLifecycleEngine extends Command
{
    protected $signature = 'lifecycle:run-daily';

    protected $description = 'Send lifecycle emails (re-engagement, conversion, feedback, recovery)';

    public function handle(LifecycleEngine $engine): int
    {
        if (! config('lifecycle.enabled')) {
            $this->warn('Lifecycle engine is disabled via config.');

            return Command::SUCCESS;
        }

        // Defence-in-depth: refuse to run if test users haven't been cleaned up.
        // The engine already skips is_lifecycle_test_user rows when not in test
        // mode, but we refuse outright here so a forgotten seeder can never
        // blast real emails to fake accounts.
        $staleTestUsers = User::where('is_lifecycle_test_user', true)->count();
        if ($staleTestUsers > 0) {
            Log::error('Lifecycle engine refusing to run: stale test users present', [
                'count' => $staleTestUsers,
            ]);
            $this->error("Refusing to run — {$staleTestUsers} test users still exist. Run 'php artisan lifecycle:e2e-cleanup' first.");

            return Command::FAILURE;
        }

        Log::info('Lifecycle engine starting');
        $stats = $engine->run();

        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf(
                '%s: %d sent, %d skipped, %d errored',
                $campaign,
                $counts['sent'] ?? 0,
                $counts['skipped'] ?? 0,
                $counts['errored'] ?? 0
            ));
        }

        Log::info('Lifecycle engine completed', ['stats' => $stats]);

        return Command::SUCCESS;
    }
}
