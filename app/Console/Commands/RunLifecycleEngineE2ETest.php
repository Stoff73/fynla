<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEngine;
use Database\Seeders\LifecycleTestSeeder;
use Illuminate\Console\Command;

class RunLifecycleEngineE2ETest extends Command
{
    protected $signature = 'lifecycle:e2e-test {--recipient= : Real email to send all test emails to}';

    protected $description = 'Run the lifecycle engine against dummy seeded users with email recipient override';

    public function handle(LifecycleEngine $engine): int
    {
        $recipient = $this->option('recipient');
        if (! $recipient) {
            $this->error('--recipient is required (e.g., --recipient=chris@fynla.org)');

            return Command::FAILURE;
        }

        $this->info("Running lifecycle e2e test, sending all emails to: {$recipient}");

        // Override config for this run — the engine's dispatchEmail() reads
        // lifecycle.test_recipient_override and redirects test users' email
        // to this single inbox.
        config(['lifecycle.test_recipient_override' => $recipient]);

        // Seed 5 test users (idempotent — clears prior runs)
        $this->info('Seeding 5 test users...');
        (new LifecycleTestSeeder())->run();

        $testUserCount = User::where('is_lifecycle_test_user', true)->count();
        $this->info("Created {$testUserCount} test users.");

        // Run the engine in test mode so the is_lifecycle_test_user filter
        // flips from "reject" to "allow".
        $this->info('Running lifecycle engine in test mode...');
        $stats = $engine->setTestMode(true)->run();

        $this->info('--- Stats ---');
        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf(
                '%s: %d sent, %d errored',
                $campaign,
                $counts['sent'] ?? 0,
                $counts['errored'] ?? 0
            ));
        }

        // Print log rows for manual inspection
        $this->info('--- Log rows ---');
        $logs = LifecycleEmailLog::with('user')
            ->whereIn('user_id', User::where('is_lifecycle_test_user', true)->pluck('id'))
            ->get();

        foreach ($logs as $log) {
            $this->info(sprintf(
                'Test user: %s (ID %d), campaign: %s, log row id: %d',
                $log->user->first_name ?? '(unknown)',
                $log->user_id,
                $log->campaign,
                $log->id,
            ));
        }

        // Print discount codes (only Campaign 2 produces these)
        $codes = DiscountCode::whereIn(
            'user_id',
            User::where('is_lifecycle_test_user', true)->pluck('id')
        )->get();

        if ($codes->isNotEmpty()) {
            $this->info('--- Per-user discount codes generated ---');
            foreach ($codes as $code) {
                $this->info(sprintf(
                    '  %s (user_id=%d, expires=%s)',
                    $code->code,
                    $code->user_id,
                    $code->expires_at?->toDateTimeString() ?? 'never'
                ));
            }
        }

        $this->newLine();
        $this->info("Done. Check {$recipient} inbox for emails.");
        $this->warn("REMEMBER to run 'php artisan lifecycle:e2e-cleanup' when finished testing.");

        return Command::SUCCESS;
    }
}
